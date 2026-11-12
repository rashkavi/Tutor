<?php

require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once '../includes/connection.php';
require_once '../includes/Project.php';

require_once './api/automatedClassFee.php';
require_once './api/automatedPaneltyPayment.php';
if (!SessionUser::ifUserIsAdmin()) {
    header("Location: ./index");
} else {
    require_once '../includes/enlixo/dashboard.php';
    $sessionUserObject = SessionUser::getCurrentUserObject('Admin');
    $sessionUserRole = "Admin";

    // one-time "just signed in" cookie from the sign-in API; the greeting below replaces the old welcome banner
    if (isset($_COOKIE['enviiz_lms_admin_login'])) {
        setcookie('enviiz_lms_admin_login', '', time() - 3600, '/');
    }

    // Everything on this page is read by Enlixo\Dashboard\DashboardRepository and drawn by includes/enlixo/dashboard.php.
    $today = date('Y-m-d');
    $dash = null;
    $dashError = null;
    try {
        $repo = enlixo_dash_repo();
        if ($repo) {
            $dash = [
                'totals' => $repo->adminTotals($today),
                'plans' => $repo->adminTutorsByPlan(),
                'months' => \Enlixo\Dashboard\Stats::monthSeries($repo->adminTutorsByMonth(date('Y-m-01', strtotime('first day of -5 months'))), $today, 6),
                'requests' => $repo->adminRecentRequests(),
                'payments' => $repo->adminRecentSubscriptionPayments(),
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
        <title>Dashboard - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                            } else {
                                $t = $dash['totals'];
                                enlixo_dash_head(enlixo_dash_greeting($sessionUserObject['user_fname'] ?? ''), date('l, j F Y'), [
                                    ['Tutor Requests', 'requested-users', 'user-add'],
                                    ['Add Tutor', 'add-user', 'add', true],
                                ]);

                                enlixo_dash_stats([
                                    ['icon' => 'user', 'value' => (string) $t['tutors'], 'title' => 'Active tutors', 'href' => 'users',
                                        'note' => $t['tutors_new'] ? $t['tutors_new'] . ' joined in ' . date('F') : 'Nobody new in ' . date('F')],
                                    ['icon' => 'students', 'value' => (string) $t['students'], 'title' => 'Students', 'note' => 'Active in at least one class'],
                                    ['icon' => 'classes', 'value' => (string) $t['classes'], 'title' => 'Active classes', 'note' => 'Across all tutors', 'href' => 'classes'],
                                    ['icon' => 'money', 'value' => enlixo_dash_money($t['income_month']), 'title' => 'Package income this month', 'tone' => 'good', 'href' => 'payments',
                                        'note' => \Enlixo\Dashboard\Stats::change($t['income_month'], $t['income_last_month']) ?? 'Approved package payments in ' . date('F')],
                                ]);

                                $attention = [];
                                if ($t['requests']) {
                                    $attention[] = ['icon' => 'user-add', 'tone' => 'info', 'href' => 'requested-users', 'action' => 'Review',
                                        'text' => $t['requests'] . ($t['requests'] === 1 ? ' tutor is' : ' tutors are') . ' waiting to join', 'sub' => 'Approve or reject the request'];
                                }
                                if ($t['to_approve']) {
                                    $attention[] = ['icon' => 'payments', 'tone' => 'warn', 'href' => 'payments', 'action' => 'Check',
                                        'text' => $t['to_approve'] . ($t['to_approve'] === 1 ? ' package payment to approve' : ' package payments to approve'), 'sub' => 'A tutor sent a payment slip'];
                                }
                            ?>
                                <div class="col-12 mb-4">
                                    <div class="row g-4">
                                        <div class="col-12 col-xl-4 d-flex">
                                            <?php enlixo_dash_attention($attention); ?>
                                        </div>
                                        <div class="col-12 col-md-7 col-xl-5 d-flex">
                                            <?php enlixo_dash_bars('New tutors', 'Tutors who joined each month, last 6 months',
                                                array_map(static fn ($m) => ['label' => $m['label'], 'value' => $m['value']], $dash['months']), 'new tutors', 'No tutors joined in the last 6 months.'); ?>
                                        </div>
                                        <div class="col-12 col-md-5 col-xl-3 d-flex">
                                            <?php enlixo_dash_bars('Tutors by package', 'Active tutors on each package',
                                                array_map(static fn ($p) => ['label' => $p['plan'], 'value' => $p['tutors']], $dash['plans']), 'tutors', 'No tutor has a package yet.'); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="row g-4">
                                        <div class="col-12 col-xl-6">
                                            <div class="component-theme p-4 w-100">
                                                <?php enlixo_dash_card_head('Tutor requests', 'People who asked to join as a tutor', 'requested-users');
                                                if (!$dash['requests']) {
                                                    enlixo_dash_empty('No requests yet.', 'user-add');
                                                } else { ?>
                                                    <div class="table-responsive">
                                                        <table class="table ex-v3-table align-middle m-0">
                                                            <thead><tr><th scope="col">Name</th><th scope="col">Asked on</th><th scope="col">Status</th></tr></thead>
                                                            <tbody>
                                                                <?php foreach ($dash['requests'] as $r) { ?>
                                                                    <tr>
                                                                        <td><span class="d-flex align-items-center gap-2"><?= enlixo_dash_avatar($r['name'], '', 'users') ?>
                                                                                <span class="min-w-0"><span class="text-14 main-text d-block"><?= enlixo_dash_e($r['name']) ?></span><span class="text-12 sub-text d-block"><?= enlixo_dash_e($r['email']) ?></span></span></span></td>
                                                                        <td class="text-nowrap"><span class="text-14 main-text"><?= date('j M Y', strtotime($r['date'])) ?></span></td>
                                                                        <td><?= enlixo_dash_status($r['status'], $r['status_id'] === 1 ? 'wait' : ($r['status_id'] === 2 ? 'good' : 'bad')) ?></td>
                                                                    </tr>
                                                                <?php } ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                        <div class="col-12 col-xl-6">
                                            <div class="component-theme p-4 w-100">
                                                <?php enlixo_dash_card_head('Package payments', 'The latest payments tutors made for their package', 'payments');
                                                if (!$dash['payments']) {
                                                    enlixo_dash_empty('No package payments yet.', 'payments');
                                                } else { ?>
                                                    <div class="table-responsive">
                                                        <table class="table ex-v3-table align-middle m-0">
                                                            <thead><tr><th scope="col">Tutor</th><th scope="col">Package</th><th scope="col" class="text-end">Amount</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Open</span></th></tr></thead>
                                                            <tbody>
                                                                <?php foreach ($dash['payments'] as $r) { ?>
                                                                    <tr>
                                                                        <td><span class="text-14 main-text d-block"><?= enlixo_dash_e($r['name']) ?></span><span class="text-12 sub-text d-block"><?= $r['date'] !== '' ? date('j M Y', strtotime($r['date'])) : 'Not paid yet' ?></span></td>
                                                                        <td><span class="ex-tag-v3"><?= enlixo_dash_e($r['plan']) ?></span></td>
                                                                        <td class="text-end text-nowrap"><span class="text-14 main-text"><?= enlixo_dash_e(enlixo_dash_money($r['amount'])) ?></span></td>
                                                                        <td><?= enlixo_dash_status($r['status'], enlixo_dash_payment_kind($r['status_id'])) ?></td>
                                                                        <td class="text-end"><a class="custom-outline-btn ex-btn-sm ex-dash-btn" href="subscription-payment-details?payment-id=<?= $r['id'] ?>" aria-label="View payment of <?= enlixo_dash_e($r['name']) ?>">View</a></td>
                                                                    </tr>
                                                                <?php } ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php } ?>
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
        <script type="module" src="<?= Project::getAssetsPath() ?>/js/enlixo/pages/app-dashboard.js?v=<?= Project::getVersion() ?>"></script>
    </body>

    </html>
<?php
}
?>
