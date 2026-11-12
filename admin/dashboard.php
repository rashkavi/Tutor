<?php
// Admin dashboard (docs/ADMIN-PLAN.md): stat cards, revenue and tutor charts, Waiting for you, recent payments,
// trials ending and tutors near their student limit. Drawn by assets/js/enlixo/pages/app-admin-dashboard.js.
require_once '../includes/UserClass.php';
if (SessionUser::ifUserIsAdmin()) {
    // The old dashboard ran the class fee / penalty jobs on each visit; keep them running.
    // A failing job must not stop the dashboard from opening.
    try {
        require_once '../includes/connection.php';
        require_once './api/automatedClassFee.php';
        require_once './api/automatedPaneltyPayment.php';
    } catch (\Throwable $e) {
        error_log('Admin dashboard jobs: ' . $e->getMessage());
    }
}
// The one-time "just signed in" cookie from the sign-in API: show the welcome card once, then forget it.
$justSignedIn = isset($_COOKIE['enviiz_lms_admin_login']);
if ($justSignedIn) {
    setcookie('enviiz_lms_admin_login', '', time() - 3600, '/');
}
$adminUser = SessionUser::getCurrentUserObject('Admin') ?? [];
$ap = [
    'title' => 'Dashboard',
    'menu' => 'Dashboard',
    'crumbs' => [['Dashboard', null]],
    'guide' => [
        'intro' => 'How ENLIXO Tutor is doing today: tutors, trials, money in and what needs you.',
        'points' => [
            'Monthly Revenue (MRR) = what all paying tutors pay each month (yearly plans divided by 12).',
            'Waiting for You lists slips to check, trials ending soon, overdue invoices and new sign-up requests.',
            'Click a list item or a card menu to open the page behind it.',
        ],
    ],
    'header' => false,
    'attrs' => 'data-name="' . htmlspecialchars((string) ($adminUser['user_fname'] ?? ''), ENT_QUOTES, 'UTF-8') . '"'
        . ' data-full-name="' . htmlspecialchars(trim(($adminUser['user_fname'] ?? '') . ' ' . ($adminUser['user_lname'] ?? '')), ENT_QUOTES, 'UTF-8') . '"'
        . ' data-welcome="' . ($justSignedIn ? '1' : '0') . '"',
    'root' => 'admin-dashboard',
    'js' => 'app-admin-dashboard.js',
    'scripts' => ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
