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
     * @var FakeReservationAuthorization
     */
    private $reservationAuthorization;

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
        $this->reservationAuthorization = new FakeReservationAuthorization();

        $this->presenter = new OverviewFullCalendarEventsPresenter(
            $this->resourceRepository,
            $this->reservationViewRepository,
            $this->permissionServiceFactory,
            $this->privacyFilter,
            $this->reservationAuthorization
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

        // FakeResources::GetString() always echoes the raw key back (never a translated
        // string, unlike the '忙碌' case two tests up, which is hardcoded directly in the
        // presenter and never goes through Resources at all) -- this assertion was checking
        // for a translation the fake can never produce, so it failed on every run
        // regardless of the real app's actual (correct) i18n behavior in the browser.
        $this->assertEquals('NoReservationTitle', $events[0]['title']);
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
        $this->assertEquals('rn1', $event['extendedProps']['referenceNumber']);
        $this->assertArrayHasKey('ownerName', $event['extendedProps']);
        $this->assertArrayHasKey('canEdit', $event['extendedProps']);
        $this->assertArrayHasKey('canDelete', $event['extendedProps']);
        $this->assertArrayHasKey('isRecurring', $event['extendedProps']);
        $this->assertEquals('2026-07-27T10:00:00', $event['start']);
        $this->assertEquals('2026-07-27T11:00:00', $event['end']);
        $this->assertStringContainsString('rn=rn1', $event['url']);

        // The whole point of using FullCalendar is that it lays out overlap/cross-midnight
        // itself — this presenter must never compute those, unlike OverviewPresenter::BuildDays().
        foreach (['top', 'height', 'left', 'width'] as $layoutKey) {
            $this->assertArrayNotHasKey($layoutKey, $event);
        }
    }

    public function testCanEditReflectsReservationAuthorizationNotJustOwnership()
    {
        // The whole point of routing through ReservationAuthorization::CanEdit() instead of
        // a hand-rolled "ownerId === currentUserId" check is that admins/resource-admins can
        // edit reservations they don't own — the fake here stands in for that real decision.
        $this->resourceRepository->_ResourceList = [new FakeBookableResource(1, 'A')];
        $this->permissionService->_CanAccessResource = true;
        $this->privacyFilter->_CanViewDetails = true;
        $this->reservationAuthorization->_CanEdit = true;
        $this->reservationViewRepository->_Reservations = [$this->Reservation('rn1', 1, 'Meeting A', 100)];

        $events = $this->presenter->GetEvents(
            Date::Parse('2026-07-27', 'Asia/Taipei'),
            Date::Parse('2026-08-03', 'Asia/Taipei'),
            $this->UserSession(false)
        );

        $this->assertTrue($events[0]['extendedProps']['canEdit']);
        $this->assertTrue($events[0]['extendedProps']['canDelete']);
    }

    public function testCanEditFalseWhenAuthorizationDenies()
    {
        $this->resourceRepository->_ResourceList = [new FakeBookableResource(1, 'A')];
        $this->permissionService->_CanAccessResource = true;
        $this->privacyFilter->_CanViewDetails = true;
        $this->reservationAuthorization->_CanEdit = false;
        $this->reservationViewRepository->_Reservations = [$this->Reservation('rn1', 1, 'Meeting A', 100)];

        $events = $this->presenter->GetEvents(
            Date::Parse('2026-07-27', 'Asia/Taipei'),
            Date::Parse('2026-08-03', 'Asia/Taipei'),
            $this->UserSession(false)
        );

        $this->assertFalse($events[0]['extendedProps']['canEdit']);
        $this->assertFalse($events[0]['extendedProps']['canDelete']);
    }

    public function testCanEditFalseWhenPrivacyFilterDeniesEvenIfAuthorizationWouldAllow()
    {
        // A participant/invitee's own visibility rules must never be bypassed by edit
        // rights — someone who can't see the real title must not get an edit/delete button.
        $this->resourceRepository->_ResourceList = [new FakeBookableResource(1, 'A')];
        $this->permissionService->_CanAccessResource = true;
        $this->privacyFilter->_CanViewDetails = false;
        $this->reservationAuthorization->_CanEdit = true;
        $this->reservationViewRepository->_Reservations = [$this->Reservation('rn1', 1, 'Meeting A', 100)];

        $events = $this->presenter->GetEvents(
            Date::Parse('2026-07-27', 'Asia/Taipei'),
            Date::Parse('2026-08-03', 'Asia/Taipei'),
            $this->UserSession(false)
        );

        $this->assertFalse($events[0]['extendedProps']['canEdit']);
        $this->assertFalse($events[0]['extendedProps']['canDelete']);
    }

    public function testIsRecurringReflectsReservationItemViewField()
    {
        $this->resourceRepository->_ResourceList = [new FakeBookableResource(1, 'A')];
        $this->permissionService->_CanAccessResource = true;
        $this->privacyFilter->_CanViewDetails = true;
        $recurring = $this->Reservation('rn1', 1, 'Meeting A');
        $recurring->IsRecurring = true;
        $this->reservationViewRepository->_Reservations = [$recurring];

        $events = $this->presenter->GetEvents(
            Date::Parse('2026-07-27', 'Asia/Taipei'),
            Date::Parse('2026-08-03', 'Asia/Taipei'),
            $this->UserSession(false)
        );

        $this->assertTrue($events[0]['extendedProps']['isRecurring']);
    }

    public function testOwnerNameShownWhenPrivacyFilterAllows()
    {
        $this->resourceRepository->_ResourceList = [new FakeBookableResource(1, 'A')];
        $this->permissionService->_CanAccessResource = true;
        $this->privacyFilter->_CanViewDetails = true;
        $this->reservationViewRepository->_Reservations = [$this->Reservation('rn1', 1, 'Meeting A')];

        $events = $this->presenter->GetEvents(
            Date::Parse('2026-07-27', 'Asia/Taipei'),
            Date::Parse('2026-08-03', 'Asia/Taipei'),
            $this->UserSession(false)
        );

        $this->assertEquals('first last', $events[0]['extendedProps']['ownerName']);
    }

    public function testOwnerNameWithheldWhenPrivacyFilterDenies()
    {
        // Seeing WHO booked a slot you can't see the purpose of is still a privacy leak —
        // same rule as the redacted title.
        $this->resourceRepository->_ResourceList = [new FakeBookableResource(1, 'A')];
        $this->permissionService->_CanAccessResource = true;
        $this->privacyFilter->_CanViewDetails = false;
        $this->reservationViewRepository->_Reservations = [$this->Reservation('rn1', 1, 'Meeting A')];

        $events = $this->presenter->GetEvents(
            Date::Parse('2026-07-27', 'Asia/Taipei'),
            Date::Parse('2026-08-03', 'Asia/Taipei'),
            $this->UserSession(false)
        );

        $this->assertEquals('', $events[0]['extendedProps']['ownerName']);
    }

    public function testMineOnlyDoesNotGrantEditToParticipantsOrInvitees()
    {
        // "只看我的" includes reservations the user merely participates in / is invited to
        // (see testMineOnlyQueriesByCurrentUserAcrossAnyRole) — that must NOT translate into
        // edit rights just because it showed up under this user's own reservationId. The
        // fake's OwnerId (100) differs from the current user (999); the real
        // ReservationAuthorization::CanEdit() would deny this, which FakeReservationAuthorization
        // stands in for here by being set to false.
        $this->resourceRepository->_ResourceList = [new FakeBookableResource(1, 'A')];
        $this->privacyFilter->_CanViewDetails = true;
        $this->reservationAuthorization->_CanEdit = false;
        $this->reservationViewRepository->_Reservations = [
            $this->Reservation('rn1', 1, 'Someone Elses Meeting', 100),
        ];

        $events = $this->presenter->GetEvents(
            Date::Parse('2026-07-27', 'Asia/Taipei'),
            Date::Parse('2026-08-03', 'Asia/Taipei'),
            $this->UserSession(false),
            true
        );

        $this->assertFalse($events[0]['extendedProps']['canEdit']);
        $this->assertFalse($events[0]['extendedProps']['canDelete']);
    }

    public function testMineOnlyQueriesByCurrentUserAcrossAnyRole()
    {
        $this->reservationViewRepository->_Reservations = [];

        $this->presenter->GetEvents(
            Date::Parse('2026-07-27', 'Asia/Taipei'),
            Date::Parse('2026-08-03', 'Asia/Taipei'),
            $this->UserSession(false),
            true
        );

        $this->assertEquals(999, $this->reservationViewRepository->_LastUserIds);
        $this->assertEquals(ReservationUserLevel::ALL, $this->reservationViewRepository->_LastUserLevel);
    }

    public function testMineOnlyIgnoresResourceViewPermission()
    {
        // Unlike the normal feed, $mineOnly must not drop a reservation just because the
        // user's current permission on that resource says no — it's their own booking.
        $this->resourceRepository->_ResourceList = [new FakeBookableResource(1, 'A')];
        $this->permissionService->_CanAccessResource = false;
        $this->privacyFilter->_CanViewDetails = true;
        $this->reservationViewRepository->_Reservations = [
            $this->Reservation('rn1', 1, 'My Own Meeting', 999),
        ];

        $events = $this->presenter->GetEvents(
            Date::Parse('2026-07-27', 'Asia/Taipei'),
            Date::Parse('2026-08-03', 'Asia/Taipei'),
            $this->UserSession(false),
            true
        );

        $this->assertCount(1, $events);
        $this->assertEquals('My Own Meeting', $events[0]['title']);
    }

    public function testNonMineDefaultsStillFilterByViewPermissionAsBefore()
    {
        $this->resourceRepository->_ResourceList = [new FakeBookableResource(1, 'A')];
        $this->permissionService->_CanAccessResource = false;
        $this->reservationViewRepository->_Reservations = [$this->Reservation('rn1', 1, 'Meeting A')];

        $events = $this->presenter->GetEvents(
            Date::Parse('2026-07-27', 'Asia/Taipei'),
            Date::Parse('2026-08-03', 'Asia/Taipei'),
            $this->UserSession(false)
        );

        $this->assertCount(0, $events);
    }
}
