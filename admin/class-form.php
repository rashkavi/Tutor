<?php
// Admin → Content → Classes → Create / Edit a class (a page, it was a pop-up). ?class-id=<id> edits.
// Drawn by assets/js/enlixo/pages/app-admin-class-form.js from GET /api/v1/admin/classes/form.
$editing = (int) ($_GET['class-id'] ?? 0) > 0;
$ap = [
    'title' => $editing ? 'Edit Class' : 'Create a Class',
    'menu' => 'Classes',
    'crumbs' => [['Content', null], ['Classes', 'classes'], [$editing ? 'Edit Class' : 'Create a Class', null]],
    'guide' => [
        'intro' => 'Make a class for a tutor, or change one. Tutors usually make their own classes in their app; use this when you set a tutor up.',
        'points' => [
            'Tutor: the class shows in that tutor\'s app and counts against their plan.',
            'Subject, medium, exam year and exam make the class name when you leave the name empty, for example "2027 A/L Physics-Sinhala".',
            'Cover: a 16:9 picture up to 2 MB, JPG, PNG or WebP.',
            'Switch Active off to hide a class from new students without deleting it.',
        ],
    ],
    'actions' => 'cf-actions',
    'root' => 'cf-page',
    'js' => 'app-admin-class-form.js',
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
