<?php
// Admin → Add Tutor (docs/ADMIN-PLAN.md): tutor details, plan and billing, how to start (free trial / paid now / complimentary),
// with a live summary of what will happen. Drawn by assets/js/enlixo/pages/app-admin-add-tutor.js.
$ap = [
    'title' => 'Add Tutor',
    'menu' => 'Add Tutor',
    'crumbs' => [['Tutors', 'tutors'], ['Add Tutor', null]],
    'guide' => [
        'intro' => 'Add a tutor, put them on a plan and start their free trial or first paid month in one go.',
        'points' => [
            'Tutor details: the e-mail address is where the "set your password" link goes.',
            'Plan: choose START, GROW or PRO, monthly or yearly, extra students and add-ons.',
            'How to start: a free trial needs no payment; "Paid from today" creates the first invoice.',
            'The summary on the right shows the price and the dates before you save.',
        ],
    ],
    'actions' => 'add-tutor-actions',
    'root' => 'add-tutor',
    'js' => 'app-admin-add-tutor.js',
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
