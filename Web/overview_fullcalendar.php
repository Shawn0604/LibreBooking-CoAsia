<?php

define('ROOT_DIR', '../');

require_once(ROOT_DIR . 'Pages/OverviewFullCalendarPage.php');

$page = new OverviewFullCalendarPage();
$page->PageLoad();
