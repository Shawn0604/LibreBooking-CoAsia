<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Presenters/NotificationPreferencesPresenter.php');

interface INotificationPreferencesSavePage
{
    /**
     * @return string|null
     */
    public function GetAction();

    /**
     * @return bool
     */
    public function GetEnabled();

    /**
     * @param array $state
     */
    public function SetSuccessResponse($state);

    /**
     * @param string $message
     */
    public function SetErrorResponse($message);
}

class NotificationPreferencesSavePresenter
{
    public const ACTION_EMAIL = 'email';
    public const ACTION_LINE_NOTIFICATIONS = 'lineNotifications';
    public const ACTION_LINE_UNLINK = 'lineUnlink';

    /**
     * @var INotificationPreferencesSavePage
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

    public function __construct(INotificationPreferencesSavePage $page, IUserRepository $userRepository)
    {
        $this->page = $page;
        $this->userRepository = $userRepository;
        $this->service = new NotificationPreferencesService($userRepository);
    }

    public function PageLoad()
    {
        $userSession = ServiceLocator::GetServer()->GetUserSession();
        $user = $this->userRepository->LoadById($userSession->UserId);

        $action = $this->page->GetAction();

        switch ($action) {
            case self::ACTION_EMAIL:
                $this->service->SaveEmailPreference($user, $this->page->GetEnabled());
                break;
            case self::ACTION_LINE_NOTIFICATIONS:
                $this->service->SaveLineNotificationsPreference($user, $this->page->GetEnabled());
                break;
            case self::ACTION_LINE_UNLINK:
                $this->service->UnlinkLine($user);
                break;
            default:
                $this->page->SetErrorResponse('Invalid action');
                return;
        }

        // Reload rather than reuse $user in-memory — every branch above already persisted its
        // own change via IUserRepository::Update(), so a fresh load reflects exactly what's
        // now in the database instead of relying on every future edit here to keep the
        // in-memory $user object perfectly in sync with what was just written.
        $refreshedUser = $this->userRepository->LoadById($userSession->UserId);
        $this->page->SetSuccessResponse($this->service->BuildStateArray($refreshedUser));
    }
}
