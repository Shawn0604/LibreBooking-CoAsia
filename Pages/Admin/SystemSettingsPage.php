<?php

require_once(ROOT_DIR . 'Pages/Admin/AdminPage.php');

/**
 * "系統設定" -- a plain hub page linking out to the 5 system-level admin pages (notification
 * emails, AD sync, custom attributes, diagnostics, data cleanup). One of 管理總覽's 5 entry tiles;
 * this page has no content of its own beyond that list, matching the app's "no persistent admin
 * nav chrome" rule -- each of these 5 destinations is otherwise reached only from here.
 */
class SystemSettingsPage extends AdminPage
{
    public function __construct()
    {
        parent::__construct('AdminSystemSettings');
    }

    public function PageLoad()
    {
        $this->Display('Configuration/system_settings.tpl');
    }
}
