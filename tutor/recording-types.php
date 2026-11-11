<?php
// Tutor → Settings → Recording Types (like ENLIXO V3 Settings → Recording Types): the places class recordings can be
// added from (YouTube, Bunny Stream, Zoom, Google Drive ...). Each type checks the pasted link, so a wrong link is
// stopped before it is saved. Opened from the gear on Class Recordings. Drawn by app-tutor-recording-types.js.
$tp = [
    'title' => 'Recording Types',
    'menu' => 'Recording Types',
    'crumbs' => [['Settings', null], ['Recording Types', null]],
    'guide' => [
        'intro' => 'Choose where your class recordings can come from.',
        'points' => [
            'Switch a type on to offer it when you add a recording; switch it off to hide it. Recordings already added keep working.',
            'Drag a type by its handle to change the order of the list.',
            'Plays in the page: students watch it inside ENLIXO. Opens in a new tab: the website (for example Zoom) does not allow that.',
            'A type that recordings already use cannot be deleted, only switched off.',
            'Every change is kept in the Activity Log.',
        ],
    ],
    'actions' => 'types-actions',
    'root' => 'recording-types-page',
    'js' => 'app-tutor-recording-types.js',
    'css' => ['enlixo-fees.css', 'enlixo-recordings.css'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
