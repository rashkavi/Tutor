<?php
// Tutor → Upgrade Plan: why the next plan is worth it for THIS tutor (what they use, the limit or feature they hit),
// the plans side by side, "Try free for 7 days" (once) and "Upgrade" without a pop-up. Opened from the plan card in
// the menu, every lock badge, every "limit reached" card and the dashboard. ?plan=grow · ?feature=<key> · ?limit=<key>.
// Drawn by assets/js/enlixo/pages/app-tutor-upgrade.js from GET /api/v1/me/access + GET /api/v1/tutor/subscription.
$tp = [
    'title' => 'Upgrade Your Plan',
    'menu' => 'Upgrade',
    'crumbs' => [['Finance', null], ['Upgrade Plan', null]],
    'guide' => [
        'intro' => 'See what you use now, what the next plan adds, and move up when your classes need it.',
        'points' => [
            'Try free for 7 days: STARTER or GROW features for a week, once, with no invoice. Your own plan stays.',
            'Upgrade from FREE: the plan works at once and the invoice is due in 7 days. Not paid? You go back to FREE and nothing is lost.',
            'Upgrade from a paid plan: you pay only the difference for the days left in this period.',
            'Monthly limits (tutes, recordings, notifications) start again on the 1st of every month.',
        ],
    ],
    'actions' => 'up-actions',
    'root' => 'up-page',
    'js' => 'app-tutor-upgrade.js',
    'css' => ['upgrade.css'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
