<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');

/**
 * Read-only "is this resource free for this exact range" check backing the "此時段可用"
 * indicator in both Quick Create and the Reservation Drawer (tpl/Overview/overview_fullcalendar.tpl,
 * Web/scripts/overview_fullcalendar.js). Re-queries on every date/time/resource change —
 * never a client-side guess — via the same ReservationViewRepository the real save-time
 * conflict check (ResourceAvailabilityRule -> ReservationConflictIdentifier ->
 * ResourceAvailability) is itself built on, just without the full ReservationSeries
 * validation-rule machinery an actual save needs.
 *
 * excludeReferenceNumber is optional and only meaningful for the Drawer's edit mode — without
 * it, editing a reservation would always see its own existing slot as a "conflict" and report
 * itself as unavailable. Quick Create never passes it (it only ever creates new reservations,
 * so there's no "self" to exclude).
 */
class OverviewFullCalendarAvailabilityPage extends SecurePage
{
    public function __construct()
    {
        parent::__construct('', 1);
    }

    public function PageLoad()
    {
        $userSession = ServiceLocator::GetServer()->GetUserSession();

        $resourceId = $this->GetQuerystring('resourceId');
        $start = Date::Parse($this->GetQuerystring('start'), $userSession->Timezone);
        $end = Date::Parse($this->GetQuerystring('end'), $userSession->Timezone);
        $excludeReferenceNumber = $this->GetQuerystring('excludeReferenceNumber');

        $available = true;
        if (!empty($resourceId) && !($start instanceof NullDate) && !($end instanceof NullDate) && $end->GreaterThan($start)) {
            $requestedRange = new DateRange($start, $end);
            $repository = new ReservationViewRepository();

            // GetReservations()'s underlying SQL deliberately over-fetches — its date filter
            // uses inclusive >=/<= comparisons, so a reservation ending exactly at $start (or
            // starting exactly at $end) comes back too, even though it doesn't actually
            // overlap. That's fine for the real save-time check (ResourceAvailabilityRule ->
            // ReservationConflictIdentifier), which re-filters with BufferedTimes()->Overlaps()
            // — an exclusive-boundary, buffer-aware comparison — before deciding there's a real
            // conflict. This endpoint skipped that second step entirely at first, which made a
            // reservation ending at 15:30 falsely block a new one starting at 15:30: same
            // re-filter applied here now, so back-to-back bookings read as available exactly
            // like they do when you actually hit save.
            $reservations = $repository->GetReservations($start, $end, null, null, null, [$resourceId]);
            $reservations = array_filter($reservations, function ($reservation) use ($requestedRange) {
                return $reservation->BufferedTimes()->Overlaps($requestedRange);
            });
            if (!empty($excludeReferenceNumber)) {
                $reservations = array_filter($reservations, function ($reservation) use ($excludeReferenceNumber) {
                    return $reservation->GetReferenceNumber() !== $excludeReferenceNumber;
                });
            }

            $blackouts = $repository->GetBlackoutsWithin($requestedRange, null, [$resourceId]);
            $blackouts = array_filter($blackouts, function ($blackout) use ($requestedRange) {
                return $blackout->BufferedTimes()->Overlaps($requestedRange);
            });

            $available = empty($reservations) && empty($blackouts);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['available' => $available]);
        exit;
    }
}
