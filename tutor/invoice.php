<?php
// Tutor → My Subscription → one invoice (a page, no pop-up): what it is for and, while it is open, how to pay it
// (ENLIXO's bank accounts + upload the slip). ?invoice-id=<id>. Drawn by assets/js/enlixo/pages/app-tutor-invoice.js
// from GET /api/v1/tutor/subscription; the slip goes to POST /api/v1/tutor/subscription/payments.
$tp = [
    'title' => 'Invoice',
    'menu' => 'My Subscription',
    'crumbs' => [['Finance', null], ['My Subscription', 'subscription'], ['Invoice', null]],
    'guide' => [
        'intro' => 'One ENLIXO invoice of your plan: the period, what it is for and how to pay it.',
        'points' => [
            'Pay into one of the ENLIXO bank accounts shown, then upload a photo or PDF of the slip.',
            'ENLIXO checks the slip within one working day; your account stays on while it is checked.',
            'Print gives the invoice on paper.',
        ],
    ],
    'actions' => 'inv-actions',
    'root' => 'inv-page',
    'js' => 'app-tutor-invoice.js',
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
