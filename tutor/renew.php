<?php
// Tutor → Renew (every page sends a tutor here while their ENLIXO subscription is suspended or cancelled):
// what happened, what it means for their students, and how to switch everything on again (pay the open invoice,
// or ask ENLIXO). Phone first. Drawn by assets/js/enlixo/pages/app-tutor-renew.js from GET /api/v1/tutor/subscription.
$tp = [
    'title' => 'Renew Your Subscription',
    'menu' => 'My Subscription',
    'crumbs' => [['Finance', null], ['Renew', null]],
    'guide' => [
        'intro' => 'Your ENLIXO subscription is paused. Pay the open invoice and everything switches on again.',
        'points' => [
            'While paused, your students and parents cannot sign in. Nothing is deleted.',
            'Pay into an ENLIXO bank account and upload the slip on the invoice page; ENLIXO checks it within one working day.',
            'Need help or a different plan? WhatsApp or call ENLIXO from this page.',
        ],
    ],
    'actions' => 'rn-actions',
    'root' => 'rn-page',
    'js' => 'app-tutor-renew.js',
    'css' => ['messages.css'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
