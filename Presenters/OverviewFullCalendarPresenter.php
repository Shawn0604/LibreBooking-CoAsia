<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Authorization/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');

/**
 * Prototype-only presenter for Web/overview_fullcalendar.php — evaluating FullCalendar
 * Standard as a replacement for the hand-rolled week grid in Presenters/OverviewPresenter.php.
 *
 * Deliberately does NOT touch OverviewPresenter.php (per the evaluation's constraints), so
 * the small amount of "which resources are Physical vs Video Conference" logic below started
 * as a duplicate of OverviewPresenter::SplitByType() (private there, so it can't be reused
 * directly without modifying that file) — AssignColors() below has since diverged from
 * OverviewPresenter's own per-resource palette into the fixed per-region one from
 * Domain/Values/ResourceLocation::Color(), so the two calendars' resource colors are
 * deliberately NOT kept in sync anymore. Event data/colors come from
 * OverviewFullCalendarEventsPresenter instead (a separate class, but calling the same
 * ResourceLocation::Color() so the picker and the actual calendar events still agree) — this
 * class only supplies the shared sidebar (resource checklist) with the same vars
 * globalheader.tpl already expects.
 */
class OverviewFullCalendarPresenter
{
    private const ATTRIBUTE_VIDEO_URL = 'video_url';

    /** Left-sidebar section order (database_schema/coasia-resources-regions.sql) — the
     * underlying resource_groups query sorts top-level groups alphabetically by name,
     * which doesn't reproduce the spec's 台北→深圳→上海→線上 order, so it's pinned here
     * instead. Any group not in this list (e.g. one an admin creates later) sorts after
     * these four, in whatever order the query already returned it. */
    private const GROUP_DISPLAY_ORDER = ['台北實體會議室', '深圳實體會議室', '上海實體會議室', '線上會議室'];

    /** Tier 4 of the default-resource priority chain (ResolveFirstLoginDefaultResourceId()) —
     * matched by name AND its own location, since every office group has a same-named "台北
     * Taipei" room (database_schema/coasia-resources-regions.sql: each office's room set
     * mirrors the other offices' names), and only the Taipei office's own copy should count
     * as the first-login default. */
    private const FIRST_LOGIN_DEFAULT_RESOURCE_NAME = '台北 Taipei';

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

        $resourceLocations = $this->GetResourceLocations($allResources);
        $resourceColors = $this->AssignColors($allResources, $resourceLocations);

        [$explicitDefaultResourceId, $systemDefaultResourceId] = $this->ResolveDefaultResourceIds($userSession, $allResources);
        $firstLoginDefaultResourceId = $this->ResolveFirstLoginDefaultResourceId($allResources, $resourceLocations);

        $this->page->SetResourceGroups(
            $groups,
            $resourceColors,
            $resourceLocations,
            $explicitDefaultResourceId,
            $systemDefaultResourceId,
            $firstLoginDefaultResourceId
        );
    }

    /**
     * Tier 4 of the resource picker's default-resource priority — used only when a user has
     * none of tiers 1-3 (no explicit "設為預設" pin, no browser localStorage history, no
     * classic default-schedule setting), which in practice means a genuinely first-time user:
     * rather than leaving the picker on the "請選擇會議室" placeholder, this points them at
     * Taipei office's own FIRST_LOGIN_DEFAULT_RESOURCE_NAME room. Matched by name AND its own
     * location (not a hardcoded resource id, which would silently break if that row's id ever
     * changed) — see the constant's own doc comment for why location has to be checked too.
     * Returns null if no such resource exists in this permission-filtered list (renamed,
     * deleted, or simply not viewable by this user), in which case the JS falls back to no
     * selection at all, same as before this tier existed.
     *
     * @param ResourceGroupAssignment[] $resources
     * @param array<int, string> $resourceLocations resource id => ResourceLocation value, see GetResourceLocations()
     * @return string|null
     */
    private function ResolveFirstLoginDefaultResourceId($resources, $resourceLocations)
    {
        foreach ($resources as $resource) {
            if (
                $resource->GetName() === self::FIRST_LOGIN_DEFAULT_RESOURCE_NAME
                && ($resourceLocations[$resource->GetId()] ?? null) === ResourceLocation::TAIPEI
            ) {
                return $resource->GetId();
            }
        }
        return null;
    }

    /**
     * The picker's "地區 · 會議室" trigger label (resourcePickerTriggerLabel() in
     * overview_fullcalendar.js) needs each resource's own resources.location value, but
     * $group['resources'] above holds ResourceGroupAssignment objects (Domain/ResourceGroup.php)
     * — a lightweight projection GetResourceGroups()/BuildResourceGroupTree() build for the
     * group tree that carries color/schedule/admin-group fields but not location — not the full
     * BookableResource, so this needs its own separate id => location lookup.
     *
     * @param ResourceGroupAssignment[] $resources
     * @return array<int, string> resource id => ResourceLocation value (or '' if unclassified)
     */
    private function GetResourceLocations($resources)
    {
        $locationsByResourceId = [];
        foreach ($this->resourceRepository->GetResourceList() as $resource) {
            $locationsByResourceId[$resource->GetId()] = $resource->GetLocation();
        }

        $result = [];
        foreach ($resources as $resource) {
            $result[$resource->GetId()] = $locationsByResourceId[$resource->GetId()] ?? '';
        }
        return $result;
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

    /**
     * Fixed per-region palette (Domain/Values/ResourceLocation::Color()) — every resource in
     * the same region shares one color now instead of the old per-resource color cycle,
     * which stopped being readable once a region had more than a handful of rooms. Keyed off
     * each resource's own location (via $resourceLocations, not the group it's displayed
     * under) so this and OverviewFullCalendarEventsPresenter::AssignColors() (which colors
     * the actual calendar events, and has no access to this page's group tree at all) can't
     * drift out of sync — both ultimately call the same ResourceLocation::Color().
     *
     * @param ResourceGroupAssignment[] $resources
     * @param array<int, string> $resourceLocations resource id => ResourceLocation value, see GetResourceLocations()
     * @return array<int, array{bg: string, fg: string}> resource id => color pair
     */
    private function AssignColors($resources, $resourceLocations)
    {
        $colors = [];
        foreach ($resources as $resource) {
            $colors[$resource->GetId()] = [
                'bg' => ResourceLocation::Color($resourceLocations[$resource->GetId()] ?? null),
                'fg' => '#ffffff',
            ];
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
     * @param array $resourceLocations resource id => ResourceLocation value (or '' if unclassified) — see OverviewFullCalendarPresenter::GetResourceLocations()
     * @param string|null $explicitDefaultResourceId the user's own "設為預設" pick (priority tier 1)
     * @param string|null $systemDefaultResourceId the resource matching the user's classic default-schedule setting (priority tier 3)
     * @param string|null $firstLoginDefaultResourceId Taipei office's "台北 Taipei" room, for a genuinely first-time user with none of tiers 1-3 (priority tier 4) — see OverviewFullCalendarPresenter::ResolveFirstLoginDefaultResourceId()
     */
    public function SetResourceGroups(
        $groups,
        $resourceColors,
        $resourceLocations,
        $explicitDefaultResourceId,
        $systemDefaultResourceId,
        $firstLoginDefaultResourceId
    );
}
