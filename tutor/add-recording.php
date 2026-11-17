<?php
// Tutor → Class Recordings → Add Recording / Edit Recording: its own page (it used to be a pop-up on Class Recordings).
//   add-recording                         choose the class and the class day
//   add-recording?class=<id>              that class chosen (also ?class-id= from older links)
//   add-recording?time-table-id=<id>      that class day chosen (Time Table, class day details)
//   add-recording?id=<recording id>       edit (edit-recording?recording-id= forwards here)
// Drawn by app-tutor-recording-form.js with the shared form of recording-form.js (POST / PUT /api/v1/tutor/recordings).
require_once __DIR__ . '/../includes/connection.php';
$rfId = ctype_digit((string) ($_GET['id'] ?? '')) ? (int) $_GET['id'] : 0;
$rfDay = (int) ($_GET['time-table-id'] ?? 0);
$rfClass = (int) ($_GET['class'] ?? $_GET['class-id'] ?? 0);
if ($rfDay > 0 && $rfClass === 0) {
    // the class of that class day (the API checks again that it is this tutor's when the recording is saved)
    $r = Database::search("SELECT `classes_class_id` FROM `time_table` WHERE `time_table_id` = '" . $rfDay . "' LIMIT 1");
    $rfClass = (int) (($r ? $r->fetch_assoc() : null)['classes_class_id'] ?? 0);
}
$rfTitle = $rfId ? 'Edit Recording' : 'Add Recording';
$tp = [
    'title' => $rfTitle,
    'menu' => 'Class Recordings',
    'crumbs' => [['Class Recordings', 'recordings'], [$rfTitle, null]],
    'guide' => [
        'intro' => 'Share the video of a class day with the students of that class.',
        'points' => [
            'Choose the class and the class day the video is from. Only days that have started are listed.',
            'Choose where the video is kept and paste its link. The link is checked before it is saved.',
            'Opens on: students see it from that day and time. Available until (GROW): it closes after that day.',
            'The places a video can come from are set in Settings → Recording Types.',
        ],
    ],
    'actions' => 'rf-actions',
    'root' => 'rf-page',
    'attrs' => 'data-id="' . $rfId . '" data-class="' . $rfClass . '" data-day="' . $rfDay . '"',
    'js' => 'app-tutor-recording-form.js',
    'css' => ['enlixo-fees.css', 'enlixo-recordings.css'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
