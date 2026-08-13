<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'Domain/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');

class ResourceCrossDayRuleTest extends TestBase
{
    private IScheduleRepository&\PHPUnit\Framework\MockObject\MockObject $scheduleRepository;

    /**
     * @var Schedule
     */
    private $schedule;

    public function setUp(): void
    {
        parent::setup();
        $this->scheduleRepository = $this->createMock('IScheduleRepository');
        $this->schedule = new FakeSchedule();
        $this->schedule->SetTimezone('America/Chicago');
    }

    public function teardown(): void
    {
        parent::teardown();
    }

    public function testRuleIsValidIfBeginsAndEndsOnSameDayInScheduleTimezone()
    {
        $start = Date::Parse('2013-01-01 12:00', 'UTC');
        $end = $start->AddHours(2);

        $reservation = new TestReservationSeries();
        $reservation->WithCurrentInstance(new TestReservation('1', new DateRange($start, $end)));
        $resource = new FakeBookableResource(1);
        $resource->SetAllowMultiday(false);

        $reservation->WithResource($resource);

        $this->scheduleRepository->expects($this->once())
        ->method('LoadById')
        ->with($this->equalTo($reservation->ScheduleId()))
        ->willReturn($this->schedule);

        $rule = new ResourceCrossDayRule($this->scheduleRepository);
        $result = $rule->Validate($reservation, null);

        $this->assertTrue($result->IsValid());
    }

    // Was testRuleIsValidIfAllResourcesAllowMultiDay, written for the pre-CoAsia rule that
    // skipped the schedule/timezone lookup entirely when every resource's own AllowMultiday
    // flag was set. That flag is unused now (Validate() always loads the schedule -- see its
    // doc comment), so this test's premise no longer matches the rule it exercises; with
    // $scheduleRepository's LoadById() left unconfigured, running it as-is called
    // GetTimezone() on the null PHPUnit's mock returns for an unconfigured method, which is
    // exactly the crash this test now covers instead -- a resource whose ScheduleId doesn't
    // resolve to a real row (orphaned/stale data) must not take the whole validation request
    // down with a fatal error.
    public function testFallsBackToDefaultTimezoneWhenScheduleCannotBeLoaded()
    {
        $start = Date::Parse('2013-01-01 23:00', 'UTC');
        $end = $start->AddHours(2);

        $reservation = new TestReservationSeries();
        $reservation->WithCurrentInstance(new TestReservation('1', new DateRange($start, $end)));
        $resource = new FakeBookableResource(1);
        $reservation->WithResource($resource);

        $rule = new ResourceCrossDayRule($this->scheduleRepository);
        $result = $rule->Validate($reservation, null);

        // Reaching this line at all (rather than a fatal error) is the point -- the rule
        // fell back to Configuration::Instance()->GetDefaultTimezone() instead of crashing.
        $this->assertNotNull($result);
    }

    public function testRuleIsInValidIfReservationCrossesDayInScheduleTimezone()
    {
        $start = Date::Now();
        $end = Date::Now()->AddDays(1);

        $reservation = new TestReservationSeries();
        $reservation->WithCurrentInstance(new TestReservation('1', new DateRange($start, $end)));
        $resource = new FakeBookableResource(1);
        $resource->SetAllowMultiday(true);

        $resource2 = new FakeBookableResource(2);
        $resource2->SetAllowMultiday(false);

        $reservation->WithResource($resource);
        $reservation->AddResource($resource2);

        $this->scheduleRepository->expects($this->once())
        ->method('LoadById')
        ->with($this->equalTo($reservation->ScheduleId()))
        ->willReturn($this->schedule);

        $rule = new ResourceCrossDayRule($this->scheduleRepository);
        $result = $rule->Validate($reservation, null);

        $this->assertFalse($result->IsValid());
    }
}
