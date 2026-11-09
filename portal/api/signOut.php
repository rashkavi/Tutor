<?php
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
if (SessionUser::ifUserIsStudent()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Student');

    $timezone = new DateTimeZone("Asia/Colombo");
    $dateTime = new DateTime("now", $timezone);
    SessionUser::destroySession('Student');
    header("Location: ../index.php");
} else {
    header("Location: ../index.php");
}
