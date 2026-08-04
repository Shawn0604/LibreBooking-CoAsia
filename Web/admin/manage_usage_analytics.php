<?php

define('ROOT_DIR', '../../');

require_once(ROOT_DIR . 'Pages/Admin/ManageUsageAnalyticsPage.php');

$page = new ManageUsageAnalyticsPage();

$page->PageLoad();
