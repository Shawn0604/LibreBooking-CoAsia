<?php

define('ROOT_DIR', '../../');

require_once(ROOT_DIR . 'Pages/Ajax/NotificationPreferencesSavePage.php');

$page = new NotificationPreferencesSavePage();
$page->PageLoad();
