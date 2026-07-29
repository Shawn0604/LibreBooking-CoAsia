<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Pages/Ajax/IReservationSaveResultsView.php');
require_once(ROOT_DIR . 'Presenters/Reservation/ReservationPresenterFactory.php');

interface IReservationDeletePage extends IReservationSaveResultsView
{
    /**
     * @return string
     */
    public function GetReferenceNumber();

    /**
     * @return SeriesUpdateScope|string
     */
    public function GetSeriesUpdateScope();

    /**
     * @return string
     */
    public function GetReason();

    /**
     * @return int|null the version the caller last saw, or null to fall back to whatever
     *   version gets loaded fresh in this same request (see ExistingReservationSeries::
     *   ExpectedVersion())
     */
    public function GetExpectedVersion();
}

class ReservationDeletePage extends SecurePage implements IReservationDeletePage
{
    /**
     * @var ReservationDeletePresenter
     */
    protected $presenter;

    /**
     * @var bool
     */
    protected $reservationSavedSuccessfully = false;

    public function __construct()
    {
        parent::__construct();

        $factory = new ReservationPresenterFactory();
        $this->presenter = $factory->Delete($this, ServiceLocator::GetServer()->GetUserSession());
    }

    public function PageLoad()
    {
        try {
            $this->EnforceCSRFCheck();
            $reservation = $this->presenter->BuildReservation();
            $this->presenter->HandleReservation($reservation);

            if ($this->reservationSavedSuccessfully) {
                $this->Display('Ajax/reservation/delete_successful.tpl');
            } else {
                $this->Display('Ajax/reservation/delete_failed.tpl');
            }
        } catch (ReservationVersionConflictException $ex) {
            Log::Debug('ReservationDeletePage - version conflict: %s', $ex->getMessage());
            http_response_code(409);
            $this->SetErrors(['這筆預約已被其他人修改，請重新載入後再試。']);
            $this->Display('Ajax/reservation/delete_failed.tpl');
        } catch (Exception $ex) {
            Log::Error('ReservationDeletePage - Critical error saving reservation: %s', $ex);
            $this->Display('Ajax/reservation/reservation_error.tpl');
        }
    }

    public function SetSaveSuccessfulMessage($succeeded)
    {
        $this->reservationSavedSuccessfully = $succeeded;
    }

    public function SetErrors($errors)
    {
        $this->Set('Errors', $errors);
    }

    public function SetWarnings($warnings)
    {
        // set warnings variable
    }

    public function GetReferenceNumber()
    {
        return $this->GetForm(FormKeys::REFERENCE_NUMBER);
    }

    public function GetSeriesUpdateScope()
    {
        return $this->GetForm(FormKeys::SERIES_UPDATE_SCOPE);
    }

    public function SetRetryMessages($messages)
    {
        // no-op
    }

    public function SetCanBeRetried($canBeRetried)
    {
        // no-op
    }

    public function SetRetryParameters($retryParameters)
    {
        // no-op
    }

    public function GetRetryParameters()
    {
        // no-op
        return [];
    }

    public function SetCanJoinWaitList($canJoinWaitlist)
    {
        // no-op
    }

    public function GetReason()
    {
        return $this->GetForm(FormKeys::DELETE_REASON);
    }

    public function GetExpectedVersion()
    {
        $raw = $this->GetForm(FormKeys::EXPECTED_VERSION);
        return ($raw === null || $raw === '') ? null : (int)$raw;
    }
}

class ReservationDeleteJsonPage extends ReservationDeletePage implements IReservationDeletePage
{
    public function __construct()
    {
        parent::__construct();
    }

    public function PageLoad()
    {
        try {
            $reservation = $this->presenter->BuildReservation();
            $this->presenter->HandleReservation($reservation);
        } catch (ReservationVersionConflictException $ex) {
            Log::Debug('ReservationDeleteJsonPage - version conflict: %s', $ex->getMessage());
            $this->SetJson(
                ['deleted' => (string)false, 'code' => 'reservation_changed'],
                ['這筆預約已被其他人修改，請重新載入後再試。'],
                409
            );
        } catch (Exception $ex) {
            Log::Error('ReservationDeleteJsonPage - Critical error deleting reservation: %s', $ex);
            $this->SetJson(['deleted' => (string)false], ['A critical error occurred.'], 500);
        }
    }

    /**
     * @param bool $succeeded
     */
    public function SetSaveSuccessfulMessage($succeeded)
    {
        if ($succeeded) {
            $this->SetJson(['deleted' => (string)$succeeded]);
        }
    }

    public function SetErrors($errors)
    {
        if (!empty($errors)) {
            $this->SetJson(['deleted' => (string)false], $errors);
        }
    }

    public function SetWarnings($warnings)
    {
        // no-op
    }

    /**
     * @param array|string[] $messages
     */
    public function SetRetryMessages($messages)
    {
        // no-op
    }

    /**
     * @param bool $canBeRetried
     */
    public function SetCanBeRetried($canBeRetried)
    {
        // no-op
    }

    /**
     * @param ReservationRetryParameter[] $retryParameters
     */
    public function SetRetryParameters($retryParameters)
    {
        // no-op
    }

    /**
     * @return ReservationRetryParameter[]
     */
    public function GetRetryParameters()
    {
        // no-op
        return [];
    }
}
