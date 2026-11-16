<?php
// Admin → Finance → Invoices → one invoice (a page, no pop-up): what it is for, its payments, and on the page itself
// Record a Payment, Send Reminder and Void. ?invoice-id=<id>. Drawn by assets/js/enlixo/pages/app-admin-invoice.js
// from GET /api/v1/admin/invoices/{id}.
$ap = [
    'title' => 'Invoice',
    'menu' => 'Invoices',
    'crumbs' => [['Finance', null], ['Invoices', 'invoices'], ['Invoice', null]],
    'guide' => [
        'intro' => 'One invoice of a tutor: the period and plan it is for, what was paid and what is left.',
        'points' => [
            'Record a Payment when the tutor paid by bank, cash or cheque without uploading a slip. Paying the full amount makes the tutor Active.',
            'Send Reminder e-mails the tutor about this invoice.',
            'Void cancels an invoice that was made by mistake; the reason is kept.',
        ],
    ],
    'actions' => 'inv-actions',
    'root' => 'inv-page',
    'js' => 'app-admin-invoice.js',
];
include 'includes/frame-top.php';
include 'includes/frame-bottom.php';
