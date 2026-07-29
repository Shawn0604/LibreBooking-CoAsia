<?php

define('ROOT_DIR', '../../');

require_once(ROOT_DIR . 'Pages/Ajax/OverviewFullCalendarReservationPage.php');

$page = new OverviewFullCalendarReservationPage();
$page->PageLoad();
