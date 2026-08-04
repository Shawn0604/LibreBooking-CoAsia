<?php

require_once(ROOT_DIR . 'Pages/Admin/AdminPage.php');

/**
 * "AD 同步" -- shell page for future AD/LDAP sync run history. The existing AD/LDAP plugins
 * (plugins/Authentication/ActiveDirectory, plugins/Authentication/Ldap) only authenticate at
 * login time -- there is no scheduled sync job and no table recording sync runs yet. This page
 * exists so the nav entry has somewhere real to go, and shows an explicit empty state rather
 * than fabricating data.
 */
class AdSyncStatusPage extends AdminPage
{
    public function __construct()
    {
        parent::__construct('AdminAdSyncStatus');
    }

    public function PageLoad()
    {
        $this->Display('Configuration/ad_sync_status.tpl');
    }
}
