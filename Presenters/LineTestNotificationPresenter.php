<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Domain/Values/UserPreferences.php');
require_once(ROOT_DIR . 'lib/Line/namespace.php');

interface ILineTestNotificationPage
{
    public function SetSuccessResponse();

    /**
     * @param string $message
     */
    public function SetErrorResponse($message);
}

class LineTestNotificationPresenter
{
    /**
     * @var ILineTestNotificationPage
     */
    private $page;

    /**
     * @var IUserRepository
     */
    private $userRepository;

    public function __construct(ILineTestNotificationPage $page, IUserRepository $userRepository)
    {
        $this->page = $page;
        $this->userRepository = $userRepository;
    }

    public function PageLoad()
    {
        $lineEnabled = Configuration::Instance()->GetKey(ConfigKeys::LINE_ENABLED, new BooleanConverter());
        if (!$lineEnabled) {
            $this->page->SetErrorResponse(Resources::GetInstance()->GetString('LineNotEnabled'));
            return;
        }

        $userSession = ServiceLocator::GetServer()->GetUserSession();
        $user = $this->userRepository->LoadById($userSession->UserId);
        $lineUserId = $user->GetPreferences()->Get(UserPreferences::LINE_USER_ID);

        if (empty($lineUserId)) {
            $this->page->SetErrorResponse(Resources::GetInstance()->GetString('LineNotLinked'));
            return;
        }

        // ILineService::Push() is fire-and-forget (see lib/Line/LineService.php) — a LINE API
        // failure is logged server-side but never surfaced to the caller, so this can only
        // confirm the push was SENT, not that it was actually delivered/received.
        ServiceLocator::GetLineService()->Push($lineUserId, Resources::GetInstance()->GetString('LineTestNotificationMessage'));
        $this->page->SetSuccessResponse();
    }
}
