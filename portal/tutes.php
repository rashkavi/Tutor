<?php
// Student → Tutes: every tute of the student's active classes (?class=<id> one class), cards like Class Recordings.
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Student');
$tpClass = null;
include __DIR__ . '/includes/tutes-page.php';
