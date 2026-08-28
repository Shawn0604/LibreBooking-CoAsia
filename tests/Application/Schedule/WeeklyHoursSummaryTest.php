<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Application/Schedule/WeeklyHoursSummary.php');

class WeeklyHoursSummaryTest extends TestBase
{
    private function day(bool $open, ?string $start = null, ?string $end = null): array
    {
        return ['open' => $open, 'start' => $start, 'end' => $end];
    }

    public function testAllSevenDaysFullDayIsAllDay()
    {
        // What the "全天開放" button produces: every day on, 00:00-00:00.
        $days = [];
        for ($d = 0; $d <= 6; $d++) {
            $days[$d] = $this->day(true, '00:00', '00:00');
        }

        $this->assertEquals(
            ['kind' => WeeklyHoursSummary::ALL_DAY, 'start' => null, 'end' => null],
            WeeklyHoursSummary::Describe($days)
        );
    }

    public function testTwentyFourColonZeroZeroEndAlsoCountsAsFullDay()
    {
        $days = [];
        for ($d = 0; $d <= 6; $d++) {
            $days[$d] = $this->day(true, '00:00', '24:00');
        }

        $this->assertEquals(WeeklyHoursSummary::ALL_DAY, WeeklyHoursSummary::Describe($days)['kind']);
    }

    public function testMondayToFridayIdenticalRangeWeekendClosedIsWeekdays()
    {
        $days = [
            0 => $this->day(false),
            1 => $this->day(true, '09:00', '18:00'),
            2 => $this->day(true, '09:00', '18:00'),
            3 => $this->day(true, '09:00', '18:00'),
            4 => $this->day(true, '09:00', '18:00'),
            5 => $this->day(true, '09:00', '18:00'),
            6 => $this->day(false),
        ];

        $this->assertEquals(
            ['kind' => WeeklyHoursSummary::WEEKDAYS, 'start' => '09:00', 'end' => '18:00'],
            WeeklyHoursSummary::Describe($days)
        );
    }

    public function testWeekdaysButOneDayDiffersIsVaries()
    {
        $days = [
            0 => $this->day(false),
            1 => $this->day(true, '09:00', '18:00'),
            2 => $this->day(true, '09:00', '18:00'),
            3 => $this->day(true, '10:00', '17:00'),
            4 => $this->day(true, '09:00', '18:00'),
            5 => $this->day(true, '09:00', '18:00'),
            6 => $this->day(false),
        ];

        $this->assertEquals(WeeklyHoursSummary::VARIES, WeeklyHoursSummary::Describe($days)['kind']);
    }

    public function testWeekdaysPlusSaturdayIsVaries()
    {
        $days = [];
        for ($d = 0; $d <= 6; $d++) {
            $days[$d] = $this->day($d >= 1, '09:00', '18:00');
        }
        $days[0] = $this->day(false);

        $this->assertEquals(WeeklyHoursSummary::VARIES, WeeklyHoursSummary::Describe($days)['kind']);
    }

    public function testNoDayOpenIsClosed()
    {
        $days = [];
        for ($d = 0; $d <= 6; $d++) {
            $days[$d] = $this->day(false);
        }

        $this->assertEquals(WeeklyHoursSummary::CLOSED, WeeklyHoursSummary::Describe($days)['kind']);
    }

    public function testMissingDaysTreatedAsClosed()
    {
        $this->assertEquals(WeeklyHoursSummary::CLOSED, WeeklyHoursSummary::Describe([])['kind']);
    }

    public function testAllSevenOpenButNotFullDayIsVaries()
    {
        $days = [];
        for ($d = 0; $d <= 6; $d++) {
            $days[$d] = $this->day(true, '09:00', '18:00');
        }

        $this->assertEquals(WeeklyHoursSummary::VARIES, WeeklyHoursSummary::Describe($days)['kind']);
    }
}
