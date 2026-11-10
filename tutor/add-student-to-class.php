<?php
// The old "Add student to class" form was replaced by the one Add New Student page (the student form,
// with the class already picked). Old links and bookmarks land there.
$classId = isset($_GET['class-id']) ? (string) (int) $_GET['class-id'] : '';
header('Location: ./add-student' . ($classId !== '' && $classId !== '0' ? '?class=' . $classId : ''), true, 301);
exit;
