<?php

require_once(ROOT_DIR . 'lib/Database/namespace.php');

/**
 * P0 concurrency protection (see COASIA-CHANGES.md) — prevents a double-click or a network
 * retry of the SAME logical "create a reservation" attempt from creating two reservations.
 * The client generates one UUID per create attempt (reused across retries of that same
 * attempt, replaced only once it succeeds or a genuinely new attempt starts) and submits it
 * as requestId; TryClaim() atomically claims the (request_id, user_id) row in
 * `reservation_requests` before any reservation write happens, so a second submission with
 * the same id either replays the first attempt's result (already succeeded) or is told
 * another submission is still in flight (in_progress), instead of racing it into a second row.
 *
 * Deliberately NOT run inside ReservationHandler's resource-lock transaction (ResourceLockService)
 * — idempotency is keyed on (request_id, user_id), not on any resource, so it doesn't need that
 * lock's serialization, and keeping it as its own short auto-committing write means the claim is
 * durable and visible to a concurrent request immediately rather than only after the whole
 * reservation transaction commits.
 */
interface IIdempotencyService
{
    /**
     * @param string $requestId
     * @param int $userId
     * @return IdempotencyClaim
     */
    public function TryClaim($requestId, $userId);

    /**
     * @param string $requestId
     * @param int $userId
     * @param string $referenceNumber
     */
    public function MarkSucceeded($requestId, $userId, $referenceNumber);

    /**
     * @param string $requestId
     * @param int $userId
     */
    public function MarkFailed($requestId, $userId);
}

/**
 * Thrown when TryClaim() finds another submission with the same (request_id, user_id) still
 * actively being handled. Distinct from a generic failure so the caller can report
 * request_in_progress rather than a plain error — the client should back off and poll/retry
 * later rather than resubmitting immediately in a tight loop.
 */
class ReservationRequestInProgressException extends Exception
{
    public function __construct($requestId)
    {
        parent::__construct("Reservation request $requestId is already being processed");
    }
}

class IdempotencyRequestStatus
{
    public const Processing = 'processing';
    public const Success = 'success';
    public const Failed = 'failed';
}

/**
 * Result of TryClaim(): exactly one of
 *  - Claimed() — no prior attempt (or a failed/stale one) exists; caller should proceed and
 *    call MarkSucceeded()/MarkFailed() when done.
 *  - AlreadySucceeded($referenceNumber) — a prior attempt with this id already created the
 *    reservation; caller must NOT create another one and should return this reference number.
 *  - InProgress() — another submission with this id is currently being handled; caller should
 *    report request_in_progress rather than proceeding or blocking indefinitely.
 */
class IdempotencyClaim
{
    private const CLAIMED = 'claimed';
    private const ALREADY_SUCCEEDED = 'already_succeeded';
    private const IN_PROGRESS = 'in_progress';

    private $outcome;
    private $referenceNumber;

    private function __construct($outcome, $referenceNumber = null)
    {
        $this->outcome = $outcome;
        $this->referenceNumber = $referenceNumber;
    }

    public static function Claimed()
    {
        return new self(self::CLAIMED);
    }

    public static function AlreadySucceeded($referenceNumber)
    {
        return new self(self::ALREADY_SUCCEEDED, $referenceNumber);
    }

    public static function InProgress()
    {
        return new self(self::IN_PROGRESS);
    }

    public function WasClaimed()
    {
        return $this->outcome === self::CLAIMED;
    }

    public function AlreadySucceededOutcome()
    {
        return $this->outcome === self::ALREADY_SUCCEEDED;
    }

    public function IsInProgress()
    {
        return $this->outcome === self::IN_PROGRESS;
    }

    /**
     * @return string|null only meaningful when AlreadySucceededOutcome() is true
     */
    public function ReferenceNumber()
    {
        return $this->referenceNumber;
    }
}

class GetIdempotencyRequestCommand extends SqlCommand
{
    public function __construct($requestId, $userId)
    {
        parent::__construct('SELECT `status`, `reference_number` FROM `reservation_requests` WHERE `request_id` = @requestid AND `user_id` = @userid');
        $this->AddParameter(new Parameter('@requestid', $requestId));
        $this->AddParameter(new Parameter('@userid', $userId));
    }
}

class InsertIdempotencyRequestCommand extends SqlCommand
{
    public function __construct($requestId, $userId)
    {
        parent::__construct(
            'INSERT INTO `reservation_requests` (`request_id`, `user_id`, `status`, `reference_number`, `date_created`, `last_modified`)
             VALUES (@requestid, @userid, @status, NULL, NOW(), NOW())'
        );
        $this->AddParameter(new Parameter('@requestid', $requestId));
        $this->AddParameter(new Parameter('@userid', $userId));
        $this->AddParameter(new Parameter('@status', IdempotencyRequestStatus::Processing));
    }
}

/**
 * Atomically flips an existing row back to 'processing' so it can be retried — either it was
 * left 'failed' by a prior attempt, or it's been 'processing' for over a minute (long enough
 * that the request that claimed it almost certainly crashed/died before finishing rather than
 * still legitimately being in flight). GetAffectedRows() === 1 means this call won the reclaim;
 * 0 means the row didn't match (still genuinely processing, or someone else reclaimed it first).
 */
class ReclaimIdempotencyRequestCommand extends SqlCommand
{
    public function __construct($requestId, $userId)
    {
        parent::__construct(
            "UPDATE `reservation_requests`
             SET `status` = @status, `last_modified` = NOW()
             WHERE `request_id` = @requestid AND `user_id` = @userid
               AND (`status` = @failedstatus OR (`status` = @processingstatus AND `last_modified` < DATE_SUB(NOW(), INTERVAL 60 SECOND)))"
        );
        $this->AddParameter(new Parameter('@requestid', $requestId));
        $this->AddParameter(new Parameter('@userid', $userId));
        $this->AddParameter(new Parameter('@status', IdempotencyRequestStatus::Processing));
        $this->AddParameter(new Parameter('@failedstatus', IdempotencyRequestStatus::Failed));
        $this->AddParameter(new Parameter('@processingstatus', IdempotencyRequestStatus::Processing));
    }
}

class UpdateIdempotencyRequestStatusCommand extends SqlCommand
{
    public function __construct($requestId, $userId, $status, $referenceNumber)
    {
        parent::__construct(
            'UPDATE `reservation_requests`
             SET `status` = @status, `reference_number` = @referencenumber, `last_modified` = NOW()
             WHERE `request_id` = @requestid AND `user_id` = @userid'
        );
        $this->AddParameter(new Parameter('@requestid', $requestId));
        $this->AddParameter(new Parameter('@userid', $userId));
        $this->AddParameter(new Parameter('@status', $status));
        $this->AddParameter(new Parameter('@referencenumber', $referenceNumber));
    }
}

class IdempotencyService implements IIdempotencyService
{
    /**
     * @var Database
     */
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function TryClaim($requestId, $userId)
    {
        return $this->TryClaimInternal($requestId, $userId, 0);
    }

    private function TryClaimInternal($requestId, $userId, $attempt)
    {
        if ($attempt > 3) {
            // Pathological repeated races on the same id — bail out to a safe "try again
            // later" response rather than looping forever.
            return IdempotencyClaim::InProgress();
        }

        $reader = $this->db->Query(new GetIdempotencyRequestCommand($requestId, $userId));
        $row = $reader->GetRow();
        $reader->Free();

        if (empty($row)) {
            try {
                $this->db->Execute(new InsertIdempotencyRequestCommand($requestId, $userId));
                return IdempotencyClaim::Claimed();
            } catch (Exception $ex) {
                // Primary key (request_id, user_id) collision — another concurrent submission
                // with the same id inserted between our SELECT and this INSERT. Re-check its
                // state rather than treating this as a hard failure.
                Log::Debug('IdempotencyService - insert race for request %s, rechecking state: %s', $requestId, $ex->getMessage());
                return $this->TryClaimInternal($requestId, $userId, $attempt + 1);
            }
        }

        if ($row['status'] === IdempotencyRequestStatus::Success) {
            return IdempotencyClaim::AlreadySucceeded($row['reference_number']);
        }

        $this->db->Execute(new ReclaimIdempotencyRequestCommand($requestId, $userId));
        if ($this->db->GetAffectedRows() === 1) {
            return IdempotencyClaim::Claimed();
        }

        return IdempotencyClaim::InProgress();
    }

    public function MarkSucceeded($requestId, $userId, $referenceNumber)
    {
        $this->db->Execute(new UpdateIdempotencyRequestStatusCommand(
            $requestId,
            $userId,
            IdempotencyRequestStatus::Success,
            $referenceNumber
        ));
    }

    public function MarkFailed($requestId, $userId)
    {
        $this->db->Execute(new UpdateIdempotencyRequestStatusCommand(
            $requestId,
            $userId,
            IdempotencyRequestStatus::Failed,
            null
        ));
    }
}
