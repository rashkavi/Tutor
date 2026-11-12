<?php
// Admin → Tutors → Requested Tutors: online sign-up requests (tutor-registration-application.php, migration 013).
// Approve opens Add Tutor filled in from the request; saving it makes the tutor and marks the request approved.
// Drawn by assets/js/enlixo/pages/app-admin-tutor-requests.js from GET /api/v1/admin/tutor-requests.
$ap = [
    'title' => 'Requested Tutors',
    'menu' => 'Requested Tutors',
    'crumbs' => [['Tutors', 'tutors'], ['Requested Tutors', null]],
    'guide' => [
        'intro' => 'Tutors who asked to join from the website. Call them, then approve or reject the request.',
        'points' => [
            'Pending requests wait for you. Call or WhatsApp the tutor before you approve.',
            'Approve opens Add Tutor with the details and the plan they picked. Choose the trial or the start and save.',
            'Reject asks for a reason. Only admins see it, and a rejected request can be opened again.',
            'The tutor gets an e-mail when the request is received and when the account is ready.',
        ],
    ],
    'actions' => 'requests-actions',
    'root' => 'requests-page',
    'js' => 'app-admin-tutor-requests.js',
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
