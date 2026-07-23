<?php

define('ROOT_DIR', '../');

require_once(ROOT_DIR . 'Pages/MyMeetingsPage.php');

$page = new MyMeetingsPage();
$page->PageLoad();
