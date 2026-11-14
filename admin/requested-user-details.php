<?php
// Admin → Tutors → Requested Tutors → one request (like V3's requested student page).
// Drawn by assets/js/enlixo/pages/app-admin-tutor-request.js from GET /api/v1/admin/tutor-requests/{id}.
$ap = [
    'title' => 'Tutor Request',
    'menu' => 'Requested Tutors',
    'crumbs' => [['Tutors', 'tutors'], ['Requested Tutors', 'requested-users'], ['Request', null]],
    'guide' => [
        'intro' => 'Everything the tutor wrote on the sign-up form, and what the team did with it.',
        'points' => [
            'Call or WhatsApp the tutor first, then use Actions → Mark as Called and write what you talked about.',
            'Approve opens Add Tutor with the details, the plan and any offer code filled in.',
            'Reject needs a reason; only admins see it. A rejected request can be opened again.',
            'Every call, note and decision is kept in the history at the bottom.',
        ],
    ],
    'actions' => 'request-actions',
    'root' => 'request-page',
    'js' => 'app-admin-tutor-request.js',
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
