<?php

define('ROOT_DIR', '../../');
require_once(ROOT_DIR . 'Pages/Ajax/OverviewFullCalendarParticipantSearchPage.php');

$page = new OverviewFullCalendarParticipantSearchPage();
$page->PageLoad();
