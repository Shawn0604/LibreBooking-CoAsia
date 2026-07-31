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
        $tz = $schedule->GetTimezone();
        $isSameDay = $reservationSeries->CurrentInstance()->StartDate()->ToTimezone($tz)->DateEquals($reservationSeries->CurrentInstance()->EndDate()->ToTimezone($tz));

        return new ReservationRuleResult($isSameDay, Resources::GetInstance()->GetString('MultiDayRule', $reservationSeries->Resource()->GetName()));
    }
}
