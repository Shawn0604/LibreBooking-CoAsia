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
     * The 6 individual reservation-lifecycle events the single "接收 Email 通知" toggle now
     * controls together, instead of 6 separate switches. Kept as one list so every place that
     * needs to read OR write "all of them at once" iterates the same source instead of
     * repeating six near-identical lines twice.
     * @return IDomainEvent[]
     */
    private function EmailEvents()
    {
        return [
            new ReservationCreatedEvent(),
            new ReservationUpdatedEvent(),
            new ReservationDeletedEvent(),
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
     * @param User $user
     * @param bool $enabled
     */
    public function SaveEmailPreference(User $user, $enabled)
    {
        foreach ($this->EmailEvents() as $event) {
            $user->ChangeEmailPreference($event, $enabled);
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
     * "開始前提醒" — minutes before a reservation starts. 0 = off. Applied to every
     * reservation this user creates (ReservationSavePresenter).
     *
     * @param User $user
     * @param int $minutes
     */
    public function SaveReservationStartReminder(User $user, $minutes)
    {
        $user->ChangePreference(UserPreferences::RESERVATION_START_REMINDER_MINUTES, (string)max(0, (int)$minutes));
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
