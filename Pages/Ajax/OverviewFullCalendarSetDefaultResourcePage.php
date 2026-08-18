<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/OverviewFullCalendarSetDefaultResourcePresenter.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Authorization/namespace.php');

class OverviewFullCalendarSetDefaultResourcePage extends SecurePage implements IOverviewFullCalendarSetDefaultResourcePage
{
    /**
     * @var OverviewFullCalendarSetDefaultResourcePresenter
     */
    private $presenter;

    public function __construct()
    {
        parent::__construct('', 1);
        $this->presenter = new OverviewFullCalendarSetDefaultResourcePresenter(
            $this,
            new UserRepository(),
            new ResourceRepository(),
            new PermissionServiceFactory()
        );
    }

    public function PageLoad()
    {
        $this->EnforceCSRFCheck();
        $this->presenter->PageLoad(ServiceLocator::GetServer()->GetUserSession());
    }

    public function GetResourceId()
    {
        return $this->GetForm('resourceId');
    }

    public function SetSuccessResponse($resourceId)
    {
        $this->SetJson(['success' => true, 'resourceId' => $resourceId]);
    }

    public function SetErrorResponse($message)
    {
        $this->SetJson(['success' => false], [$message]);
    }
}
