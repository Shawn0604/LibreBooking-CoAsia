<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/Notification/Reminder/ReservationReminderSender.php');

/**
 * "會議開始前提醒" fan-out policy — who is reminded, over which channel, and what never
 * leaks. See {@see ReservationReminderSender}.
 */
class ReservationReminderSenderTest extends TestBase
{
    /** @var FakeAttributeRepository */
    private $attributeRepo;
    /** @var FakeUserRepository */
    private $userRepo;
    /** @var FakeLineService */
    private $lineService;
    /** @var FakeWeComService */
    private $wecomService;

    public function setUp(): void
    {
        parent::setUp();
        $this->attributeRepo = new FakeAttributeRepository();
        $this->userRepo = new FakeUserRepository();
        $this->lineService = new FakeLineService();
        ServiceLocator::SetLineService($this->lineService);
        $this->wecomService = new FakeWeComService();
        ServiceLocator::SetWeComService($this->wecomService);
    }

    public function tearDown(): void
    {
        ServiceLocator::SetLineService(new NullLineService());
        ServiceLocator::SetWeComService(null);
        parent::tearDown();
    }

    // ---- helpers -------------------------------------------------------------

    private function user(
        int $id,
        string $email,
        bool $wantsEmail,
        ?string $lineUserId = null,
        bool $lineOff = false,
        ?string $wecomUserId = null,
        bool $wecomOff = false
    ): FakeUser {
        $user = new FakeUser($id, $email);
        $user->_WantsEmail = $wantsEmail;
        if ($lineUserId !== null) {
            $user->ChangePreference(UserPreferences::LINE_USER_ID, $lineUserId);
        }
        if ($lineOff) {
            $user->ChangePreference(UserPreferences::LINE_NOTIFICATIONS_ENABLED, '0');
        }
        if ($wecomUserId !== null) {
            $user->ChangePreference(UserPreferences::WECOM_USER_ID, $wecomUserId);
        }
        if ($wecomOff) {
            $user->ChangePreference(UserPreferences::WECOM_NOTIFICATIONS_ENABLED, '0');
        }
        $this->userRepo->_UserById[$id] = $user;
        return $user;
    }

    /**
     * @param int[] $participantIds
     * @param int[] $inviteeIds
     * @param string[] $participatingGuests
     * @param string[] $invitedGuests
     */
    private function series(
        int $ownerId,
        array $participantIds = [],
        array $inviteeIds = [],
        array $participatingGuests = [],
        array $invitedGuests = [],
        int $statusId = ReservationStatus::Created
    ): TestReservationSeries {
        $instance = new TestReservation();
        $instance->ChangeParticipants($participantIds);
        $instance->ChangeInvitees($inviteeIds);
        $instance->ChangeParticipatingGuests($participatingGuests);
        $instance->ChangeInvitedGuests($invitedGuests);

        $series = new TestReservationSeries();
        $series->WithOwnerId($ownerId);
        $series->WithResource(new FakeBookableResource(1, 'Room A'));
        $series->WithTitle('Weekly sync');
        $series->WithCurrentInstance($instance);
        $series->WithStatus($statusId);

        return $series;
    }

    private function sender(bool $emailEnabled = true, bool $lineEnabled = true, bool $wecomEnabled = true): ReservationReminderSender
    {
        return new ReservationReminderSender($this->userRepo, $this->attributeRepo, $emailEnabled, $lineEnabled, $wecomEnabled);
    }

    /** @return string[] */
    private function emailRecipients(): array
    {
        $addresses = [];
        foreach ($this->fakeEmailService->_Messages as $message) {
            $addresses[] = $message->To()[0]->Address();
        }
        sort($addresses);
        return $addresses;
    }

    /** @return string[] lineUserIds that got a Flex push */
    private function flexRecipients(): array
    {
        $ids = array_map(static fn ($p) => $p['lineUserId'], $this->lineService->_PushedFlex);
        sort($ids);
        return $ids;
    }

    /** @return string[] wecomUserIds that got a push */
    private function wecomRecipients(): array
    {
        $ids = array_map(static fn ($p) => $p['wecomUserId'], $this->wecomService->_Pushed);
        sort($ids);
        return $ids;
    }

    // ---- recipient rules --------------------------------------------------

    public function testRemindsOwnerAndInternalAttendeesWhenEverythingIsOn()
    {
        $this->user(10, 'owner@co.com', true, 'Uowner');
        $this->user(20, 'part@co.com', true, 'Upart');
        $this->user(30, 'invitee@co.com', true, 'Uinvitee');

        // External guests are present on the reservation but must not be reminded.
        $series = $this->series(10, [20], [30], ['guest-p@ext.com'], ['guest-i@ext.com']);

        $this->sender()->Send($series, ReservationReminderType::Start, 15);

        $this->assertEquals(
            ['invitee@co.com', 'owner@co.com', 'part@co.com'],
            $this->emailRecipients()
        );
        $this->assertEquals(['Uinvitee', 'Uowner', 'Upart'], $this->flexRecipients());
    }

    public function testExternalGuestsAreNotReminded()
    {
        $this->user(10, 'owner@co.com', true, 'Uowner');

        $series = $this->series(10, [], [], ['p-guest@ext.com'], ['i-guest@ext.com']);

        $this->sender()->Send($series, ReservationReminderType::Start, 15);

        // The mail server can't reach external addresses, so guests get nothing at all —
        // only the owner is reminded.
        $this->assertEquals(['owner@co.com'], $this->emailRecipients());
        $this->assertEquals(['Uowner'], $this->flexRecipients());
    }

    public function testOwnerWhoIsAlsoListedAsParticipantIsRemindedOnce()
    {
        $this->user(10, 'owner@co.com', true, 'Uowner');

        $series = $this->series(10, [10], [10]);

        $this->sender()->Send($series, ReservationReminderType::Start, 15);

        $this->assertEquals(['owner@co.com'], $this->emailRecipients());
        $this->assertEquals(['Uowner'], $this->flexRecipients());
    }

    // ---- per-channel opt-out (must apply to BOTH channels) ---------------

    public function testOwnerWithEmailOffGetsNoEmailButStillGetsLine()
    {
        $this->user(10, 'owner@co.com', false, 'Uowner'); // email off, LINE on

        $series = $this->series(10);

        $this->sender()->Send($series, ReservationReminderType::Start, 15);

        $this->assertEquals([], $this->emailRecipients());
        $this->assertEquals(['Uowner'], $this->flexRecipients());
    }

    /**
     * Unlike the owner, an attendee's generic "接收 Email 通知" toggle never suppresses their
     * reminder email — they were put on this specific meeting by someone else, so a toggle they
     * may never have touched (e.g. a freshly-synced AD account, see COASIA-CHANGES.md §108)
     * shouldn't hide "this meeting starts soon" from them. Their LINE opt-in is unaffected and
     * still gates the LINE push independently.
     */
    public function testAttendeeWithEmailOffStillGetsReminderEmail()
    {
        $this->user(10, 'owner@co.com', true, 'Uowner');
        $this->user(20, 'part@co.com', false, 'Upart'); // email off, LINE on

        $series = $this->series(10, [20]);

        $this->sender()->Send($series, ReservationReminderType::Start, 15);

        $this->assertEquals(['owner@co.com', 'part@co.com'], $this->emailRecipients());
        $this->assertEquals(['Uowner', 'Upart'], $this->flexRecipients());
    }

    public function testAttendeeWithLineOffGetsNoLineButStillGetsEmail()
    {
        $this->user(10, 'owner@co.com', true, 'Uowner');
        $this->user(20, 'part@co.com', true, 'Upart', lineOff: true);

        $series = $this->series(10, [20]);

        $this->sender()->Send($series, ReservationReminderType::Start, 15);

        $this->assertEquals(['owner@co.com', 'part@co.com'], $this->emailRecipients());
        $this->assertEquals(['Uowner'], $this->flexRecipients());
    }

    public function testAttendeeWithoutLineBindingGetsNoLine()
    {
        $this->user(10, 'owner@co.com', true, 'Uowner');
        $this->user(20, 'part@co.com', true, null); // no LineUserId

        $series = $this->series(10, [20]);

        $this->sender()->Send($series, ReservationReminderType::Start, 15);

        $this->assertEquals(['owner@co.com', 'part@co.com'], $this->emailRecipients());
        $this->assertEquals(['Uowner'], $this->flexRecipients());
    }

    // ---- channel enable flags ------------------------------------------------

    public function testWhenOnlyEmailEnabledNoLineIsSent()
    {
        $this->user(10, 'owner@co.com', true, 'Uowner');
        $this->user(20, 'part@co.com', true, 'Upart');

        $series = $this->series(10, [20]);

        $this->sender(emailEnabled: true, lineEnabled: false)->Send($series, ReservationReminderType::Start, 15);

        $this->assertEquals(['owner@co.com', 'part@co.com'], $this->emailRecipients());
        $this->assertCount(0, $this->lineService->_PushedFlex);
    }

    public function testWhenOnlyLineEnabledNoEmailIsSent()
    {
        $this->user(10, 'owner@co.com', true, 'Uowner');
        $this->user(20, 'part@co.com', true, 'Upart');

        $series = $this->series(10, [20]);

        $this->sender(emailEnabled: false, lineEnabled: true)->Send($series, ReservationReminderType::Start, 15);

        $this->assertCount(0, $this->fakeEmailService->_Messages);
        $this->assertEquals(['Uowner', 'Upart'], $this->flexRecipients());
    }

    public function testWhenBothChannelsEnabledOwnerGetsBoth()
    {
        $this->user(10, 'owner@co.com', true, 'Uowner');

        $series = $this->series(10);

        $this->sender()->Send($series, ReservationReminderType::Start, 15);

        $this->assertEquals(['owner@co.com'], $this->emailRecipients());
        $this->assertEquals(['Uowner'], $this->flexRecipients());
    }

    // ---- 企業微信: same fan-out / opt-out rules as LINE, independent channel -----

    public function testWeComRemindsOwnerAndInternalAttendees()
    {
        $this->user(10, 'owner@co.com', true, wecomUserId: 'wowner');
        $this->user(20, 'part@co.com', true, wecomUserId: 'wpart');
        $this->user(30, 'invitee@co.com', true, wecomUserId: 'winvitee');

        $series = $this->series(10, [20], [30], ['guest@ext.com']);

        $this->sender()->Send($series, ReservationReminderType::Start, 15);

        $this->assertEquals(['winvitee', 'wowner', 'wpart'], $this->wecomRecipients());
    }

    public function testAttendeeWithoutWeComIdGetsNoWeCom()
    {
        $this->user(10, 'owner@co.com', true, wecomUserId: 'wowner');
        $this->user(20, 'part@co.com', true); // no WeCom id

        $series = $this->series(10, [20]);

        $this->sender()->Send($series, ReservationReminderType::Start, 15);

        $this->assertEquals(['wowner'], $this->wecomRecipients());
    }

    public function testAttendeeWithWeComOffGetsNoWeComButStillGetsEmail()
    {
        $this->user(10, 'owner@co.com', true, wecomUserId: 'wowner');
        $this->user(20, 'part@co.com', true, wecomUserId: 'wpart', wecomOff: true);

        $series = $this->series(10, [20]);

        $this->sender()->Send($series, ReservationReminderType::Start, 15);

        $this->assertEquals(['owner@co.com', 'part@co.com'], $this->emailRecipients());
        $this->assertEquals(['wowner'], $this->wecomRecipients());
    }

    public function testWhenOnlyWeComEnabledNoEmailOrLineIsSent()
    {
        $this->user(10, 'owner@co.com', true, 'Uowner', wecomUserId: 'wowner');

        $series = $this->series(10);

        $this->sender(emailEnabled: false, lineEnabled: false, wecomEnabled: true)
            ->Send($series, ReservationReminderType::Start, 15);

        $this->assertCount(0, $this->fakeEmailService->_Messages);
        $this->assertCount(0, $this->lineService->_PushedFlex);
        $this->assertEquals(['wowner'], $this->wecomRecipients());
    }

    public function testWhenWeComDisabledNoWeComIsSentEvenWithAnId()
    {
        $this->user(10, 'owner@co.com', true, wecomUserId: 'wowner');

        $series = $this->series(10);

        $this->sender(emailEnabled: true, lineEnabled: true, wecomEnabled: false)
            ->Send($series, ReservationReminderType::Start, 15);

        $this->assertCount(0, $this->wecomService->_Pushed);
    }

    public function testWeComOwnerAndAttendeeCanBothBeRemindedAlongsideLine()
    {
        // A person with both a LineUserId and a WeCom id is reminded on both.
        $this->user(10, 'owner@co.com', true, 'Uowner', wecomUserId: 'wowner');

        $series = $this->series(10);

        $this->sender()->Send($series, ReservationReminderType::Start, 15);

        $this->assertEquals(['Uowner'], $this->flexRecipients());
        $this->assertEquals(['wowner'], $this->wecomRecipients());
    }

    // ---- cancelled --------------------------------------------------------

    public function testCancelledSeriesRemindsNobody()
    {
        $this->user(10, 'owner@co.com', true, 'Uowner', wecomUserId: 'wowner');
        $this->user(20, 'part@co.com', true, 'Upart');

        $series = $this->series(10, [20], [], ['g@ext.com'], [], ReservationStatus::Deleted);

        $this->sender()->Send($series, ReservationReminderType::Start, 15);

        $this->assertCount(0, $this->fakeEmailService->_Messages);
        $this->assertCount(0, $this->lineService->_PushedFlex);
        $this->assertCount(0, $this->lineService->_Pushed);
        $this->assertCount(0, $this->wecomService->_Pushed);
    }

    public function testNullSeriesIsANoOp()
    {
        $this->sender()->Send(null, ReservationReminderType::Start, 15);

        $this->assertCount(0, $this->fakeEmailService->_Messages);
        $this->assertCount(0, $this->lineService->_PushedFlex);
        $this->assertCount(0, $this->wecomService->_Pushed);
    }

    // ---- host credentials never reach a non-owner -----------------------

    public function testOnlyTheOwnerCopyIsMarkedRecipientOwner()
    {
        $this->user(10, 'owner@co.com', true, 'Uowner');
        $this->user(20, 'part@co.com', true, 'Upart');
        $this->user(30, 'invitee@co.com', true);

        $series = $this->series(10, [20], [30]);

        $this->sender()->Send($series, ReservationReminderType::Start, 15);

        $ownerCopies = 0;
        foreach ($this->fakeEmailService->_Messages as $message) {
            $this->assertInstanceOf(ReservationReminderEmail::class, $message);
            if ($message->To()[0]->Address() === 'owner@co.com') {
                $this->assertTrue($message->RecipientIsOwner());
                $ownerCopies++;
            } else {
                // The host-login-info box in reservation-notification-body.tpl is gated on
                // this flag — false here means participants / invitees can never see the
                // shared host account / password.
                $this->assertFalse($message->RecipientIsOwner());
            }
        }
        $this->assertEquals(1, $ownerCopies);

        // The plain-text host-credentials follow-up push only ever targets the owner's
        // LineUserId (and only when there is actually a video resource — none here).
        $this->assertCount(0, $this->lineService->_Pushed);
    }
}
