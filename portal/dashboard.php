<?php

require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once '../includes/connection.php';
require_once '../includes/Project.php';

if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
} else {
    require_once '../includes/enlixo/dashboard.php';
    $sessionUserObject = SessionUser::getCurrentUserObject('Student');

    // one-time "just signed in" cookie from the sign-in API; the greeting below replaces the old welcome banner
    if (isset($_COOKIE['enviiz_lms_student_login'])) {
        setcookie('enviiz_lms_student_login', '', time() - 3600, '/');
    }

    // Everything on this page is read by Enlixo\Dashboard\DashboardRepository and drawn by includes/enlixo/dashboard.php.
    $ids = [(int) $sessionUserObject['student_id']];
    $today = date('Y-m-d');
    $calFrom = date('Y-m-01', strtotime('first day of -2 months'));
    $calTo = date('Y-m-t', strtotime('first day of +3 months'));
    $dash = null;
    $dashError = null;
    try {
        $repo = enlixo_dash_repo();
        if ($repo) {
            $sessions = $repo->studentSessions($ids, $calFrom, $calTo);
            $weeks = \Enlixo\Dashboard\Stats::weekSeries(
                $repo->studentAttendanceByDay($ids, date('Y-m-d', strtotime('-48 days')), $today, date('Y-m-d H:i:s')), $today, 6);
            $dash = [
                'totals' => $repo->studentTotals($ids, $today),
                'sessions' => $sessions,
                'upcoming' => array_values(array_filter($sessions, static fn ($s) => $s['date'] >= $today && $s['date'] <= date('Y-m-d', strtotime('+7 days')))),
                'weeks' => $weeks,
                'rate' => \Enlixo\Dashboard\Stats::seriesRate($weeks),
                'due' => $repo->studentDuePayments($ids),
                'payments' => $repo->studentRecentPayments($ids, 5),
                'classes' => $repo->studentClasses($ids, 6),
            ];
        }
    } catch (Throwable $e) {
        $dashError = $e;
    }
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Dashboard - <?= Project::getPortalBasePath() ?></title>

        <?php enlixo_dash_styles(); ?>

        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
    </head>

    <body class="overflow-hidden">
        <div class="notifications"></div>
        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
            <div id="appContainer" class="row p-0">
                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                    <?php
                    include "includes/side-menu.php";
                    load_side_menu::load("Dashboard");
                    ?>
                </div>
                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                    <div class="col-12 p-0 m-0 g-0">
                        <?php
                        include "includes/header.php";
                        selectItem::setSelected("Dashboard");
                        ?>
                    </div>

                    <?php enlixo_dash_skeleton(); ?>

                    <div class="col-12 d-flex flex-column contentBodyArea vh-100" id="real-list">
                        <div class="row p-4 m-0 g-0">
                            <?php
                            if (!$dash) {
                                enlixo_dash_error($dashError);
                            } else {
                                $t = $dash['totals'];
                                enlixo_dash_head(enlixo_dash_greeting($sessionUserObject['user_fname'] ?? ''), date('l, j F Y'), array_merge(
                                    [['My Classes', 'classes', 'classes']],
                                    $t['due_count'] ? [['Pay Fees', 'payments', 'payments', true]] : [],
                                ));

                                enlixo_dash_stats([
                                    ['icon' => 'classes', 'value' => (string) $t['classes'], 'title' => 'My classes', 'note' => 'Classes you have joined', 'href' => 'classes'],
                                    ['icon' => 'timetable', 'value' => (string) $t['week_sessions'], 'title' => 'Classes this week', 'color' => 'teal', 'note' => 'Monday to Sunday', 'href' => 'time-table'],
                                    ['icon' => 'attendance', 'value' => $dash['rate'] === null ? '-' : $dash['rate'] . '%', 'title' => 'Attendance', 'href' => 'attendance',
                                        'color' => 'purple', 'note' => $dash['rate'] === null ? 'No class days yet' : 'Last 6 weeks, recordings count'],
                                    ['icon' => 'payments', 'value' => enlixo_dash_money($t['due']), 'title' => 'Fees to pay', 'color' => $t['due_count'] ? 'orange' : 'green', 'alert' => $t['overdue_count'] > 0, 'href' => 'payments',
                                        'note' => $t['due_count'] === 0 ? 'You have paid everything' : $t['due_count'] . ($t['due_count'] === 1 ? ' fee' : ' fees') . ' to pay' . ($t['overdue_count'] ? ', ' . $t['overdue_count'] . ' overdue' : '')],
                                ]);
                            ?>
                                <div class="col-12 mb-4">
                                    <div class="row g-4">
                                        <div class="col-12 col-xl-5 d-flex">
                                            <?php enlixo_dash_sessions($dash['upcoming'], [
                                                'today' => $today,
                                                'action' => 'Open',
                                                'link' => static fn ($s) => 'time-table-class-details?time-table-id=' . $s['id'],
                                                'who' => static fn ($s) => (string) $s['tutor'],
                                                'empty' => 'No classes in the next 7 days.',
                                            ]); ?>
                                        </div>
                                        <div class="col-12 col-md-7 col-xl-4 d-flex">
                                            <?php enlixo_dash_attendance($dash['weeks'], [
                                                'title' => 'My attendance',
                                                'sub' => 'Class days per week, last 6 weeks',
                                                'present' => 'Attended',
                                                'absent' => 'Missed',
                                                'href' => 'attendance',
                                            ]); ?>
                                        </div>
                                        <div class="col-12 col-md-5 col-xl-3 d-flex">
                                            <?php enlixo_dash_calendar($dash['sessions'], $today, $calFrom, $calTo); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mb-4">
                                    <div class="row g-4">
                                        <div class="col-12 col-xl-6">
                                            <?php enlixo_dash_payments($dash['due'], [
                                                'title' => 'Fees to pay',
                                                'sub' => $t['waiting_count'] ? $t['waiting_count'] . ($t['waiting_count'] === 1 ? ' slip is' : ' slips are') . ' waiting for your tutor to approve' : 'Pay before the due date to keep your recordings open',
                                                'all' => 'payments',
                                                'student' => false,
                                                'date' => 'due',
                                                'action' => 'Pay now',
                                                'primary' => true,
                                                'link' => static fn ($r) => 'pay-payment?payment-id=' . $r['id'],
                                                'empty' => 'Nothing to pay right now.',
                                            ]); ?>
                                        </div>
                                        <div class="col-12 col-xl-6">
                                            <?php enlixo_dash_payments($dash['payments'], [
                                                'title' => 'Recent payments',
                                                'all' => 'payments',
                                                'student' => false,
                                                'link' => static fn ($r) => 'payment-details?payment-id=' . $r['id'],
                                                'empty' => 'Your payments show up here after you pay a fee.',
                                            ]); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 component-theme p-4">
                                    <?php enlixo_dash_card_head('My classes', '', 'classes');
                                    if (!$dash['classes']) {
                                        enlixo_dash_empty('You have not joined a class yet.', 'classes');
                                    } else { ?>
                                        <div class="row g-3">
                                            <?php foreach ($dash['classes'] as $c) { ?>
                                                <div class="col-12 col-md-6 col-xl-4">
                                                    <a class="ex-hd-class" href="class-details?class-id=<?= $c['id'] ?>">
                                                        <?= enlixo_dash_cover($c['cover']) ?>
                                                        <span class="min-w-0">
                                                            <span class="text-14 fw-medium ex-hd-title d-block text-truncate"><?= enlixo_dash_e($c['name']) ?></span>
                                                            <span class="text-12 sub-text d-block text-truncate"><?= enlixo_dash_e($c['subject'] . ' · ' . $c['tutor']) ?></span>
                                                        </span>
                                                    </a>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="flex-grow-1"></div>
                        <footer>
                            <?php include '../includes/footer.php' ?>
                        </footer>
                    </div>
                </div>
            </div>
        </div>

        <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/functions.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script type="module" src="<?= Project::getAssetsPath() ?>/js/enlixo/pages/app-dashboard.js?v=<?= Project::getVersion() ?>"></script>
    </body>

    </html>
<?php
}
?>
