<?php

require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once '../includes/connection.php';
require_once '../includes/Project.php';

require_once './api/automatedClassFee.php';
require_once './api/automatedPaneltyPayment.php';
if (!SessionUser::ifUserIsTutor()) {
    header("Location: ./index");
} else {
    require_once '../includes/enlixo/dashboard.php';
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

    // one-time "just signed in" cookie from the sign-in API; the greeting below replaces the old welcome banner
    if (isset($_COOKIE['enviiz_lms_user_login'])) {
        setcookie('enviiz_lms_user_login', '', time() - 3600, '/');
    }

    // Everything on this page is read by Enlixo\Dashboard\DashboardRepository and drawn by includes/enlixo/dashboard.php.
    $tutorId = (int) $sessionUserObject['user_id'];
    $today = date('Y-m-d');
    $calFrom = date('Y-m-01', strtotime('first day of -2 months'));
    $calTo = date('Y-m-t', strtotime('first day of +3 months'));
    $dash = null;
    $dashError = null;
    try {
        $repo = enlixo_dash_repo();
        if ($repo) {
            $sessions = $repo->tutorSessions($tutorId, $calFrom, $calTo);
            $dash = [
                'totals' => $repo->tutorTotals($tutorId, $today),
                'sessions' => $sessions,
                'upcoming' => array_values(array_filter($sessions, static fn ($s) => $s['date'] >= $today && $s['date'] <= date('Y-m-d', strtotime('+7 days')))),
                'attendance' => \Enlixo\Dashboard\Stats::daySeries($repo->tutorAttendanceByDay($tutorId, date('Y-m-d', strtotime('-6 days')), $today), $today, 7),
                'best' => $repo->tutorBestAttendanceClass($tutorId, date('Y-m-d', strtotime('-29 days')), $today),
                'payments' => $repo->tutorRecentPayments($tutorId),
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
        <title>Dashboard - Tutor Dashboard | <?= Project::getBaseName() ?></title>

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

                    <div class="col-12 d-flex flex-column contentBodyArea vh-100 d-none" id="real-list">
                        <div class="row p-4 m-0 g-0">
                            <?php
                            if (!$dash) {
                                enlixo_dash_error($dashError);
                            } else {
                                $t = $dash['totals'];
                                enlixo_dash_head(enlixo_dash_greeting($sessionUserObject['user_fname'] ?? ''), date('l, j F Y'), [
                                    ['Add Student', 'add-student-to-class', 'user-add'],
                                    ['Create a Class', 'create-class', 'add', true],
                                ]);

                                enlixo_dash_stats([
                                    ['icon' => 'classes', 'value' => (string) $t['classes'], 'title' => 'Active classes', 'note' => 'Classes you are teaching now', 'href' => 'classes'],
                                    ['icon' => 'students', 'value' => (string) $t['students'], 'title' => 'Students', 'note' => 'Active in at least one class', 'href' => 'students'],
                                    ['icon' => 'money', 'value' => enlixo_dash_money($t['income_month']), 'title' => 'Income this month', 'tone' => 'good', 'href' => 'payments',
                                        'note' => \Enlixo\Dashboard\Stats::change($t['income_month'], $t['income_last_month']) ?? 'Fees you approved in ' . date('F')],
                                    ['icon' => 'payments', 'value' => enlixo_dash_money($t['to_collect']), 'title' => 'Fees to collect', 'tone' => $t['overdue_count'] ? 'warn' : '', 'href' => 'payments',
                                        'note' => $t['to_collect_count'] === 0 ? 'Nobody owes you fees' : $t['to_collect_count'] . ($t['to_collect_count'] === 1 ? ' fee' : ' fees') . ' not paid yet' . ($t['overdue_count'] ? ', ' . $t['overdue_count'] . ' overdue' : '')],
                                ]);

                                $attention = [];
                                if ($t['to_approve_count']) {
                                    $attention[] = ['icon' => 'payments', 'tone' => 'warn', 'href' => 'payments', 'action' => 'Check',
                                        'text' => $t['to_approve_count'] . ($t['to_approve_count'] === 1 ? ' payment slip to approve' : ' payment slips to approve'), 'sub' => 'Students are waiting for your answer'];
                                }
                                if ($t['overdue_count']) {
                                    $attention[] = ['icon' => 'clock', 'tone' => 'bad', 'href' => 'payments', 'action' => 'See who',
                                        'text' => $t['overdue_count'] . ($t['overdue_count'] === 1 ? ' fee is overdue' : ' fees are overdue'), 'sub' => 'The due date has passed'];
                                }
                                foreach ($dash['sessions'] as $s) {
                                    $ended = $s['date'] < $today || ($s['date'] === $today && $s['end'] <= date('H:i'));
                                    if ($ended && $s['date'] >= date('Y-m-d', strtotime('-7 days')) && $s['marked'] === 0 && $s['students'] > 0 && count($attention) < 5) {
                                        $attention[] = ['icon' => 'attendance', 'tone' => 'info', 'href' => 'time-table-attendance?time-table-id=' . $s['id'], 'action' => 'Mark',
                                            'text' => 'Attendance not marked: ' . $s['class'], 'sub' => date('D j M', strtotime($s['date'])) . ' · ' . enlixo_dash_time($s['start'])];
                                    }
                                }
                            ?>
                                <div class="col-12 mb-4">
                                    <div class="row g-4">
                                        <div class="col-12 col-xl-5 d-flex">
                                            <?php enlixo_dash_sessions($dash['upcoming'], [
                                                'today' => $today,
                                                'action' => 'Attendance',
                                                'link' => static fn ($s) => 'time-table-attendance?time-table-id=' . $s['id'],
                                                'who' => static fn ($s) => $s['students'] . ($s['students'] === 1 ? ' student' : ' students'),
                                                'empty' => 'No classes in the next 7 days. Add weekly times to a class and they show up here.',
                                            ]); ?>
                                        </div>
                                        <div class="col-12 col-md-7 col-xl-4 d-flex">
                                            <?php enlixo_dash_attendance($dash['attendance'], [
                                                'title' => 'Attendance',
                                                'sub' => 'Last 7 days, all classes',
                                                'href' => 'attendance',
                                                'footer' => $dash['best'] ? 'Best attendance this month: ' . $dash['best']['name'] . ' (' . $dash['best']['rate'] . '%)' : '',
                                            ]); ?>
                                        </div>
                                        <div class="col-12 col-md-5 col-xl-3 d-flex">
                                            <?php enlixo_dash_calendar($dash['sessions'], $today, $calFrom, $calTo); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="row g-4">
                                        <div class="col-12 col-xl-8">
                                            <?php enlixo_dash_payments($dash['payments'], [
                                                'title' => 'Recent payments',
                                                'sub' => 'The latest fees students paid or sent a slip for',
                                                'all' => 'payments',
                                                'link' => static fn ($r) => 'student-payment?payment-id=' . $r['id'],
                                                'empty' => 'No payments yet. They show up here when a student pays a fee.',
                                            ]); ?>
                                        </div>
                                        <div class="col-12 col-xl-4 d-flex flex-column gap-4">
                                            <?php enlixo_dash_attention($attention); ?>
                                            <div class="component-theme p-4 w-100">
                                                <?php enlixo_dash_card_head('Quick notes', 'Only you see these'); ?>
                                                <textarea class="ex-dash-note" data-dash-note="tutor-<?= $tutorId ?>" placeholder="Write a reminder for yourself..." aria-label="Quick notes"></textarea>
                                                <div class="text-12 sub-text mt-2" data-note-state>Saved on this device</div>
                                            </div>
                                        </div>
                                    </div>
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
