<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Schedule/namespace.php');

class OverviewPresenter
{
    /** Full-day window the week grid renders. */
    private const DAY_START_HOUR = 0;
    private const DAY_END_HOUR = 24;

    /** Google Calendar's own 11 event colors (Tomato through Graphite) — used as the
     * fallback palette so resources without an admin-configured color still land on a
     * real Material swatch instead of an arbitrary hue. */
    private const FALLBACK_COLORS = [
        '#d50000', '#e67c73', '#f4511e', '#f6bf26', '#33b679', '#0b8043',
        '#039be5', '#3f51b5', '#7986cb', '#8e24aa', '#616161',
    ];

    /** Same custom attribute label Task 1-3 use to detect a Video Conference resource. */
    private const ATTRIBUTE_VIDEO_URL = 'video_url';

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

    /**
     * @var IAttributeRepository
     */
    private $attributeRepository;

    public function __construct(
        IOverviewPage $page,
        IResourceRepository $resourceRepository,
        IReservationViewRepository $reservationViewRepository,
        IAttributeRepository $attributeRepository
    ) {
        $this->page = $page;
        $this->resourceRepository = $resourceRepository;
        $this->reservationViewRepository = $reservationViewRepository;
        $this->attributeRepository = $attributeRepository;
    }

    public function PageLoad(UserSession $userSession)
    {
        $timezone = $userSession->Timezone;
        $weekStart = $this->GetWeekStart($timezone);
        $weekEnd = $weekStart->AddDays(7);

        $resources = $this->resourceRepository->GetResourceList();
        $resourceColors = $this->AssignColors($resources);
        [$physicalResources, $videoResources] = $this->SplitByType($resources);

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

        $days = $this->BuildDays($weekStart, $timezone, $reservations, $resourceColors);
        $currentWeekStart = $this->GetWeekStart($timezone, true);

        $this->page->SetWeekStart($weekStart);
        $this->page->SetWeekEnd($weekStart->AddDays(6));
        $this->page->SetPreviousWeek($weekStart->AddDays(-7));
        $this->page->SetNextWeek($weekStart->AddDays(7));
        $this->page->SetIsCurrentWeek($weekStart->DateEquals($currentWeekStart));
        $this->page->SetResources($physicalResources, $videoResources, $resourceColors);
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
     * @param bool $ignoreRequested pass true to always compute *this instant's* week,
     *        regardless of the requested ?sd= — used to tell "今天" apart from other weeks.
     * @return Date Monday of the requested (or current) week, at midnight.
     */
    private function GetWeekStart($timezone, $ignoreRequested = false)
    {
        $requested = $ignoreRequested ? null : $this->page->GetRequestedWeekStart();
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
    private function BuildDays($weekStart, $timezone, $reservations, $resourceColors)
    {
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->AddDays($i);
            $days[] = [
                'date' => $date,
                'isoDate' => $date->Format('Y-m-d'),
                'isToday' => $date->DateEquals(Date::Now()->ToTimezone($timezone)),
                'isWeekend' => $i >= 5,
                'events' => [],
            ];
        }

        $windowMinutes = (self::DAY_END_HOUR - self::DAY_START_HOUR) * 60;
        $now = Date::Now();

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
                'bg' => $this->PastelColor($colors['bg']),
                'accent' => $colors['bg'],
                'resourceId' => $reservation->ResourceId,
                'isPast' => $end->LessThanOrEqual($now),
                'startLabel' => $start->Format('H:i'),
                'endLabel' => $end->Format('H:i'),
                // Only used by AssignEventColumns() below — stripped before the view sees it.
                'startMinutes' => $startMinutes,
                'endMinutes' => $endMinutes,
            ];
        }

        foreach ($days as &$day) {
            $day['events'] = $this->AssignEventColumns($day['events']);
        }
        unset($day);

        return $days;
    }

    /** Horizontal gap between side-by-side columns, in pixels. */
    private const EVENT_COLUMN_GAP_PX = 2;

    /** Fixed inset from the day column's left/right edge, in pixels (matches the old
     * static `left: 3px; right: 3px;` every event used before overlap layout existed). */
    private const EVENT_OUTER_INSET_PX = 3;

    /**
     * Lays out one day's events Google Calendar-style: events that overlap in time sit
     * in side-by-side columns instead of stacking on top of each other. Events that only
     * touch (one ends exactly when the other starts) are NOT considered overlapping and
     * each keep the full width.
     *
     * Two passes over the events (sorted by start time, so the result never depends on
     * the order reservations came back from the DB in):
     *  1. Sweep left-to-right assigning each event the first free column (a column is
     *     "free" once its last event's end time is <= this event's start time), while
     *     tracking the running max end-time of the current overlap cluster. Once an
     *     event starts at or after that running max, the cluster is closed out and a
     *     fresh one begins.
     *  2. Every event in a cluster shares that cluster's total column count, which is
     *     what actually determines each event's width — an event's own column index
     *     only determines its position among that shared width.
     *
     * @param array $events
     * @return array Same events, sorted by start time, with 'left'/'width' CSS values
     *               added and the temporary 'startMinutes'/'endMinutes' keys removed.
     */
    private function AssignEventColumns($events)
    {
        if (count($events) <= 1) {
            foreach ($events as &$event) {
                $event['left'] = self::EVENT_OUTER_INSET_PX . 'px';
                $event['width'] = 'calc(100% - ' . (self::EVENT_OUTER_INSET_PX * 2) . 'px)';
                unset($event['startMinutes'], $event['endMinutes']);
            }
            unset($event);
            return $events;
        }

        usort($events, function ($a, $b) {
            if ($a['startMinutes'] !== $b['startMinutes']) {
                return $a['startMinutes'] <=> $b['startMinutes'];
            }
            return $a['endMinutes'] <=> $b['endMinutes'];
        });

        $columnEndTimes = [];
        $clusterStartIndex = 0;
        $clusterMaxEnd = null;

        $count = count($events);
        for ($i = 0; $i < $count; $i++) {
            if ($clusterMaxEnd !== null && $events[$i]['startMinutes'] >= $clusterMaxEnd) {
                $this->FinalizeEventCluster($events, $clusterStartIndex, $i, count($columnEndTimes));
                $columnEndTimes = [];
                $clusterStartIndex = $i;
                $clusterMaxEnd = null;
            }

            $assignedColumn = null;
            foreach ($columnEndTimes as $columnIndex => $endTime) {
                if ($endTime <= $events[$i]['startMinutes']) {
                    $assignedColumn = $columnIndex;
                    break;
                }
            }
            if ($assignedColumn === null) {
                $assignedColumn = count($columnEndTimes);
            }

            $columnEndTimes[$assignedColumn] = $events[$i]['endMinutes'];
            $events[$i]['columnIndex'] = $assignedColumn;
            $clusterMaxEnd = $clusterMaxEnd === null
                ? $events[$i]['endMinutes']
                : max($clusterMaxEnd, $events[$i]['endMinutes']);
        }
        $this->FinalizeEventCluster($events, $clusterStartIndex, $count, count($columnEndTimes));

        foreach ($events as &$event) {
            [$event['left'], $event['width']] = $this->EventColumnStyle($event['columnIndex'], $event['columnCount']);
            unset($event['startMinutes'], $event['endMinutes'], $event['columnIndex'], $event['columnCount']);
        }
        unset($event);

        return $events;
    }

    /** Stamps 'columnCount' onto every event in $events[$startIndex, $endIndexExclusive). */
    private function FinalizeEventCluster(&$events, $startIndex, $endIndexExclusive, $columnCount)
    {
        for ($i = $startIndex; $i < $endIndexExclusive; $i++) {
            $events[$i]['columnCount'] = $columnCount;
        }
    }

    /**
     * @param int $columnIndex 0-based column this event sits in
     * @param int $columnCount total columns its overlap cluster needs
     * @return string[] [left, width] CSS values (nested calc(), gap-aware)
     */
    private function EventColumnStyle($columnIndex, $columnCount)
    {
        $inset = self::EVENT_OUTER_INSET_PX;
        $gap = self::EVENT_COLUMN_GAP_PX;

        if ($columnCount <= 1) {
            return [$inset . 'px', 'calc(100% - ' . ($inset * 2) . 'px)'];
        }

        $totalGaps = ($columnCount - 1) * $gap;
        $columnWidthExpr = '((100% - ' . ($inset * 2 + $totalGaps) . 'px) / ' . $columnCount . ')';

        $left = $columnIndex === 0
            ? $inset . 'px'
            : 'calc(' . $inset . 'px + ' . $columnIndex . ' * (' . $columnWidthExpr . ' + ' . $gap . 'px))';

        return [$left, 'calc(' . $columnWidthExpr . ')'];
    }

    /**
     * Splits resources into physical rooms vs. Video Conference resources, using the
     * same "video_url is non-empty" rule Task 1-3 use elsewhere (see
     * Presenters/Reservation/ReservationSavePresenter.php::GetVideoConferenceResources()).
     *
     * @param BookableResource[] $resources
     * @return array{0: BookableResource[], 1: BookableResource[]} [physicalResources, videoResources]
     */
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

    /**
     * @param BookableResource[] $resources
     * @return array<int, bool> resource ids that have a non-empty video_url attribute value
     */
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

    /**
     * Mixes a resource's full-saturation color toward white for a Google Calendar-style
     * pastel event background, computed server-side rather than relying on the browser's
     * CSS color-mix() support (inconsistent across browsers/versions).
     *
     * @param string $hex e.g. '#3e7bd1'
     * @return string e.g. '#d5e2f5'
     */
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
     * @param bool $isCurrentWeek
     */
    public function SetIsCurrentWeek($isCurrentWeek);

    /**
     * @param BookableResource[] $physicalResources
     * @param BookableResource[] $videoResources
     * @param array $resourceColors
     */
    public function SetResources($physicalResources, $videoResources, $resourceColors);

    public function SetDays(array $days);

    public function SetDayStartHour($hour);

    public function SetDayEndHour($hour);

    /**
     * @param string[] $hours
     */
    public function SetHours($hours);
}
