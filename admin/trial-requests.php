<?php
// Admin → Tutors → Trial Requests: tutors who asked to try STARTER or GROW free for 7 days (tutor Upgrade page).
// Approve starts the trial at once (PlatformBilling 'preview'); Reject asks for a reason the tutor sees.
// Drawn by assets/js/enlixo/pages/app-admin-trial-requests.js from GET /api/v1/admin/trial-requests (migration 020).
$ap = [
    'title' => 'Trial Requests',
    'menu' => 'Trial Requests',
    'crumbs' => [['Tutors', 'tutors'], ['Trial Requests', null]],
    'guide' => [
        'intro' => 'Tutors who asked to try a bigger plan free. The trial starts only when you approve it.',
        'points' => [
            'Approve starts the trial now, for 7 days unless you choose another length (1 to 14 days).',
            'During the trial the tutor has every feature of that plan; their own plan and invoices do not change.',
            'Reject asks for a reason. The tutor sees it on their Upgrade page and can ask again or upgrade.',
            'A tutor can have one waiting request, and withdraw it while it waits.',
        ],
    ],
    'actions' => 'trial-actions',
    'root' => 'trial-page',
    'js' => 'app-admin-trial-requests.js',
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
