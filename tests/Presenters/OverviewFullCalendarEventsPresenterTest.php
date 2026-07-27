<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'Presenters/OverviewFullCalendarEventsPresenter.php');

class OverviewFullCalendarEventsPresenterTest extends TestBase
{
    /**
     * @var FakeResourceRepository
     */
    private $resourceRepository;

    /**
     * @var FakeReservationViewRepository
     */
    private $reservationViewRepository;

    /**
     * @var FakePermissionService
     */
    private $permissionService;

    /**
     * @var FakePermissionServiceFactory
     */
    private $permissionServiceFactory;

    /**
     * @var FakePrivacyFilter
     */
    private $privacyFilter;

    /**
     * @var OverviewFullCalendarEventsPresenter
     */
    private $presenter;

    public function setUp(): void
    {
        parent::setUp();

        $this->resourceRepository = new FakeResourceRepository();
        $this->reservationViewRepository = new FakeReservationViewRepository();
        $this->permissionService = new FakePermissionService();
        $this->permissionServiceFactory = new FakePermissionServiceFactory();
        $this->permissionServiceFactory->service = $this->permissionService;
        $this->privacyFilter = new FakePrivacyFilter();

        $this->presenter = new OverviewFullCalendarEventsPresenter(
            $this->resourceRepository,
            $this->reservationViewRepository,
            $this->permissionServiceFactory,
            $this->privacyFilter
        );
    }

    private function UserSession($isAdmin = false)
    {
        $session = new UserSession(999);
        $session->Timezone = 'Asia/Taipei';
        $session->IsAdmin = $isAdmin;
        return $session;
    }

    private function Reservation($referenceNumber, $resourceId, $title, $ownerId = 100)
    {
        return new ReservationItemView(
            $referenceNumber,
            Date::Parse('2026-07-27 10:00:00', 'Asia/Taipei'),
            Date::Parse('2026-07-27 11:00:00', 'Asia/Taipei'),
            'Resource ' . $resourceId,
            $resourceId,
            1,
            null,
            $title,
            null,
            null,
            'first',
            'last',
            $ownerId
        );
    }

    public function testExcludesResourcesUserCannotView()
    {
        $this->resourceRepository->_ResourceList = [
            new FakeBookableResource(1, 'Viewable'),
            new FakeBookableResource(2, 'NotViewable'),
        ];
        $this->permissionService->_CanAccessResource = [1 => true, 2 => false];
        $this->reservationViewRepository->_Reservations = [
            $this->Reservation('rn1', 1, 'Meeting A'),
            $this->Reservation('rn2', 2, 'Meeting B'),
        ];

        $events = $this->presenter->GetEvents(
            Date::Parse('2026-07-27', 'Asia/Taipei'),
            Date::Parse('2026-08-03', 'Asia/Taipei'),
            $this->UserSession(false)
        );

        $this->assertCount(1, $events);
        $this->assertEquals('rn1', $events[0]['id']);
    }

    public function testAdminSeesAllResourcesRegardlessOfPermissionService()
    {
        $this->resourceRepository->_ResourceList = [
            new FakeBookableResource(1, 'A'),
            new FakeBookableResource(2, 'B'),
        ];
        // Even though the permission service would deny everything, admin bypasses it.
        $this->permissionService->_CanAccessResource = false;
        $this->reservationViewRepository->_Reservations = [
            $this->Reservation('rn1', 1, 'Meeting A'),
            $this->Reservation('rn2', 2, 'Meeting B'),
        ];

        $events = $this->presenter->GetEvents(
            Date::Parse('2026-07-27', 'Asia/Taipei'),
            Date::Parse('2026-08-03', 'Asia/Taipei'),
            $this->UserSession(true)
        );

        $this->assertCount(2, $events);
    }

    public function testTitleShownWhenPrivacyFilterAllows()
    {
        $this->resourceRepository->_ResourceList = [new FakeBookableResource(1, 'A')];
        $this->permissionService->_CanAccessResource = true;
        $this->privacyFilter->_CanViewDetails = true;
        $this->reservationViewRepository->_Reservations = [$this->Reservation('rn1', 1, 'Confidential Title')];

        $events = $this->presenter->GetEvents(
            Date::Parse('2026-07-27', 'Asia/Taipei'),
            Date::Parse('2026-08-03', 'Asia/Taipei'),
            $this->UserSession(false)
        );

        $this->assertEquals('Confidential Title', $events[0]['title']);
    }

    public function testTitleRedactedWhenPrivacyFilterDenies()
    {
        $this->resourceRepository->_ResourceList = [new FakeBookableResource(1, 'A')];
        $this->permissionService->_CanAccessResource = true;
        $this->privacyFilter->_CanViewDetails = false;
        $this->reservationViewRepository->_Reservations = [$this->Reservation('rn1', 1, 'Confidential Title')];

        $events = $this->presenter->GetEvents(
            Date::Parse('2026-07-27', 'Asia/Taipei'),
            Date::Parse('2026-08-03', 'Asia/Taipei'),
            $this->UserSession(false)
        );

        $this->assertNotEquals('Confidential Title', $events[0]['title']);
        $this->assertEquals('忙碌', $events[0]['title']);
    }

    public function testEmptyTitleFallsBackToDefaultLabelWhenViewable()
    {
        $this->resourceRepository->_ResourceList = [new FakeBookableResource(1, 'A')];
        $this->permissionService->_CanAccessResource = true;
        $this->privacyFilter->_CanViewDetails = true;
        $this->reservationViewRepository->_Reservations = [$this->Reservation('rn1', 1, '')];

        $events = $this->presenter->GetEvents(
            Date::Parse('2026-07-27', 'Asia/Taipei'),
            Date::Parse('2026-08-03', 'Asia/Taipei'),
            $this->UserSession(false)
        );

        $this->assertEquals('未命名預約', $events[0]['title']);
    }

    public function testEventShapeHasRequiredFieldsAndNoServerSideLayout()
    {
        $this->resourceRepository->_ResourceList = [new FakeBookableResource(8, 'Zoom TW')];
        $this->permissionService->_CanAccessResource = true;
        $this->privacyFilter->_CanViewDetails = true;
        $this->reservationViewRepository->_Reservations = [$this->Reservation('rn1', 8, 'Standup')];

        $events = $this->presenter->GetEvents(
            Date::Parse('2026-07-27', 'Asia/Taipei'),
            Date::Parse('2026-08-03', 'Asia/Taipei'),
            $this->UserSession(false)
        );

        $event = $events[0];
        foreach (['id', 'title', 'start', 'end', 'url', 'backgroundColor', 'borderColor', 'textColor', 'extendedProps'] as $key) {
            $this->assertArrayHasKey($key, $event, "Missing FullCalendar Event Object field: $key");
        }
        $this->assertEquals('8', $event['extendedProps']['resourceId']);
        $this->assertEquals('2026-07-27T10:00:00', $event['start']);
        $this->assertEquals('2026-07-27T11:00:00', $event['end']);
        $this->assertStringContainsString('rn=rn1', $event['url']);

        // The whole point of using FullCalendar is that it lays out overlap/cross-midnight
        // itself — this presenter must never compute those, unlike OverviewPresenter::BuildDays().
        foreach (['top', 'height', 'left', 'width'] as $layoutKey) {
            $this->assertArrayNotHasKey($layoutKey, $event);
        }
    }
}
