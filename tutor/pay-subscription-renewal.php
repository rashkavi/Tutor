<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsTutor()) {
    header("Location: ./index");
} else {

    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
    if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['renewal-id']) && !empty($_GET['renewal-id']))) {
        header("Location: ./billing");
    } else {
        $payment_id = $_GET['renewal-id'];

        $validPayment = Database::search("SELECT * FROM `users_has_payment_plans_payments` 
        INNER JOIN `users_has_payment_plans` ON `users_has_payment_plans_payments`.`users_has_payment_plans_users_has_payment_plan_id` = `users_has_payment_plans`.`users_has_payment_plan_id`
        INNER JOIN `payment_plans` ON `users_has_payment_plans`.`payment_plans_payment_plan_id` = `payment_plans`.`payment_plan_id`
        WHERE `users_has_payment_plans_payment_id`='" . $payment_id . "'
        AND `users_has_payment_plans`.`users_user_id`='" . $sessionUserObject['user_id'] . "'
        AND `payment_status_payment_status_id` = '1'");

        if ($validPayment->num_rows == 0) {
            header("Location: ./billing");
        } else {
            $paymentData = $validPayment->fetch_assoc();

            $fromDate = new DateTime($paymentData['from']);
            $toDate = new DateTime($paymentData['to']);
            $diff = $fromDate->diff($toDate);
            $pendingPaymentMonthCount = ($diff->y * 12) + $diff->m;


            $lastPayment = Database::search("SELECT * FROM `users_has_payment_plans_payments`
            WHERE `users_has_payment_plans_users_has_payment_plan_id` = '" . $paymentData['users_has_payment_plans_users_has_payment_plan_id'] . "'
            AND `payment_status_payment_status_id` = '3' ORDER BY `users_has_payment_plans_payment_id` DESC LIMIT 1")->fetch_assoc();

            $activeRenew = "7 Days Trial";
            $expireDate = new DateTime($paymentData['date_added']);
            $expireDate->modify('+7 days');

            if (!empty($lastPayment)) {
                $fromDate = new DateTime($lastPayment['from']);
                $toDate = new DateTime($lastPayment['to']);

                $diff = $fromDate->diff($toDate);

                $monthCount = ($diff->y * 12) + $diff->m;

                $activeRenew = $monthCount == 1 ? "One Month" : "12 Months";
                $expireDate = $toDate;
            }
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Pay Subscription Renewal Payment - Tutor Dashboard | <?= Project::getBaseName() ?></title>

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
                                                        <span class="main-topic lh-lg" data-i18n="">Subscription Renewal Payment</span>
                                                        <span class="text-14 sub-text lh-base mt-1" data-i18n="">Connect email, SMS, notifications, and external messaging services to streamline communication across the system.</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="container-fluid">
                                            <div class="row justify-content-center">
                                                <div class="col-12 p-0">
                                                    <div class="row g-4">
                                                        <!-- Left Side -->
                                                        <div class="col-12 col-xl-7">
                                                            <div class="component-theme p-4 h-100">
                                                                <div class="custom-alert p-3 mb-4">
                                                                    <div class="row g-3 align-items-center">
                                                                        <div class="col-12 col-md-8">
                                                                            <div class="text-18 text-dark-color fw-semibold mb-1" id="selectedPlanName">
                                                                                ENLIXO Tutor <?= $paymentData['payment_plan'] ?> Plan
                                                                            </div>
                                                                            <div class="text-14 sub-text mb-2" id="selectedPlanDesc">
                                                                                Flexible tools for tutors who need student management, billing, and communication support.
                                                                            </div>

                                                                            <div class="d-flex flex-wrap gap-2">
                                                                                <span class="table-btn table-btn-success px-3 py-1">Active Renewal</span>
                                                                                <span class="table-btn table-btn-primary px-3 py-1" id="selectedPlanPeriodBadge"><?= $activeRenew ?></span>
                                                                            </div>
                                                                        </div>

                                                                        <div class="col-12 col-md-4 text-md-end">
                                                                            <div class="text-12 sub-text mb-1">Expires On</div>
                                                                            <div class="text-16 text-dark-color fw-semibold" id="selectedExpireDate"><?= date('M d, Y', strtotime($expireDate->format('Y-m-d'))) ?></div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="row g-3 mb-4">
                                                                    <div class="col-12 col-md-6">
                                                                        <label class="text-14 main-text required">Select Period</label>
                                                                        <select class="form-select custom-select mt-2" id="paymentPeriod" disabled>
                                                                            <option value="1" <?= $pendingPaymentMonthCount == 1 ? "selected" : "" ?>>1 Month</option>
                                                                            <option value="12" <?= $pendingPaymentMonthCount == 12 ? "selected" : "" ?>>12 Months</option>
                                                                        </select>
                                                                        <span id="payment-period-feedback" class="text-dark-red text-12"></span>
                                                                    </div>

                                                                    <div class="col-12 col-md-6">
                                                                        <label class="text-14 main-text required">Payable Amount</label>
                                                                        <input type="text" class="form-control custom-input mt-2" id="paidAmount" value="<?= $paymentData['paid_amount'] ?>" readonly>
                                                                        <span id="payment-amount-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                </div>

                                                                <div class="row g-3">
                                                                    <div class="col-12 col-md-6">
                                                                        <label class="text-14 main-text required">Reference Number</label>
                                                                        <input type="text" class="form-control custom-input mt-2" id="paymentReference" value="<?= $paymentData['refference'] ?>" readonly>
                                                                        <span id="payment-reference-feedback" class="text-dark-red text-12"></span>
                                                                    </div>

                                                                    <div class="col-12 col-md-6">
                                                                        <label class="text-14 main-text required">Payment Method</label>
                                                                        <select class="form-select custom-select mt-2" id="paymentMethod">
                                                                            <option value="1" selected>Bank Transfer</option>
                                                                            <option value="2">Bank Deposit</option>
                                                                        </select>
                                                                        <span id="payment-method-feedback" class="text-dark-red text-12"></span>
                                                                    </div>
                                                                </div>

                                                                <div class="text-18 text-dark-color fw-semibold my-3">Payment Slip</div>

                                                                <div class="bl-solid p-4 bg-content-body mb-3">
                                                                    <div class="row g-3 align-items-center">
                                                                        <div class="col-12">
                                                                            <div class="text-16 text-dark-color fw-semibold mb-1">Upload Payment Proof</div>
                                                                            <div class="text-14 sub-text">
                                                                                Upload a clear bank transfer or bank deposit slip in JPG, PNG, or PDF format.
                                                                            </div>
                                                                        </div>

                                                                        <div class="col-12 d-flex flex-column mt-3">
                                                                            <div class="p-5 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                                                <img src="../assets/imgs/icons/cloud.png" alt="cloud">
                                                                                <span>Drag & Drop your files here</span>
                                                                                <span>OR</span>
                                                                                <button class="custom-btn px-4" id="file-selector">Select Files</button>
                                                                                <input type="file" class="d-none" id="file-selector-input" multiple accept=".pdf, .png, .jpg">
                                                                                <div class="col-12 px-1 mt-4">
                                                                                    <div class="row modalFileList" id="list-section">
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <span id="attachment-feedback" class="text-dark-red text-12"></span>
                                                                            <!-- File upload input field for PDF, Word, and PowerPoint files -->
                                                                        </div>
                                                                    </div>
                                                                    <div id="payment-slip-feedback" class="text-dark-red text-12 mt-2 d-block"></div>
                                                                </div>

                                                                <div class="custom-alert p-3 mt-4">
                                                                    <div class="text-14 text-dark-color fw-semibold mb-2">
                                                                        Important Guidelines
                                                                    </div>

                                                                    <ul class="mb-0 ps-3 text-13 sub-text">
                                                                        <li class="mb-1">
                                                                            Please transfer the <span class="fw-semibold text-dark-color">exact renewal amount</span> displayed in the payment summary.
                                                                        </li>

                                                                        <li class="mb-1">
                                                                            <span class="fw-semibold text-dark-color">Subscription payments are non-refundable</span> once the verification process has started.
                                                                        </li>

                                                                        <li class="mb-1">
                                                                            Make sure the <span class="fw-semibold text-dark-color">bank reference number</span> is clearly visible on your uploaded payment slip.
                                                                        </li>

                                                                        <li class="mb-1">
                                                                            Upload a <span class="fw-semibold text-dark-color">clear and readable payment receipt</span> to avoid verification delays.
                                                                        </li>

                                                                        <li>
                                                                            Incorrect or missing information may delay the activation of your subscription renewal.
                                                                        </li>
                                                                    </ul>
                                                                </div>

                                                            </div>
                                                        </div>

                                                        <!-- Right Side -->
                                                        <div class="col-12 col-xl-5">
                                                            <div class="component-theme p-4 h-100">

                                                                <div class="text-18 text-dark-color fw-semibold mb-3">Account Details</div>

                                                                <div class="bl-solid p-3 bg-content-body mb-4">
                                                                    <div class="row g-3">
                                                                        <div class="col-12">
                                                                            <div class="text-12 sub-text">Bank Name</div>
                                                                            <div class="text-16 text-dark-color fw-semibold">Commercial Bank</div>
                                                                        </div>

                                                                        <div class="col-12">
                                                                            <div class="text-12 sub-text">Account Name</div>
                                                                            <div class="text-16 text-dark-color fw-semibold">ENVIIZ Softwares (PVT) LTD</div>
                                                                        </div>

                                                                        <div class="col-12">
                                                                            <div class="text-12 sub-text">Account Number</div>
                                                                            <div class="text-16 text-dark-color fw-semibold">1000660461</div>
                                                                        </div>

                                                                        <div class="col-12">
                                                                            <div class="text-12 sub-text">Branch</div>
                                                                            <div class="text-16 text-dark-color fw-semibold">Piliyandala Branch</div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="text-18 text-dark-color fw-semibold mb-3">Payment Summary</div>

                                                                <div class="bl-solid p-3 bg-content-body">
                                                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                                        <span class="text-14 sub-text">Plan</span>
                                                                        <span class="text-14 text-dark-color fw-semibold" id="summaryPlanName"><?= $paymentData['payment_plan'] ?> Plan</span>
                                                                    </div>

                                                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                                        <span class="text-14 sub-text">Period</span>
                                                                        <span class="text-14 text-dark-color fw-semibold" id="summaryPeriod"><?= $pendingPaymentMonthCount ?> Months</span>
                                                                    </div>

                                                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                                        <span class="text-14 sub-text">Monthly Price</span>
                                                                        <span class="text-14 text-dark-color fw-semibold" id="summaryMonthlyPrice">LKR <?= number_format(($paymentData['paid_amount'] / $pendingPaymentMonthCount), 2) ?>/mo</span>
                                                                    </div>

                                                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                                        <span class="text-14 sub-text">Regular Price</span>
                                                                        <span class="text-14 sub-text text-decoration-line-through" id="summaryOldPrice">LKR <?= number_format((($paymentData['paid_amount'] + $paymentData['discount_amount']) / $pendingPaymentMonthCount), 2) ?>/mo</span>
                                                                    </div>

                                                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                                        <span class="text-14 sub-text">Discount</span>
                                                                        <span class="text-14 text-dark-green fw-semibold" id="summaryDiscount">LKR <?= number_format($paymentData['discount_amount'], 2) ?></span>
                                                                    </div>

                                                                    <div class="d-flex justify-content-between align-items-center pt-3">
                                                                        <span class="text-18 text-dark-color fw-semibold">Total Amount</span>
                                                                        <span class="text-20 dark-theme fw-semibold" id="summaryTotal">LKR <?= number_format($paymentData['paid_amount'], 2) ?></span>
                                                                    </div>
                                                                </div>

                                                                <div class="d-grid gap-2 mt-4">
                                                                    <button class="custom-btn w-100" onclick="submitSubscriptionRenewalPayment(this,<?= $payment_id ?>)">
                                                                        Submit Payment Request
                                                                    </button>
                                                                    <button class="btn btn-light bl-solid br-0 w-100" type="button">
                                                                        Cancel
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
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