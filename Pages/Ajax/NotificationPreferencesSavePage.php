<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/NotificationPreferencesSavePresenter.php');

class NotificationPreferencesSavePage extends SecurePage implements INotificationPreferencesSavePage
{
    /**
     * @var NotificationPreferencesSavePresenter
     */
    private $presenter;

    public function __construct()
    {
        parent::__construct('', 1);
        $this->presenter = new NotificationPreferencesSavePresenter($this, new UserRepository());
    }

    public function PageLoad()
    {
        $this->EnforceCSRFCheck();
        $this->presenter->PageLoad();
    }

    public function GetAction()
    {
        return $this->GetForm('action');
    }

    public function GetEnabled()
    {
        return $this->GetForm('enabled') === '1';
    }

    public function GetReminderMinutes()
    {
        return max(0, (int)$this->GetForm('minutes'));
    }

    public function SetSuccessResponse($state)
    {
        $this->SetJson(array_merge(['success' => true], $state));
    }

    public function SetErrorResponse($message)
    {
        $this->SetJson(['success' => false], [$message]);
    }
}
