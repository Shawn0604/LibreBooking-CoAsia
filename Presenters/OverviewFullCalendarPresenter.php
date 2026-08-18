<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Authorization/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');

/**
 * Prototype-only presenter for Web/overview_fullcalendar.php — evaluating FullCalendar
 * Standard as a replacement for the hand-rolled week grid in Presenters/OverviewPresenter.php.
 *
 * Deliberately does NOT touch OverviewPresenter.php (per the evaluation's constraints), so
 * the small amount of "which resources are Physical vs Video Conference, and what color is
 * each" logic below is a duplicate of OverviewPresenter::SplitByType()/AssignColors() (both
 * private there, so they can't be reused directly without modifying that file). All actual
 * event data comes from OverviewFullCalendarEventsPresenter instead — this class only
 * supplies the shared sidebar (resource checklist) with the same vars globalheader.tpl
 * already expects.
 */
class OverviewFullCalendarPresenter
{
    /** Same fallback palette as OverviewPresenter — kept in sync deliberately so the two
     * calendars' resource colors look identical during side-by-side evaluation. */
    private const FALLBACK_COLORS = [
        '#d50000', '#e67c73', '#f4511e', '#f6bf26', '#33b679', '#0b8043',
        '#039be5', '#3f51b5', '#7986cb', '#8e24aa', '#616161',
    ];

    private const ATTRIBUTE_VIDEO_URL = 'video_url';

    /** Left-sidebar section order (database_schema/coasia-resources-regions.sql) — the
     * underlying resource_groups query sorts top-level groups alphabetically by name,
     * which doesn't reproduce the spec's 台北→深圳→上海→線上 order, so it's pinned here
     * instead. Any group not in this list (e.g. one an admin creates later) sorts after
     * these four, in whatever order the query already returned it. */
    private const GROUP_DISPLAY_ORDER = ['台北實體會議室', '深圳實體會議室', '上海實體會議室', '線上會議室'];

    /**
     * @var IOverviewFullCalendarPage
     */
    private $page;

    /**
     * @var IResourceRepository
     */
    private $resourceRepository;

    /**
     * @var IAttributeRepository
     */
    private $attributeRepository;

    /**
     * @var IPermissionServiceFactory
     */
    private $permissionServiceFactory;

    /**
     * @var IUserRepository
     */
    private $userRepository;

    public function __construct(
        IOverviewFullCalendarPage $page,
        IResourceRepository $resourceRepository,
        IAttributeRepository $attributeRepository,
        IPermissionServiceFactory $permissionServiceFactory,
        IUserRepository $userRepository
    ) {
        $this->page = $page;
        $this->resourceRepository = $resourceRepository;
        $this->attributeRepository = $attributeRepository;
        $this->permissionServiceFactory = $permissionServiceFactory;
        $this->userRepository = $userRepository;
    }

    public function PageLoad(UserSession $userSession)
    {
        $permissionService = $this->permissionServiceFactory->GetPermissionService();
        // Same rule the JSON events feed uses (OverviewFullCalendarEventsPresenter) — a
        // non-admin only sees the resources they're actually allowed to view, in the
        // sidebar list too, not just in the event data itself. CanAccessResource() (not
        // the confusingly-named PermissionService::CanViewResource(), a much narrower
        // "view-only" grant bucket — see the ResourcePermissionFilter it wraps) already
        // short-circuits true for admins, so this needs no separate admin bypass.
        $resourceFilter = new ResourcePermissionFilter($permissionService, $userSession);

        // resource_groups/resource_group_assignment (database_schema/coasia-resources-
        // regions.sql) already carries the real left-sidebar structure — one row per
        // region-scoped physical group (台北/深圳/上海實體會議室) plus the shared
        // 線上會議室 group — so this reads that instead of re-deriving a flat
        // physical/video split from the video_url attribute alone.
        $groupTree = $this->resourceRepository->GetResourceGroups(ResourceRepository::ALL_SCHEDULES, $resourceFilter);

        $groups = [];
        $allResources = [];
        foreach ($groupTree->GetGroups(false) as $group) {
            $resources = array_values(array_filter(
                $group->children,
                fn ($child) => $child->type === ResourceGroup::RESOURCE_TYPE
            ));
            if (count($resources) === 0) {
                // Nothing left to show for this group once permission filtering ran (or
                // an admin-created group has no resources assigned yet) — omit it rather
                // than rendering an empty, uselessly-expandable section.
                continue;
            }
            $groups[] = ['id' => $group->id, 'name' => $group->name, 'resources' => $resources];
            array_push($allResources, ...$resources);
        }

        usort($groups, function ($a, $b) {
            $orderA = array_search($a['name'], self::GROUP_DISPLAY_ORDER);
            $orderB = array_search($b['name'], self::GROUP_DISPLAY_ORDER);
            return ($orderA === false ? count(self::GROUP_DISPLAY_ORDER) : $orderA)
                <=> ($orderB === false ? count(self::GROUP_DISPLAY_ORDER) : $orderB);
        });

        $videoResourceIds = $this->GetVideoResourceIds($allResources);
        foreach ($groups as &$group) {
            $group['isVideo'] = false;
            foreach ($group['resources'] as $resource) {
                if (isset($videoResourceIds[$resource->GetId()])) {
                    $group['isVideo'] = true;
                    break;
                }
            }
        }
        unset($group);

        $resourceColors = $this->AssignColors($allResources);

        [$explicitDefaultResourceId, $systemDefaultResourceId] = $this->ResolveDefaultResourceIds($userSession, $allResources);

        $this->page->SetResourceGroups($groups, $resourceColors, $explicitDefaultResourceId, $systemDefaultResourceId);
    }

    /**
     * Tiers 1 and 3 of the resource picker's default-resource priority (tier 2, the
     * last-selected room, lives entirely client-side in localStorage — see
     * loadStoredResourceSelection() in overview_fullcalendar.js — and tier 4 is simply "none
     * of the above", which the JS falls back to on its own).
     *
     * @param BookableResource[] $resources permission-filtered resources actually shown in
     *   the picker — a stale preference/default pointing at a resource outside this set (no
     *   longer viewable, deleted, or never existed) must resolve to null, not silently expose
     *   or select something the caller can't otherwise see.
     * @return array{0: string|null, 1: string|null} [$explicitDefaultResourceId, $systemDefaultResourceId]
     */
    private function ResolveDefaultResourceIds(UserSession $userSession, $resources)
    {
        if ($userSession->UserId == 0) {
            return [null, null];
        }

        $user = $this->userRepository->LoadById($userSession->UserId);

        $explicitDefaultResourceId = $user->GetPreference(UserPreferences::OVERVIEW_DEFAULT_RESOURCE_ID);
        $systemDefaultScheduleId = $user->GetDefaultScheduleId();

        $explicitValid = null;
        $systemValid = null;
        foreach ($resources as $resource) {
            if (!empty($explicitDefaultResourceId) && $resource->GetId() == $explicitDefaultResourceId) {
                $explicitValid = $resource->GetId();
            }
            if (!empty($systemDefaultScheduleId) && $resource->GetScheduleId() == $systemDefaultScheduleId) {
                $systemValid = $resource->GetId();
            }
        }

        return [$explicitValid, $systemValid];
    }

    private function AssignColors($resources)
    {
        $colors = [];
        foreach ($resources as $index => $resource) {
            $color = $resource->GetColor();
            if (!empty($color)) {
                $colors[$resource->GetId()] = [
                    'bg' => $color,
                    'fg' => $resource->GetTextColor() ?: '#ffffff',
                ];
            } else {
                $hex = self::FALLBACK_COLORS[$index % count(self::FALLBACK_COLORS)];
                $colors[$resource->GetId()] = [
                    'bg' => $hex,
                    'fg' => '#ffffff',
                ];
            }
        }
        return $colors;
    }

    private function GetVideoResourceIds($resources)
    {
        $videoUrlAttributeId = null;
        foreach ($this->attributeRepository->GetByCategory(CustomAttributeCategory::RESOURCE) as $attribute) {
            if ($attribute->Label() === self::ATTRIBUTE_VIDEO_URL) {
                $videoUrlAttributeId = $attribute->Id();
                break;
            }
        }

        if ($videoUrlAttributeId === null) {
            return [];
        }

        $resourceIds = array_map(fn ($r) => $r->GetId(), $resources);
        $values = $this->attributeRepository->GetEntityValues(CustomAttributeCategory::RESOURCE, $resourceIds);

        $ids = [];
        foreach ($values as $value) {
            if ($value->AttributeId == $videoUrlAttributeId && !empty($value->Value)) {
                $ids[$value->EntityId] = true;
            }
        }

        return $ids;
    }
}

interface IOverviewFullCalendarPage
{
    /**
     * @param array $groups ordered list of ['id' => int, 'name' => string, 'isVideo' => bool, 'resources' => BookableResource[]]
     * @param array $resourceColors
     * @param string|null $explicitDefaultResourceId the user's own "設為預設" pick (priority tier 1)
     * @param string|null $systemDefaultResourceId the resource matching the user's classic default-schedule setting (priority tier 3)
     */
    public function SetResourceGroups($groups, $resourceColors, $explicitDefaultResourceId, $systemDefaultResourceId);
}
