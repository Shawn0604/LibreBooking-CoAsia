<?php

require_once(ROOT_DIR . 'Pages/Admin/AdminPage.php');

/**
 * "Admin 操作紀錄" -- shell page for a future admin action audit log. No admin page/action in this
 * codebase currently records who changed what -- this page exists so the nav entry has somewhere
 * real to go, and shows an explicit empty state rather than fabricating data.
 */
class AdminActivityLogPage extends AdminPage
{
    public function __construct()
    {
        parent::__construct('AdminActivityLog');
    }

    public function PageLoad()
    {
        $this->Display('Configuration/admin_activity_log.tpl');
    }
}
