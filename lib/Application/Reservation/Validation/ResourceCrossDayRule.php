<?php

class ResourceCrossDayRule implements IReservationValidationRule
{
    /**
     * @var IScheduleRepository
     */
    private $scheduleRepository;

    public function __construct(IScheduleRepository $scheduleRepository)
    {
        $this->scheduleRepository = $scheduleRepository;
    }

    public function Validate($reservationSeries, $retryParameters)
    {
        // CoAsia: cross-day reservations are disallowed for every resource, full stop — no
        // longer gated by each resource's own GetAllowMultiday() flag (that admin setting is
        // unused now, left in place rather than ripping out its column/UI for a one-line
        // policy change).
        $schedule = $this->scheduleRepository->LoadById($reservationSeries->ScheduleId());
        // LoadById() returns plain null (not a null-object) when the id does not resolve to a
        // row -- a stale/orphaned ScheduleId on the resource, or bad test data, would
        // otherwise crash this rule with a fatal "call to GetTimezone() on null" and take the
        // whole reservation-save request down with it. Falling back to the system default
        // timezone keeps the cross-day check meaningful instead of failing closed on data
        // that's broken in some unrelated way.
        $tz = $schedule !== null ? $schedule->GetTimezone() : Configuration::Instance()->GetDefaultTimezone();
        $isSameDay = $reservationSeries->CurrentInstance()->StartDate()->ToTimezone($tz)->DateEquals($reservationSeries->CurrentInstance()->EndDate()->ToTimezone($tz));

        return new ReservationRuleResult($isSameDay, Resources::GetInstance()->GetString('MultiDayRule', $reservationSeries->Resource()->GetName()));
    }
}
