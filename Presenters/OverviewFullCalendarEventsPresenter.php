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

    /**
     * @var IReservationAuthorization
     */
    private $reservationAuthorization;

    public function __construct(
        IResourceRepository $resourceRepository,
        IReservationViewRepository $reservationViewRepository,
        IPermissionServiceFactory $permissionServiceFactory,
        IPrivacyFilter $privacyFilter,
        IReservationAuthorization $reservationAuthorization
    ) {
        $this->resourceRepository = $resourceRepository;
        $this->reservationViewRepository = $reservationViewRepository;
        $this->permissionServiceFactory = $permissionServiceFactory;
        $this->privacyFilter = $privacyFilter;
        $this->reservationAuthorization = $reservationAuthorization;
    }

    /**
     * @param Date $start
     * @param Date $end
     * @param UserSession $userSession
     * @param bool $mineOnly When true, ignores resource selection entirely and returns only
     *   reservations this user is involved in (any role — owner/participant/invitee), same
     *   query MyMeetingsPresenter uses. Resource-viewability filtering is skipped too: it's
     *   the user's own reservation, so it should still show even if their access to that
     *   resource changed since booking.
     * @return array FullCalendar Event Object[]
     */
    public function GetEvents(Date $start, Date $end, UserSession $userSession, $mineOnly = false)
    {
        $allResources = $this->resourceRepository->GetResourceList();

        if ($mineOnly) {
            $viewableResourceIds = null;
            $resourceColors = $this->AssignColors($allResources);
            $reservations = $this->reservationViewRepository->GetReservations(
                $start,
                $end,
                $userSession->UserId,
                ReservationUserLevel::ALL,
                null,
                null,
                false
            );
        } else {
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
        }

        $timezone = $userSession->Timezone;
        $scriptUrl = Configuration::Instance()->GetScriptUrl();
        $events = [];
        // Memoized per reference number — GetEvents() gets one ReservationItemView row per
        // resource (consolidateByReferenceNumber=false), so a multi-resource reservation
        // would otherwise re-fetch the same ReservationView from the DB once per resource row.
        $reservationViewsByReferenceNumber = [];

        foreach ($reservations as $reservation) {
            // Only resources this user is actually allowed to view make it into the feed
            // at all — not just filtered client-side. Skipped entirely in $mineOnly mode
            // ($viewableResourceIds === null), see the doc comment above.
            if ($viewableResourceIds !== null && !isset($viewableResourceIds[$reservation->ResourceId])) {
                continue;
            }

            $canViewDetails = $this->privacyFilter->CanViewDetails($userSession, $reservation, $reservation->UserId);
            if ($canViewDetails) {
                $title = !empty($reservation->Title) ? $reservation->Title : Resources::GetInstance()->GetString('NoReservationTitle');
                $ownerName = (string)$reservation->GetUserName();
                $ownerFirstName = (string)$reservation->OwnerFirstName;
            } else {
                // Same privacy rule Domain/Access-level consumers already respect
                // (lib/Application/Reservation/PrivacyFilter.php) — never leak the real
                // title to someone who isn't the owner/admin/authorized viewer. Owner name
                // is withheld the same way — seeing WHO booked a slot you can't see the
                // purpose of is still a privacy leak.
                $title = '忙碌';
                $ownerName = '';
                $ownerFirstName = '';
            }

            // Real authorization check (ReservationAuthorization::CanEdit(), the same service
            // Presenters/Reservation/ReservationPage.php's edit form uses) — not a guessed
            // "ownerId === currentUserId" shortcut, which would miss admin/resource-admin
            // edit rights and would wrongly grant edit to someone who is merely a
            // participant/invitee (relevant for $mineOnly, which includes those roles).
            // Also gated on $canViewDetails: someone who can't even see the real title must
            // never get an edit/delete affordance either.
            $referenceNumber = $reservation->ReferenceNumber;
            if (!array_key_exists($referenceNumber, $reservationViewsByReferenceNumber)) {
                $reservationViewsByReferenceNumber[$referenceNumber] =
                    $this->reservationViewRepository->GetReservationForEditing($referenceNumber);
            }
            $canEdit = $canViewDetails
                && $this->reservationAuthorization->CanEdit($reservationViewsByReferenceNumber[$referenceNumber], $userSession);

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
                    'referenceNumber' => $reservation->ReferenceNumber,
                    'resourceId' => (string)$reservation->ResourceId,
                    'resourceName' => $reservation->ResourceName,
                    'ownerName' => $ownerName,
                    'ownerFirstName' => $ownerFirstName,
                    'canEdit' => $canEdit,
                    'canDelete' => $canEdit,
                    'isRecurring' => (bool)$reservation->IsRecurring,
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
