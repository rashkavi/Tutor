<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsTutor()) {
    header("Location: ./index");
} else {

    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    // ---------------------------------------------------------------
    // Static plan display config (from ENLIXO Tutor pricing comparison)
    // Pricing itself is pulled live from `payment_plans` below - this
    // array is only presentation copy, so it's safe to hardcode.
    // ---------------------------------------------------------------
    $planIcon = [
        1 => 'solar:danger-circle-bold-duotone',
        2 => 'solar:shield-star-bold-duotone',
        3 => 'solar:crown-star-bold-duotone',
    ];
    $planIconClass = [
        1 => 'expired', // matches existing subscription-logo-box modifier naming in this file
        2 => '',
        3 => 'pro',
    ];
    $planSubtext = [
        1 => 'Starter access package',
        2 => 'For active tutors and class managers',
        3 => 'Advanced controls and analytics',
    ];
    $planStudentLimit = [
        1 => 'Up to 50 students',
        2 => 'Up to 150 students',
        3 => 'Up to 300 students',
    ];
    $planHighlights = [
        1 => [
            'Timetable & class scheduling',
            'Manual + QR attendance',
            'Bank transfer & manual approval',
            'Basic reports',
        ],
        2 => [
            'MCQ exams + grading',
            'Online card payments (Tutor Gateway)',
            'WhatsApp & SMS (Tutor API)',
            'Attendance & payment analytics',
        ],
        3 => [
            'Parent login & payment dashboard',
            'Arrears tracking dashboard',
            'Controlled & expiry-based recordings',
            'Advanced class analytics',
        ],
    ];

    // Full feature matrix for the "Compare all features" collapsible table.
    // Value per plan: true = included, false = not included, 'addon' = paid add-on
    $planFeatureMatrix = [
        'Class & Student Management' => [
            'Subject / Grade / Language Setup'      => [1 => true,  2 => true, 3 => true],
            'Student Registration'                   => [1 => true,  2 => true, 3 => true],
            'Timetable Management'                    => [1 => true,  2 => true, 3 => true],
            'Physical & Online Class Scheduling'      => [1 => true,  2 => true, 3 => true],
            'Tute / Material Management (Online)'     => [1 => true,  2 => true, 3 => true],
            'Tute / Material Management (Courier)'    => [1 => false, 2 => true, 3 => true],
            'Class-Based Fee Setup'                   => [1 => true,  2 => true, 3 => true],
            'MCQ Exams + Grading'                     => [1 => false, 2 => true, 3 => true],
            'Parent Login'                            => [1 => false, 2 => false, 3 => true],
        ],
        'Payment Management' => [
            'Bank Transfer Tracking'                  => [1 => true,  2 => true, 3 => true],
            'Manual Payment Approval'                 => [1 => true,  2 => true, 3 => true],
            'Online Card Payments (Tutor Gateway)'    => [1 => false, 2 => true, 3 => true],
            'Arrears Tracking Dashboard'               => [1 => false, 2 => false, 3 => true],
            'Parent Payment Dashboard'                 => [1 => false, 2 => false, 3 => true],
            'ENLIXO Payment Gateway'                   => [1 => 'addon', 2 => 'addon', 3 => 'addon'],
        ],
        'Classes & Recordings' => [
            'Tutor Zoom Integration'                   => [1 => true,  2 => true, 3 => true],
            'ENLIXO Zoom Account'                      => [1 => 'addon', 2 => 'addon', 3 => 'addon'],
            'Non-Secured Recordings'                   => [1 => true,  2 => true, 3 => true],
            'Secured Recordings (Tutor API)'           => [1 => false, 2 => true, 3 => true],
            'Controlled Recording Access'              => [1 => false, 2 => false, 3 => true],
            'Expiry-Based Recording Access'            => [1 => false, 2 => false, 3 => true],
            'ENLIXO Secure Streaming'                  => [1 => 'addon', 2 => 'addon', 3 => 'addon'],
        ],
        'Communication' => [
            'Email Notifications'                      => [1 => true,  2 => true, 3 => true],
            'WhatsApp (Tutor API)'                     => [1 => false, 2 => true, 3 => true],
            'SMS (Tutor Gateway)'                      => [1 => false, 2 => true, 3 => true],
            'ENLIXO SMS Service'                       => [1 => 'addon', 2 => 'addon', 3 => 'addon'],
            'ENLIXO WhatsApp API'                      => [1 => 'addon', 2 => 'addon', 3 => 'addon'],
        ],
        'Attendance & ID' => [
            'Manual Attendance'                        => [1 => true,  2 => true, 3 => true],
            'QR-Based Attendance'                      => [1 => true,  2 => true, 3 => true],
            'Attendance Reports Export'                 => [1 => false, 2 => true, 3 => true],
            'Digital Student ID (QR)'                  => [1 => false, 2 => true, 3 => true],
            'Physical ID Card Printing'                 => [1 => 'addon', 2 => 'addon', 3 => 'addon'],
        ],
        'Reporting & Analytics' => [
            'Basic Reports'                            => [1 => true,  2 => true, 3 => true],
            'Attendance Analytics'                      => [1 => false, 2 => true, 3 => true],
            'Payment Analytics'                         => [1 => false, 2 => true, 3 => true],
            'Advanced Class Analytics'                  => [1 => false, 2 => false, 3 => true],
        ],
    ];

    // Live plan pricing from DB
    $allPlansResult = Database::search("SELECT * FROM `payment_plans` ORDER BY `payment_plan_id` ASC");
    $allPlans = [];
    if ($allPlansResult && $allPlansResult->num_rows > 0) {
        while ($p = $allPlansResult->fetch_assoc()) {
            $allPlans[$p['payment_plan_id']] = $p;
        }
    }

    // ---------------------------------------------------------------
    // Auto-trigger scan: find the nearest-expiring subscription that is
    // "Expiring Soon" (<=7 days) or already "Expired", and identify the
    // tutor's current plan tier for the card badges below.
    // ---------------------------------------------------------------
    $subsQuery = "SELECT *
            FROM `users_has_payment_plans`
            INNER JOIN `payment_plans`
                ON `users_has_payment_plans`.`payment_plans_payment_plan_id` = `payment_plans`.`payment_plan_id`
            LEFT JOIN `users_has_payment_plans_payments`
                ON `users_has_payment_plans`.`users_has_payment_plan_id` =
                `users_has_payment_plans_payments`.`users_has_payment_plans_users_has_payment_plan_id`
                AND `users_has_payment_plans_payments`.`users_has_payment_plans_payment_id` = (
                    SELECT MAX(`users_has_payment_plans_payment_id`)
                    FROM `users_has_payment_plans_payments`
                    WHERE `users_has_payment_plans_users_has_payment_plan_id` =
                        `users_has_payment_plans`.`users_has_payment_plan_id`
                    AND `payment_status_payment_status_id` = 3
                )
            WHERE `users_has_payment_plans`.`users_user_id` = '" . $sessionUserObject['user_id'] . "'";

    $subsResults = Database::search($subsQuery);

    $autoUpgradeShouldShow = false;
    $autoUpgradeDaysLeft = null;
    $autoUpgradeStatus = null;
    $autoUpgradeCurrentPlanId = null;
    $autoUpgradeExpireDate = null;

    while ($scanRow = $subsResults->fetch_assoc()) {
        if (!empty($scanRow['users_has_payment_plans_payment_id'])) {
            $scanTo = new DateTime($scanRow['to']);
            $scanExpire = $scanTo->format('Y-m-d');
        } else {
            $scanAdded = new DateTime($scanRow['date_added']);
            $scanAdded->modify('+7 days');
            $scanExpire = $scanAdded->format('Y-m-d');
        }

        $scanToday = new DateTime();
        $scanExpireDate = new DateTime($scanExpire);
        $scanDaysLeft = $scanToday->diff($scanExpireDate)->days;

        if ($scanExpireDate < $scanToday) {
            $scanStatus = "Expired";
            $scanDaysLeft = 0;
        } elseif ($scanDaysLeft <= 7) {
            $scanStatus = "Expiring Soon";
        } else {
            $scanStatus = "Active";
        }

        // Track the tutor's current plan tier (nearest / most relevant subscription)
        if ($autoUpgradeCurrentPlanId === null || in_array($scanStatus, ["Expired", "Expiring Soon"])) {
            $autoUpgradeCurrentPlanId = $scanRow['payment_plan_id'];
            $autoUpgradeExpireDate = $scanExpire;
        }

        if (in_array($scanStatus, ["Expired", "Expiring Soon"])) {
            if ($autoUpgradeDaysLeft === null || $scanDaysLeft < $autoUpgradeDaysLeft) {
                $autoUpgradeShouldShow = true;
                $autoUpgradeDaysLeft = $scanDaysLeft;
                $autoUpgradeStatus = $scanStatus;
            }
        }
    }

    // ---------------------------------------------------------------
    // Billing History (past payments) - paginated via ?history_page=N
    // ASSUMPTION: `users_has_payment_plans_payments` carries its own
    // `date_added`/`from`/`to` columns for the transaction itself. If
    // your schema only stores those on `users_has_payment_plans`, the
    // aliased `plan_*` fallback fields below cover that case too.
    // ASSUMPTION: payment_status_payment_status_id => 1 = Pending,
    // 2 = Processing/Awaiting Approval, 3 = Success. Adjust the
    // $paymentStatusMap below to match your real payment_status table.
    // ---------------------------------------------------------------
    $paymentStatusMap = [
        1 => ['label' => 'Pending',    'class' => 'table-btn-orange'],
        2 => ['label' => 'Processing', 'class' => 'table-btn-orange'],
        3 => ['label' => 'Success',    'class' => 'table-btn-success'],
    ];
    $paymentStatusDefault = ['label' => 'Failed', 'class' => 'table-btn-danger'];

    $historyPerPage = 10;
    $historyPageNo = isset($_GET['history_page']) ? max(1, (int)$_GET['history_page']) : 1;

    $historyBaseQuery = "SELECT uhpp_payments.*,
                uhpp_payments.`from` AS plan_from,
                uhpp_payments.`to` AS plan_to,
                uhpp.`date_added` AS plan_date_added,
                pp.`payment_plan`, pp.`per_month_amount`, pp.`annual_discount`
            FROM `users_has_payment_plans_payments` uhpp_payments
            INNER JOIN `users_has_payment_plans` uhpp
                ON uhpp_payments.`users_has_payment_plans_users_has_payment_plan_id` = uhpp.`users_has_payment_plan_id`
            INNER JOIN `payment_plans` pp
                ON uhpp.`payment_plans_payment_plan_id` = pp.`payment_plan_id`
            WHERE uhpp.`users_user_id` = '" . $sessionUserObject['user_id'] . "'
            ORDER BY uhpp_payments.`users_has_payment_plans_payment_id` DESC";

    $historyCountResult = Database::search($historyBaseQuery);
    $historyTotal = $historyCountResult ? $historyCountResult->num_rows : 0;
    $historyTotalPages = $historyTotal > $historyPerPage ? (int)ceil($historyTotal / $historyPerPage) : 1;
    if ($historyPageNo > $historyTotalPages) {
        $historyPageNo = $historyTotalPages;
    }
    $historyOffset = ($historyPageNo - 1) * $historyPerPage;
    $historyResult = Database::search($historyBaseQuery . " LIMIT " . $historyPerPage . " OFFSET " . $historyOffset);
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Billing - Tutor Dashboard | <?= Project::getBaseName() ?></title>

        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
        <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/richtexteditor/rte_theme_default.css" />

        <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script type="text/javascript" src="<?= Project::getAssetsPath() ?>/plugins/richtexteditor/rte.js"></script>
        <script type="text/javascript" src='<?= Project::getAssetsPath() ?>/plugins/richtexteditor/plugins/all_plugins.js'></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>

        <style>
            /* ---- Billing toggle pill ---- */
            .billing-toggle {
                gap: 4px;
            }

            .billing-toggle .toggle-btn {
                border: none;
                background: transparent;
                border-radius: 8px;
                font-size: 14px;
            }

            /* ---- Plan cards ---- */
            .plan-card-v2 {
                position: relative;
                height: 100%;
                display: flex;
                flex-direction: column;
                transition: transform .15s ease, box-shadow .15s ease;
            }

            .plan-card-v2.highlighted {
                background: #14151a;
                border-color: #14151a;
                transform: translateY(-8px);
                box-shadow: 0 16px 32px rgba(0, 0, 0, .18);
            }

            .plan-card-v2 .plan-badge {
                font-size: 11px;
                font-weight: 600;
                letter-spacing: .03em;
                border-radius: 20px;
                padding: 4px 10px;
                white-space: nowrap;
            }

            .plan-badge-current {
                background: rgba(108, 117, 125, .15);
                color: #6c757d;
            }

            .plan-badge-popular {
                background: rgba(255, 255, 255, .15);
                color: #fff;
            }

            .plan-badge-tier {
                background: rgba(13, 110, 253, .1);
                color: #0d6efd;
            }

            .plan-highlight-list {
                list-style: none;
                padding: 0;
                margin: 0;
            }

            .plan-highlight-list li {
                display: flex;
                align-items: flex-start;
                gap: 8px;
                margin-bottom: 10px;
            }

            /* ---- Upgrade modal (comparison matrix) ---- */
            #upgradePlanModal .modal-dialog {
                max-width: 1040px;
            }

            #upgradePlanModal .feature-matrix-table td,
            #upgradePlanModal .feature-matrix-table th {
                vertical-align: middle;
            }

            #upgradePlanModal .feature-matrix-table td:not(:first-child),
            #upgradePlanModal .feature-matrix-table th:not(:first-child) {
                text-align: center;
            }
        </style>

    </head>

    <body class="overflow-hidden">
        <div class="notifications"></div>
        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
            <div id="appContainer" class="row p-0">
                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                    <?php
                    include "includes/side-menu.php";
                    load_side_menu::load("Billing");
                    ?>
                </div>
                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                    <div class="row p-0 m-0 g-0">
                        <div class="col-12 p-0 m-0 g-0">
                            <?php
                            include "includes/header.php";
                            selectItem::setSelected("Billing");
                            ?>
                        </div>
                        <div class="container p-4 m-0" id="skeleton-list">
                            <?php
                            include '../includes/components/skeleton-header.php';
                            include '../includes/components/skeleton-class-card.php'; ?>
                        </div>
                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                            <div class="row p-4 m-0 g-0">

                                <!-- ============================================================ -->
                                <!-- Header: title + subtitle + Monthly/Yearly toggle             -->
                                <!-- ============================================================ -->
                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                    <div class="row align-items-center p-0 m-0 g-0">
                                        <div class="col-12 col-lg-7">
                                            <div class="row">
                                                <span class="main-topic lh-lg" data-i18n="">Billing & Subscription</span>
                                                <span class="text-14 sub-text lh-base mt-1" data-i18n="">Keep track of your subscription details, update your billing information, and control your account's payment.</span>
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-5 mt-3 mt-lg-0">
                                            <div class="row gx-3 justify-content-lg-end align-items-center">
                                                <div class="col-auto">
                                                    <div class="billing-toggle d-inline-flex align-items-center bl-solid br-8 p-1" id="billingToggle">
                                                        <button type="button" class="toggle-btn custom-btn px-4 py-2 active" data-period="monthly">Monthly</button>
                                                        <button type="button" class="toggle-btn px-4 py-2" data-period="yearly">Yearly</button>
                                                    </div>
                                                </div>
                                                <div class="col-auto">
                                                    <button class="custom-btn" onclick="window.location.href = 'subscription-payments'">Payments</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ============================================================ -->
                                <!-- Plan cards                                                    -->
                                <!-- ============================================================ -->
                                <div class="col-12 p-4 component-theme mb-4">
                                    <div class="row g-3">
                                        <?php foreach ([1, 2, 3] as $pid):
                                            $planRow = $allPlans[$pid] ?? null;
                                            if (!$planRow) continue;

                                            $isCurrent = ($autoUpgradeCurrentPlanId !== null && (int)$autoUpgradeCurrentPlanId === $pid);
                                            $isRecommended = (!$isCurrent && $pid == 2 && (int)$autoUpgradeCurrentPlanId !== 3);

                                            $monthly = (float)$planRow['per_month_amount'];
                                            $annualMonthly = $monthly - (float)$planRow['annual_discount'];

                                            if ($isCurrent) {
                                                $badgeLabel = 'CURRENT';
                                                $badgeClass = 'plan-badge-current';
                                            } elseif ($isRecommended) {
                                                $badgeLabel = 'POPULAR';
                                                $badgeClass = 'plan-badge-popular';
                                            } else {
                                                $badgeLabel = $pid == 3 ? 'PREMIUM' : 'BASIC';
                                                $badgeClass = 'plan-badge-tier';
                                            }

                                            $textColorClass = $isRecommended ? 'text-white' : 'main-text';
                                            $mutedColorClass = $isRecommended ? 'text-white-50' : 'sub-text';
                                        ?>
                                            <div class="col-12 col-lg-4 d-flex">
                                                <div class="plan-card-v2 <?= $isRecommended ? 'highlighted' : 'component-theme bl-solid' ?> br-8 p-4 w-100">

                                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                                        <span class="text-16 fw-semibold <?= $textColorClass ?>"><?= htmlspecialchars($planRow['payment_plan']) ?></span>
                                                        <span class="plan-badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
                                                    </div>

                                                    <div class="mb-1">
                                                        <span class="fs-2 fw-bold <?= $textColorClass ?> plan-price"
                                                            data-monthly="<?= $monthly ?>"
                                                            data-yearly="<?= $annualMonthly ?>">
                                                            LKR <?= number_format($monthly, 0) ?>
                                                        </span>
                                                        <span class="text-14 <?= $mutedColorClass ?> plan-price-unit">/month</span>
                                                    </div>
                                                    <div class="text-14 <?= $mutedColorClass ?> mb-3"><?= $planStudentLimit[$pid] ?></div>

                                                    <?php if ($isCurrent): ?>
                                                        <button class="table-btn w-100" disabled>Current Plan</button>
                                                    <?php else: ?>
                                                        <button
                                                            class="<?= $isRecommended ? 'btn btn-light' : 'custom-btn' ?> w-100 upgrade-plan-btn"
                                                            data-plan-id="<?= $pid ?>"
                                                            data-plan-name="<?= htmlspecialchars($planRow['payment_plan']) ?>"
                                                            data-month-amount="<?= $monthly ?>"
                                                            data-year-amount="<?= $annualMonthly ?>">
                                                            Upgrade Plan
                                                        </button>
                                                    <?php endif; ?>

                                                    <ul class="plan-highlight-list mt-4">
                                                        <?php foreach ($planHighlights[$pid] as $feature): ?>
                                                            <li>
                                                                <iconify-icon icon="solar:check-circle-bold" width="18" class="<?= $isRecommended ? 'text-white' : 'text-success' ?> flex-shrink-0 mt-1"></iconify-icon>
                                                                <span class="text-14 <?= $textColorClass ?>"><?= htmlspecialchars($feature) ?></span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="mt-3">
                                        <button class="btn btn-link p-0 text-14" type="button" data-bs-toggle="collapse" data-bs-target="#pageFeatureMatrixCollapse">
                                            <iconify-icon icon="tabler:chevron-down" width="16"></iconify-icon>
                                            Compare all features
                                        </button>
                                        <div class="collapse mt-3" id="pageFeatureMatrixCollapse">
                                            <div class="table-responsive">
                                                <table class="table feature-matrix-table">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-14 main-text">Feature</th>
                                                            <th class="text-14 main-text">Start</th>
                                                            <th class="text-14 main-text">Grow</th>
                                                            <th class="text-14 main-text">Pro</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($planFeatureMatrix as $category => $features): ?>
                                                            <tr>
                                                                <td colspan="4" class="fw-semibold text-14 main-text pt-3"><?= htmlspecialchars($category) ?></td>
                                                            </tr>
                                                            <?php foreach ($features as $featureName => $planValues): ?>
                                                                <tr>
                                                                    <td class="text-14 sub-text"><?= htmlspecialchars($featureName) ?></td>
                                                                    <?php foreach ([1, 2, 3] as $pid): ?>
                                                                        <td>
                                                                            <?php if ($planValues[$pid] === true): ?>
                                                                                <iconify-icon icon="solar:check-circle-bold" width="18" class="text-success"></iconify-icon>
                                                                            <?php elseif ($planValues[$pid] === 'addon'): ?>
                                                                                <span class="text-12 sub-text">Add-on</span>
                                                                            <?php else: ?>
                                                                                <iconify-icon icon="solar:close-circle-bold" width="18" class="text-muted"></iconify-icon>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                    <?php endforeach; ?>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ============================================================ -->
                                <!-- Billing History                                               -->
                                <!-- ============================================================ -->
                                <div class="col-12 p-4 component-theme">
                                    <div class="row p-0 m-0 g-0 align-content-center">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                                            <div>
                                                <h2 class="sub-topic mb-1">Billing History</h2>
                                                <p class="sub-text mb-0">
                                                    A record of your past payments and receipts.
                                                </p>
                                            </div>
                                        </div>

                                        <div class="table-responsive-wrapper mt-4">
                                            <div class="table-responsive">
                                                <table class="table bl-solid m-0 p-0 g-0">
                                                    <thead>
                                                        <tr class="br-t-5">
                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium align-content-center">Plan Name</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Amount</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Purchase Date</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">End Date</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if ($historyResult && $historyResult->num_rows > 0): ?>
                                                            <?php while ($hrow = $historyResult->fetch_assoc()):
                                                                $purchaseDateRaw = !empty($hrow['date_added']) ? $hrow['date_added'] : ($hrow['plan_date_added'] ?? null);
                                                                $endDateRaw = !empty($hrow['to']) ? $hrow['to'] : ($hrow['plan_to'] ?? null);
                                                                $fromRaw = !empty($hrow['from']) ? $hrow['from'] : ($hrow['plan_from'] ?? null);

                                                                $hAmount = (float)$hrow['per_month_amount'];
                                                                if (!empty($fromRaw) && !empty($endDateRaw)) {
                                                                    $hFrom = new DateTime($fromRaw);
                                                                    $hTo = new DateTime($endDateRaw);
                                                                    $hDiff = $hFrom->diff($hTo);
                                                                    $hMonthCount = ($hDiff->y * 12) + $hDiff->m;
                                                                    $hAmount = $hMonthCount <= 1 ? (float)$hrow['per_month_amount'] : ((float)$hrow['per_month_amount'] - (float)$hrow['annual_discount']);
                                                                }

                                                                $statusInfo = $paymentStatusMap[$hrow['payment_status_payment_status_id']] ?? $paymentStatusDefault;
                                                                $periodLabel = !empty($fromRaw) && !empty($endDateRaw)
                                                                    ? date("M Y", strtotime($fromRaw))
                                                                    : '';
                                                            ?>
                                                                <tr>
                                                                    <td class="px-4 py-3 align-middle">
                                                                        <span class="text-14 main-text fw-medium"><?= htmlspecialchars($hrow['payment_plan']) ?><?= $periodLabel ? ' - ' . htmlspecialchars($periodLabel) : '' ?></span>
                                                                    </td>
                                                                    <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= number_format($hAmount, 2) ?></span></td>
                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $purchaseDateRaw ? date("d M Y", strtotime($purchaseDateRaw)) : '-' ?></span></td>
                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $endDateRaw ? date("d M Y", strtotime($endDateRaw)) : '-' ?></span></td>
                                                                    <td class="py-2 align-middle">
                                                                        <button class="table-btn <?= $statusInfo['class'] ?> w-100"><?= $statusInfo['label'] ?></button>
                                                                    </td>
                                                                    <td class="py-2 align-middle">
                                                                        <div class="d-flex gap-2">
                                                                            <!-- TODO: wire to your actual receipt/slip view + download endpoints -->
                                                                            <button class="table-btn px-3" onclick="viewPaymentRecord(<?= (int)$hrow['users_has_payment_plans_payment_id'] ?>)" title="View">
                                                                                <iconify-icon icon="tabler:eye" width="16"></iconify-icon>
                                                                            </button>
                                                                            <button class="table-btn px-3" onclick="downloadPaymentReceipt(<?= (int)$hrow['users_has_payment_plans_payment_id'] ?>)" title="Download">
                                                                                <iconify-icon icon="tabler:download" width="16"></iconify-icon>
                                                                            </button>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endwhile; ?>
                                                        <?php else: ?>
                                                            <tr class="justify-content-center align-middle">
                                                                <td colspan="6" class="align-middle justify-content-center">
                                                                    <div class="d-flex flex-column align-items-center">
                                                                        <img src="<?= Project::getAssetsPath() ?>/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>

                                                <?php if ($historyTotalPages > 1): ?>
                                                    <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper">
                                                        <nav aria-label="Billing history pagination" class="col-12 px-2 pe-2 m-0 container-fluid">
                                                            <div class="row p-4 align-items-center">
                                                                <div class="col-12 d-flex justify-content-center gap-2">
                                                                    <?php for ($i = 1; $i <= $historyTotalPages; $i++): ?>
                                                                        <a href="?history_page=<?= $i ?>" class="page-item <?= $i == $historyPageNo ? 'active' : '' ?>">
                                                                            <button class="py-2 px-3" <?= $i == $historyPageNo ? 'disabled' : '' ?>><?= $i ?></button>
                                                                        </a>
                                                                    <?php endfor; ?>
                                                                </div>
                                                            </div>
                                                        </nav>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                            <div class="flex-grow-1"></div>
                            <footer>
                                <?php include '../includes/footer.php' ?>
                            </footer>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="renewSubscriptionModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-body p-4">

                        <h5 class="fw-bold mb-1" id="renewModalTitle">Renew your ENLIXO Tutor Plan</h5>
                        <p class="text-muted small mb-4">
                            Review the details and proceed to checkout.
                        </p>

                        <!-- Period -->
                        <div class="mb-3">
                            <label class="fw-semibold small mb-2">Period</label>

                            <div class="border p-3">
                                <div class="row align-items-center">
                                    <div class="col-6">
                                        <select class="form-select custom-select" id="renewPeriod" onchange="updateRenewModalAmounts()">
                                            <option value="1">1 Month</option>
                                            <option value="12" selected>12 Months</option>
                                        </select>
                                    </div>

                                    <div class="col-6 text-end">
                                        <span class="badge bg-success me-2" id="saveBadge">
                                            Save LKR 0
                                        </span>

                                        <div class="text-muted text-decoration-line-through small" id="oldPrice">
                                            LKR 0/mo
                                        </div>

                                        <div class="fs-4 fw-bold">
                                            LKR <span id="currentPrice">0</span>/mo
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div class="mb-3">
                            <label class="fw-semibold small mb-2">Payment Method</label>
                            <select class="form-select custom-select" id="renewPaymentMethod">
                                <option selected value="1">Bank Transfer</option>
                                <option value="2">Bank Deposit</option>
                            </select>
                        </div>

                        <!-- Summary -->
                        <div class="border p-3 mt-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Expiration date</span>
                                <span class="fw-semibold" id="renewExpireDate">2026-12-31</span>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span>
                                    <div class="fw-semibold" id="renewPlanName">ENLIXO Tutor Grow Plan</div>
                                    <small class="text-muted" id="renewPlanPeriodText">(12 months)</small>
                                </span>

                                <span class="text-end">
                                    <div class="text-muted text-decoration-line-through small" id="renewSummaryOldPrice">
                                        LKR 0
                                    </div>
                                    <div class="fw-bold" id="renewSummaryNewPrice">
                                        LKR 0
                                    </div>
                                </span>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Total</span>
                                <span class="fs-4 fw-bold" id="renewTotalPrice">LKR 0</span>
                            </div>

                            <p class="text-muted small mt-3 mb-0">
                                By checking out you agree to our Terms of Service and Privacy Policy.
                            </p>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <button class="btn btn-light br-0" data-bs-dismiss="modal">
                                Cancel
                            </button>

                            <button class="btn btn-primary br-0" id="completeRenewBtn">
                                Confirm Payment
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="bankPaymentModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">

                    <div class="modal-body p-4">

                        <h5 class="fw-bold mb-1">Complete Your Payment</h5>
                        <p class="text-muted small mb-4">
                            Transfer the amount to the following bank account and upload your payment slip.
                        </p>

                        <!-- Bank Details -->
                        <div class="border p-3 mb-3">

                            <div class="fw-semibold mb-2">Bank Account Details</div>

                            <div class="row small">
                                <div class="col-6 text-muted">Bank</div>
                                <div class="col-6 text-end fw-semibold">Commercial Bank</div>

                                <div class="col-6 text-muted">Account Name</div>
                                <div class="col-6 text-end fw-semibold">ENLIXO Pvt Ltd</div>

                                <div class="col-6 text-muted">Account Number</div>
                                <div class="col-6 text-end fw-semibold">1234567890</div>

                                <div class="col-6 text-muted">Branch</div>
                                <div class="col-6 text-end fw-semibold">Colombo</div>
                            </div>

                        </div>

                        <!-- Payment Summary -->
                        <div class="border p-3 mt-3">

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Subscription</span>
                                <span class="fw-semibold" id="bankPlanName">
                                    ENLIXO Tutor Grow Plan
                                </span>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Billing Period</span>
                                <span class="fw-semibold" id="bankPlanPeriod">
                                    12 Months
                                </span>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Total Amount</span>
                                <span class="fw-bold text-primary" id="bankTotalAmount">
                                    LKR 42,000
                                </span>
                            </div>

                        </div>

                        <!-- Upload Slip -->

                        <div class="col-12 d-flex flex-column mt-3">
                            <label class="text-16 main-text" for="">Upload Payment Slip</label>
                            <div class="p-5 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                <img src="../assets/imgs/icons/cloud.png" alt="cloud">
                                <span>Drag & Drop your files here</span>
                                <span>OR</span>
                                <button class="custom-btn px-4" id="file-selector">Select Files</button>
                                <input type="file" class="d-none" id="file-selector-input" multiple accept=".pdf, .png, .jpg, .jpeg">
                                <div class="col-12 px-1 mt-4">
                                    <div class="row modalFileList" id="list-section">
                                    </div>
                                </div>
                            </div>
                            <span id="attachment-feedback" class="text-dark-red text-12"></span>
                            <!-- File upload input field for PDF, Word, and PowerPoint files -->
                        </div>

                        <!-- Buttons -->
                        <div class="d-flex justify-content-end gap-2 mt-4">

                            <button class="btn btn-light br-0" data-bs-dismiss="modal">
                                Cancel
                            </button>

                            <button class="btn btn-success br-0" id="confirmPaymentBtn">
                                Submit Payment
                            </button>

                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- Auto-open reminder modal - shows once per session when the        -->
        <!-- tutor's plan is "Expiring Soon" (<=7 days) or "Expired". Points   -->
        <!-- them back at the plan cards above rather than duplicating them.  -->
        <!-- ================================================================= -->
        <div class="modal fade" id="upgradePlanModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-body p-4 text-center">
                        <iconify-icon icon="solar:danger-triangle-bold-duotone" width="40" class="text-warning mb-2"></iconify-icon>
                        <h5 class="fw-bold mb-1" id="upgradeModalTitle">Your plan needs attention</h5>
                        <p class="text-muted small mb-4" id="upgradeModalSubtitle">
                            Your plan is expiring soon.
                        </p>
                        <button class="custom-btn w-100" data-bs-dismiss="modal" onclick="document.getElementById('billingToggle').scrollIntoView({behavior:'smooth', block:'center'})">
                            View Plans
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/fileChooser.js?v=<?= Project::getVersion() ?>"></script>

        <script>
            // ---------------------------------------------------------------
            // Monthly / Yearly toggle - updates every .plan-price on the page
            // ---------------------------------------------------------------
            document.getElementById('billingToggle').addEventListener('click', function(e) {
                const btn = e.target.closest('.toggle-btn');
                if (!btn) return;

                document.querySelectorAll('#billingToggle .toggle-btn').forEach(b => b.classList.remove('active', 'custom-btn'));
                btn.classList.add('active', 'custom-btn');

                const period = btn.dataset.period; // 'monthly' | 'yearly'

                document.querySelectorAll('.plan-price').forEach(function(priceEl) {
                    const amount = period === 'yearly' ? priceEl.dataset.yearly : priceEl.dataset.monthly;
                    priceEl.textContent = 'LKR ' + Math.round(parseFloat(amount)).toLocaleString();
                });

                document.querySelectorAll('.plan-price-unit').forEach(function(unitEl) {
                    unitEl.textContent = period === 'yearly' ? '/mo billed yearly' : '/month';
                });
            });

            // ---------------------------------------------------------------
            // Auto-open reminder modal (once per browser session)
            // ---------------------------------------------------------------
            const autoUpgradeTrigger = {
                shouldShow: <?= $autoUpgradeShouldShow ? 'true' : 'false' ?>,
                daysLeft: <?= $autoUpgradeDaysLeft === null ? 'null' : (int)$autoUpgradeDaysLeft ?>,
                status: <?= $autoUpgradeStatus === null ? 'null' : json_encode($autoUpgradeStatus) ?>
            };

            document.addEventListener('DOMContentLoaded', function() {
                if (!autoUpgradeTrigger.shouldShow) return;

                const sessionFlag = 'upgradePlanModalShown';
                if (sessionStorage.getItem(sessionFlag)) return;

                const modalEl = document.getElementById('upgradePlanModal');
                if (!modalEl) return;

                const subtitleEl = document.getElementById('upgradeModalSubtitle');
                if (subtitleEl) {
                    subtitleEl.textContent = autoUpgradeTrigger.status === 'Expired'
                        ? 'Your plan has expired. Renew or upgrade to keep everything running.'
                        : `Your plan expires in ${autoUpgradeTrigger.daysLeft} day${autoUpgradeTrigger.daysLeft === 1 ? '' : 's'}. Renew or upgrade to avoid interruption.`;
                }

                new bootstrap.Modal(modalEl).show();
                sessionStorage.setItem(sessionFlag, '1');
            });

            // ---------------------------------------------------------------
            // "Upgrade Plan" button on each card
            // ---------------------------------------------------------------
            document.querySelectorAll('.upgrade-plan-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const planData = {
                        planID: null, // TODO(backend): confirm handleSubscriptionRenewal()/its endpoint
                        //                accepts switching payment_plan_id on an existing
                        //                users_has_payment_plan record, since this button
                        //                represents a tier CHANGE, not a same-plan renewal.
                        plan: btn.dataset.planName,
                        monthAmount: parseFloat(btn.dataset.monthAmount),
                        yearAmount: parseFloat(btn.dataset.yearAmount),
                        expireDate: null,
                        status: 'Upgrade'
                    };

                    if (typeof handleSubscriptionRenewal === 'function') {
                        handleSubscriptionRenewal(planData);
                    } else {
                        console.warn('handleSubscriptionRenewal() not found - check js/functions.js is loaded.');
                    }
                });
            });

            // TODO: point these at your real receipt endpoints
            function viewPaymentRecord(paymentId) {
                console.warn('viewPaymentRecord() not wired up yet for payment', paymentId);
            }

            function downloadPaymentReceipt(paymentId) {
                console.warn('downloadPaymentReceipt() not wired up yet for payment', paymentId);
            }
        </script>

    </body>

    </html>
<?php
}

?>