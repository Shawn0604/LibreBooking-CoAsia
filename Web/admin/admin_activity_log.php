<?php

define('ROOT_DIR', '../../');

require_once(ROOT_DIR . 'Pages/Admin/AdminActivityLogPage.php');

$page = new AdminActivityLogPage();

$page->PageLoad();
