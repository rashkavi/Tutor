<?php
// Admin → Tutors → Share Registration Link (a page, it was a pop-up): the online sign-up link for new tutors, one per
// plan, to copy or send on WhatsApp / e-mail. Drawn by assets/js/enlixo/pages/app-admin-share-link.js.
$ap = [
    'title' => 'Share Registration Link',
    'menu' => 'Requested Tutors',
    'crumbs' => [['Tutors', null], ['Requested Tutors', 'requested-users'], ['Share Registration Link', null]],
    'guide' => [
        'intro' => 'Send a tutor the link to the online sign-up form. Their request comes to Requested Tutors.',
        'points' => [
            'The general link lets the tutor pick a plan; a plan link opens the form with that plan chosen.',
            'Copy a link, or send it straight on WhatsApp or by e-mail.',
            'The sign-up form works in Sinhala, Tamil and English on a phone.',
        ],
    ],
    'actions' => 'sl-actions',
    'root' => 'sl-page',
    'js' => 'app-admin-share-link.js',
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
