<?php
// Time Table (parent): the classes of every child by week or month, phone first. Same page as the student Time Table
// (portal/includes/time-table-page.php + portal/js/time-table.js); data: parent/api/getTimeTableData (own children only).
// A class opens parent/class-day.
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsParent()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Parent');
$ttClass = null;
$ttFrame = [
    'menu' => 'Time Table',
    'side' => __DIR__ . '/includes/side-menu.php',
    'header' => dirname(__DIR__) . '/includes/header.php',
    'js' => ['../portal/js/time-table.js'],
];
$ttDetails = 'class-day?time-table-id=';
$ttIntro = "See your children's classes by week or month. Tap a class to see who attended and the recording.";
include dirname(__DIR__) . '/portal/includes/time-table-page.php';
