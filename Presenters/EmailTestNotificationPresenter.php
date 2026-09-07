<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Email/Messages/NotificationTestEmail.php');

interface IEmailTestNotificationPage
{
    public function SetSuccessResponse();

    /**
     * @param string $message
     */
    public function SetErrorResponse($message);
}

class EmailTestNotificationPresenter
{
    /**
     * @var IEmailTestNotificationPage
     */
    private $page;

    /**
     * @var IUserRepository
     */
    private $userRepository;

    public function __construct(IEmailTestNotificationPage $page, IUserRepository $userRepository)
    {
        $this->page = $page;
        $this->userRepository = $userRepository;
    }

    public function PageLoad()
    {
        $emailEnabled = Configuration::Instance()->GetKey(ConfigKeys::EMAIL_ENABLED, new BooleanConverter());
        if (!$emailEnabled) {
            $this->page->SetErrorResponse(Resources::GetInstance()->GetString('EmailDisabled'));
            return;
        }

        $userSession = ServiceLocator::GetServer()->GetUserSession();
        $user = $this->userRepository->LoadById($userSession->UserId);
        $address = $user->EmailAddress();

        if (empty($address)) {
            $this->page->SetErrorResponse(Resources::GetInstance()->GetString('EmailTestNotificationNoAddress'));
            return;
        }

        ServiceLocator::GetEmailService()->Send(new NotificationTestEmail($user));
        $this->page->SetSuccessResponse();
    }
}
