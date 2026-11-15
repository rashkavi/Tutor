<?php
// Tutor → Class → Tutes → Deliveries → one parcel (a page, no pop-up): step, courier, tracking number and the address
// on this parcel. ?class-id=&tute-id=&delivery-id=[&step=dispatched]. Drawn by assets/js/enlixo/pages/app-tutor-tute-parcel.js
// (GET / POST /api/v1/tutor/tutes/{id}/deliveries; the API only answers for the tutor's own tutes).
$tp = [
    'title' => 'Tute Parcel',
    'menu' => 'Classes',
    'crumbs' => [['Classes', 'classes'], ['Tute Deliveries', 'couriered-tute?class-id=' . (int) ($_GET['class-id'] ?? 0) . '&tute-id=' . (int) ($_GET['tute-id'] ?? 0)], ['Parcel', null]],
    'guide' => [
        'intro' => 'One student\'s tute parcel: how far it has got and where it goes.',
        'points' => [
            'Step: to pack, packed, sent, delivered or returned. When you send it, add the courier and the tracking number.',
            'The address here changes this parcel only; the student changes their own address in My Profile.',
        ],
    ],
    'actions' => 'parcel-actions',
    'root' => 'parcel-page',
    'js' => 'app-tutor-tute-parcel.js',
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
