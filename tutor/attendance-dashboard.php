<?php
// Tutor → Academic → Attendance → Attendance Dashboard (docs/ATTENDANCE-PLAN.md): attendance of every class day,
// filtered by class and by day, week, month or a custom date range. Drawn by assets/js/enlixo/pages/app-tutor-attendance.js
// from GET /api/v1/tutor/attendance/... (no SQL on this page). The old attendance.php stays until step B replaces it.
$tp = [
    'title' => 'Attendance Dashboard',
    'menu' => 'Attendance Dashboard',
    'crumbs' => [['Academic', null], ['Attendance', null], ['Attendance Dashboard', null]],
    'guide' => [
        'intro' => 'Who came to your classes: every class day, every class and every student.',
        'points' => [
            'Choose Day, Week, Month or Custom, then step back and forward with the arrows. Pick a class to see only that class.',
            'Attendance Rate = students who came (present, late or joined online) out of the students in the class that day.',
            'Students to Follow Up missed the last 2 or more class days in a row. Their unpaid fees are shown beside them.',
            'Class Days and Students below follow the same period and class. Export them with Export Report.',
        ],
    ],
    'actions' => 'attendance-actions',
    'root' => 'attendance-page',
    'js' => 'app-tutor-attendance.js',
    'css' => ['enlixo-fees.css', 'enlixo-attendance.css'],
    'scripts' => ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
