<?php

require_once(ROOT_DIR . 'Pages/Admin/AdminPage.php');

/**
 * "通知紀錄" -- shell page for a future notification (Email/LINE) send log. There is no
 * database table or instrumentation recording send attempts yet (LineService/email senders only
 * write to the application log today) -- this page exists so the nav entry has somewhere real to
 * go, and shows an explicit empty state rather than fabricating data.
 */
class NotificationLogPage extends AdminPage
{
    public function __construct()
    {
        parent::__construct('AdminNotificationLog');
    }

    public function PageLoad()
    {
        $this->Display('Configuration/notification_log.tpl');
    }
}
