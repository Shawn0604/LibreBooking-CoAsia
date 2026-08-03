<?php

define('ROOT_DIR', '../../');

require_once(ROOT_DIR . 'Pages/Admin/ManageOverviewPage.php');

$page = new ManageOverviewPage();

$page->PageLoad();
