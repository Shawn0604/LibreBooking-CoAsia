<?php

declare(strict_types=1);

class FakeResourceLockService implements IResourceLockService
{
    /**
     * @var int[]
     */
    public $_LockedResourceIds = null;

    public $_CommitCalled = false;
    public $_RollbackCalled = false;

    /**
     * @var callable|null Set to simulate LockResources() throwing (e.g. lock timeout).
     */
    public $_ThrowOnLock = null;

    public function LockResources($resourceIds)
    {
        $this->_LockedResourceIds = ResourceLockService::NormalizeResourceIds($resourceIds);

        if ($this->_ThrowOnLock !== null) {
            throw $this->_ThrowOnLock;
        }
    }

    public function Commit()
    {
        $this->_CommitCalled = true;
    }

    public function Rollback()
    {
        $this->_RollbackCalled = true;
    }
}
