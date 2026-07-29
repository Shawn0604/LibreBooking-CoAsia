<?php

/**
 * Test-only decorator: sleeps for $delayMs right after acquiring the real resource lock, while
 * still holding the transaction open. Used to force a genuine, observable overlap window
 * between two independently-connected worker processes in tests/concurrency/ — without this,
 * two real OS processes racing for the same lock might happen to never actually overlap,
 * making a "does the lock work" test flaky/meaningless instead of a reliable proof.
 */
class SlowResourceLockService implements IResourceLockService
{
    /**
     * @var IResourceLockService
     */
    private $inner;

    private $delayMs;

    public function __construct(IResourceLockService $inner, $delayMs)
    {
        $this->inner = $inner;
        $this->delayMs = $delayMs;
    }

    public function LockResources($resourceIds)
    {
        $this->inner->LockResources($resourceIds);
        usleep($this->delayMs * 1000);
    }

    public function Commit()
    {
        $this->inner->Commit();
    }

    public function Rollback()
    {
        $this->inner->Rollback();
    }
}
