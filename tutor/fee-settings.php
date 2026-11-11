<?php
// Tutor → Settings → Fees & Receipts (docs/FEES-PLAN.md): how students pay, due dates and late fees,
// free cards, reminders, receipts, class fees and the activity log. Opened from the gear on Student Fees.
$tp = [
    'title' => 'Fee Settings',
    'menu' => 'Fee Settings',
    'crumbs' => [['Finance', 'fees'], ['Student Fees', 'fees'], ['Fee Settings', null]],
    'guide' => [
        'intro' => 'How your students pay you and what happens when they do not.',
        'points' => [
            'How Students Pay: the bank accounts students see in the app, and whether you take cash or cards.',
            'Due Dates & Late Fees: when a fee is due and the late fee added after it.',
            'Reminders: the WhatsApp message sent before and after the due date.',
            'Every change is kept in the Activity Log.',
        ],
    ],
    'actions' => 'settings-actions',
    'root' => 'settings-page',
    'js' => 'app-tutor-fee-settings.js',
    'css' => ['enlixo-fees.css'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
