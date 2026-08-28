<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/OverviewFullCalendarPresenter.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Domain/Values/ReservationReminder.php');
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
        $config = Configuration::Instance();
        $this->Set('AllowGuestParticipation', $config->GetKey(ConfigKeys::RESERVATION_ALLOW_GUEST_PARTICIPATION, new BooleanConverter()));
        $this->Set('TitleRequired', $config->GetKey(ConfigKeys::RESERVATION_TITLE_REQUIRED, new BooleanConverter()));

        // Quick-create reminder control: shown when reservations reminders are on.
        // The default interval (e.g. "15 minutes") pre-fills + pre-checks the field so the
        // common case is one click; the user can adjust the number/unit or uncheck it.
        $remindersEnabled = $config->GetKey(ConfigKeys::RESERVATION_REMINDERS_ENABLED, new BooleanConverter());
        $this->Set('RemindersEnabled', $remindersEnabled);
        $defaultStart = ReservationReminder::ConfigIntervalPieces(
            $config->GetKey(ConfigKeys::RESERVATION_REMINDER_DEFAULT_START)
        );
        $this->Set('DefaultStartReminderValue', $defaultStart['value'] ?? 15);
        $this->Set('DefaultStartReminderInterval', $defaultStart['interval'] ?? 'minutes');
        $this->Set('DefaultStartReminderChecked', $remindersEnabled && $defaultStart !== null);

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
