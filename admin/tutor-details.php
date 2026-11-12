<?php
// Admin → Tutor details (docs/ADMIN-PLAN.md): profile card, subscription card with the plan actions,
// and tabs Invoices / Payments / History. Drawn by assets/js/enlixo/pages/app-admin-tutor-details.js.
$ap = [
    'title' => 'Tutor Details',
    'menu' => 'Tutor Details',
    'crumbs' => [['Tutors', 'tutors'], ['All Tutors', 'tutors'], ['Tutor Details', null]],
    'guide' => [
        'intro' => 'One tutor: who they are, what they pay for, what they owe and everything that changed.',
        'points' => [
            'Subscription: plan, status, billing period or trial end, students used, add-ons.',
            'Manage Plan changes the plan, extends a trial, adds students, sets a custom price, suspends or cancels. Every change asks for a reason and is kept in History.',
            'Invoices and Payments are the same lists the tutor sees on their Billing page.',
        ],
    ],
    'actions' => 'tutor-actions',
    'root' => 'tutor-details',
    'js' => 'app-admin-tutor-details.js',
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
