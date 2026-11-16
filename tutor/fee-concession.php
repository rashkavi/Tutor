<?php
// Tutor → a student's Fee Card → Free Card / Discount (a page, no pop-up). ?student-id=<id>
// Drawn by assets/js/enlixo/pages/app-tutor-fee-concession.js (fees-common.js mountConcession).
$tp = [
    'title' => 'Free Card / Discount',
    'menu' => 'Student Fees',
    'crumbs' => [['Finance', null], ['Student Fees', 'fees'], ['Free Card / Discount', null]],
    'guide' => [
        'intro' => 'Give one student a free card, a half card or a discount in one class, from a month you choose.',
        'points' => ['Fees already paid do not change.', 'The reason is kept on the student\'s Fee Card.'],
    ],
    'actions' => 'cc-actions',
    'root' => 'cc-page',
    'js' => 'app-tutor-fee-concession.js',
    'css' => ['enlixo-fees.css'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
