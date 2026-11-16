<?php
// Tutor → Communication → Message Center: write one message to the students and / or parents of chosen classes and
// send it by e-mail or WhatsApp. Drawn by assets/js/enlixo/pages/app-tutor-messages.js (API: /api/v1/tutor/messages*,
// src/Messaging/MessageService.php). Sending opens the message page (tutor/message.php), never a pop-up.
$tp = [
    'title' => 'Message Center',
    'menu' => 'Message Center',
    'crumbs' => [['Communication', null], ['Message Center', null]],
    'guide' => [
        'intro' => 'Send a notice to your students and parents by e-mail or WhatsApp: a cancelled class, a new time, an exam date, a fee reminder.',
        'points' => [
            'Choose E-mail or WhatsApp, the classes, and whether students, parents or both get it.',
            'Write once; {name}, {class} and {tutor} are filled in for every person.',
            'WhatsApp: send from your own phone (free, nothing to set up), or connect your WhatsApp Business number in WhatsApp Settings so messages go out by themselves.',
            'Everything you send is listed under Sent Messages with who got it.',
        ],
    ],
    'actions' => 'mc-actions',
    'root' => 'mc-page',
    'js' => 'app-tutor-messages.js',
    'css' => ['messages.css'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
