<?php

require_once(__DIR__ . '/bootstrap.php');

class ThrowingPersistenceServiceDecorator implements IReservationPersistenceService
{
    public function Persist($reservation)
    {
        throw new Exception('injected failure for concurrency scenario 9 (exception mid-transaction)');
    }
}

/**
 * Generic worker process for the dual-connection concurrency harness. Reads one JSON job
 * description from argv[1], does all setup, signals READY, blocks for the orchestrator's "go",
 * then performs exactly one write against its OWN real DB connection (ServiceLocator is
 * per-process) and reports the outcome as a RESULT: JSON line.
 *
 * Job fields (action-dependent):
 *   action: 'create' | 'update' | 'create_throw'
 *   resourceIds: int[]            (create)
 *   title: string                 (create/update)
 *   startIso: string 'Y-m-d H:i:s' (create)
 *   durationMinutes: int           (create)
 *   referenceNumber: string        (update)
 *   expectedVersion: int|null      (update)
 *   requestId: string|null         (create — idempotency)
 *   userId: int
 *   slowMs: int                    (0 = real-speed lock)
 */

$job = json_decode($argv[1], true);
if (!$job) {
    fwrite(STDERR, "invalid job JSON\n");
    exit(2);
}

$session = ct_session($job['userId'] ?? 1);

try {
    if ($job['action'] === 'create' || $job['action'] === 'create_throw') {
        $resources = array_map('ct_resourceById', $job['resourceIds']);
        $primary = $resources[0];
        $start = Date::Parse($job['startIso'], 'UTC');
        $duration = new DateRange($start, $start->AddMinutes($job['durationMinutes']));
        $series = ReservationSeries::Create($job['userId'] ?? 1, $primary, $job['title'], 'concurrency-test', $duration, new RepeatNone(), $session);
        for ($i = 1; $i < count($resources); $i++) {
            $series->AddResource($resources[$i]);
        }

        $persistenceFactory = new ReservationPersistenceFactory();
        $persistenceService = $persistenceFactory->Create(ReservationAction::Create);

        if ($job['action'] === 'create_throw') {
            $persistenceService = new ThrowingPersistenceServiceDecorator();
        }

        $requestId = $job['requestId'] ?? null;
        $idempotencyService = $requestId !== null ? new IdempotencyService(ServiceLocator::GetDatabase()) : null;

        ct_waitForGo();

        if ($idempotencyService !== null) {
            $claim = $idempotencyService->TryClaim($requestId, $job['userId'] ?? 1);
            if ($claim->AlreadySucceededOutcome()) {
                ct_emitResult(['success' => true, 'referenceNumber' => $claim->ReferenceNumber(), 'replayed' => true]);
                exit(0);
            }
            if ($claim->IsInProgress()) {
                ct_emitResult(['success' => false, 'inProgress' => true]);
                exit(0);
            }
        }

        $handler = ct_createHandler(ReservationAction::Create, $persistenceService, $session, $job['slowMs'] ?? 0);
        $view = new ConcurrencyTestResultsView();

        try {
            $success = $handler->Handle($series, $view);
            if ($idempotencyService !== null) {
                if ($success) {
                    $idempotencyService->MarkSucceeded($requestId, $job['userId'] ?? 1, $series->CurrentInstance()->ReferenceNumber());
                } else {
                    $idempotencyService->MarkFailed($requestId, $job['userId'] ?? 1);
                }
            }
            ct_emitResult([
                'success' => $success,
                'referenceNumber' => $success ? $series->CurrentInstance()->ReferenceNumber() : null,
                'errors' => $view->Errors,
            ]);
        } catch (Throwable $ex) {
            if ($idempotencyService !== null) {
                $idempotencyService->MarkFailed($requestId, $job['userId'] ?? 1);
            }
            ct_emitResult(['success' => false, 'exception' => get_class($ex), 'message' => $ex->getMessage()]);
        }
    } elseif ($job['action'] === 'update') {
        $persistenceFactory = new ReservationPersistenceFactory();
        $persistenceService = $persistenceFactory->Create(ReservationAction::Update);
        $existingSeries = $persistenceService->LoadByReferenceNumber($job['referenceNumber']);
        if (isset($job['expectedVersion'])) {
            $existingSeries->SetExpectedVersion($job['expectedVersion']);
        }
        $resource = $existingSeries->Resource();
        $existingSeries->Update($existingSeries->UserId(), $resource, $job['title'], 'concurrency-test', $session);
        if (isset($job['newStartIso'])) {
            $newStart = Date::Parse($job['newStartIso'], 'UTC');
            $existingSeries->UpdateDuration(new DateRange($newStart, $newStart->AddMinutes($job['newDurationMinutes'] ?? 30)));
        }

        ct_waitForGo();

        $handler = ct_createHandler(ReservationAction::Update, $persistenceService, $session, $job['slowMs'] ?? 0);
        $view = new ConcurrencyTestResultsView();
        try {
            $success = $handler->Handle($existingSeries, $view);
            ct_emitResult(['success' => $success, 'errors' => $view->Errors]);
        } catch (Throwable $ex) {
            ct_emitResult(['success' => false, 'exception' => get_class($ex), 'message' => $ex->getMessage()]);
        }
    } else {
        fwrite(STDERR, 'unknown action: ' . $job['action'] . "\n");
        exit(2);
    }
} catch (Throwable $ex) {
    fwrite(STDERR, 'worker fatal: ' . get_class($ex) . ': ' . $ex->getMessage() . "\n" . $ex->getTraceAsString() . "\n");
    exit(1);
}
