<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'Presenters/OverviewFullCalendarReservationPresenter.php');

class OverviewFullCalendarReservationPresenterTest extends TestBase
{
    /**
     * @var FakeReservationViewRepository
     */
    private $reservationViewRepository;

    /**
     * @var FakePrivacyFilter
     */
    private $privacyFilter;

    /**
     * @var FakeReservationAuthorization
     */
    private $reservationAuthorization;

    /**
     * @var OverviewFullCalendarReservationPresenter
     */
    private $presenter;

    public function setUp(): void
    {
        parent::setUp();

        $this->reservationViewRepository = new FakeReservationViewRepository();
        $this->privacyFilter = new FakePrivacyFilter();
        $this->reservationAuthorization = new FakeReservationAuthorization();

        $this->presenter = new OverviewFullCalendarReservationPresenter(
            $this->reservationViewRepository,
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

    private function ReservationView()
    {
        $view = new ReservationView();
        $view->ReferenceNumber = 'rn1';
        $view->Title = 'Standup';
        $view->Description = 'Daily sync';
        $view->ResourceId = 8;
        $view->AdditionalResourceIds = [];
        $view->StartDate = Date::Parse('2026-07-29 08:30:00', 'Asia/Taipei');
        $view->EndDate = Date::Parse('2026-07-29 13:00:00', 'Asia/Taipei');
        $view->RepeatType = RepeatType::None;
        $view->RepeatInterval = 1;
        $view->RepeatWeekdays = [];
        $view->RepeatMonthlyType = '';
        $view->RepeatTerminationDate = new NullDate();
        $view->CustomRepeatDates = [];
        $view->Participants = [new ReservationUserView(5, 'Jane', 'Doe', 'jane@coasia.com', ReservationUserLevel::PARTICIPANT)];
        $view->Invitees = [];
        $view->StartReminder = new ReservationReminderView(15);
        $view->EndReminder = null;
        $view->AllowParticipation = true;
        $view->Attributes = [1 => new AttributeValue(1, '42', 'Project Code'), 2 => new AttributeValue(2, 'hello', 'Notes')];
        $view->ParticipatingGuests = ['guest@example.com'];
        $view->InvitedGuests = ['invited@example.com'];
        $view->Accessories = [new ReservationAccessoryView(3, 1, 'Projector', 5)];
        $view->Attachments = [new ReservationAttachmentView(9, 1, 'slides.pdf')];
        $view->OwnerId = 100;
        $view->OwnerFirstName = 'first';
        $view->OwnerLastName = 'last';
        $view->Version = 7;
        return $view;
    }

    public function testReturnsNotFoundWhenReservationDoesNotExist()
    {
        $this->reservationViewRepository->_ReservationView = new NullReservationView();

        $data = $this->presenter->GetReservationData('missing', $this->UserSession(false));

        $this->assertFalse($data['found']);
        $this->assertArrayNotHasKey('title', $data);
    }

    public function testWithholdsAllDataWhenPrivacyFilterDenies()
    {
        $this->reservationViewRepository->_ReservationView = $this->ReservationView();
        $this->privacyFilter->_CanViewDetails = false;

        $data = $this->presenter->GetReservationData('rn1', $this->UserSession(false));

        $this->assertTrue($data['found']);
        $this->assertFalse($data['canView']);
        // Nothing else — no title, no participants, not even canEdit — leaks through.
        $this->assertCount(2, $data);
    }

    public function testReturnsFullDataWhenAuthorized()
    {
        $this->reservationViewRepository->_ReservationView = $this->ReservationView();
        $this->privacyFilter->_CanViewDetails = true;
        $this->reservationAuthorization->_CanEdit = true;

        $data = $this->presenter->GetReservationData('rn1', $this->UserSession(false));

        $this->assertTrue($data['found']);
        $this->assertTrue($data['canView']);
        $this->assertTrue($data['canEdit']);
        $this->assertTrue($data['canDelete']);
        $this->assertEquals('Standup', $data['title']);
        $this->assertEquals('Daily sync', $data['description']);
        $this->assertEquals('8', $data['resourceId']);
        $this->assertEquals('2026-07-29', $data['startDate']);
        $this->assertEquals('08:30:00', $data['startTime']);
        $this->assertEquals('2026-07-29', $data['endDate']);
        $this->assertEquals('13:00:00', $data['endTime']);
        $this->assertFalse($data['isRecurring']);
        $this->assertEquals('none', $data['repeat']['type']);
        $this->assertCount(1, $data['participants']);
        $this->assertEquals(5, $data['participants'][0]['userId']);
        $this->assertEquals('Jane Doe', $data['participants'][0]['fullName']);
        $this->assertTrue($data['reminders']['start']['enabled']);
        $this->assertEquals(15, $data['reminders']['start']['value']);
        $this->assertFalse($data['reminders']['end']['enabled']);
        $this->assertTrue($data['allowParticipation']);
        $this->assertEquals([
            '1' => ['label' => 'Project Code', 'value' => '42'],
            '2' => ['label' => 'Notes', 'value' => 'hello'],
        ], $data['attributes']);
        $this->assertEquals(['guest@example.com'], $data['participatingGuests']);
        $this->assertEquals(['invited@example.com'], $data['invitedGuests']);
        $this->assertCount(1, $data['accessories']);
        $this->assertEquals('Projector', $data['accessories'][0]['name']);
        $this->assertEquals(1, $data['accessories'][0]['quantityReserved']);
        $this->assertCount(1, $data['attachments']);
        $this->assertEquals('slides.pdf', $data['attachments'][0]['fileName']);
        $this->assertEquals('first last', $data['ownerName']);
        $this->assertEquals(7, $data['version']);
    }

    public function testCanEditFalseWhenAuthorizationDenies()
    {
        $this->reservationViewRepository->_ReservationView = $this->ReservationView();
        $this->privacyFilter->_CanViewDetails = true;
        $this->reservationAuthorization->_CanEdit = false;

        $data = $this->presenter->GetReservationData('rn1', $this->UserSession(false));

        $this->assertFalse($data['canEdit']);
        $this->assertFalse($data['canDelete']);
        // Still viewable even though not editable — a participant should see the data.
        $this->assertTrue($data['canView']);
        $this->assertEquals('Standup', $data['title']);
    }
}
