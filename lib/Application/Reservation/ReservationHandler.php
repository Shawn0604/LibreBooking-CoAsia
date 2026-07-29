<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Server/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'Domain/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/Persistence/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/Validation/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/Notification/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/Concurrency/ResourceLockService.php');

interface IReservationHandler
{
    /**
     * @param ReservationSeries|ExistingReservationSeries $reservationSeries
     * @param IReservationSaveResultsView $view
     * @return bool if the reservation was handled or not
     */
    public function Handle($reservationSeries, IReservationSaveResultsView $view);
}

class ReservationHandler implements IReservationHandler
{
    /**
     * @var IReservationPersistenceService
     */
    private $persistenceService;

    /**
     * @var IReservationValidationService
     */
    private $validationService;

    /**
     * @var IReservationNotificationService
     */
    private $notificationService;

    /**
     * @var IReservationRetryOptions
     */
    private $retryOptions;

    /**
     * @var IResourceLockService
     */
    private $lockService;

    public function __construct(
        IReservationPersistenceService $persistenceService,
        IReservationValidationService $validationService,
        IReservationNotificationService $notificationService,
        IReservationRetryOptions $retryOptions,
        IResourceLockService $lockService
    ) {
        $this->persistenceService = $persistenceService;
        $this->validationService = $validationService;
        $this->notificationService = $notificationService;
        $this->retryOptions = $retryOptions;
        $this->lockService = $lockService;
    }

    /**
     * @static
     * @param $reservationAction string|ReservationAction
     * @param $persistenceService null|IReservationPersistenceService
     * @param UserSession $session
     * @return IReservationHandler
     */
    public static function Create($reservationAction, $persistenceService, UserSession $session)
    {
        if (!isset($persistenceService)) {
            $persistenceFactory = new ReservationPersistenceFactory();
            $persistenceService = $persistenceFactory->Create($reservationAction);
        }

        $validationFactory = new ReservationValidationFactory();
        $validationService = $validationFactory->Create($reservationAction, $session);

        $notificationFactory = new ReservationNotificationFactory();
        $notificationService = $notificationFactory->Create($reservationAction, $session);

        $scheduleRepository = new ScheduleRepository();
        $retryOptions = new ReservationRetryOptions(new ReservationConflictIdentifier(new ResourceAvailability(new ReservationViewRepository())), $scheduleRepository);

        $lockService = new ResourceLockService(ServiceLocator::GetDatabase());

        return new ReservationHandler($persistenceService, $validationService, $notificationService, $retryOptions, $lockService);
    }

    /**
     * @param ReservationSeries|ExistingReservationSeries $reservationSeries
     * @param IReservationSaveResultsView $view
     * @return bool if the reservation was handled or not
     * @throws Throwable
     */
    public function Handle($reservationSeries, IReservationSaveResultsView $view)
    {
        $this->retryOptions->AdjustReservation($reservationSeries, $view->GetRetryParameters());

        // P0 concurrency protection: lock every resource this reservation touches (including
        // ones being moved AWAY from, on update — see GetResourceIdsToLock()) before doing
        // the "real" availability check, and re-run that check inside the same lock/
        // transaction rather than trusting whatever was already established before the lock
        // was acquired. Two requests racing for the same resource/time now serialize on the
        // row lock instead of both reading "available" and both writing.
        $this->lockService->LockResources($this->GetResourceIdsToLock($reservationSeries));

        try {
            $validationResult = $this->validationService->Validate($reservationSeries, $view->GetRetryParameters());
            $result = $validationResult->CanBeSaved();

            if ($result) {
                $this->persistenceService->Persist($reservationSeries);
                $this->lockService->Commit();
            } else {
                // Nothing to persist — still an ended transaction (lock released), just via
                // rollback rather than commit. Not an exception: a real conflict/validation
                // failure is a normal outcome, surfaced below via SetErrors() etc., not a
                // thrown error.
                $this->lockService->Rollback();
            }
        } catch (Throwable $ex) {
            $this->lockService->Rollback();
            Log::Error('Error saving reservation: %s', $ex);
            throw $ex;
        }

        if ($result) {
            $view->SetSaveSuccessfulMessage($result);
        } else {
            $view->SetSaveSuccessfulMessage($result);
            $view->SetErrors($validationResult->GetErrors());

            $view->SetCanBeRetried($validationResult->CanBeRetried());
            $view->SetRetryParameters($validationResult->GetRetryParameters());
            $view->SetRetryMessages($validationResult->GetRetryMessages());
            $view->SetCanJoinWaitList($validationResult->CanJoinWaitList() && Configuration::Instance()->GetKey(
                ConfigKeys::RESERVATION_ALLOW_WAITLIST,
                new BooleanConverter()
            ));
        }

        $view->SetWarnings($validationResult->GetWarnings());

        // Only after the transaction has committed and the resource lock released — SMTP/
        // LINE latency must never hold a meeting room lock open (this is also why Persist()
        // errors above are rolled back and re-thrown before ever reaching here: a failed
        // write must never trigger a "your reservation is confirmed" notification).
        if ($result) {
            $this->notificationService->Notify($reservationSeries);
        }

        return $result;
    }

    /**
     * @param ReservationSeries|ExistingReservationSeries $reservationSeries
     * @return int[]
     */
    private function GetResourceIdsToLock($reservationSeries)
    {
        $ids = [];
        foreach ($reservationSeries->AllResources() as $resource) {
            $ids[] = $resource->GetResourceId();
        }

        // On update, a resource being REMOVED from the series needs to be locked too —
        // freeing it up can unblock someone else's conflicting request just as much as
        // adding a resource does, and both need to serialize against the same row lock.
        if ($reservationSeries instanceof ExistingReservationSeries) {
            foreach ($reservationSeries->GetEvents() as $event) {
                if ($event instanceof ResourceRemovedEvent) {
                    $ids[] = $event->ResourceId();
                }
            }
        }

        return $ids;
    }
}
