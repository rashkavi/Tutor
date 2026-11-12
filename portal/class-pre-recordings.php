<?php
// The pre-recorded classes of one class now live on portal/pre-recorded-classes.php?class=<id>.
$classId = (int) ($_GET['class-id'] ?? 0);
header('Location: ./pre-recorded-classes' . ($classId > 0 ? '?class=' . $classId : ''), true, 301);
exit;
