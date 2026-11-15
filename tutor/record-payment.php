<?php
// Tutor → Finance → Student Fees → Record Payment (a page, no pop-up): cash, bank deposit or card the tutor took.
// ?student-id=<id>&months=<fee ids> starts with that student and those months ticked. Save opens the receipt page.
// Drawn by assets/js/enlixo/pages/app-tutor-record-payment.js (fees-common.js mountRecordPayment).
$tp = [
    'title' => 'Record a Payment',
    'menu' => 'Student Fees',
    'crumbs' => [['Finance', null], ['Student Fees', 'fees'], ['Record a Payment', null]],
    'guide' => [
        'intro' => 'Money a student paid you directly: in cash, at the bank or by card. You get a receipt number at once.',
        'points' => [
            'Choose the student, tick every month this money is for, then how they paid.',
            'A bank deposit needs the bank reference; a cash payment can have your receipt book number.',
            'Save & Give Receipt opens the receipt to print or send on WhatsApp.',
        ],
    ],
    'actions' => 'rp-actions',
    'root' => 'rp-page',
    'js' => 'app-tutor-record-payment.js',
    'css' => ['enlixo-fees.css'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
