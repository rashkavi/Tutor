<?php
// Tutor → Payment pending: while an ENLIXO invoice is due or overdue and the tutor's slip is waiting for ENLIXO to
// check it, every page sends the tutor here (tutor/includes/side-menu.php). Shows the payment that was sent, what
// happens next and how to reach ENLIXO. Drawn by assets/js/enlixo/pages/app-tutor-payment-pending.js.
$tp = [
    'title' => 'Payment Being Checked',
    'menu' => 'My Subscription',
    'crumbs' => [['Finance', null], ['Payment Being Checked', null]],
    'guide' => [
        'intro' => 'Your payment slip was received. ENLIXO checks it against the bank, usually within one working day.',
        'points' => [
            'While it is being checked, the rest of the app is closed to you; your students and parents can still use the portal.',
            'Once ENLIXO approves the slip, everything opens again by itself. Reload this page to see it.',
            'If the slip is rejected, the reason is shown on the invoice page and you can upload a new one.',
            'Need it sooner? WhatsApp or call ENLIXO from this page.',
        ],
    ],
    'actions' => 'pp-actions',
    'root' => 'pp-page',
    'js' => 'app-tutor-payment-pending.js',
    'css' => ['messages.css'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
