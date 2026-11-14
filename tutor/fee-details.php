<?php
// Tutor → Finance → Student Fees → one fee (like V3's student payment page): the steps, the fee card, the slip, the
// breakdown and the history, with Approve / Reject / Record Payment. ?queue=1 = "Check One by One".
// Drawn by assets/js/enlixo/pages/app-tutor-fee.js from GET /api/v1/tutor/fees/{id} (no SQL on this page).
$tp = [
    'title' => 'Student Fee',
    'menu' => 'Student Fees',
    'crumbs' => [['Finance', null], ['Student Fees', 'fees'], ['Fee', null]],
    'guide' => [
        'intro' => 'One month\'s class fee of one student: what they owe, the slip they uploaded and what happened to it.',
        'points' => [
            'Slip to check: compare the slip with your bank account. Approve gives the receipt and sends it on WhatsApp; Reject tells the student why.',
            'Check One by One: after Approve the next waiting slip opens by itself.',
            'Not paid: Record Payment when the student paid you in cash or at the bank, or send a WhatsApp reminder from Actions.',
            'Everything that happened to this fee is in the history at the bottom.',
        ],
    ],
    'actions' => 'fee-actions',
    'root' => 'fee-page',
    'js' => 'app-tutor-fee.js',
    'css' => ['enlixo-fees.css'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
