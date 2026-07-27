<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/OverviewFullCalendarEventsPresenter.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Authorization/namespace.php');

/**
 * Prototype-only JSON feed backing Web/overview_fullcalendar.php's FullCalendar instance.
 * FullCalendar's own "events as a URL" convention (https://fullcalendar.io/docs/events-json-feed)
 * appends `start`/`end` query params for the visible range automatically.
 */
class OverviewFullCalendarEventsPage extends SecurePage
{
    public function __construct()
    {
        parent::__construct('', 1);
    }

    public function PageLoad()
    {
        $userSession = ServiceLocator::GetServer()->GetUserSession();

        $start = Date::Parse($this->GetQuerystring('start'), $userSession->Timezone);
        $end = Date::Parse($this->GetQuerystring('end'), $userSession->Timezone);

        $presenter = new OverviewFullCalendarEventsPresenter(
            new ResourceRepository(),
            new ReservationViewRepository(),
            new PermissionServiceFactory(),
            new PrivacyFilter()
        );

        $events = [];
        if (!($start instanceof NullDate) && !($end instanceof NullDate)) {
            $events = $presenter->GetEvents($start, $end, $userSession);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($events);
        exit;
    }
}
