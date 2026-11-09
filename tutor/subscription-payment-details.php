<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsTutor()) {
    header("Location: ./index");
} else {

    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
    if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['payment-id']) && !empty($_GET['payment-id']))) {
        header("Location: ./billing");
    } else {
        $payment_id = $_GET['payment-id'];

        $validPayment = Database::search("SELECT * FROM `users_has_payment_plans_payments` 
        INNER JOIN `users_has_payment_plans` ON `users_has_payment_plans_payments`.`users_has_payment_plans_users_has_payment_plan_id` = `users_has_payment_plans`.`users_has_payment_plan_id`
        INNER JOIN `payment_plans` ON `users_has_payment_plans`.`payment_plans_payment_plan_id` = `payment_plans`.`payment_plan_id`
       INNER JOIN `payment_status` ON `users_has_payment_plans_payments`.`payment_status_payment_status_id` = `payment_status`.`payment_status_id`
        WHERE `users_has_payment_plans_payment_id`='" . $payment_id . "'
        AND `users_has_payment_plans`.`users_user_id`='" . $sessionUserObject['user_id'] . "'
        AND `payment_status_payment_status_id` != '1'");

        if ($validPayment->num_rows == 0) {
            header("Location: ./billing");
        } else {
            $paymentData = $validPayment->fetch_assoc();

            $fromDate = new DateTime($paymentData['from']);
            $toDate = new DateTime($paymentData['to']);
            $diff = $fromDate->diff($toDate);
            $pendingPaymentMonthCount = ($diff->y * 12) + $diff->m;

?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Subscription Payment - Tutor Dashboard | <?= Project::getBaseName() ?></title>

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
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-lg-8">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n="">Subscription Payment Details</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./billing">Billing</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./subscription-payments">Subscription Payments</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Subscription Payment Details</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="row p-4 m-0 g-0 component-theme w-100">
                                                <div class="col-12">
                                                    <div class="payment-timeline">
                                                        <div class="timeline-progress">
                                                            <?php
                                                            $query = "SELECT * FROM `payment_status`";
                                                            $progress = 0;
                                                            $progressColor = "";
                                                            switch ($paymentData['payment_status_id']) {
                                                                case 2:
                                                                    $progressColor = "completed";
                                                                    $progress = 100 / 3 * 1;
                                                                    $query .= " WHERE `payment_status_id` IN (1,2,3)";
                                                                    break;
                                                                case 3:
                                                                    $progressColor = "accepted";
                                                                    $progress = 100 / 3 * 3;
                                                                    $query .= " WHERE `payment_status_id` IN (1,2,3)";
                                                                    break;

                                                                case 4:
                                                                    $progressColor = "reject";
                                                                    $progress = 100 / 3 * 3;
                                                                    $query .= " WHERE `payment_status_id` IN (1,2,4)";
                                                                    break;
                                                            }

                                                            ?>
                                                            <div class="progress-bar <?= $progressColor ?>" style="width: <?= $progress ?>%"></div>
                                                        </div>
                                                        <div class="timeline-items">
                                                            <?php

                                                            $icons = ["majesticons:clock", "material-symbols:pending-outline", "mdi:snowflake-approve", "fontisto:close", "material-symbols:pending-outline", "mdi:snowflake-approve", "fontisto:close", "mdi:approve"];

                                                            $paymentStatus = Database::search($query);
                                                            while ($status = $paymentStatus->fetch_assoc()) {
                                                                $timelineItemColor = "";
                                                                $bubbleAction = "";

                                                                if ($paymentData['payment_status_id'] >= $status['payment_status_id']) {
                                                                    if ($paymentData['payment_status_id'] == 4) {
                                                                        $timelineItemColor = "reject";
                                                                    } else if ($paymentData['payment_status_id'] == 3) {
                                                                        $timelineItemColor = "accepted";
                                                                    } else {
                                                                        if ($paymentData['payment_status_id']  ==  $status['payment_status_id']) {
                                                                            $bubbleAction = "pulse";
                                                                        } else {
                                                                            $timelineItemColor = "completed";
                                                                        }
                                                                    }
                                                                }

                                                            ?>
                                                                <div class="timeline-item <?= $timelineItemColor ?>">
                                                                    <div class="timeline-bubble <?= $bubbleAction ?>">
                                                                        <iconify-icon icon="<?= $icons[$status['payment_status_id'] - 1] ?>" width="25" height="25"></iconify-icon>
                                                                    </div>
                                                                    <div class="timeline-label">
                                                                        <span><?= $status['payment_status'] ?></span>
                                                                    </div>
                                                                </div>
                                                            <?php

                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="payment-card-container mt-4">
                                                    <div class="payment-card">
                                                        <!-- Background elements -->
                                                        <div class="card-background"></div>

                                                        <!-- Card content -->
                                                        <div class="card-content">
                                                            <div class="payment-header reference-section">
                                                                <!-- Reference on left -->
                                                                <div class="reference-section">
                                                                    <span class="reference-label">PAYMENT REFERENCE</span>
                                                                    <h2 class="reference-number"><?= $paymentData["refference"]; ?></h2>
                                                                    <hr>
                                                                </div>

                                                                <!-- Amount on right -->
                                                                <div class="amount-section">
                                                                    <div class="amount-display">
                                                                        <span class="amount-label">Total Paid Amount</span>
                                                                        <span class="amount-value">LKR <?= number_format($paymentData['paid_amount'], 2); ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="details-row d-flex flex-column flex-lg-row">
                                                                <!-- Date -->
                                                                <div class="detail-item">
                                                                    <div class="detail-icon">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                            <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                        </svg>
                                                                    </div>
                                                                    <div class="detail-content">
                                                                        <span class="detail-label">Plan</span>
                                                                        <span class="detail-value"><?= $paymentData['payment_plan'] ?></span>
                                                                    </div>
                                                                </div>

                                                                <!-- Type -->
                                                                <div class="detail-item">
                                                                    <div class="detail-icon">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                            <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                            <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                        </svg>
                                                                    </div>
                                                                    <div class="detail-content">
                                                                        <span class="detail-label">Date Paid</span>
                                                                        <span class="detail-value"><?= $paymentData['date_paid'] ?></span>
                                                                    </div>
                                                                </div>

                                                                <div class="detail-item">
                                                                    <div class="detail-icon">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                            <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                        </svg>
                                                                    </div>
                                                                    <div class="detail-content">
                                                                        <span class="detail-label">Period</span>
                                                                        <span class="detail-value"><?= $pendingPaymentMonthCount ?> Months</span>
                                                                    </div>
                                                                </div>



                                                            </div>


                                                        </div>
                                                    </div>
                                                </div>

                                                <?php

                                                $paymentSlips = Database::search("SELECT * FROM `users_payments_slips` WHERE `users_has_payment_plans_payment_id`='" . $payment_id . "'");
                                                if ($paymentSlips->num_rows > 0) {
                                                ?>
                                                    <div class="col-12 mt-4">
                                                        <div class="row">
                                                            <small class="sub-topic">Payment Slips</small>
                                                            <?php
                                                            while ($data = $paymentSlips->fetch_assoc()) {
                                                                if (!empty($data["slip"]) && file_exists(Project::getAssetsPath() . "/uploads/users-payment-slips/" . $data["slip"])) {
                                                                    $extension = strtolower(pathinfo($data["slip"], PATHINFO_EXTENSION));

                                                                    if ($extension === "pdf") {
                                                                        $image = "pdf.png";
                                                                    } else {
                                                                        $image = "image.png";
                                                                    }
                                                            ?>
                                                                    <div class="col-12 col-lg-6 my-3 file-item cursor-pointer" onclick="window.open('<?= Project::getAssetsPath() . "/uploads/users-payment-slips/" . $data["slip"] ?>', '_blank')">
                                                                        <div class="p-3 bl-solid">
                                                                            <div class="d-flex">
                                                                                <img src="<?= Project::getAssetsPath() ?>/imgs/icons/<?php echo $image ?>" alt="Payment Slip" class="h-48 w-auto">
                                                                                <div class="d-flex flex-column px-4">
                                                                                    <span class="name"><?php echo $data["slip"] ?></span>
                                                                                    <span class="text-muted text-12">Click to view</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                            <?php
                                                                }
                                                            }
                                                            ?>

                                                        </div>
                                                    </div>
                                                <?php
                                                }

                                                ?>

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
                                        Complete Payment
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
                <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/fileChooser.js?v=<?= Project::getVersion() ?>"></script>

            </body>

            </html>
<?php
        }
    }
}

?>