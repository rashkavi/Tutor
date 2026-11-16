<?php
// Tutor → Message Center → one message (?id=): sends it batch by batch with a progress bar, or, for WhatsApp from
// the tutor's phone, one "Open WhatsApp" button per person. Then who got it, who did not and why.
// Drawn by assets/js/enlixo/pages/app-tutor-message.js.
$tp = [
    'title' => 'Message',
    'menu' => 'Message Center',
    'crumbs' => [['Communication', null], ['Message Center', 'message-center'], ['Message', null]],
    'guide' => [
        'intro' => 'One message and every person it goes to.',
        'points' => [
            'E-mail and connected WhatsApp: keep this page open until the bar reaches 100%.',
            'WhatsApp from your phone: press Open WhatsApp for each person; WhatsApp opens with the message ready, you press Send there.',
            'Failed: press Try Failed Again, or fix the e-mail / number of the student and send a new message.',
        ],
    ],
    'actions' => 'msg-actions',
    'root' => 'msg-page',
    'js' => 'app-tutor-message.js',
    'css' => ['messages.css'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
