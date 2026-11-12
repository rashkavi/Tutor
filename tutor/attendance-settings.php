<?php
// Tutor → Settings → Attendance (docs/ATTENDANCE-PLAN.md): the ways to mark (QR code, barcode, manual; one or more),
// what the scan card shows and what happens to students who owe fees, the times and rules, and the activity log.
// Opened from the gear on Attendance Dashboard and Mark Attendance. Drawn by app-tutor-attendance-settings.js.
$tp = [
    'title' => 'Attendance Settings',
    'menu' => 'Attendance Settings',
    'crumbs' => [['Academic', null], ['Attendance', 'attendance-dashboard'], ['Attendance Settings', null]],
    'guide' => [
        'intro' => 'How you mark attendance and what you see when a student is scanned.',
        'points' => [
            'Ways to Mark: tick QR Code, Barcode and / or Manual. Mark Attendance shows a tab for each one you tick.',
            'When a Student Is Scanned: what the card shows, and whether a student who owes fees is marked, asked about or stopped.',
            'Times & Rules: when scanning opens and closes, and after how many minutes a student is Late.',
            'Every change is kept in the Activity Log.',
        ],
    ],
    'actions' => 'settings-actions',
    'root' => 'attendance-settings-page',
    'js' => 'app-tutor-attendance-settings.js',
    'css' => ['enlixo-fees.css', 'enlixo-attendance.css'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
