<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Domain/Values/UserPreferences.php');
require_once(ROOT_DIR . 'lib/WeCom/namespace.php');

interface IWeComTestNotificationPage
{
    public function SetSuccessResponse();

    /**
     * @param string $message
     */
    public function SetErrorResponse($message);
}

class WeComTestNotificationPresenter
{
    /**
     * @var IWeComTestNotificationPage
     */
    private $page;

    /**
     * @var IUserRepository
     */
    private $userRepository;

    public function __construct(IWeComTestNotificationPage $page, IUserRepository $userRepository)
    {
        $this->page = $page;
        $this->userRepository = $userRepository;
    }

    public function PageLoad()
    {
        $wecomEnabled = Configuration::Instance()->GetKey(ConfigKeys::WECOM_ENABLED, new BooleanConverter());
        if (!$wecomEnabled) {
            $this->page->SetErrorResponse(Resources::GetInstance()->GetString('WeComNotEnabled'));
            return;
        }

        $userSession = ServiceLocator::GetServer()->GetUserSession();
        $user = $this->userRepository->LoadById($userSession->UserId);
        $wecomUserId = $user->GetPreferences()->Get(UserPreferences::WECOM_USER_ID);

        if (empty($wecomUserId)) {
            $this->page->SetErrorResponse(Resources::GetInstance()->GetString('WeComNotLinkedError'));
            return;
        }

        // IWeComService::Push() is fire-and-forget (see lib/WeCom/WeComService.php) -- a gateway
        // failure is logged server-side but never surfaced to the caller, so this can only
        // confirm the push was SENT, not that it was actually delivered/received.
        ServiceLocator::GetWeComService()->Push($wecomUserId, Resources::GetInstance()->GetString('WeComTestNotificationMessage'));
        $this->page->SetSuccessResponse();
    }
}
