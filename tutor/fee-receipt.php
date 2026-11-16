<?php
// Tutor → Finance → Student Fees → a receipt (a page, no pop-up): print it or send it on WhatsApp. ?receipt-id=<id>
// Drawn by assets/js/enlixo/pages/app-tutor-fee-receipt.js (fees-common.js mountReceipt).
$tp = [
    'title' => 'Receipt',
    'menu' => 'Student Fees',
    'crumbs' => [['Finance', null], ['Student Fees', 'fees'], ['Receipt', null]],
    'guide' => [
        'intro' => 'The receipt of a payment, the same on screen, on paper and on WhatsApp.',
        'points' => ['Print prints only the receipt.', 'Send on WhatsApp opens WhatsApp with the receipt details for the student.'],
    ],
    'actions' => 'rc-actions',
    'root' => 'rc-page',
    'js' => 'app-tutor-fee-receipt.js',
    'css' => ['enlixo-fees.css'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
