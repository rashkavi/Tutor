<?php
// Old per-class recordings page. Class recordings are now managed on one page (tutor/recordings.php):
// ?class-id=<id> opens it on that class, &time-table-id=<id> on one class day. Old links keep working.
$q = [];
if (!empty($_GET['class-id']) && ctype_digit((string) $_GET['class-id'])) {
    $q['class'] = $_GET['class-id'];
}
if (!empty($_GET['time-table-id']) && ctype_digit((string) $_GET['time-table-id'])) {
    $q['day'] = $_GET['time-table-id'];
}
header('Location: ./recordings' . ($q ? '?' . http_build_query($q) : ''), true, 302);
exit;
