<?php
// Old edit page. Recordings are edited in the dialog of tutor/recordings.php (?edit=<id> opens it).
$id = (string) ($_GET['recording-id'] ?? '');
header('Location: ./recordings' . (ctype_digit($id) ? '?edit=' . $id : ''), true, 302);
exit;
