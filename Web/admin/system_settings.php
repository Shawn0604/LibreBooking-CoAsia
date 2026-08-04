<?php

define('ROOT_DIR', '../../');

require_once(ROOT_DIR . 'Pages/Admin/SystemSettingsPage.php');

$page = new SystemSettingsPage();

$page->PageLoad();
