<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'Presenters/OverviewPresenter.php');
require_once(ROOT_DIR . 'Pages/OverviewPage.php');

class OverviewPresenterTest extends TestBase
{
    /**
     * @var OverviewPresenter
     */
    private $presenter;

    public function setUp(): void
    {
        parent::setUp();

        $this->presenter = new OverviewPresenter(
            $this->createMock(IOverviewPage::class),
            $this->createMock(IResourceRepository::class),
            $this->createMock(IReservationViewRepository::class),
            $this->createMock(IAttributeRepository::class)
        );
    }

    /**
     * @param array $events array of [startMinutes, endMinutes] pairs
     * @return array event dicts as AssignEventColumns() expects them. 'id' is derived
     *               from the (start, end) pair itself (not array position), so the same
     *               event carries the same identity regardless of what order a test
     *               passes the events in.
     */
    private function BuildEvents(array $events)
    {
        $result = [];
        foreach ($events as [$start, $end]) {
            $result[] = ['id' => "$start-$end", 'startMinutes' => $start, 'endMinutes' => $end];
        }
        return $result;
    }

    private function AssignColumns(array $events)
    {
        $method = new ReflectionMethod(OverviewPresenter::class, 'AssignEventColumns');
        $method->setAccessible(true);
        return $method->invoke($this->presenter, $events);
    }

    private function FullWidthLeft()
    {
        return '3px';
    }

    private function FullWidthWidth()
    {
        return 'calc(100% - 6px)';
    }

    public function testSingleEventGetsFullWidth()
    {
        $events = $this->BuildEvents([[600, 660]]);
        $result = $this->AssignColumns($events);

        $this->assertCount(1, $result);
        $this->assertEquals($this->FullWidthLeft(), $result[0]['left']);
        $this->assertEquals($this->FullWidthWidth(), $result[0]['width']);
        $this->assertArrayNotHasKey('startMinutes', $result[0]);
        $this->assertArrayNotHasKey('columnIndex', $result[0]);
    }

    public function testTouchingEventsAreNotOverlapping()
    {
        // 10:00-11:00 and 11:00-12:00 — share an exact boundary, must NOT split columns.
        $events = $this->BuildEvents([[600, 660], [660, 720]]);
        $result = $this->AssignColumns($events);

        foreach ($result as $event) {
            $this->assertEquals($this->FullWidthLeft(), $event['left']);
            $this->assertEquals($this->FullWidthWidth(), $event['width']);
        }
    }

    public function testFullyOverlappingEventsSplitIntoTwoColumns()
    {
        $events = $this->BuildEvents([[600, 660], [600, 660]]);
        $result = $this->AssignColumns($events);

        $this->assertEquals('3px', $result[0]['left']);
        $this->assertEquals('calc(3px + 1 * (((100% - 8px) / 2) + 2px))', $result[1]['left']);
        $this->assertEquals('calc(((100% - 8px) / 2))', $result[0]['width']);
        $this->assertEquals($result[0]['width'], $result[1]['width']);
    }

    public function testPartiallyOverlappingEventsSplitIntoTwoColumns()
    {
        // 10:00-11:00 and 10:30-11:30 — overlap for 30 minutes.
        $events = $this->BuildEvents([[600, 660], [630, 690]]);
        $result = $this->AssignColumns($events);

        $this->assertNotEquals($result[0]['left'], $result[1]['left']);
        $this->assertStringContainsString('/ 2)', $result[0]['width']);
    }

    public function testChainOverlapGroupsAllThreeTogether()
    {
        // A: 10:00-11:00, B: 10:30-11:30 (overlaps A), C: 11:15-12:00 (overlaps B, not A).
        // A and C never overlap directly but share a cluster transitively through B, so
        // the whole cluster needs 2 columns (A and C can share one column; B needs the other).
        $events = $this->BuildEvents([[600, 660], [630, 690], [675, 720]]);
        $result = $this->AssignColumns($events);

        foreach ($result as $event) {
            $this->assertStringContainsString('/ 2)', $event['width']);
        }
    }

    public function testResultIsIndependentOfInputOrder()
    {
        $forward = $this->BuildEvents([[600, 660], [630, 690], [675, 720]]);
        $shuffled = $this->BuildEvents([[675, 720], [600, 660], [630, 690]]);

        $forwardResult = $this->AssignColumns($forward);
        $shuffledResult = $this->AssignColumns($shuffled);

        $byId = function ($events) {
            $out = [];
            foreach ($events as $e) {
                $out[$e['id']] = ['left' => $e['left'], 'width' => $e['width']];
            }
            ksort($out);
            return $out;
        };

        $this->assertEquals($byId($forwardResult), $byId($shuffledResult));
    }

    public function testSeparateClustersOnSameDayEachGetFullWidth()
    {
        // Two overlapping events in the morning, one unrelated event in the afternoon —
        // the afternoon event must not be squeezed just because something else that day overlaps.
        $events = $this->BuildEvents([[600, 660], [615, 675], [900, 960]]);
        $result = $this->AssignColumns($events);

        $afternoon = array_values(array_filter($result, fn ($e) => $e['id'] === '900-960'))[0];
        $this->assertEquals($this->FullWidthLeft(), $afternoon['left']);
        $this->assertEquals($this->FullWidthWidth(), $afternoon['width']);
    }
}
