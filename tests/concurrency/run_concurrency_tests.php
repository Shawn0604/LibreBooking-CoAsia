<?php

/**
 * Real dual (and in scenario 5, multi-) MySQL-connection concurrency test harness.
 *
 * This is deliberately NOT a PHPUnit test: PHPUnit runs the whole suite in one PHP process
 * against FakeDatabase, which cannot open two independent real DB connections or exercise
 * actual InnoDB row locks / transaction isolation. Each scenario below launches 2+ separate
 * `php worker.php` CLI subprocesses (see worker.php), each with its OWN mysqli connection to
 * the real dev database, synchronized via a READY/go barrier so their conflicting operations
 * genuinely overlap — then asserts on the real outcome.
 *
 * Run inside the app container:
 *   docker compose -f docker-compose.dev.yml exec app php tests/concurrency/run_concurrency_tests.php
 */

define('ROOT_DIR', dirname(__DIR__, 2) . '/');
require_once(__DIR__ . '/bootstrap.php');

$results = ['pass' => 0, 'fail' => 0, 'details' => []];

function ct_report($name, $ok, $detail)
{
    global $results;
    $results[$ok ? 'pass' : 'fail']++;
    $results['details'][] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
    fwrite(STDOUT, ($ok ? "PASS" : "FAIL") . " - $name" . ($detail ? " ($detail)" : '') . "\n");
}

/**
 * Launches all $jobs as separate worker.php subprocesses, waits for every one to signal
 * READY, then writes "go" to all of them back-to-back so they enter their real work within
 * milliseconds of each other, and collects each one's RESULT: line.
 *
 * @param array[] $jobs
 * @return array[] one result array per job, same order
 */
function ct_runConcurrent($jobs)
{
    $descriptorSpec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $procs = [];
    $pipes = [];

    foreach ($jobs as $i => $job) {
        $cmd = 'php ' . escapeshellarg(__DIR__ . '/worker.php') . ' ' . escapeshellarg(json_encode($job));
        $proc = proc_open($cmd, $descriptorSpec, $pipe);
        if (!is_resource($proc)) {
            throw new Exception("failed to launch worker $i");
        }
        $procs[$i] = $proc;
        $pipes[$i] = $pipe;
    }

    // Barrier: block on each worker's READY line, in order. A worker won't print it until
    // it's done all its setup (loading resources/series, building the request), so by the
    // time every worker has reported ready, all of them are sitting right at the "go" gate.
    foreach ($pipes as $i => $pipe) {
        $line = fgets($pipe[1]);
        if (trim((string)$line) !== 'READY') {
            $stderr = stream_get_contents($pipe[2]);
            throw new Exception("worker $i did not signal READY (got " . var_export($line, true) . "). stderr: $stderr");
        }
    }

    foreach ($pipes as $pipe) {
        fwrite($pipe[0], "go\n");
        fflush($pipe[0]);
    }

    $results = [];
    foreach ($pipes as $i => $pipe) {
        $resultLine = null;
        while (($l = fgets($pipe[1])) !== false) {
            if (str_starts_with($l, 'RESULT:')) {
                $resultLine = substr($l, strlen('RESULT:'));
                break;
            }
        }
        $stderr = stream_get_contents($pipe[2]);
        if ($resultLine === null) {
            throw new Exception("worker $i produced no RESULT line. stderr: $stderr");
        }
        $results[$i] = json_decode(trim($resultLine), true);
        fclose($pipe[0]);
        fclose($pipe[1]);
        fclose($pipe[2]);
        proc_close($procs[$i]);
    }

    return $results;
}

$createdReferenceNumbers = [];
function ct_track($refNumber)
{
    global $createdReferenceNumbers;
    if (!empty($refNumber)) {
        $createdReferenceNumbers[] = $refNumber;
    }
}

// Two distinct physical resources for the "different resources" / multi-resource scenarios.
$resourceRepo = new ResourceRepository();
$allResources = $resourceRepo->GetResourceList();
if (count($allResources) < 2) {
    fwrite(STDERR, "Need at least 2 resources in this dev DB to run the full scenario set.\n");
    exit(1);
}
$resourceA = $allResources[0];
$resourceB = $allResources[1];
$testUserId = ct_findTestUserId();
$session = ct_session($testUserId);
// Randomized per run so repeated runs (e.g. re-running after a failure) never collide with
// a previous run's slots — matters most when a previous run's cleanup didn't get to run.
$dayBase = 100 + random_int(0, 5000);

fwrite(STDOUT, "Using resources: {$resourceA->GetName()} (id {$resourceA->GetId()}), {$resourceB->GetName()} (id {$resourceB->GetId()})\n\n");

// ---------------------------------------------------------------------------------------
// Scenario 1: same-slot concurrent create on the SAME resource — only one may succeed.
// ---------------------------------------------------------------------------------------
$slot1 = ct_findSlot($resourceA, $session, $dayBase + 0);
$startIso1 = $slot1->ToTimezone('UTC')->Format('Y-m-d H:i:s');
$r = ct_runConcurrent([
    ['action' => 'create', 'resourceIds' => [$resourceA->GetId()], 'title' => 'CT-S1-A', 'startIso' => $startIso1, 'durationMinutes' => 30, 'slowMs' => 1200, 'userId' => $testUserId],
    ['action' => 'create', 'resourceIds' => [$resourceA->GetId()], 'title' => 'CT-S1-B', 'startIso' => $startIso1, 'durationMinutes' => 30, 'slowMs' => 0, 'userId' => $testUserId],
]);
ct_track($r[0]['referenceNumber'] ?? null);
ct_track($r[1]['referenceNumber'] ?? null);
$successCount = ($r[0]['success'] ? 1 : 0) + ($r[1]['success'] ? 1 : 0);
ct_report(
    'Scenario 1: same-slot concurrent create, only one succeeds',
    $successCount === 1,
    "successCount=$successCount, r0=" . json_encode($r[0]) . " r1=" . json_encode($r[1])
);

// ---------------------------------------------------------------------------------------
// Scenario 2: partial time overlap on the SAME resource — only one may succeed.
// ---------------------------------------------------------------------------------------
$slot2 = ct_findSlot($resourceA, $session, $dayBase + 1);
$start2a = $slot2->ToTimezone('UTC')->Format('Y-m-d H:i:s');
$start2b = $slot2->AddMinutes(15)->ToTimezone('UTC')->Format('Y-m-d H:i:s'); // overlaps the back half of A's 30-min slot
$r = ct_runConcurrent([
    ['action' => 'create', 'resourceIds' => [$resourceA->GetId()], 'title' => 'CT-S2-A', 'startIso' => $start2a, 'durationMinutes' => 30, 'slowMs' => 1200, 'userId' => $testUserId],
    ['action' => 'create', 'resourceIds' => [$resourceA->GetId()], 'title' => 'CT-S2-B', 'startIso' => $start2b, 'durationMinutes' => 30, 'slowMs' => 0, 'userId' => $testUserId],
]);
ct_track($r[0]['referenceNumber'] ?? null);
ct_track($r[1]['referenceNumber'] ?? null);
$successCount = ($r[0]['success'] ? 1 : 0) + ($r[1]['success'] ? 1 : 0);
ct_report(
    'Scenario 2: partial overlap concurrent create, only one succeeds',
    $successCount === 1,
    "successCount=$successCount, r0=" . json_encode($r[0]) . " r1=" . json_encode($r[1])
);

// ---------------------------------------------------------------------------------------
// Scenario 3: adjacent (back-to-back, non-overlapping) slots on the SAME resource — both
// succeed.
// ---------------------------------------------------------------------------------------
$slot3 = ct_findSlot($resourceA, $session, $dayBase + 2);
$start3a = $slot3->ToTimezone('UTC')->Format('Y-m-d H:i:s');
$start3b = $slot3->AddMinutes(30)->ToTimezone('UTC')->Format('Y-m-d H:i:s'); // starts exactly when A ends
$r = ct_runConcurrent([
    ['action' => 'create', 'resourceIds' => [$resourceA->GetId()], 'title' => 'CT-S3-A', 'startIso' => $start3a, 'durationMinutes' => 30, 'slowMs' => 800, 'userId' => $testUserId],
    ['action' => 'create', 'resourceIds' => [$resourceA->GetId()], 'title' => 'CT-S3-B', 'startIso' => $start3b, 'durationMinutes' => 30, 'slowMs' => 0, 'userId' => $testUserId],
]);
ct_track($r[0]['referenceNumber'] ?? null);
ct_track($r[1]['referenceNumber'] ?? null);
$bothSucceeded = ($r[0]['success'] ?? false) && ($r[1]['success'] ?? false);
ct_report(
    'Scenario 3: adjacent non-overlapping slots, both succeed',
    $bothSucceeded,
    "r0=" . json_encode($r[0]) . " r1=" . json_encode($r[1])
);

// ---------------------------------------------------------------------------------------
// Scenario 4: same time slot, but DIFFERENT resources — both succeed (locks don't cross
// resources).
// ---------------------------------------------------------------------------------------
$slot4 = ct_findSlot($resourceA, $session, $dayBase + 3);
$start4 = $slot4->ToTimezone('UTC')->Format('Y-m-d H:i:s');
$r = ct_runConcurrent([
    ['action' => 'create', 'resourceIds' => [$resourceA->GetId()], 'title' => 'CT-S4-A', 'startIso' => $start4, 'durationMinutes' => 30, 'slowMs' => 800, 'userId' => $testUserId],
    ['action' => 'create', 'resourceIds' => [$resourceB->GetId()], 'title' => 'CT-S4-B', 'startIso' => $start4, 'durationMinutes' => 30, 'slowMs' => 0, 'userId' => $testUserId],
]);
ct_track($r[0]['referenceNumber'] ?? null);
ct_track($r[1]['referenceNumber'] ?? null);
$bothSucceeded = ($r[0]['success'] ?? false) && ($r[1]['success'] ?? false);
ct_report(
    'Scenario 4: same slot, different resources, both succeed',
    $bothSucceeded,
    "r0=" . json_encode($r[0]) . " r1=" . json_encode($r[1])
);

// ---------------------------------------------------------------------------------------
// Scenario 5: multi-resource reservations submitted in REVERSE resource order, contending
// on a shared resource — must not deadlock, and exactly one may succeed (they share
// resourceA and overlap in time).
// ---------------------------------------------------------------------------------------
$slot5 = ct_findSlot($resourceA, $session, $dayBase + 4);
$start5 = $slot5->ToTimezone('UTC')->Format('Y-m-d H:i:s');
$deadlockStart = microtime(true);
$r = ct_runConcurrent([
    ['action' => 'create', 'resourceIds' => [$resourceA->GetId(), $resourceB->GetId()], 'title' => 'CT-S5-A', 'startIso' => $start5, 'durationMinutes' => 30, 'slowMs' => 1200, 'userId' => $testUserId],
    ['action' => 'create', 'resourceIds' => [$resourceB->GetId(), $resourceA->GetId()], 'title' => 'CT-S5-B', 'startIso' => $start5, 'durationMinutes' => 30, 'slowMs' => 0, 'userId' => $testUserId],
]);
$elapsed = microtime(true) - $deadlockStart;
ct_track($r[0]['referenceNumber'] ?? null);
ct_track($r[1]['referenceNumber'] ?? null);
$successCount = ($r[0]['success'] ? 1 : 0) + ($r[1]['success'] ? 1 : 0);
// A real deadlock would show up as one side stalling for InnoDB's lock wait timeout (default
// 50s) before erroring out; finishing in a few seconds is the actual proof of no deadlock.
$noDeadlock = $elapsed < 15.0;
ct_report(
    'Scenario 5: reverse-order multi-resource lock, no deadlock, only one succeeds',
    $noDeadlock && $successCount === 1,
    "elapsed={$elapsed}s successCount=$successCount r0=" . json_encode($r[0]) . " r1=" . json_encode($r[1])
);

// ---------------------------------------------------------------------------------------
// Scenario 6: a CREATE and an UPDATE (moving an existing reservation onto the same
// resource/slot) racing for the same slot — only one may succeed.
// ---------------------------------------------------------------------------------------
$slot6 = ct_findSlot($resourceA, $session, $dayBase + 5);
$start6 = $slot6->ToTimezone('UTC')->Format('Y-m-d H:i:s');
$otherSlot6 = ct_findSlot($resourceA, $session, $dayBase + 6); // where the "existing" reservation starts, before being moved
// Seed an existing reservation elsewhere on resourceA, to be updated into start6's slot.
$seedJob = ['action' => 'create', 'resourceIds' => [$resourceA->GetId()], 'title' => 'CT-S6-SEED', 'startIso' => $otherSlot6->ToTimezone('UTC')->Format('Y-m-d H:i:s'), 'durationMinutes' => 30, 'slowMs' => 0, 'userId' => $testUserId];
$seedResult = ct_runConcurrent([$seedJob])[0];
ct_track($seedResult['referenceNumber'] ?? null);
if (!($seedResult['success'] ?? false)) {
    ct_report('Scenario 6: create-vs-update race for same slot', false, 'seed reservation failed to create: ' . json_encode($seedResult));
} else {
    $r = ct_runConcurrent([
        ['action' => 'create', 'resourceIds' => [$resourceA->GetId()], 'title' => 'CT-S6-CREATE', 'startIso' => $start6, 'durationMinutes' => 30, 'slowMs' => 1200, 'userId' => $testUserId],
        ['action' => 'update', 'referenceNumber' => $seedResult['referenceNumber'], 'title' => 'CT-S6-MOVED', 'expectedVersion' => 0, 'slowMs' => 0, 'userId' => $testUserId, 'newStartIso' => $start6, 'newDurationMinutes' => 30],
    ]);
    ct_track($r[0]['referenceNumber'] ?? null);
    $successCount = ($r[0]['success'] ? 1 : 0) + ($r[1]['success'] ? 1 : 0);
    ct_report(
        'Scenario 6: create-vs-update race for same slot, only one succeeds',
        $successCount === 1,
        "r0=" . json_encode($r[0]) . " r1=" . json_encode($r[1])
    );
}

// ---------------------------------------------------------------------------------------
// Scenario 7: two updates loading the SAME version, racing — second must get a version
// conflict (ReservationVersionConflictException), never silently overwrite.
// ---------------------------------------------------------------------------------------
$slot7 = ct_findSlot($resourceA, $session, $dayBase + 7);
$seedJob7 = ['action' => 'create', 'resourceIds' => [$resourceA->GetId()], 'title' => 'CT-S7-SEED', 'startIso' => $slot7->ToTimezone('UTC')->Format('Y-m-d H:i:s'), 'durationMinutes' => 30, 'slowMs' => 0, 'userId' => $testUserId];
$seedResult7 = ct_runConcurrent([$seedJob7])[0];
ct_track($seedResult7['referenceNumber'] ?? null);
if (!($seedResult7['success'] ?? false)) {
    ct_report('Scenario 7: concurrent updates with same loaded version', false, 'seed failed: ' . json_encode($seedResult7));
} else {
    $r = ct_runConcurrent([
        ['action' => 'update', 'referenceNumber' => $seedResult7['referenceNumber'], 'title' => 'CT-S7-EDIT-A', 'expectedVersion' => 0, 'slowMs' => 1200, 'userId' => $testUserId],
        ['action' => 'update', 'referenceNumber' => $seedResult7['referenceNumber'], 'title' => 'CT-S7-EDIT-B', 'expectedVersion' => 0, 'slowMs' => 0, 'userId' => $testUserId],
    ]);
    $successCount = ($r[0]['success'] ? 1 : 0) + ($r[1]['success'] ? 1 : 0);
    $oneGotVersionConflict = ($r[0]['exception'] ?? '') === 'ReservationVersionConflictException'
        || ($r[1]['exception'] ?? '') === 'ReservationVersionConflictException';
    ct_report(
        'Scenario 7: concurrent updates with same loaded version, second gets version conflict',
        $successCount === 1 && $oneGotVersionConflict,
        "successCount=$successCount r0=" . json_encode($r[0]) . " r1=" . json_encode($r[1])
    );
}

// ---------------------------------------------------------------------------------------
// Scenario 8: double-submit with the SAME idempotency key — DB has exactly one reservation,
// both responses return the same reference number.
// ---------------------------------------------------------------------------------------
$slot8 = ct_findSlot($resourceA, $session, $dayBase + 8);
$start8 = $slot8->ToTimezone('UTC')->Format('Y-m-d H:i:s');
$requestId8 = 'ct-scenario8-' . bin2hex(random_bytes(6));
$r = ct_runConcurrent([
    ['action' => 'create', 'resourceIds' => [$resourceA->GetId()], 'title' => 'CT-S8', 'startIso' => $start8, 'durationMinutes' => 30, 'slowMs' => 1200, 'userId' => $testUserId, 'requestId' => $requestId8],
    ['action' => 'create', 'resourceIds' => [$resourceA->GetId()], 'title' => 'CT-S8', 'startIso' => $start8, 'durationMinutes' => 30, 'slowMs' => 0, 'userId' => $testUserId, 'requestId' => $requestId8],
]);
ct_track($r[0]['referenceNumber'] ?? null);
ct_track($r[1]['referenceNumber'] ?? null);
// TryClaim() itself races on the (request_id, user_id) primary key — whichever worker's INSERT
// lands first "wins" the claim regardless of slowMs (slowMs only delays the resource lock
// step, which happens after claiming). So a truly simultaneous double-submit has two correct
// possible shapes, and exactly one incorrect one:
//   - both report success with the SAME reference number (the loser's claim landed after the
//     winner had already committed and marked itself 'success', so it replayed instead of
//     creating), or
//   - exactly one reports success (a real creation) and the other reports inProgress=true (the
//     loser's claim landed while the winner was still mid-flight — a correct "still processing"
//     signal, not an error, per the request_in_progress contract)
//   - WRONG: both report success with two DIFFERENT reference numbers — that would mean two
//     reservations got created from one logical double-click, exactly what this is testing for.
$successCount = ($r[0]['success'] ? 1 : 0) + ($r[1]['success'] ? 1 : 0);
$inProgressCount = (($r[0]['inProgress'] ?? false) ? 1 : 0) + (($r[1]['inProgress'] ?? false) ? 1 : 0);
$noDuplicateReferenceNumbers = !($r[0]['success'] && $r[1]['success'] && $r[0]['referenceNumber'] !== $r[1]['referenceNumber']);
$shapeIsValid = ($successCount === 2 && $inProgressCount === 0) || ($successCount === 1 && $inProgressCount === 1);
ct_report(
    'Scenario 8: double-submit same idempotency key never creates two reservations',
    $noDuplicateReferenceNumbers && $shapeIsValid,
    "successCount=$successCount inProgressCount=$inProgressCount r0=" . json_encode($r[0]) . " r1=" . json_encode($r[1])
);

// ---------------------------------------------------------------------------------------
// Scenario 9: exception mid-transaction — no partial data left, lock released, the NEXT
// request for the same slot still succeeds normally afterward.
// ---------------------------------------------------------------------------------------
$slot9 = ct_findSlot($resourceA, $session, $dayBase + 9);
$start9 = $slot9->ToTimezone('UTC')->Format('Y-m-d H:i:s');
$throwResult = ct_runConcurrent([
    ['action' => 'create_throw', 'resourceIds' => [$resourceA->GetId()], 'title' => 'CT-S9-SHOULD-NOT-EXIST', 'startIso' => $start9, 'durationMinutes' => 30, 'slowMs' => 0, 'userId' => $testUserId],
])[0];
$threwAsExpected = ($throwResult['success'] ?? true) === false && isset($throwResult['exception']);

$followUp = ct_runConcurrent([
    ['action' => 'create', 'resourceIds' => [$resourceA->GetId()], 'title' => 'CT-S9-FOLLOWUP', 'startIso' => $start9, 'durationMinutes' => 30, 'slowMs' => 0, 'userId' => $testUserId],
])[0];
ct_track($followUp['referenceNumber'] ?? null);
$followUpSucceeded = $followUp['success'] ?? false;

ct_report(
    'Scenario 9: exception mid-transaction leaves no partial data and releases the lock',
    $threwAsExpected && $followUpSucceeded,
    "throwResult=" . json_encode($throwResult) . " followUp=" . json_encode($followUp)
);

// ---------------------------------------------------------------------------------------
// Cleanup
// ---------------------------------------------------------------------------------------
fwrite(STDOUT, "\nCleaning up " . count($createdReferenceNumbers) . " test reservation(s)...\n");
$deletePersistence = (new ReservationPersistenceFactory())->Create(ReservationAction::Delete);
foreach (array_unique($createdReferenceNumbers) as $refNumber) {
    try {
        $series = $deletePersistence->LoadByReferenceNumber($refNumber);
        if ($series === null) {
            continue;
        }
        $series->Delete($session);
        $handler = ReservationHandler::Create(ReservationAction::Delete, $deletePersistence, $session);
        $view = new ConcurrencyTestResultsView();
        $handler->Handle($series, $view);
    } catch (Throwable $ex) {
        fwrite(STDOUT, "  cleanup warning for $refNumber: " . $ex->getMessage() . "\n");
    }
}

fwrite(STDOUT, "\n==== SUMMARY: {$results['pass']} passed, {$results['fail']} failed ====\n");
exit($results['fail'] > 0 ? 1 : 0);
