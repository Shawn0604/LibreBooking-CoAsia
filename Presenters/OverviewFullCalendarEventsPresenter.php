<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Authorization/namespace.php');
// namespace.php (not just PrivacyFilter.php) — PrivacyFilter::CanViewDetails() depends on
// the sibling ReservationDetailsFilter class, which a narrower require was missing; that
// gap surfaced as a fatal "Class ReservationDetailsFilter not found" on every real request,
// which is why no events ever rendered (the unit tests didn't catch it because they use
// tests/fakes/FakePrivacyFilter.php, not the real PrivacyFilter).
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');
require_once(ROOT_DIR . 'lib/Server/namespace.php');
require_once(ROOT_DIR . 'Pages/Pages.php');

/**
 * Prototype-only JSON feed for Web/overview_fullcalendar.php (served via
 * Web/ajax/overview_fullcalendar_events.php). Builds FullCalendar Event Objects —
 * https://fullcalendar.io/docs/event-parsing — deliberately computing NO layout (no
 * top/height/left/width): FullCalendar lays out overlapping and cross-midnight events
 * itself. This is the read-only counterpart to OverviewPresenter::BuildDays(), reusing the
 * same repositories/services rather than duplicating any reservation or permission logic.
 */
class OverviewFullCalendarEventsPresenter
{
    /** Kept identical to OverviewPresenter::FALLBACK_COLORS and
     * OverviewFullCalendarPresenter::FALLBACK_COLORS so all three surfaces agree on colors
     * during the side-by-side evaluation. */
    private const FALLBACK_COLORS = [
        '#d50000', '#e67c73', '#f4511e', '#f6bf26', '#33b679', '#0b8043',
        '#039be5', '#3f51b5', '#7986cb', '#8e24aa', '#616161',
    ];

    /**
     * @var IResourceRepository
     */
    private $resourceRepository;

    /**
     * @var IReservationViewRepository
     */
    private $reservationViewRepository;

    /**
     * @var IPermissionServiceFactory
     */
    private $permissionServiceFactory;

    /**
     * @var IPrivacyFilter
     */
    private $privacyFilter;

    public function __construct(
        IResourceRepository $resourceRepository,
        IReservationViewRepository $reservationViewRepository,
        IPermissionServiceFactory $permissionServiceFactory,
        IPrivacyFilter $privacyFilter
    ) {
        $this->resourceRepository = $resourceRepository;
        $this->reservationViewRepository = $reservationViewRepository;
        $this->permissionServiceFactory = $permissionServiceFactory;
        $this->privacyFilter = $privacyFilter;
    }

    /**
     * @param Date $start
     * @param Date $end
     * @param UserSession $userSession
     * @return array FullCalendar Event Object[]
     */
    public function GetEvents(Date $start, Date $end, UserSession $userSession)
    {
        $allResources = $this->resourceRepository->GetResourceList();
        $viewableResources = $this->FilterViewableResources($allResources, $userSession);
        $resourceColors = $this->AssignColors($viewableResources);

        $viewableResourceIds = [];
        foreach ($viewableResources as $resource) {
            $viewableResourceIds[$resource->GetId()] = true;
        }

        $reservations = $this->reservationViewRepository->GetReservations(
            $start,
            $end,
            null,
            null,
            null,
            null,
            false
        );

        $timezone = $userSession->Timezone;
        $scriptUrl = Configuration::Instance()->GetScriptUrl();
        $events = [];

        foreach ($reservations as $reservation) {
            // Only resources this user is actually allowed to view make it into the feed
            // at all — not just filtered client-side.
            if (!isset($viewableResourceIds[$reservation->ResourceId])) {
                continue;
            }

            $canViewDetails = $this->privacyFilter->CanViewDetails($userSession, $reservation, $reservation->UserId);
            if ($canViewDetails) {
                $title = !empty($reservation->Title) ? $reservation->Title : '未命名預約';
            } else {
                // Same privacy rule Domain/Access-level consumers already respect
                // (lib/Application/Reservation/PrivacyFilter.php) — never leak the real
                // title to someone who isn't the owner/admin/authorized viewer.
                $title = '忙碌';
            }

            $colors = $resourceColors[$reservation->ResourceId] ?? ['bg' => '#5c6b84'];

            $events[] = [
                'id' => $reservation->ReferenceNumber,
                'title' => $title,
                'start' => $reservation->StartDate->ToTimezone($timezone)->Format('Y-m-d\TH:i:s'),
                'end' => $reservation->EndDate->ToTimezone($timezone)->Format('Y-m-d\TH:i:s'),
                'url' => $scriptUrl . '/' . Pages::RESERVATION . '?' . QueryStringKeys::REFERENCE_NUMBER . '=' . urlencode($reservation->ReferenceNumber),
                'backgroundColor' => $this->PastelColor($colors['bg']),
                'borderColor' => $colors['bg'],
                'textColor' => '#1c2333',
                'extendedProps' => [
                    'resourceId' => (string)$reservation->ResourceId,
                    'resourceName' => $reservation->ResourceName,
                ],
            ];
        }

        return $events;
    }

    /**
     * Uses CanAccessResource(), not PermissionService::CanViewResource() — see the matching
     * comment on OverviewFullCalendarPresenter::FilterViewableResources() for why: the
     * latter only checks a resource's narrow "view-only" grants, not a user's actual
     * full/bookable permissions, and using it here made every reservation on a resource a
     * non-admin could normally book disappear from their events feed entirely.
     *
     * @param BookableResource[] $resources
     * @param UserSession $userSession
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
                $colors[$resource->GetId()] = ['bg' => $resource->GetColor()];
            } else {
                $colors[$resource->GetId()] = ['bg' => self::FALLBACK_COLORS[$index % count(self::FALLBACK_COLORS)]];
            }
        }
        return $colors;
    }

    /** Same formula as OverviewPresenter::PastelColor(), kept in sync deliberately. */
    private function PastelColor($hex)
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return '#eef0f4';
        }

        $whiteWeight = 0.70;
        $mix = function ($channelHex) use ($whiteWeight) {
            $value = hexdec($channelHex);
            return (int) round($value * (1 - $whiteWeight) + 255 * $whiteWeight);
        };

        return sprintf(
            '#%02x%02x%02x',
            $mix(substr($hex, 0, 2)),
            $mix(substr($hex, 2, 2)),
            $mix(substr($hex, 4, 2))
        );
    }
}
