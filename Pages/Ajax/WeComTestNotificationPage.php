<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/WeComTestNotificationPresenter.php');

class WeComTestNotificationPage extends SecurePage implements IWeComTestNotificationPage
{
    /**
     * @var WeComTestNotificationPresenter
     */
    private $presenter;

    public function __construct()
    {
        parent::__construct('', 1);
        $this->presenter = new WeComTestNotificationPresenter($this, new UserRepository());
    }

    public function PageLoad()
    {
        $this->EnforceCSRFCheck();
        $this->presenter->PageLoad();
    }

    public function SetSuccessResponse()
    {
        $this->SetJson(['success' => true]);
    }

    public function SetErrorResponse($message)
    {
        $this->SetJson(['success' => false], [$message]);
    }
}
