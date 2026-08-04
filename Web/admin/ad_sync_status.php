<?php

define('ROOT_DIR', '../../');

require_once(ROOT_DIR . 'Pages/Admin/AdSyncStatusPage.php');

$page = new AdSyncStatusPage();

$page->PageLoad();
