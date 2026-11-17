<?php
// Edit Recording is the same page as Add Recording now: add-recording?id=<recording id>.
$id = (string) ($_GET['recording-id'] ?? $_GET['id'] ?? '');
header('Location: ./' . (ctype_digit($id) ? 'add-recording?id=' . $id : 'recordings'), true, 302);
