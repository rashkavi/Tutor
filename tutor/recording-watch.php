<?php
// Tutor → Class Recordings → one recording → Who Watched: every student of the class, the minutes watched, how many
// times they opened it and when last; on GROW the tutor gives a student extra watch time here (no pop-up).
// ?recording-id=<id>. Drawn by assets/js/enlixo/pages/app-tutor-recording-watch.js.
$tp = [
    'title' => 'Who Watched',
    'menu' => 'Class Recordings',
    'crumbs' => [['Academic', null], ['Class Recordings', 'recordings'], ['Who Watched', null]],
    'guide' => [
        'intro' => 'See which students watched this recording and for how long.',
        'points' => [
            'Watched: the minutes played across every time the student opened it; % is against the length of the recording.',
            'Not opened: students of the class who have not watched it yet. Send them a reminder from Message Center.',
            'Give extra time (GROW): when a student used up their watch time, add minutes for that student only.',
            'A recording of a class day opens for a student once that month\'s fee is paid (classes that take fees in the app).',
        ],
    ],
    'actions' => 'rw-actions',
    'root' => 'rw-page',
    'js' => 'app-tutor-recording-watch.js',
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
