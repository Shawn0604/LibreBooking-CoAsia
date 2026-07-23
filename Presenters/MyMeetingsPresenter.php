<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');

class MyMeetingsPresenter
{
    /** How far back/forward to look. Bounded so a long-running recurring series can't blow this up. */
    private const PAST_DAYS = 180;
    private const FUTURE_DAYS = 365;

    /**
     * @var IMyMeetingsPage
     */
    private $page;

    /**
     * @var IReservationViewRepository
     */
    private $reservationViewRepository;

    public function __construct(IMyMeetingsPage $page, IReservationViewRepository $reservationViewRepository)
    {
        $this->page = $page;
        $this->reservationViewRepository = $reservationViewRepository;
    }

    public function PageLoad(UserSession $userSession)
    {
        $timezone = $userSession->Timezone;
        $now = Date::Now()->ToTimezone($timezone);

        $reservations = $this->reservationViewRepository->GetReservations(
            $now->AddDays(-self::PAST_DAYS),
            $now->AddDays(self::FUTURE_DAYS),
            $userSession->UserId,
            ReservationUserLevel::ALL,
            null,
            null,
            true
        );

        $upcoming = [];
        $past = [];

        /** @var ReservationItemView $reservation */
        foreach ($reservations as $reservation) {
            $start = $reservation->StartDate->ToTimezone($timezone);
            if ($start->GreaterThanOrEqual($now)) {
                $upcoming[] = $reservation;
            } else {
                $past[] = $reservation;
            }
        }

        usort($upcoming, fn ($a, $b) => $a->StartDate->Compare($b->StartDate));
        usort($past, fn ($a, $b) => $b->StartDate->Compare($a->StartDate));

        $this->page->SetUpcoming($this->GroupByDay($upcoming, $timezone));
        $this->page->SetPast($this->GroupByDay($past, $timezone));
    }

    /**
     * @param ReservationItemView[] $reservations
     * @param string $timezone
     * @return array
     */
    private function GroupByDay($reservations, $timezone)
    {
        $groups = [];
        $order = [];

        foreach ($reservations as $reservation) {
            $start = $reservation->StartDate->ToTimezone($timezone);
            $key = $start->Format('Y-m-d');

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'date' => $start->GetDate(),
                    'items' => [],
                ];
                $order[] = $key;
            }

            $groups[$key]['items'][] = [
                'referenceNumber' => $reservation->ReferenceNumber,
                'title' => $reservation->Title,
                'resourceName' => $reservation->ResourceName,
                'startLabel' => $start->Format('H:i'),
                'endLabel' => $reservation->EndDate->ToTimezone($timezone)->Format('H:i'),
                'bg' => $reservation->GetColor(),
                'fg' => $reservation->GetTextColor(),
                'requiresApproval' => !empty($reservation->RequiresApproval),
            ];
        }

        $ordered = [];
        foreach ($order as $key) {
            $ordered[] = $groups[$key];
        }

        return $ordered;
    }
}

interface IMyMeetingsPage
{
    /**
     * @param array $days
     */
    public function SetUpcoming($days);

    /**
     * @param array $days
     */
    public function SetPast($days);
}
