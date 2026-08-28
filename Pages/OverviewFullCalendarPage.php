<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/OverviewFullCalendarPresenter.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Authorization/namespace.php');

/**
 * Prototype-only page for evaluating FullCalendar Standard as a replacement for
 * Pages/OverviewPage.php's hand-rolled week grid. See Presenters/OverviewFullCalendarPresenter.php
 * for why this doesn't reuse OverviewPage/OverviewPresenter directly.
 */
class OverviewFullCalendarPage extends SecurePage implements IOverviewFullCalendarPage
{
    /**
     * @var OverviewFullCalendarPresenter
     */
    private $presenter;

    public function __construct()
    {
        parent::__construct('Overview');

        $this->presenter = new OverviewFullCalendarPresenter(
            $this,
            new ResourceRepository(),
            new AttributeRepository(),
            new PermissionServiceFactory(),
            new UserRepository()
        );
    }

    public function PageLoad()
    {
        $this->presenter->PageLoad(ServiceLocator::GetServer()->GetUserSession());
        $this->Set('AllowGuestParticipation', Configuration::Instance()->GetKey(ConfigKeys::RESERVATION_ALLOW_GUEST_PARTICIPATION, new BooleanConverter()));
        $this->Set('TitleRequired', Configuration::Instance()->GetKey(ConfigKeys::RESERVATION_TITLE_REQUIRED, new BooleanConverter()));
        $this->Display('Overview/overview_fullcalendar.tpl');
    }

    public function SetResourceGroups(
        $groups,
        $resourceColors,
        $resourceLocations,
        $explicitDefaultResourceId,
        $systemDefaultResourceId,
        $firstLoginDefaultResourceId
    ) {
        $this->Set('ResourceGroups', $groups);
        $this->Set('ResourceColors', $resourceColors);
        $this->Set('ResourceLocations', $resourceLocations);
        $this->Set('ExplicitDefaultResourceId', $explicitDefaultResourceId);
        $this->Set('SystemDefaultResourceId', $systemDefaultResourceId);
        $this->Set('FirstLoginDefaultResourceId', $firstLoginDefaultResourceId);
    }
}
