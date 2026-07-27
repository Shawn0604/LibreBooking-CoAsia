<?php

define('ROOT_DIR', '../../');
require_once(ROOT_DIR . 'Pages/Ajax/OverviewFullCalendarEventsPage.php');

$page = new OverviewFullCalendarEventsPage();
$page->PageLoad();
