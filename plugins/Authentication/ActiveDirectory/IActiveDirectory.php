<?php

interface IActiveDirectory
{
    /**
     * @return bool If connection was successful
     */
    public function Connect();

    /**
     * @return bool If authentication was successful
     */
    public function Authenticate($username, $password);

    /**
     * @return ActiveDirectoryUser The details for the user
     */
    public function GetLdapUser($username);

    /**
     * Used by Jobs/ad-reconcile-account-status.php -- a lighter-weight lookup than
     * GetLdapUser(), fetching only enough to tell whether the account still exists in AD and,
     * if so, whether it's disabled/locked.
     *
     * @return string One of the AdAccountStatus::* constants
     */
    public function GetAccountStatus($username);
}

/**
 * @see IActiveDirectory::GetAccountStatus()
 */
class AdAccountStatus
{
    /** Account exists in AD and is neither disabled nor locked. */
    public const ACTIVE = 'active';
    /** Account exists in AD but is disabled or locked (userAccountControl ACCOUNTDISABLE/LOCKOUT bits). */
    public const DISABLED = 'disabled';
    /** No matching account in AD at all (typically means the employee has left). */
    public const NOT_FOUND = 'not_found';
}
