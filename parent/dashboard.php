<?php

require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once '../includes/connection.php';
require_once '../includes/Project.php';
if (!SessionUser::ifUserIsParent()) {
    header("Location: ./index");
} else {
    require_once '../includes/enlixo/dashboard.php';
    $sessionUserObject = SessionUser::getCurrentUserObject('Parent');

    // one-time "just signed in" cookie from the sign-in API; the greeting below replaces the old welcome banner
    if (isset($_COOKIE['enviiz_lms_parent_login'])) {
        setcookie('enviiz_lms_parent_login', '', time() - 3600, '/');
    }

    // Everything on this page is read by Enlixo\Dashboard\DashboardRepository and drawn by includes/enlixo/dashboard.php.
    // ?child=ID shows one child; without it the numbers cover all children. Only the parent's own children are accepted.
    $today = date('Y-m-d');
    $calFrom = date('Y-m-01', strtotime('first day of -2 months'));
    $calTo = date('Y-m-t', strtotime('first day of +3 months'));
    $dash = null;
    $dashError = null;
    $children = [];
    $childId = 0;
    try {
        $repo = enlixo_dash_repo();
        if ($repo) {
            $children = $repo->parentChildren((int) $sessionUserObject['user_id']);
            $names = array_column($children, 'first_name', 'id');
            $childId = isset($_GET['child'], $names[(int) $_GET['child']]) ? (int) $_GET['child'] : 0;
            $ids = $childId ? [$childId] : array_keys($names);
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
                'names' => $names,
            ];
        }
    } catch (Throwable $e) {
        $dashError = $e;
    }
    $several = count($children) > 1;
    $showChild = $several && !$childId; // name the child on each row only when more than one is shown
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Dashboard - <?= Project::getParentBasePath() ?></title>

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
                        include "../includes/header.php";
                        selectItem::setSelected("Dashboard");
                        ?>
                    </div>

                    <?php enlixo_dash_skeleton(); ?>

                    <div class="col-12 d-flex flex-column contentBodyArea vh-100 d-none" id="real-list">
                        <div class="row p-4 m-0 g-0">
                            <?php
                            if (!$dash) {
                                enlixo_dash_error($dashError);
                            } elseif (!$children) {
                                enlixo_dash_head(enlixo_dash_greeting($sessionUserObject['user_fname'] ?? ''), date('l, j F Y'));
                                echo '<div class="col-12 component-theme p-4">';
                                enlixo_dash_empty('No child is linked to your number yet. Ask the tutor to add your WhatsApp number to your child as the parent.', 'students');
                                echo '</div>';
                            } else {
                                $t = $dash['totals'];
                                $who = $childId ? $dash['names'][$childId] : ($several ? 'your children' : $children[0]['first_name']);
                                enlixo_dash_head(enlixo_dash_greeting($sessionUserObject['user_fname'] ?? ''), date('l, j F Y'), array_merge(
                                    [['My Children', 'my-children', 'students']],
                                    $t['due_count'] ? [['Pay Fees', 'payments', 'payments', true]] : [],
                                ));

                                if ($several) { ?>
                                    <div class="col-12 mb-4">
                                        <nav class="ex-hd-switch" aria-label="Choose a child">
                                            <a href="dashboard" class="<?= $childId ? '' : 'active' ?>"<?= $childId ? '' : ' aria-current="page"' ?>>All children</a>
                                            <?php foreach ($children as $c) { ?>
                                                <a href="dashboard?child=<?= $c['id'] ?>" class="<?= $childId === $c['id'] ? 'active' : '' ?>"<?= $childId === $c['id'] ? ' aria-current="page"' : '' ?>><?= enlixo_dash_e($c['first_name']) ?></a>
                                            <?php } ?>
                                        </nav>
                                    </div>
                                <?php }

                                enlixo_dash_stats([
                                    $childId || !$several
                                        ? ['icon' => 'classes', 'value' => (string) $t['classes'], 'title' => 'Classes', 'note' => 'Classes ' . $who . ' has joined', 'href' => 'classes']
                                        : ['icon' => 'students', 'value' => (string) count($children), 'title' => 'Children', 'note' => $t['classes'] . ($t['classes'] === 1 ? ' class' : ' classes') . ' in total', 'href' => 'my-children'],
                                    ['icon' => 'timetable', 'value' => (string) $t['week_sessions'], 'title' => 'Classes this week', 'note' => 'Monday to Sunday', 'href' => 'time-table'],
                                    ['icon' => 'attendance', 'value' => $dash['rate'] === null ? '-' : $dash['rate'] . '%', 'title' => 'Attendance', 'href' => 'attendance',
                                        'tone' => $dash['rate'] !== null && $dash['rate'] >= 80 ? 'good' : '', 'note' => $dash['rate'] === null ? 'No class days yet' : 'Last 6 weeks, recordings count'],
                                    ['icon' => 'payments', 'value' => enlixo_dash_money($t['due']), 'title' => 'Fees to pay', 'tone' => $t['due_count'] ? 'warn' : 'good', 'href' => 'payments',
                                        'note' => $t['due_count'] === 0 ? 'Everything is paid' : $t['due_count'] . ($t['due_count'] === 1 ? ' fee' : ' fees') . ' to pay' . ($t['overdue_count'] ? ', ' . $t['overdue_count'] . ' overdue' : '')],
                                ]);

                                if ($showChild) { ?>
                                    <div class="col-12 mb-4">
                                        <div class="row g-4">
                                            <?php foreach ($children as $c) { ?>
                                                <div class="col-12 col-md-6 col-xl-4">
                                                    <div class="component-theme p-4 h-100 d-flex align-items-center gap-3">
                                                        <?= str_replace('ex-hd-avatar', 'ex-hd-avatar ex-lg', enlixo_dash_avatar($c['name'], $c['image'], 'students')) ?>
                                                        <span class="flex-grow-1 min-w-0">
                                                            <span class="text-14 fw-medium ex-hd-title d-block text-truncate"><?= enlixo_dash_e($c['name']) ?></span>
                                                            <span class="text-12 sub-text d-block"><?= $c['classes'] . ($c['classes'] === 1 ? ' class' : ' classes') ?> · <?= $c['due_count'] ? enlixo_dash_e(enlixo_dash_money($c['due'])) . ' to pay' : 'Fees paid' ?></span>
                                                        </span>
                                                        <a class="custom-outline-btn ex-btn-sm ex-hd-btn" href="dashboard?child=<?= $c['id'] ?>" aria-label="View <?= enlixo_dash_e($c['name']) ?>">View</a>
                                                    </div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                <?php } ?>

                                <div class="col-12 mb-4">
                                    <div class="row g-4">
                                        <div class="col-12 col-xl-5 d-flex">
                                            <?php enlixo_dash_sessions($dash['upcoming'], [
                                                'today' => $today,
                                                'who' => static fn ($s) => $showChild ? ($dash['names'][$s['student_id']] ?? '') : (string) $s['tutor'],
                                                'empty' => 'No classes in the next 7 days.',
                                            ]); ?>
                                        </div>
                                        <div class="col-12 col-md-7 col-xl-4 d-flex">
                                            <?php enlixo_dash_attendance($dash['weeks'], [
                                                'title' => 'Attendance',
                                                'sub' => 'Class days per week, last 6 weeks' . ($showChild ? ', all children' : ''),
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

                                <div class="col-12">
                                    <div class="row g-4">
                                        <div class="col-12 col-xl-6">
                                            <?php enlixo_dash_payments($dash['due'], [
                                                'title' => 'Fees to pay',
                                                'sub' => $t['waiting_count'] ? $t['waiting_count'] . ($t['waiting_count'] === 1 ? ' slip is' : ' slips are') . ' waiting for the tutor to approve' : 'Pay before the due date to keep recordings open',
                                                'all' => 'payments',
                                                'student' => $showChild,
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
                                                'student' => $showChild,
                                                'link' => static fn ($r) => 'payment-details?payment-id=' . $r['id'],
                                                'empty' => 'Payments show up here after a fee is paid.',
                                            ]); ?>
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
        <script type="module" src="<?= Project::getAssetsPath() ?>/js/enlixo/pages/app-dashboard.js?v=<?= Project::getVersion() ?>"></script>
    </body>

    </html>
<?php
}
?>
