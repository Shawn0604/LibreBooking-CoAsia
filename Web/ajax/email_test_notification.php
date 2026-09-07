<?php

define('ROOT_DIR', '../../');

require_once(ROOT_DIR . 'Pages/Ajax/EmailTestNotificationPage.php');

$page = new EmailTestNotificationPage();
$page->PageLoad();
