<?php

require_once(ROOT_DIR . 'lib/Database/namespace.php');

/**
 * P0 concurrency protection (see COASIA-CHANGES.md) — serializes any write that could
 * conflict over the same resource/time slot. LockResources() opens a DB transaction and
 * takes exclusive row locks (SELECT ... FOR UPDATE) on the `resources` table rows for every
 * resource id involved, in ascending id order, so two requests touching overlapping resource
 * sets always attempt their locks in the same order and can't deadlock each other. The
 * transaction stays open — every Database::Query()/Execute() the caller runs afterward (the
 * re-validation, then the persist) happens on the same DB session as the lock, which is what
 * makes the lock actually protect anything; see MySqlConnection::Disconnect()'s in-transaction
 * guard, which is what keeps that session alive across those calls instead of the
 * connect/disconnect-per-query pattern the rest of the app uses.
 */
interface IResourceLockService
{
    /**
     * @param int[] $resourceIds
     */
    public function LockResources($resourceIds);

    public function Commit();

    public function Rollback();
}

class LockResourcesCommand extends SqlCommand
{
    /**
     * @param int[] $resourceIds non-empty, already deduped/sorted
     */
    public function __construct($resourceIds)
    {
        parent::__construct('SELECT resource_id FROM resources WHERE resource_id IN (@resourceid) ORDER BY resource_id FOR UPDATE');
        $this->AddParameter(new Parameter(ParameterNames::RESOURCE_ID, $resourceIds));
    }
}

class ResourceLockService implements IResourceLockService
{
    /**
     * @var Database
     */
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function LockResources($resourceIds)
    {
        $ids = self::NormalizeResourceIds($resourceIds);

        $this->db->BeginTransaction();

        // A reservation always has at least one resource by the time it reaches this point
        // (ReservationSeries::Create() requires a primary resource), but stay defensive
        // rather than run a syntactically invalid "IN ()" — an empty set just means there's
        // nothing to serialize against; the transaction is still open for the caller.
        if (empty($ids)) {
            return;
        }

        $this->db->Query(new LockResourcesCommand($ids));
    }

    public function Commit()
    {
        $this->db->Commit();
    }

    public function Rollback()
    {
        $this->db->Rollback();
    }

    /**
     * Dedupe, cast to int, and sort ascending — a fixed lock-acquisition order across every
     * caller is what prevents deadlock when two requests lock overlapping multi-resource sets
     * in different orders (classic lock-ordering deadlock).
     *
     * @param int[] $resourceIds
     * @return int[]
     */
    public static function NormalizeResourceIds($resourceIds)
    {
        $ids = array_values(array_unique(array_map('intval', $resourceIds)));
        sort($ids, SORT_NUMERIC);
        return $ids;
    }
}
