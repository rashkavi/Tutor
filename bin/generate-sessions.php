<?php
declare(strict_types=1);

// Daily cron: create timetable sessions (and Zoom meetings) from weekly class schedules.
// Hostinger cron example: 0 1 * * * php /home/USER/tutor/bin/generate-sessions.php
use Enlixo\App;

$config = require dirname(__DIR__) . '/src/bootstrap.php';
$app = new App($config);
$report = $app->schedule()->generate((int) $config['zoom']['schedule_weeks_ahead']);

printf("[%s] created %d sessions, %d Zoom meetings, %d already existed\n", date('c'), $report['created'], $report['meetings'], $report['skipped_existing']);
foreach ([...$report['conflicts'], ...$report['errors']] as $line) {
    echo "  - $line\n";
}
