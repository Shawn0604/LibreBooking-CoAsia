<?php

require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Domain/Values/UserPreferences.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

/**
 * Read/write logic shared by the classic page render (NotificationPreferencesPresenter,
 * below) and the ajax auto-save endpoint (NotificationPreferencesSavePresenter) — pulled out
 * on its own, with no dependency on either page's display interface, so the save endpoint
 * doesn't need to fake up an unused INotificationPreferencesPage just to reuse these methods.
 */
class NotificationPreferencesService
{
    /**
     * @var IUserRepository
     */
    private $userRepository;

    public function __construct(IUserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * The reservation-lifecycle events the single "接收 Email 通知" toggle controls together
     * (instead of separate switches). Trimmed by request to just create / update / cancel —
     * the "something on my calendar changed" set, which is high-value and low-volume. The
     * three retired events (approved / participation-changed / series-ending) were dropped as
     * noise; see RetiredEmailEvents() for how existing opt-ins get cleaned up.
     * @return IDomainEvent[]
     */
    private function EmailEvents()
    {
        return [
            new ReservationCreatedEvent(),
            new ReservationUpdatedEvent(),
            new ReservationDeletedEvent(),
        ];
    }

    /**
     * Events the toggle USED to control and no longer does. They are force-disabled whenever
     * the toggle is saved, and NormalizeRetiredEmailEvents() clears any lingering opt-in on
     * page load, so no manual DB migration is needed — a user who never revisits the page
     * still gets cleaned up the next time they do.
     * @return IDomainEvent[]
     */
    private function RetiredEmailEvents()
    {
        return [
            new ReservationApprovedEvent(),
            new ParticipationChangedEvent(),
            new ReservationSeriesEndingEvent(),
        ];
    }

    /**
     * @param User $user
     * @return bool
     */
    public function AllEmailEventsEnabled(User $user)
    {
        foreach ($this->EmailEvents() as $event) {
            if (!$user->WantsEventEmail($event)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Turn off any retired event this user still has enabled. Only writes when something
     * actually changed, so it's cheap to call on every page load. Returns true if it wrote.
     *
     * @param User $user
     * @return bool
     */
    public function NormalizeRetiredEmailEvents(User $user)
    {
        $changed = false;
        foreach ($this->RetiredEmailEvents() as $event) {
            if ($user->WantsEventEmail($event)) {
                $user->ChangeEmailPreference($event, false);
                $changed = true;
            }
        }
        if ($changed) {
            $this->userRepository->Update($user);
        }
        return $changed;
    }

    /**
     * @param User $user
     * @param bool $enabled
     */
    public function SaveEmailPreference(User $user, $enabled)
    {
        foreach ($this->EmailEvents() as $event) {
            $user->ChangeEmailPreference($event, $enabled);
        }
        // Retired events never come back on, regardless of $enabled.
        foreach ($this->RetiredEmailEvents() as $event) {
            $user->ChangeEmailPreference($event, false);
        }
        $this->userRepository->Update($user);
    }

    /**
     * @param User $user
     * @param bool $enabled
     */
    public function SaveLineNotificationsPreference(User $user, $enabled)
    {
        $user->ChangeLineNotificationsPreference($enabled);
        $this->userRepository->Update($user);
    }

    /**
     * @param User $user
     */
    public function UnlinkLine(User $user)
    {
        $user->ChangePreference(UserPreferences::LINE_USER_ID, '');
        $this->userRepository->Update($user);
    }

    /**
     * The fixed "提醒時間" choices offered on 通知設定, in minutes before start. Any value
     * outside this whitelist is snapped to 15 — the page only ever submits one of these, so a
     * different value means a tampered/stale request, or a value saved before the list was
     * trimmed, not a user choice to honour verbatim.
     */
    public const ALLOWED_START_REMINDER_MINUTES = [15, 30, 60];

    /**
     * "會議開始前提醒" — minutes before a reservation starts. 0 = off. Applied to every
     * reservation this user creates (ReservationSavePresenter), and fanned out to the owner +
     * internal attendees + external guests by Jobs/sendreminders.php.
     *
     * @param User $user
     * @param int $minutes
     */
    public function SaveReservationStartReminder(User $user, $minutes)
    {
        $minutes = (int)$minutes;
        if ($minutes > 0 && !in_array($minutes, self::ALLOWED_START_REMINDER_MINUTES, true)) {
            $minutes = 15;
        }
        $user->ChangePreference(UserPreferences::RESERVATION_START_REMINDER_MINUTES, (string)max(0, $minutes));
        $this->userRepository->Update($user);
    }

    /**
     * @param User $user
     * @return array
     */
    public function BuildStateArray(User $user)
    {
        return array_merge(
            [
                'emailEnabled' => Configuration::Instance()->GetKey(ConfigKeys::EMAIL_ENABLED, new BooleanConverter()),
                'emailNotificationsEnabled' => $this->AllEmailEventsEnabled($user),
                'reservationStartReminderMinutes' => (int)$user->GetPreferences()->Get(UserPreferences::RESERVATION_START_REMINDER_MINUTES),
            ],
            $this->ComputeLineState($user)
        );
    }

    /**
     * @param User $user
     * @return array
     */
    public function ComputeLineState(User $user)
    {
        $lineEnabled = Configuration::Instance()->GetKey(ConfigKeys::LINE_ENABLED, new BooleanConverter());
        if (!$lineEnabled) {
            return ['lineEnabled' => false];
        }

        $isLinked = !empty($user->GetPreferences()->Get(UserPreferences::LINE_USER_ID));
        if ($isLinked) {
            return [
                'lineEnabled' => true,
                'lineLinked' => true,
                'lineNotificationsEnabled' => $user->WantsLineNotifications(),
            ];
        }

        $userChanged = false;
        $code = $user->GetPreferences()->Get(UserPreferences::LINE_LINK_CODE);
        if (empty($code)) {
            $code = (string)random_int(100000, 999999);
            $user->ChangePreference(UserPreferences::LINE_LINK_CODE, $code);
            $userChanged = true;
        }
        if ($userChanged) {
            $this->userRepository->Update($user);
        }

        $friendUrl = $this->BuildLineFriendUrl();

        return [
            'lineEnabled' => true,
            'lineLinked' => false,
            'lineLinkCode' => $code,
            'lineFriendUrl' => $friendUrl,
            'lineQrDataUri' => $this->BuildLineQrDataUri($friendUrl),
        ];
    }

    /**
     * @return string
     */
    private function BuildLineFriendUrl()
    {
        $officialAccountId = Configuration::Instance()->GetKey(ConfigKeys::LINE_OFFICIAL_ACCOUNT_ID);
        return 'https://line.me/R/ti/p/' . rawurlencode($officialAccountId);
    }

    /**
     * Rendered fresh on every load rather than cached to disk — this QR carries no per-user
     * data (every unbound user sees the exact same official-account add-friend code), so
     * there's nothing worth persisting to a file for; regenerating it here also means it can
     * never go stale if LINE_OFFICIAL_ACCOUNT_ID is ever changed in config.
     * @param string $friendUrl
     * @return string data: URI
     */
    private function BuildLineQrDataUri($friendUrl)
    {
        $renderer = new GDLibRenderer(240);
        $writer = new Writer($renderer);
        $png = $writer->writeString($friendUrl);
        return 'data:image/png;base64,' . base64_encode($png);
    }
}

class NotificationPreferencesPresenter
{
    /**
     * @var INotificationPreferencesPage
     */
    private $page;

    /**
     * @var IUserRepository
     */
    private $userRepository;

    /**
     * @var NotificationPreferencesService
     */
    private $service;

    public function __construct(INotificationPreferencesPage $page, IUserRepository $userRepository)
    {
        $this->page = $page;
        $this->userRepository = $userRepository;
        $this->service = new NotificationPreferencesService($userRepository);
    }

    public function PageLoad()
    {
        $userSession = ServiceLocator::GetServer()->GetUserSession();
        $user = $this->userRepository->LoadById($userSession->UserId);

        // Self-heal any lingering opt-in on the events the Email toggle no longer manages,
        // so no manual DB migration is needed after trimming the event list.
        $this->service->NormalizeRetiredEmailEvents($user);

        $this->page->SetEmailEnabled(Configuration::Instance()->GetKey(ConfigKeys::EMAIL_ENABLED, new BooleanConverter()));
        $this->page->SetEmailNotificationsEnabled($this->service->AllEmailEventsEnabled($user));

        $this->page->SetRemindersEnabled(Configuration::Instance()->GetKey(ConfigKeys::RESERVATION_REMINDERS_ENABLED, new BooleanConverter()));
        $this->page->SetReservationStartReminderMinutes((int)$user->GetPreferences()->Get(UserPreferences::RESERVATION_START_REMINDER_MINUTES));

        $lineState = $this->service->ComputeLineState($user);
        $this->page->SetLineEnabled($lineState['lineEnabled']);
        if (!$lineState['lineEnabled']) {
            return;
        }

        $this->page->SetLineLinked($lineState['lineLinked']);
        if ($lineState['lineLinked']) {
            $this->page->SetLineNotificationsEnabled($lineState['lineNotificationsEnabled']);
        } else {
            $this->page->SetLineLinkCode($lineState['lineLinkCode']);
            $this->page->SetLineFriendUrl($lineState['lineFriendUrl']);
            $this->page->SetLineQrDataUri($lineState['lineQrDataUri']);
        }
    }
}
