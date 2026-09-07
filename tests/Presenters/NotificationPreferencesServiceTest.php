<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'Presenters/NotificationPreferencesPresenter.php');

/**
 * The "會議開始前提醒" save path — the page only ever submits one of the fixed dropdown
 * values, so the service snaps anything else back to a safe default.
 */
class NotificationPreferencesServiceTest extends TestBase
{
    /** @var NotificationPreferencesService */
    private $service;
    /** @var FakeUserRepository */
    private $userRepo;
    /** @var FakeUser */
    private $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->userRepo = new FakeUserRepository();
        $this->user = new FakeUser(5);
        $this->service = new NotificationPreferencesService($this->userRepo);
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    private function savedMinutes(): string
    {
        return (string)$this->userRepo->_UpdatedUser->GetPreferences()->Get(UserPreferences::RESERVATION_START_REMINDER_MINUTES);
    }

    public function testAcceptsEachWhitelistedLeadTime()
    {
        foreach (NotificationPreferencesService::ALLOWED_START_REMINDER_MINUTES as $minutes) {
            $this->service->SaveReservationStartReminder($this->user, $minutes);
            $this->assertEquals((string)$minutes, $this->savedMinutes());
        }
    }

    public function testZeroMeansOff()
    {
        $this->service->SaveReservationStartReminder($this->user, 0);
        $this->assertEquals('0', $this->savedMinutes());
    }

    public function testOutOfWhitelistValueIsSnappedTo15()
    {
        $this->service->SaveReservationStartReminder($this->user, 7);
        $this->assertEquals('15', $this->savedMinutes());

        $this->service->SaveReservationStartReminder($this->user, 99999);
        $this->assertEquals('15', $this->savedMinutes());
    }

    public function testNegativeValueIsClampedToOff()
    {
        $this->service->SaveReservationStartReminder($this->user, -30);
        $this->assertEquals('0', $this->savedMinutes());
    }

    public function testPersistsThroughTheUserRepository()
    {
        $this->service->SaveReservationStartReminder($this->user, 30);
        $this->assertSame($this->user, $this->userRepo->_UpdatedUser);
    }

    // ---- Email 通知範圍:只剩 建立 / 修改 / 取消 -------------------------------

    private function userWithEmailPrefs(): User
    {
        $user = new User();
        $user->WithEmailPreferences(new EmailPreferences());
        return $user;
    }

    public function testSaveEmailPreferenceOnEnablesOnlyCreateUpdateDelete()
    {
        $user = $this->userWithEmailPrefs();

        $this->service->SaveEmailPreference($user, true);

        $this->assertTrue($user->WantsEventEmail(new ReservationCreatedEvent()));
        $this->assertTrue($user->WantsEventEmail(new ReservationUpdatedEvent()));
        $this->assertTrue($user->WantsEventEmail(new ReservationDeletedEvent()));
        // retired — never turned on by the toggle
        $this->assertFalse($user->WantsEventEmail(new ReservationApprovedEvent()));
        $this->assertFalse($user->WantsEventEmail(new ParticipationChangedEvent()));
        $this->assertFalse($user->WantsEventEmail(new ReservationSeriesEndingEvent()));
    }

    public function testSaveEmailPreferenceOffDisablesEverything()
    {
        $user = $this->userWithEmailPrefs();
        $this->service->SaveEmailPreference($user, true);

        $this->service->SaveEmailPreference($user, false);

        foreach ([
            new ReservationCreatedEvent(), new ReservationUpdatedEvent(), new ReservationDeletedEvent(),
            new ReservationApprovedEvent(), new ParticipationChangedEvent(), new ReservationSeriesEndingEvent(),
        ] as $event) {
            $this->assertFalse($user->WantsEventEmail($event));
        }
    }

    public function testAllEmailEventsEnabledIgnoresRetiredEvents()
    {
        $user = $this->userWithEmailPrefs();
        $user->ChangeEmailPreference(new ReservationCreatedEvent(), true);
        $user->ChangeEmailPreference(new ReservationUpdatedEvent(), true);
        $user->ChangeEmailPreference(new ReservationDeletedEvent(), true);
        // a retired event left OFF must not make the toggle read as "off"
        $this->assertTrue($this->service->AllEmailEventsEnabled($user));
    }

    public function testNormalizeRetiredEmailEventsClearsLingeringOptInAndPersistsOnce()
    {
        $user = $this->userWithEmailPrefs();
        $user->ChangeEmailPreference(new ReservationApprovedEvent(), true);
        $user->ChangeEmailPreference(new ParticipationChangedEvent(), true);
        $this->userRepo->_UserById[9] = $user;

        $wrote = $this->service->NormalizeRetiredEmailEvents($user);

        $this->assertTrue($wrote);
        $this->assertFalse($user->WantsEventEmail(new ReservationApprovedEvent()));
        $this->assertFalse($user->WantsEventEmail(new ParticipationChangedEvent()));
        $this->assertSame($user, $this->userRepo->_UpdatedUser);
    }

    public function testNormalizeRetiredEmailEventsIsANoOpWhenNothingToClear()
    {
        $user = $this->userWithEmailPrefs();
        $user->ChangeEmailPreference(new ReservationCreatedEvent(), true);

        $wrote = $this->service->NormalizeRetiredEmailEvents($user);

        $this->assertFalse($wrote);
        $this->assertCount(0, $this->userRepo->_UpdatedUsers);
    }

    // ---- 企業微信:這頁只有開關,ID 由管理員在後台填 --------------------------

    public function testComputeWeComStateIsDisabledWhenConfigOff()
    {
        $this->fakeConfig->SetKey(ConfigKeys::WECOM_ENABLED, false);

        $state = $this->service->ComputeWeComState(new FakeUser(5));

        $this->assertFalse($state['wecomEnabled']);
        $this->assertArrayNotHasKey('wecomLinked', $state);
    }

    public function testComputeWeComStateUnlinkedWhenNoIdSet()
    {
        $this->fakeConfig->SetKey(ConfigKeys::WECOM_ENABLED, true);

        $state = $this->service->ComputeWeComState(new FakeUser(5));

        $this->assertTrue($state['wecomEnabled']);
        $this->assertFalse($state['wecomLinked']);
    }

    public function testComputeWeComStateLinkedAndOnByDefaultOnceIdSet()
    {
        $this->fakeConfig->SetKey(ConfigKeys::WECOM_ENABLED, true);
        $user = new FakeUser(5);
        $user->ChangePreference(UserPreferences::WECOM_USER_ID, 'shawn_tsai');

        $state = $this->service->ComputeWeComState($user);

        $this->assertTrue($state['wecomLinked']);
        $this->assertEquals('shawn_tsai', $state['wecomUserId']);
        $this->assertTrue($state['wecomNotificationsEnabled']);
    }

    public function testSaveWeComUserIdStoresATrimmedValue()
    {
        $user = new FakeUser(5);

        $this->service->SaveWeComUserId($user, '  shawn_tsai  ');

        $this->assertSame($user, $this->userRepo->_UpdatedUser);
        $this->assertEquals('shawn_tsai', $user->GetPreferences()->Get(UserPreferences::WECOM_USER_ID));
    }

    public function testUnlinkWeComClearsTheId()
    {
        $user = new FakeUser(5);
        $user->ChangePreference(UserPreferences::WECOM_USER_ID, 'shawn_tsai');

        $this->service->UnlinkWeCom($user);

        $this->assertSame($user, $this->userRepo->_UpdatedUser);
        $this->assertEquals('', $user->GetPreferences()->Get(UserPreferences::WECOM_USER_ID));
    }

    public function testComputeWeComStateReflectsAnExplicitlyDisabledToggle()
    {
        $this->fakeConfig->SetKey(ConfigKeys::WECOM_ENABLED, true);
        $user = new FakeUser(5);
        $user->ChangePreference(UserPreferences::WECOM_USER_ID, 'shawn_tsai');
        $user->ChangeWeComNotificationsPreference(false);

        $state = $this->service->ComputeWeComState($user);

        $this->assertTrue($state['wecomLinked']);
        $this->assertFalse($state['wecomNotificationsEnabled']);
    }

    public function testSaveWeComNotificationsPreferencePersistsThroughTheRepository()
    {
        $user = new FakeUser(5);

        $this->service->SaveWeComNotificationsPreference($user, false);
        $this->assertSame($user, $this->userRepo->_UpdatedUser);
        $this->assertFalse($user->WantsWeComNotifications());

        $this->service->SaveWeComNotificationsPreference($user, true);
        $this->assertTrue($user->WantsWeComNotifications());
    }
}
