<?php
// The recordings of one class now live on the Class Recordings page (portal/recordings.php?class=<id>).
$classId = (int) ($_GET['class-id'] ?? 0);
header('Location: ./recordings' . ($classId > 0 ? '?class=' . $classId : ''), true, 301);
exit;
