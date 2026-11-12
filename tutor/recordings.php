<?php
// Tutor → Academic → Class Recordings: every recording of every class (?class=<id> shows one class), with stat
// cards, search, filters, and add / edit / watch / delete. Links are checked against Settings → Recording Types.
// Drawn by app-tutor-recordings.js (GET /api/v1/tutor/recordings, /tutor/recordings/overview).
$tp = [
    'title' => 'Class Recordings',
    'menu' => 'Class Recordings',
    'crumbs' => [['Academic', null], ['Class Recordings', null]],
    'guide' => [
        'intro' => 'The recordings of your class days, for students who missed a class or want to watch it again.',
        'points' => [
            'Add Recording: pick the class and the class day, choose where the video is kept and paste its link. The link is checked before it is saved.',
            'Opens on: students see the recording from that day and time. Available until (PRO): it closes after that day.',
            'Watched: how many students opened the recording.',
            'The places a recording can come from are set in Settings → Recording Types (the gear).',
        ],
    ],
    'actions' => 'recordings-actions',
    'root' => 'recordings-page',
    'js' => 'app-tutor-recordings.js',
    'css' => ['enlixo-fees.css', 'enlixo-recordings.css'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
