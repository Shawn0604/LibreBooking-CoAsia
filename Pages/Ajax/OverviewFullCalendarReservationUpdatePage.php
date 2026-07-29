<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Pages/Ajax/IReservationSaveResultsView.php');
require_once(ROOT_DIR . 'Presenters/OverviewFullCalendarReservationUpdatePresenter.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Authorization/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');

/**
 * Basic-fields-only update endpoint backing the reservation Drawer — see
 * OverviewFullCalendarReservationUpdatePresenter's doc comment for why this is a
 * deliberately separate, narrower code path from ajax/reservation_update.php rather than a
 * thin JSON wrapper around it.
 */
class OverviewFullCalendarReservationUpdatePage extends SecurePage implements IReservationSaveResultsView
{
    private $succeeded = false;
    private $errors = [];
    private $code = null;
    private $httpStatus = 200;

    public function __construct()
    {
        parent::__construct('', 1);
    }

    public function PageLoad()
    {
        try {
            $this->EnforceCSRFCheck();
            $userSession = ServiceLocator::GetServer()->GetUserSession();

            $scope = $this->GetForm(FormKeys::SERIES_UPDATE_SCOPE);
            if (!is_scalar($scope) || !SeriesUpdateScope::IsValid((string)$scope)) {
                $scope = SeriesUpdateScope::FullSeries;
            }

            $persistenceFactory = new ReservationPersistenceFactory();
            $persistenceService = $persistenceFactory->Create(ReservationAction::Update);
            $handler = ReservationHandler::Create(ReservationAction::Update, $persistenceService, $userSession);

            $presenter = new OverviewFullCalendarReservationUpdatePresenter(
                new ReservationViewRepository(),
                new ReservationAuthorization(AuthorizationServiceFactory::GetAuthorizationService()),
                $persistenceService,
                new ResourceRepository(),
                $handler
            );

            $expectedVersionRaw = $this->GetForm(FormKeys::EXPECTED_VERSION);
            $expectedVersion = ($expectedVersionRaw === null || $expectedVersionRaw === '') ? null : (int)$expectedVersionRaw;

            $presenter->UpdateBasicFields(
                (string)$this->GetForm(FormKeys::REFERENCE_NUMBER),
                (string)$scope,
                (string)$this->GetForm(FormKeys::RESERVATION_TITLE),
                (string)$this->GetForm(FormKeys::DESCRIPTION),
                (string)$this->GetForm(FormKeys::BEGIN_DATE),
                (string)$this->GetForm(FormKeys::BEGIN_PERIOD),
                (string)$this->GetForm(FormKeys::END_DATE),
                (string)$this->GetForm(FormKeys::END_PERIOD),
                (int)$this->GetForm(FormKeys::RESOURCE_ID),
                $expectedVersion,
                $userSession,
                $this
            );
        } catch (ReservationVersionConflictException $ex) {
            // Someone else changed (or deleted) this reservation since the Drawer loaded it —
            // the WHERE version=? check inside ReservationRepository::Update() affected 0 rows.
            // Distinct from a generic failure so the Drawer can prompt "reload vs keep my
            // content" instead of silently last-write-wins or showing a plain error.
            Log::Debug('OverviewFullCalendarReservationUpdatePage - version conflict: %s', $ex->getMessage());
            $this->succeeded = false;
            $this->code = 'reservation_changed';
            $this->httpStatus = 409;
            $this->errors = ['這筆預約已被其他人修改，請重新載入後再試。'];
        } catch (Exception $ex) {
            Log::Error('OverviewFullCalendarReservationUpdatePage - Critical error updating reservation: %s', $ex);
            $this->succeeded = false;
            $this->errors = ['A critical error occurred.'];
        }

        http_response_code($this->httpStatus);
        header('Content-Type: application/json; charset=utf-8');
        $response = ['success' => $this->succeeded, 'errors' => $this->errors];
        if ($this->code !== null) {
            $response['code'] = $this->code;
            $response['message'] = $this->errors[0] ?? '';
        }
        echo json_encode($response);
        exit;
    }

    public function SetSaveSuccessfulMessage($succeeded)
    {
        $this->succeeded = (bool)$succeeded;
    }

    public function SetErrors($errors)
    {
        $this->errors = $errors;
    }

    public function SetWarnings($warnings)
    {
        // no-op — Drawer Phase 1-3 doesn't surface warnings separately from errors yet
    }

    public function SetRetryMessages($messages)
    {
        // no-op — conflict-retry-with-alternatives isn't built yet (P3)
    }

    public function SetCanBeRetried($canBeRetried)
    {
        // A validation failure with retry options available means the ReservationHandler's
        // re-validation-inside-the-lock found a genuine resource/time conflict (see
        // ReservationRetryOptions) rather than some other kind of validation error — tag it
        // distinctly so the Drawer can refresh the calendar (someone else's booking is what
        // caused this) instead of just showing a generic error.
        if ($canBeRetried) {
            $this->code = 'resource_conflict';
            $this->httpStatus = 409;
        }
    }

    public function SetRetryParameters($retryParameters)
    {
        // no-op
    }

    public function GetRetryParameters()
    {
        return [];
    }

    public function SetCanJoinWaitList($canJoinWaitlist)
    {
        // no-op
    }
}
