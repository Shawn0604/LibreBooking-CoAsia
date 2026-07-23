<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Schedule/namespace.php');

class OverviewPresenter
{
    /** Business-hours window the week grid renders (matches the CoAsia mockup: 09:00–18:00). */
    private const DAY_START_HOUR = 9;
    private const DAY_END_HOUR = 18;

    private const FALLBACK_COLORS = [
        '#3e7bd1', '#1c9c8c', '#7a5cc4', '#c98a2e', '#3f9d5c', '#c1547a', '#5c6b84',
    ];

    /**
     * @var IOverviewPage
     */
    private $page;

    /**
     * @var IResourceRepository
     */
    private $resourceRepository;

    /**
     * @var IReservationViewRepository
     */
    private $reservationViewRepository;

    public function __construct(
        IOverviewPage $page,
        IResourceRepository $resourceRepository,
        IReservationViewRepository $reservationViewRepository
    ) {
        $this->page = $page;
        $this->resourceRepository = $resourceRepository;
        $this->reservationViewRepository = $reservationViewRepository;
    }

    public function PageLoad(UserSession $userSession)
    {
        $timezone = $userSession->Timezone;
        $weekStart = $this->GetWeekStart($timezone);
        $weekEnd = $weekStart->AddDays(7);

        $resources = $this->resourceRepository->GetResourceList();
        $resourceColors = $this->AssignColors($resources);

        // Not consolidated by reference number: a reservation spanning multiple resources
        // should render as one colored block per resource, matching the resource's own color.
        $reservations = $this->reservationViewRepository->GetReservations(
            $weekStart,
            $weekEnd,
            null,
            null,
            null,
            null,
            false
        );

        $days = $this->BuildDays($weekStart, $timezone, $reservations, $resourceColors, $userSession);

        $this->page->SetWeekStart($weekStart);
        $this->page->SetWeekEnd($weekStart->AddDays(6));
        $this->page->SetPreviousWeek($weekStart->AddDays(-7));
        $this->page->SetNextWeek($weekStart->AddDays(7));
        $this->page->SetResources($resources, $resourceColors);
        $this->page->SetDays($days);
        $this->page->SetDayStartHour(self::DAY_START_HOUR);
        $this->page->SetDayEndHour(self::DAY_END_HOUR);
        $this->page->SetHours($this->BuildHourLabels());
    }

    /**
     * @return string[] e.g. ['09:00', '10:00', ... ] for the header row of hour ticks
     */
    private function BuildHourLabels()
    {
        $hours = [];
        for ($hour = self::DAY_START_HOUR; $hour < self::DAY_END_HOUR; $hour++) {
            $hours[] = sprintf('%02d:00', $hour);
        }
        return $hours;
    }

    /**
     * @param string $timezone
     * @return Date Monday of the requested (or current) week, at midnight.
     */
    private function GetWeekStart($timezone)
    {
        $requested = $this->page->GetRequestedWeekStart();
        $reference = $requested !== null ? $requested : Date::Now()->ToTimezone($timezone);

        $weekday = $reference->Weekday();
        // DayOfWeek: Sunday=0 ... Saturday=6. Shift so Monday is the start of the grid.
        $daysSinceMonday = ($weekday === 0) ? 6 : $weekday - 1;

        return $reference->GetDate()->AddDays(-$daysSinceMonday);
    }

    /**
     * @param BookableResource[] $resources
     * @return array<int, array{bg: string, fg: string}> keyed by resource id
     */
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

    /**
     * @param Date $weekStart
     * @param string $timezone
     * @param ReservationItemView[] $reservations
     * @param array $resourceColors
     * @return array
     */
    private function BuildDays($weekStart, $timezone, $reservations, $resourceColors, UserSession $userSession)
    {
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->AddDays($i);
            $days[] = [
                'date' => $date,
                'isToday' => $date->DateEquals(Date::Now()->ToTimezone($timezone)),
                'isWeekend' => $i >= 5,
                'events' => [],
            ];
        }

        $windowMinutes = (self::DAY_END_HOUR - self::DAY_START_HOUR) * 60;

        foreach ($reservations as $reservation) {
            $start = $reservation->StartDate->ToTimezone($timezone);
            $end = $reservation->EndDate->ToTimezone($timezone);

            $dayIndex = $this->DayIndexWithin($days, $start);
            if ($dayIndex === null) {
                continue;
            }

            $startMinutes = ($start->Hour() * 60 + $start->Minute()) - (self::DAY_START_HOUR * 60);
            $endMinutes = ($end->Hour() * 60 + $end->Minute()) - (self::DAY_START_HOUR * 60);

            if (!$end->DateEquals($start)) {
                // Reservation spans past midnight (within this day's column) — clip to the visible window.
                $endMinutes = $windowMinutes;
            }

            $startMinutes = max(0, min($windowMinutes, $startMinutes));
            $endMinutes = max($startMinutes + 15, min($windowMinutes, $endMinutes));

            $colors = $resourceColors[$reservation->ResourceId] ?? ['bg' => '#5c6b84', 'fg' => '#ffffff'];

            $days[$dayIndex]['events'][] = [
                'referenceNumber' => $reservation->ReferenceNumber,
                'title' => $reservation->Title,
                'resourceName' => $reservation->ResourceName,
                'top' => round(($startMinutes / $windowMinutes) * 100, 2),
                'height' => round((($endMinutes - $startMinutes) / $windowMinutes) * 100, 2),
                'bg' => $colors['bg'],
                'fg' => $colors['fg'],
                'resourceId' => $reservation->ResourceId,
                'isMine' => $reservation->UserId == $userSession->UserId,
                'startLabel' => $start->Format('H:i'),
            ];
        }

        return $days;
    }

    /**
     * @param array $days
     * @param Date $date
     * @return int|null 0-6 (Mon-Sun) if within the displayed week, otherwise null
     */
    private function DayIndexWithin($days, $date)
    {
        foreach ($days as $index => $day) {
            if ($date->DateEquals($day['date'])) {
                return $index;
            }
        }
        return null;
    }
}

interface IOverviewPage
{
    /**
     * @return Date|null
     */
    public function GetRequestedWeekStart();

    public function SetWeekStart(Date $date);

    public function SetWeekEnd(Date $date);

    public function SetPreviousWeek(Date $date);

    public function SetNextWeek(Date $date);

    /**
     * @param BookableResource[] $resources
     * @param array $resourceColors
     */
    public function SetResources($resources, $resourceColors);

    public function SetDays(array $days);

    public function SetDayStartHour($hour);

    public function SetDayEndHour($hour);

    /**
     * @param string[] $hours
     */
    public function SetHours($hours);
}
