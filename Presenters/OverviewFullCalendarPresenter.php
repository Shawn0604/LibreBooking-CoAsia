<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Authorization/namespace.php');

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

    public function __construct(
        IOverviewFullCalendarPage $page,
        IResourceRepository $resourceRepository,
        IAttributeRepository $attributeRepository,
        IPermissionServiceFactory $permissionServiceFactory
    ) {
        $this->page = $page;
        $this->resourceRepository = $resourceRepository;
        $this->attributeRepository = $attributeRepository;
        $this->permissionServiceFactory = $permissionServiceFactory;
    }

    public function PageLoad(UserSession $userSession)
    {
        $allResources = $this->resourceRepository->GetResourceList();
        $resources = $this->FilterViewableResources($allResources, $userSession);

        $resourceColors = $this->AssignColors($resources);
        [$physicalResources, $videoResources] = $this->SplitByType($resources);

        $this->page->SetResources($physicalResources, $videoResources, $resourceColors);
    }

    /**
     * Same rule the JSON events feed uses (OverviewFullCalendarEventsPresenter) — a
     * non-admin only sees the resources they're actually allowed to view, in the sidebar
     * checklist too, not just in the event data itself.
     *
     * IMPORTANT: this uses CanAccessResource(), not the confusingly-named
     * PermissionService::CanViewResource() — that method only checks a resource's narrow
     * "view-only" permission grants (IResourcePermissionStore::GetViewOnlyResources()), a
     * completely different, much smaller bucket than a user's actual full/bookable resource
     * permissions (GetAllResources()). Using CanViewResource() here meant every resource a
     * user could normally book — the vast majority of real permission grants — was treated
     * as invisible, so a non-admin account correctly placed in the "全體員工" group still saw
     * an empty sidebar and zero calendar events. Confirmed live against user_id=3 (shawn):
     * CanViewResource() returned false for all 12 resources despite group_resource_permissions
     * covering every one of them; CanAccessResource() correctly returned true for all 12.
     *
     * @param BookableResource[] $resources
     * @return BookableResource[]
     */
    private function FilterViewableResources($resources, UserSession $userSession)
    {
        if ($userSession->IsAdmin) {
            return $resources;
        }

        $permissionService = $this->permissionServiceFactory->GetPermissionService();

        return array_values(array_filter(
            $resources,
            fn ($resource) => $permissionService->CanAccessResource($resource, $userSession)
        ));
    }

    private function AssignColors($resources)
    {
        $colors = [];
        foreach ($resources as $index => $resource) {
            if ($resource->HasColor()) {
                $colors[$resource->GetId()] = [
                    'bg' => $resource->GetColor(),
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

    private function SplitByType($resources)
    {
        $videoResourceIds = $this->GetVideoResourceIds($resources);

        $physical = [];
        $video = [];
        foreach ($resources as $resource) {
            if (isset($videoResourceIds[$resource->GetId()])) {
                $video[] = $resource;
            } else {
                $physical[] = $resource;
            }
        }

        return [$physical, $video];
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
     * @param BookableResource[] $physicalResources
     * @param BookableResource[] $videoResources
     * @param array $resourceColors
     */
    public function SetResources($physicalResources, $videoResources, $resourceColors);
}
