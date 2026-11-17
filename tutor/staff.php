<?php
// Tutor → Settings → Staff & Roles (GROW: staff.custom_roles). Invite staff, give each a role, decide what a role may
// do. Staff sign in on the tutor sign-in page and work inside this account with only their role's pages
// (includes/enlixo/staff-access.php). Drawn by assets/js/enlixo/pages/app-tutor-staff.js.
$tp = [
    'title' => 'Staff & Roles',
    'menu' => 'Staff & Roles',
    'crumbs' => [['Settings', null], ['Staff & Roles', null]],
    'guide' => [
        'intro' => 'Give a class assistant, a cashier or a paper marker their own sign-in, with only the pages they need.',
        'points' => [
            'Make a role first on the Roles tab, for example "Cashier" with View payments and Approve / reject payments.',
            'Invite Staff sends a "Set my password" e-mail. The link also shows here so you can send it on WhatsApp.',
            'Staff sign in on the tutor sign-in page with their own e-mail. They see your classes and students, never your subscription or billing.',
            'Switch Off stops a person signing in at once. Changing a role applies from their next page.',
        ],
    ],
    'actions' => 'st-actions',
    'root' => 'st-page',
    'js' => 'app-tutor-staff.js',
    'css' => ['messages.css'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
