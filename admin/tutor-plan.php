<?php
// Admin → Tutors → a tutor → Manage Plan (a page, no pop-up): give a free trial on a chosen plan, extend the trial,
// change the plan, extra students, custom price, suspend, reactivate, cancel. ?tutor-id=<id>&do=<action>.
// Drawn by assets/js/enlixo/pages/app-admin-tutor-plan.js (GET /api/v1/admin/tutors/{id}, POST .../{action}).
$ap = [
    'title' => 'Manage Plan',
    'menu' => 'All Tutors',
    'crumbs' => [['Tutors', 'tutors'], ['Tutor', null], ['Manage Plan', null]],
    'guide' => [
        'intro' => 'Everything about one tutor\'s subscription, one step at a time.',
        'points' => [
            'Give a Free Trial: choose the plan and the number of days; the tutor gets every feature of that plan until the trial ends, then the first invoice.',
            'Change Plan: upgrades start today (the difference is invoiced), downgrades at the next renewal.',
            'Suspend pauses the tutor, their students and parents; Reactivate switches everything on again.',
            'Every change asks for a reason and is kept in the tutor\'s History.',
        ],
    ],
    'actions' => 'tp-actions',
    'root' => 'tp-page',
    'js' => 'app-admin-tutor-plan.js',
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
