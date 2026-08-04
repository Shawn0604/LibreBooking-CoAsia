<?php

define('ROOT_DIR', '../../');

require_once(ROOT_DIR . 'Pages/Admin/NotificationLogPage.php');

$page = new NotificationLogPage();

$page->PageLoad();
