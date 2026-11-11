<?php
// Admin → Plans & Billing (docs/ADMIN-PLAN.md): Plans & Prices, Add-ons, Trial & Billing Rules, Bank Accounts (settings layout).
$ap = [
    'title' => 'Plans & Billing',
    'menu' => 'Plans',
    'crumbs' => [['Plans & Billing', null]],
    'guide' => [
        'intro' => 'Prices, add-ons and the rules every tutor subscription follows.',
        'points' => [
            'Plans & Prices: monthly price, yearly discount, student limit and extra student packs of START, GROW and PRO.',
            'Trial & Billing Rules: trial length, when invoices are made, grace period, reminders and tax.',
            'A price change applies from each tutor\'s next invoice. Tutors with a custom price keep it.',
        ],
    ],
    'actions' => 'plans-actions',
    'root' => 'plans-page',
    'js' => 'app-admin-plans.js',
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
