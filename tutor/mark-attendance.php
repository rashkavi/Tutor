<?php
// Tutor → Academic → Attendance → Mark Attendance (docs/ATTENDANCE-PLAN.md). Without ?session= it lists today's classes;
// with ?session=<class day> it marks that class day by QR code, barcode or by hand (only the ways switched on in
// Attendance Settings) and shows the scanned student with the fees they still owe.
// Drawn by assets/js/enlixo/pages/app-tutor-mark-attendance.js (no SQL on this page).
$tp = [
    'title' => 'Mark Attendance',
    'menu' => 'Mark Attendance',
    'crumbs' => [['Academic', null], ['Attendance', 'attendance-dashboard'], ['Mark Attendance', null]],
    'guide' => [
        'intro' => 'Mark who came to a class day.',
        'points' => [
            'Pick today\'s class. Scanning opens a little before the class starts (Attendance Settings → Times & Rules).',
            'QR Code: hold the student\'s card in front of the camera. Barcode: scan with the barcode scanner, or type the Student ID.',
            'Manual: tick Present, Late or Absent. Use it to correct a mark on a finished class day too.',
            'The Scanned Student card shows the fees the student still owes. Record the payment from there.',
            'Finish Class marks every student not marked as Absent.',
        ],
    ],
    'actions' => 'mark-actions',
    'root' => 'mark-page',
    'js' => 'app-tutor-mark-attendance.js',
    'css' => ['enlixo-fees.css', 'enlixo-attendance.css'],
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
