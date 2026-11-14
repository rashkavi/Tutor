<?php
// Admin → Finance → Payments → one tutor payment (like V3's student payment page): steps, the payment card, the slip,
// the invoice it pays and the tutor's billing history, with Approve / Reject.
// Drawn by assets/js/enlixo/pages/app-admin-payment.js from GET /api/v1/admin/payments/{id}.
$ap = [
    'title' => 'Tutor Payment',
    'menu' => 'Payments',
    'crumbs' => [['Finance', null], ['Payments', 'tutor-payments'], ['Payment', null]],
    'guide' => [
        'intro' => 'One payment from a tutor: the slip they uploaded (or what ENLIXO recorded), the invoice it pays and what happened.',
        'points' => [
            'Check the slip against the ENLIXO bank account, then Approve. The invoice is marked paid and the tutor becomes Active.',
            'Reject asks for a reason; the tutor sees it on their Billing page and can upload the slip again.',
            'The invoice on the right shows what this payment is for and what is still to pay.',
            'The tutor\'s billing history is at the bottom.',
        ],
    ],
    'actions' => 'payment-actions',
    'root' => 'payment-page',
    'js' => 'app-admin-payment.js',
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
