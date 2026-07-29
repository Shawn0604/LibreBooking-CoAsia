<?php

/**
 * Shared bootstrap for the real dual-MySQL-connection concurrency test harness
 * (tests/concurrency/). NOT part of the PHPUnit suite — PHPUnit runs everything in one PHP
 * process against a FakeDatabase, which cannot exercise real row locks, real transaction
 * isolation, or genuine two-connection races. These scripts are run as separate CLI
 * subprocesses (see run_concurrency_tests.php), each opening its own real mysqli connection
 * to the dev database, to actually prove what the mocked test suite structurally cannot.
 */

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 2) . '/');
}

require_once(ROOT_DIR . 'Domain/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');
require_once(ROOT_DIR . 'Pages/Ajax/IReservationSaveResultsView.php');
require_once(__DIR__ . '/SlowResourceLockService.php');

class ConcurrencyTestResultsView implements IReservationSaveResultsView
{
    public $Succeeded;
    public $Errors = [];

    public function SetSaveSuccessfulMessage($succeeded)
    {
        $this->Succeeded = $succeeded;
    }
    public function SetErrors($errors)
    {
        $this->Errors = $errors;
    }
    public function SetWarnings($warnings)
    {
    }
    public function SetCanBeRetried($canBeRetried)
    {
    }
    public function SetRetryParameters($retryParameters)
    {
    }
    public function GetRetryParameters()
    {
        return [];
    }
    public function SetRetryMessages($messages)
    {
    }
    public function SetCanJoinWaitList($canJoinWaitlist)
    {
    }
}

function ct_session($userId = 1)
{
    $session = new UserSession($userId);
    $session->Timezone = 'UTC';
    return $session;
}

/**
 * Finds a reservable slot on the given resource, at least $minDaysOut days out, avoiding the
 * shared day-0 range every scenario in the same run uses by taking a distinct offset per call.
 */
function ct_findSlot($resource, $session, $daysOut)
{
    $scheduleRepo = new ScheduleRepository();
    $layout = $scheduleRepo->GetLayout($resource->GetScheduleId(), new ScheduleLayoutFactory($session->Timezone));
    $probeDay = Date::Now()->AddDays($daysOut);
    $periods = $layout->GetLayout($probeDay);
    foreach ($periods as $p) {
        if ($p->IsReservable()) {
            return $p->BeginDate();
        }
    }
    throw new Exception("No reservable slot found $daysOut days out");
}

function ct_resourceById($id)
{
    $repo = new ResourceRepository();
    return $repo->LoadById($id);
}

/**
 * Builds a real ReservationHandler wired exactly like production (ReservationHandler::Create())
 * except the resource-lock service can be swapped for SlowResourceLockService to force a real,
 * observable overlap window between two independently-connected worker processes instead of
 * relying on OS scheduling luck to make them race.
 */
function ct_createHandler($action, $persistenceService, UserSession $session, $slowMs = 0)
{
    $validationFactory = new ReservationValidationFactory();
    $validationService = $validationFactory->Create($action, $session);
    $notificationFactory = new ReservationNotificationFactory();
    $notificationService = $notificationFactory->Create($action, $session);
    $scheduleRepository = new ScheduleRepository();
    $retryOptions = new ReservationRetryOptions(
        new ReservationConflictIdentifier(new ResourceAvailability(new ReservationViewRepository())),
        $scheduleRepository
    );

    $realLock = new ResourceLockService(ServiceLocator::GetDatabase());
    $lockService = $slowMs > 0 ? new SlowResourceLockService($realLock, $slowMs) : $realLock;

    return new ReservationHandler($persistenceService, $validationService, $notificationService, $retryOptions, $lockService);
}

/**
 * Two-phase readiness barrier: print READY, flush, then block reading one line from STDIN.
 * The orchestrator only writes "go\n" to a worker's stdin once every worker in the scenario
 * has printed READY, so both workers enter their real work (LockResources()/TryClaim()) within
 * a few milliseconds of each other rather than whenever the OS happened to schedule them.
 */
function ct_waitForGo()
{
    fwrite(STDOUT, "READY\n");
    fflush(STDOUT);
    fgets(STDIN);
}

function ct_emitResult($data)
{
    fwrite(STDOUT, "RESULT:" . json_encode($data) . "\n");
}
