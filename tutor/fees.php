<?php
// Tutor → Finance → Student Fees (docs/FEES-PLAN.md): this month's fees, slips to check, unpaid students, receipts.
// Drawn by assets/js/enlixo/pages/app-tutor-fees.js from GET /api/v1/tutor/fees/... (no SQL on this page).
$tp = [
    'title' => 'Student Fees',
    'menu' => 'Student Fees',
    'crumbs' => [['Finance', null], ['Student Fees', null]],
    'guide' => [
        'intro' => 'Every class fee of your students, month by month: who paid, whose slip is waiting and who still owes.',
        'points' => [
            'Fees are created on the 1st of each month for every active student in a class with a monthly fee.',
            'Slips to Check: compare the slip with your bank account, then Approve or Reject. "Check One by One" opens the next slip after each one.',
            'Record Payment when a student pays you in cash, at the bank or by card. The receipt number is given at once.',
            'Unpaid lists the students who did not pay after the due date. Send them a WhatsApp reminder from there.',
        ],
    ],
    'actions' => 'fees-actions',
    'root' => 'fees-page',
    'js' => 'app-tutor-fees.js',
    'css' => ['enlixo-fees.css'],
    'scripts' => ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
