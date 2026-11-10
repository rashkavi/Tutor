<?php
// Admin → Finance → Invoices (docs/ADMIN-PLAN.md): every tutor invoice; view, record a payment, remind, void, export.
$ap = [
    'title' => 'Invoices',
    'menu' => 'Invoices',
    'crumbs' => [['Finance', null], ['Invoices', null]],
    'guide' => [
        'intro' => 'Every invoice sent to tutors: plan, extra students, add-ons and what is still open.',
        'points' => [
            'Invoices are made 7 days before a period ends, when a trial is converted and for an upgrade.',
            'Open = waiting for payment. Overdue = past the due date. Void = cancelled with a reason.',
            'View an invoice to record a payment, send a reminder, print it or void it.',
        ],
    ],
    'actions' => 'invoices-actions',
    'root' => 'invoices-list',
    'js' => 'app-admin-invoices.js',
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
