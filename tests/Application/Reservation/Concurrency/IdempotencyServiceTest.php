<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Application/Reservation/Concurrency/IdempotencyService.php');

class IdempotencyServiceTest extends TestBase
{
    private const REQUEST_ID = 'req-abc';
    private const USER_ID = 42;

    /**
     * @var IdempotencyService
     */
    private $service;

    public function setUp(): void
    {
        parent::setup();
        $this->service = new IdempotencyService($this->db);
    }

    public function testClaimsWhenNoPriorRowExists()
    {
        $this->db->SetRow(0, []);

        $claim = $this->service->TryClaim(self::REQUEST_ID, self::USER_ID);

        $this->assertTrue($claim->WasClaimed());
        $insertCommands = $this->db->GetCommandsOfType('InsertIdempotencyRequestCommand');
        $this->assertCount(1, $insertCommands);
    }

    public function testReturnsAlreadySucceededWithStoredReferenceNumberWithoutWriting()
    {
        $this->db->SetRow(0, [['status' => IdempotencyRequestStatus::Success, 'reference_number' => 'REF-123']]);

        $claim = $this->service->TryClaim(self::REQUEST_ID, self::USER_ID);

        $this->assertTrue($claim->AlreadySucceededOutcome());
        $this->assertEquals('REF-123', $claim->ReferenceNumber());
        // Only the read (GetIdempotencyRequestCommand) happened — no insert/reclaim/status
        // write, since replaying an already-succeeded attempt must never touch the row again.
        $this->assertCount(1, $this->db->_Commands);
        $this->assertInstanceOf('GetIdempotencyRequestCommand', $this->db->_Commands[0]);
    }

    public function testReclaimsAFailedRowAndReturnsClaimed()
    {
        $this->db->SetRow(0, [['status' => IdempotencyRequestStatus::Failed, 'reference_number' => null]]);
        $this->db->_AffectedRows = 1;

        $claim = $this->service->TryClaim(self::REQUEST_ID, self::USER_ID);

        $this->assertTrue($claim->WasClaimed());
        $this->assertCount(1, $this->db->GetCommandsOfType('ReclaimIdempotencyRequestCommand'));
    }

    public function testStillProcessingAndNotStaleReturnsInProgress()
    {
        $this->db->SetRow(0, [['status' => IdempotencyRequestStatus::Processing, 'reference_number' => null]]);
        // The reclaim UPDATE's own WHERE clause is what actually decides staleness (it only
        // matches a 'processing' row whose last_modified is over 60s old) — a real, current
        // in-flight request affects 0 rows, which is what this simulates.
        $this->db->_AffectedRows = 0;

        $claim = $this->service->TryClaim(self::REQUEST_ID, self::USER_ID);

        $this->assertTrue($claim->IsInProgress());
        $this->assertFalse($claim->WasClaimed());
        $this->assertFalse($claim->AlreadySucceededOutcome());
    }

    public function testStaleProcessingRowGetsReclaimed()
    {
        $this->db->SetRow(0, [['status' => IdempotencyRequestStatus::Processing, 'reference_number' => null]]);
        // Simulates the reclaim UPDATE's staleness condition matching (last_modified > 60s ago).
        $this->db->_AffectedRows = 1;

        $claim = $this->service->TryClaim(self::REQUEST_ID, self::USER_ID);

        $this->assertTrue($claim->WasClaimed());
    }

    public function testMarkSucceededWritesStatusAndReferenceNumber()
    {
        $this->service->MarkSucceeded(self::REQUEST_ID, self::USER_ID, 'REF-999');

        $commands = $this->db->GetCommandsOfType('UpdateIdempotencyRequestStatusCommand');
        $this->assertCount(1, $commands);
    }

    public function testMarkFailedWritesFailedStatus()
    {
        $this->service->MarkFailed(self::REQUEST_ID, self::USER_ID);

        $commands = $this->db->GetCommandsOfType('UpdateIdempotencyRequestStatusCommand');
        $this->assertCount(1, $commands);
    }
}
