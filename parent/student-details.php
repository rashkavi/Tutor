<?php
// Parents do not edit a student's record; the old page (a copy of the tutor's edit form) now opens Child Progress.
require_once '../includes/UserClass.php';
$child = (int) ($_GET['student-id'] ?? 0);
header('Location: ./child-progress' . ($child > 0 ? '?child-id=' . $child : ''), true, 302);
