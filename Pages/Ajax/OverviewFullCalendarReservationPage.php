<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/OverviewFullCalendarReservationPresenter.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Authorization/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');

/**
 * JSON feed backing the reservation Drawer (Web/scripts/overview_fullcalendar.js) — given a
 * reference number, returns everything the Drawer needs to edit a reservation without
 * guessing at fields the calendar's own lightweight event objects don't carry.
 */
class OverviewFullCalendarReservationPage extends SecurePage
{
    public function __construct()
    {
        parent::__construct('', 1);
    }

    public function PageLoad()
    {
        $userSession = ServiceLocator::GetServer()->GetUserSession();
        $referenceNumber = $this->GetQuerystring(QueryStringKeys::REFERENCE_NUMBER);

        $data = ['found' => false];
        if (!empty($referenceNumber)) {
            $presenter = new OverviewFullCalendarReservationPresenter(
                new ReservationViewRepository(),
                new PrivacyFilter(),
                new ReservationAuthorization(AuthorizationServiceFactory::GetAuthorizationService())
            );
            $data = $presenter->GetReservationData($referenceNumber, $userSession);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}
