<?php

/**
 * Turns a schedule's weekly open hours into a one-line summary kind, for the resource-list
 * column (§94). Pure -- takes plain per-day tuples so it is trivially unit-testable; the
 * presenter is responsible for pulling those tuples out of an IScheduleLayout and for
 * turning the returned kind into a localized string.
 */
class WeeklyHoursSummary
{
    public const ALL_DAY = 'all_day';
    public const WEEKDAYS = 'weekdays';
    public const CLOSED = 'closed';
    public const VARIES = 'varies';

    /**
     * A day counts as "full day" when its reservable period runs from midnight to midnight.
     * LibreBooking stores that as 00:00-00:00 (see schedule.js saveScheduleHours / the
     * "全天開放" button); 24:00 is accepted defensively.
     */
    private static function isFullDay(?string $start, ?string $end): bool
    {
        return in_array($start, ['00:00', '0:00'], true)
            && in_array($end, ['00:00', '0:00', '24:00'], true);
    }

    /**
     * @param array<int, array{open: bool, start: ?string, end: ?string}> $days keyed 0(Sun)..6(Sat)
     * @return array{kind: string, start: ?string, end: ?string}
     */
    public static function Describe(array $days): array
    {
        $open = [];
        for ($d = 0; $d <= 6; $d++) {
            $day = $days[$d] ?? ['open' => false, 'start' => null, 'end' => null];
            if (!empty($day['open'])) {
                $open[$d] = [$day['start'] ?? null, $day['end'] ?? null];
            }
        }

        if (count($open) === 0) {
            return ['kind' => self::CLOSED, 'start' => null, 'end' => null];
        }

        if (count($open) === 7) {
            $allFull = true;
            foreach ($open as [$s, $e]) {
                if (!self::isFullDay($s, $e)) {
                    $allFull = false;
                    break;
                }
            }
            if ($allFull) {
                return ['kind' => self::ALL_DAY, 'start' => null, 'end' => null];
            }
        }

        // Mon..Fri (1..5) open on one identical, non-full range; Sun & Sat closed.
        if (array_keys($open) === [1, 2, 3, 4, 5]) {
            $ranges = array_unique(array_map(static fn ($r) => $r[0] . '-' . $r[1], $open));
            if (count($ranges) === 1) {
                [$s, $e] = $open[1];
                if (!self::isFullDay($s, $e)) {
                    return ['kind' => self::WEEKDAYS, 'start' => $s, 'end' => $e];
                }
            }
        }

        return ['kind' => self::VARIES, 'start' => null, 'end' => null];
    }
}
