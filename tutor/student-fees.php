<?php
// Tutor → Students → a student's Fee Card (docs/FEES-PLAN.md): every month of every class, receipts and reminders.
// Drawn by assets/js/enlixo/pages/app-tutor-student-fees.js from GET /api/v1/tutor/students/{id}/fees.
$tp = [
    'title' => 'Fee Card',
    'menu' => 'Student Fees',
    'crumbs' => [['Finance', 'fees'], ['Student Fees', 'fees'], ['Fee Card', null]],
    'guide' => [
        'intro' => 'One student\'s fees in all your classes, like the paper fee card: one box per month.',
        'points' => [
            'Green = paid, orange = slip waiting, red = not paid after the due date, purple = free card.',
            'Click a month to see the fee, the slip and its history.',
            'Record Payment, Free Card / Discount and reminders are in the buttons at the top.',
        ],
    ],
    'actions' => 'card-actions',
    'root' => 'card-page',
    'js' => 'app-tutor-student-fees.js',
    'css' => ['enlixo-fees.css'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
