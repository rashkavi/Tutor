<?php
declare(strict_types=1);

// Daily job for tutor subscriptions (docs/ADMIN-PLAN.md): makes renewal invoices (and the first invoice before a
// trial ends), moves tutors to Payment Due / Overdue, and suspends them after the grace period.
// Safe to run more than once a day. The admin dashboard also runs it when it opens, so nothing waits for cron.
// Usage (cron, once a day after midnight Sri Lanka time):  php /path/to/bin/platform-billing-daily.php

use Enlixo\App;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$config = require dirname(__DIR__) . '/src/bootstrap.php';
$app = new App($config);
$n = $app->platformBilling()->tick();
printf("%s: %d invoice(s) made, %d tutor status change(s).\n", $app->today(), $n['invoices'], $n['changed']);
