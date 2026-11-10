<?php
// Admin → Tutors (docs/ADMIN-PLAN.md): every tutor with plan, subscription status, renewal / trial end, students used and balance.
$ap = [
    'title' => 'All Tutors',
    'menu' => 'Tutors',
    'crumbs' => [['Tutors', null], ['All Tutors', null]],
    'guide' => [
        'intro' => 'Every tutor on ENLIXO Tutor and where their subscription stands.',
        'points' => [
            'Status: Trial, Active, Payment Due (invoice open), Overdue (grace period), Suspended, Complimentary, Cancelled.',
            'Students shows how many students the tutor has against the plan limit.',
            'Balance is what the tutor still owes on open invoices.',
            'Tick tutors and use "Update ticked tutors" to send a payment reminder to many at once.',
        ],
    ],
    'actions' => 'tutors-actions',
    'root' => 'tutors-list',
    'js' => 'app-admin-tutors.js',
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
