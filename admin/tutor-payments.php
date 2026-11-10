<?php
// Admin → Finance → Payments (docs/ADMIN-PLAN.md): bank slips waiting for approval and every tutor payment.
$ap = [
    'title' => 'Tutor Payments',
    'menu' => 'Payments',
    'crumbs' => [['Finance', null], ['Payments', null]],
    'guide' => [
        'intro' => 'Money received from tutors. Bank slips wait here until someone checks them.',
        'points' => [
            'Check a slip against the bank account, then Approve: the invoice is paid and the tutor becomes Active.',
            'Reject with a reason: the tutor sees it on their Billing page and can upload again.',
            'Cash and cheque payments are recorded from the invoice (Record Payment).',
        ],
    ],
    'actions' => 'payments-actions',
    'root' => 'payments-list',
    'js' => 'app-admin-payments.js',
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
