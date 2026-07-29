<?php

interface IDbConnection
{
    public function Connect();
    public function Disconnect();

    /**
     * Queries the database and returns an IReader
     *
     * @param ISqlCommand $command
     * @return IReader to iterate over
     */
    public function Query(ISqlCommand $command);

    /**
     * @param ISqlCommand $command
     * @param int $limit
     * @param int $offset
     * @return IReader to iterate over
     */
    public function LimitQuery(ISqlCommand $command, $limit, $offset = null);

    /**
     * Executes an alter query against the database
     *
     * @param ISqlCommand $command
     * @return void
     */
    public function Execute(ISqlCommand $command);

    /**
     * @return int last auto-increment id inserted for this connection
     */
    public function GetLastInsertId();

    /**
     * Starts a transaction and keeps the underlying connection open (Disconnect() becomes a
     * no-op) until Commit() or Rollback() is called, so every Query()/Execute() in between
     * runs on the same DB session — required for both transactional consistency and for any
     * row locks (e.g. SELECT ... FOR UPDATE) acquired within it to actually hold.
     */
    public function BeginTransaction();

    /**
     * @return int number of rows affected by the last Execute() on this connection
     *   (mysqli_affected_rows) — used for optimistic-locking UPDATE ... WHERE version = ?
     *   checks, where 0 means someone else already changed the row.
     */
    public function GetAffectedRows();

    public function Commit();

    public function Rollback();

    /**
     * @return bool
     */
    public function InTransaction();
}
