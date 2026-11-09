<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {

    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

    $class_id = 0;

    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['plan-id']) && !empty($_GET['plan-id'])) {

        $plan_id = $_GET["plan-id"];

        $query = "SELECT * FROM `users_has_payment_plans` 
        INNER JOIN `payment_plans` ON `users_has_payment_plans`.`payment_plans_payment_plan_id` = `payment_plans`.`payment_plan_id`
        WHERE `users_has_payment_plan_id` = '" . $plan_id . "'";

        $plan = Database::search($query);
        if ($plan->num_rows == 0) {
            header("Location: ./classes");
        } else {
            $planData = $plan->fetch_assoc();
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Checkout - Tutor Dashboard | <?= Project::getBaseName(); ?></title>

                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
            </head>

            <body onload="initializeCheckout()">
                <style>
                    .payment-page-wrapper {
                        position: relative;
                    }

                    .payment-page-header {
                        max-width: 760px;
                        margin-inline: auto;
                    }

                    .payment-mini-badge {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        padding: 7px 14px;
                        border-radius: 999px;
                        background: var(--light-theme);
                        color: var(--dark-theme);
                        border: 1px solid var(--line-color);
                        font-size: 12px;
                        font-weight: 800;
                        letter-spacing: .3px;
                        margin-bottom: 14px;
                    }

                    .payment-page-title {
                        font-size: clamp(2rem, 4vw, 3rem);
                        font-weight: 900;
                        color: var(--dark-color);
                        line-height: 1.1;
                        margin-bottom: 12px;
                    }

                    .payment-page-text {
                        color: var(--sub-text);
                        font-size: 15px;
                        line-height: 1.8;
                    }

                    .payment-card {
                        height: 100%;
                        background: linear-gradient(180deg, var(--custom-white) 0%, var(--white) 100%);
                        border: 1px solid var(--line-color);
                        border-radius: 28px;
                        padding: 28px;
                        box-shadow: 0 18px 60px rgba(15, 23, 42, 0.08);
                    }

                    .section-head {
                        display: flex;
                        align-items: flex-start;
                        justify-content: space-between;
                        gap: 12px;
                    }

                    .section-eyebrow {
                        font-size: 12px;
                        font-weight: 800;
                        letter-spacing: .3px;
                        color: var(--dark-theme);
                        margin-bottom: 6px;
                        text-transform: uppercase;
                    }

                    .section-title {
                        font-size: 22px;
                        font-weight: 900;
                        color: var(--dark-color);
                    }

                    .plan-status-badge {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        min-height: 34px;
                        padding: 6px 14px;
                        border-radius: 999px;
                        background: var(--light-orange);
                        color: var(--dark-orange);
                        font-size: 12px;
                        font-weight: 800;
                        white-space: nowrap;
                    }

                    .selected-plan-card {
                        padding: 20px;
                        border-radius: 22px;
                        background: linear-gradient(135deg, var(--light-theme), var(--custom-white));
                        border: 1px solid var(--line-color);
                    }

                    .selected-plan-top {
                        display: flex;
                        align-items: flex-start;
                        gap: 14px;
                    }

                    .selected-plan-icon {
                        width: 56px;
                        height: 56px;
                        border-radius: 18px;
                        background: linear-gradient(135deg, var(--dark-theme), var(--highlight-theme));
                        color: #fff;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        flex-shrink: 0;
                        box-shadow: 0 14px 28px rgba(0, 97, 210, 0.18);
                    }

                    .selected-plan-content {
                        flex: 1;
                        min-width: 0;
                    }

                    .selected-plan-name-row {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        flex-wrap: wrap;
                        margin-bottom: 8px;
                    }

                    .selected-plan-name {
                        font-size: 24px;
                        font-weight: 900;
                        color: var(--dark-color);
                    }

                    .selected-plan-badge {
                        padding: 5px 10px;
                        border-radius: 999px;
                        background: var(--light-blue);
                        color: var(--dark-blue);
                        font-size: 11px;
                        font-weight: 800;
                    }

                    .selected-plan-desc {
                        font-size: 14px;
                        line-height: 1.7;
                        color: var(--sub-text);
                    }

                    .selected-plan-meta {
                        display: grid;
                        grid-template-columns: repeat(3, 1fr);
                        gap: 12px;
                        margin-top: 18px;
                    }

                    .plan-meta-box {
                        padding: 14px;
                        border-radius: 16px;
                        background: var(--body-color);
                        border: 1px solid var(--line-color);
                    }

                    .meta-label {
                        display: block;
                        font-size: 11px;
                        font-weight: 700;
                        color: var(--sub-text);
                        margin-bottom: 4px;
                        text-transform: uppercase;
                    }

                    .meta-value {
                        display: block;
                        font-size: 14px;
                        font-weight: 800;
                        color: var(--dark-color);
                    }

                    .payment-method-card {
                        display: flex;
                        align-items: flex-start;
                        gap: 14px;
                        padding: 18px;
                        border-radius: 20px;
                        background: var(--body-color);
                        border: 1px solid var(--line-color);
                    }

                    .method-icon {
                        width: 52px;
                        height: 52px;
                        border-radius: 16px;
                        background: var(--light-blue);
                        color: var(--dark-blue);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        flex-shrink: 0;
                    }

                    .method-name {
                        font-size: 15px;
                        font-weight: 800;
                        color: var(--dark-color);
                        margin-bottom: 4px;
                    }

                    .method-text {
                        font-size: 13px;
                        line-height: 1.7;
                        color: var(--sub-text);
                    }

                    .premium-input {
                        min-height: 52px;
                        border-radius: 16px;
                        border: 1px solid var(--line-color);
                        background: var(--custom-white);
                        color: var(--dark-color);
                        padding-inline: 16px;
                        font-size: 14px;
                        font-weight: 600;
                        box-shadow: none !important;
                    }

                    .premium-input:focus {
                        border-color: var(--dark-theme);
                        box-shadow: 0 0 0 0.15rem rgba(0, 97, 210, 0.08) !important;
                        background: var(--custom-white);
                        color: var(--dark-color);
                    }

                    .premium-input-wrap {
                        position: relative;
                    }

                    .input-prefix {
                        position: absolute;
                        top: 50%;
                        left: 16px;
                        transform: translateY(-50%);
                        font-size: 13px;
                        font-weight: 800;
                        color: var(--sub-topic);
                        z-index: 2;
                    }

                    .premium-input.with-prefix {
                        padding-left: 58px;
                    }

                    .payment-upload-box {
                        border-radius: 20px;
                        border: 1px dashed var(--line-color);
                        background: var(--body-color);
                        overflow: hidden;
                    }

                    .upload-inner {
                        text-align: center;
                        padding: 28px 18px;
                        cursor: pointer;
                        transition: .2s ease;
                    }

                    .upload-inner:hover {
                        background: var(--light-theme);
                    }

                    .upload-icon {
                        width: 58px;
                        height: 58px;
                        margin: 0 auto 12px;
                        border-radius: 18px;
                        background: var(--light-blue);
                        color: var(--dark-blue);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }

                    .upload-title {
                        font-size: 15px;
                        font-weight: 800;
                        color: var(--dark-color);
                    }

                    .upload-text {
                        font-size: 13px;
                        color: var(--sub-text);
                        margin-top: 4px;
                    }

                    .upload-btn {
                        min-height: 42px;
                        padding: 0 18px;
                        border-radius: 999px;
                        background: linear-gradient(135deg, var(--dark-theme), var(--highlight-theme));
                        color: #fff;
                        border: none;
                        font-size: 13px;
                        font-weight: 800;
                    }

                    .upload-btn:hover {
                        color: #fff;
                    }

                    .selected-file-view {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 12px;
                        padding: 16px;
                        border-top: 1px dashed var(--line-color);
                        background: var(--custom-white);
                    }

                    .selected-file-left {
                        display: flex;
                        align-items: center;
                        gap: 12px;
                        min-width: 0;
                    }

                    .selected-file-icon {
                        width: 46px;
                        height: 46px;
                        border-radius: 14px;
                        background: var(--light-purple);
                        color: var(--dark-purple);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        flex-shrink: 0;
                    }

                    .selected-file-name {
                        font-size: 14px;
                        font-weight: 800;
                        color: var(--dark-color);
                        word-break: break-word;
                    }

                    .selected-file-meta {
                        font-size: 12px;
                        color: var(--sub-text);
                        margin-top: 2px;
                    }

                    .file-remove-btn {
                        width: 38px;
                        height: 38px;
                        border: none;
                        border-radius: 12px;
                        background: var(--light-red2);
                        color: var(--dark-red);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }

                    .payment-submit-btn {
                        min-height: 54px;
                        border-radius: 18px;
                        border: none;
                        background: linear-gradient(135deg, var(--dark-theme), var(--highlight-theme));
                        color: #fff;
                        font-size: 14px;
                        font-weight: 800;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        gap: 10px;
                        box-shadow: 0 18px 36px rgba(0, 97, 210, 0.18);
                    }

                    .payment-submit-btn:hover {
                        color: #fff;
                        transform: translateY(-1px);
                    }

                    .bank-hero-card {
                        display: flex;
                        align-items: center;
                        gap: 14px;
                        padding: 18px;
                        border-radius: 20px;
                        background: linear-gradient(135deg, var(--info-gradient-start), var(--info-gradient-end));
                        color: #fff;
                    }

                    .bank-hero-icon {
                        width: 54px;
                        height: 54px;
                        border-radius: 18px;
                        background: rgba(255, 255, 255, 0.12);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        flex-shrink: 0;
                    }

                    .bank-hero-title {
                        font-size: 17px;
                        font-weight: 900;
                    }

                    .bank-hero-subtitle {
                        font-size: 13px;
                        opacity: .85;
                        margin-top: 4px;
                    }

                    .bank-detail-list {
                        display: flex;
                        flex-direction: column;
                        gap: 12px;
                    }

                    .bank-detail-item {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 12px;
                        padding: 14px 16px;
                        border-radius: 16px;
                        background: var(--body-color);
                        border: 1px solid var(--line-color);
                    }

                    .bank-label {
                        font-size: 13px;
                        font-weight: 700;
                        color: var(--sub-text);
                    }

                    .bank-value {
                        font-size: 14px;
                        font-weight: 800;
                        color: var(--dark-color);
                        text-align: right;
                    }

                    .highlight-row {
                        background: linear-gradient(135deg, var(--light-green), var(--custom-white));
                    }

                    .amount-highlight {
                        color: var(--dark-green);
                        font-size: 16px;
                    }

                    .payment-note-card {
                        padding: 18px;
                        border-radius: 18px;
                        background: var(--light-yellow-green);
                        border: 1px solid var(--line-color);
                    }

                    .payment-note-title {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        font-size: 14px;
                        font-weight: 800;
                        color: var(--dark-color);
                        margin-bottom: 10px;
                    }

                    .payment-note-list {
                        padding-left: 18px;
                        color: var(--main-text-color);
                        font-size: 13px;
                        line-height: 1.8;
                    }

                    .period-pricing-card {
                        padding: 18px 20px;
                        border-radius: 22px;
                        background: linear-gradient(180deg, var(--custom-white) 0%, var(--white) 100%);
                        border: 1px solid var(--line-color);
                    }

                    .period-select-input {
                        min-height: 54px;
                        font-weight: 700;
                    }

                    .period-price-summary {
                        display: flex;
                        flex-direction: column;
                        align-items: flex-start;
                        gap: 6px;
                    }

                    .save-badge {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        min-height: 30px;
                        padding: 4px 12px;
                        border-radius: 999px;
                        background: var(--light-yellow);
                        color: var(--dark-color);
                        font-size: 12px;
                        font-weight: 800;
                    }

                    .current-price-line {
                        display: flex;
                        align-items: flex-end;
                        gap: 4px;
                        flex-wrap: wrap;
                    }

                    .current-price {
                        font-size: 32px;
                        line-height: 1;
                        font-weight: 900;
                        color: var(--dark-color);
                    }

                    .price-duration {
                        font-size: 15px;
                        font-weight: 700;
                        color: var(--main-text-color);
                        margin-bottom: 3px;
                    }

                    .old-price-line {
                        min-height: 20px;
                    }

                    .old-price {
                        font-size: 15px;
                        color: var(--sub-text);
                        text-decoration: line-through;
                        text-decoration-thickness: 1.5px;
                    }

                    @media (min-width: 768px) {
                        .period-price-summary {
                            align-items: flex-end;
                        }
                    }

                    @media (max-width: 767.98px) {
                        .payment-card {
                            padding: 20px;
                            border-radius: 22px;
                        }

                        .selected-plan-meta {
                            grid-template-columns: 1fr;
                        }

                        .payment-page-title {
                            font-size: 32px;
                        }

                        .bank-detail-item {
                            flex-direction: column;
                            align-items: flex-start;
                        }

                        .bank-value {
                            text-align: left;
                        }
                    }
                </style>
                <div class="container py-4 py-lg-5">
                    <div class="row justify-content-center">
                        <div class="col-12 col-xl-10">

                            <div class="payment-page-wrapper">

                                <!-- Header -->
                                <div class="payment-page-header text-center mb-4">
                                    <span class="payment-mini-badge">Secure Plan Payment</span>
                                    <h2 class="payment-page-title">Complete Your Plan Upgrade</h2>
                                    <p class="payment-page-text">
                                        Review your selected package, make the bank transfer, and upload your payment slip to activate your plan.
                                    </p>
                                </div>

                                <div class="row g-4">

                                    <!-- Left Side -->
                                    <div class="col-12 col-lg-7">
                                        <div class="payment-card payment-form-card">

                                            <div class="section-head">
                                                <div>
                                                    <div class="section-eyebrow">Selected Package</div>
                                                    <h5 class="section-title mb-0">Your Chosen Plan</h5>
                                                </div>
                                                <span class="plan-status-badge">Pending Payment</span>
                                            </div>

                                            <!-- Selected Plan -->
                                            <div class="selected-plan-card mt-4">
                                                <div class="selected-plan-top">
                                                    <div class="selected-plan-icon">
                                                        <?php
                                                        $icon = "solar:rocket-2-bold-duotone";
                                                        if ($planData['payment_plan_id'] == 1) {
                                                            $icon = "solar:stars-line-duotone";
                                                        } else if ($planData['payment_plan_id'] == 2) {
                                                            $icon = "solar:rocket-2-bold-duotone";
                                                        } else {
                                                            $icon = "solar:crown-star-bold-duotone";
                                                        }
                                                        ?>
                                                        <iconify-icon icon="<?php echo $icon; ?>" width="24"></iconify-icon>
                                                    </div>

                                                    <div class="selected-plan-content">
                                                        <div class="selected-plan-name-row">
                                                            <h4 class="selected-plan-name mb-0"><?php echo $planData['payment_plan']; ?> Plan</h4>
                                                            <?php
                                                            if ($planData['payment_plan_id'] == 2) {
                                                            ?>
                                                                <span class="selected-plan-badge">Most Popular</span>
                                                            <?php
                                                            }
                                                            ?>

                                                        </div>
                                                        <p class="selected-plan-desc mb-0">
                                                            <?php
                                                            if ($planData['payment_plan_id'] == 1) {
                                                                echo " Best for tutors starting with essential class and student management tools.";
                                                            } else if ($planData['payment_plan_id'] == 2) {
                                                                echo "Perfect for active tutors who need more communication, payments, and analytics tools.";
                                                            } else {
                                                                echo "  Built for tutors who want premium control, automation, and advanced academic operations.";
                                                            }
                                                            ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Payment Period -->
                                            <div class="section-head mt-4">
                                                <div>
                                                    <div class="section-eyebrow">Payment Period</div>
                                                    <h5 class="section-title mb-0">Select Payment Period</h5>
                                                </div>
                                            </div>

                                            <div class="period-pricing-card mt-3">
                                                <div class="row g-3 align-items-center">
                                                    <div class="col-12 col-md-6">
                                                        <label class="text-14 main-text mb-2 d-block">Period</label>
                                                        <select class="form-select premium-input period-select-input" id="payment-period" onchange="chnagePeriodPrice()">
                                                            <?php

                                                            $monthPrice = 0;
                                                            $yearPrice = 0;
                                                            $periods = Database::search("SELECT * FROM `payment_plan_has_durations`
                                                            INNER JOIN `payment_durations` ON payment_plan_has_durations.payment_durations_payment_duration_id = payment_durations.payment_duration_id 
                                                            WHERE payment_plans_payment_plan_id = '" . $planData['payment_plan_id'] . "'
                                                            AND `payment_duration_id` != '1'");

                                                            while ($period = $periods->fetch_assoc()) {
                                                                if ($period['payment_duration_id'] == 2) {
                                                                    $monthPrice = $period['amount'];
                                                                }
                                                            
                                                                if ($period['payment_duration_id'] == 3) {
                                                                    $yearPrice = $period['amount'];
                                                                }
                                                            ?>
                                                                <option value="<?= $period['payment_duration_id'] ?>"
                                                                    <?= $period['payment_duration_id'] == 3 ? "selected" : "" ?>
                                                                    data-price="<?= $period['amount'] ?>" data-period="<?= $period['payment_duration'] ?>"><?= $period['payment_duration'] ?></option>
                                                            <?php
                                                            }

                                                            $yearFullPayment = $monthPrice * 12;
                                                            $discountAmount = $yearFullPayment - ($yearPrice * 12);
                                                            ?>

                                                        </select>
                                                        <span class="text-dark-red text-12" id="payment-period-feedback"></span>
                                                    </div>



                                                    <div class="col-12 col-md-6">
                                                        <div class="period-price-summary text-md-end">
                                                            <div class="save-badge" id="saveBadge">Save LKR <?= number_format($discountAmount) ?></div>

                                                            <div class="current-price-line">
                                                                <span class="current-price" id="currentPeriodPrice">LKR <?= number_format($yearPrice) ?></span>
                                                                <span class="price-duration">/mo</span>
                                                            </div>

                                                            <div class="old-price-line" id="oldPriceLine">
                                                            <span class="old-price" id="oldPeriodPrice">LKR <?= number_format($monthPrice) ?>/mo</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Payment Method -->
                                            <div class="section-head mt-4">
                                                <div>
                                                    <div class="section-eyebrow">Payment Method</div>
                                                    <h5 class="section-title mb-0">Bank Transfer</h5>
                                                </div>
                                            </div>

                                            <div class="payment-method-card mt-3">
                                                <div class="method-icon">
                                                    <iconify-icon icon="solar:card-transfer-bold-duotone" width="24"></iconify-icon>
                                                </div>
                                                <div class="method-content">
                                                    <div class="method-name">Direct Bank Transfer</div>
                                                    <div class="method-text">
                                                        Transfer the exact amount to the account below and upload your payment slip for verification.
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Payment Form -->
                                            <div class="row g-3 mt-1">
                                                <div class="col-12">
                                                    <label class="text-14 main-text required">Transfer Amount</label>
                                                    <div class="premium-input-wrap mt-2">
                                                        <span class="input-prefix">LKR</span>
                                                        <input type="text" class="form-control premium-input with-prefix" id="plan-payment-amount" value="5500" placeholder="Enter amount">
                                                    </div>
                                                    <span class="text-dark-red text-12" id="plan-payment-amount-feedback"></span>
                                                </div>

                                                <div class="col-12">
                                                    <label class="text-14 main-text required">Transfer Reference</label>
                                                    <input type="text" class="form-control premium-input mt-2" id="transfer-reference" placeholder="Enter bank reference number">
                                                    <span class="text-dark-red text-12" id="transfer-reference-feedback"></span>
                                                </div>

                                                <div class="col-12">
                                                    <label class="text-14 main-text required">Upload Payment Slip</label>

                                                    <div class="payment-upload-box mt-2" id="paymentUploadBox">
                                                        <input type="file" id="payment-slip" class="d-none" accept=".jpg,.jpeg,.png,.pdf">
                                                        <div class="upload-inner" onclick="document.getElementById('payment-slip').click();">
                                                            <div class="upload-icon">
                                                                <iconify-icon icon="solar:upload-bold-duotone" width="28"></iconify-icon>
                                                            </div>
                                                            <div class="upload-title">Choose payment proof</div>
                                                            <div class="upload-text">
                                                                Upload JPG, PNG or PDF payment slip
                                                            </div>
                                                            <button type="button" class="btn upload-btn mt-3">Choose File</button>
                                                        </div>

                                                        <div class="selected-file-view d-none" id="selectedFileView">
                                                            <div class="selected-file-left">
                                                                <div class="selected-file-icon">
                                                                    <iconify-icon icon="solar:file-text-bold-duotone" width="22"></iconify-icon>
                                                                </div>
                                                                <div>
                                                                    <div class="selected-file-name" id="selectedFileName">payment-slip.jpg</div>
                                                                    <div class="selected-file-meta" id="selectedFileMeta">0 KB</div>
                                                                </div>
                                                            </div>
                                                            <button type="button" class="file-remove-btn" onclick="removeSelectedPaymentFile()">
                                                                <iconify-icon icon="solar:trash-bin-trash-bold-duotone" width="18"></iconify-icon>
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <span class="text-dark-red text-12" id="payment-slip-feedback"></span>
                                                </div>
                                            </div>

                                            <div class="d-flex gap-3 mt-4">
                                                <button class="btn payment-submit-btn w-100" onclick="submitPlanPayment(this)">
                                                    <iconify-icon icon="solar:shield-check-bold-duotone" width="20"></iconify-icon>
                                                    Submit Payment Request
                                                </button>
                                            </div>

                                        </div>
                                    </div>

                                    <!-- Right Side -->
                                    <div class="col-12 col-lg-5">
                                        <div class="payment-card bank-details-card">

                                            <div class="section-head">
                                                <div>
                                                    <div class="section-eyebrow">Bank Details</div>
                                                    <h5 class="section-title mb-0">Transfer Information</h5>
                                                </div>
                                            </div>

                                            <div class="bank-hero-card mt-4">
                                                <div class="bank-hero-icon">
                                                    <iconify-icon icon="solar:buildings-3-bold-duotone" width="26"></iconify-icon>
                                                </div>
                                                <div>
                                                    <div class="bank-hero-title">Commercial Bank</div>
                                                    <div class="bank-hero-subtitle">Use these account details for your payment</div>
                                                </div>
                                            </div>

                                            <div class="bank-detail-list mt-4">
                                                <div class="bank-detail-item">
                                                    <span class="bank-label">Bank Name</span>
                                                    <span class="bank-value">Commercial Bank</span>
                                                </div>
                                                <div class="bank-detail-item">
                                                    <span class="bank-label">Account Name</span>
                                                    <span class="bank-value">ENVIIZ Softwares (PVT) LTD</span>
                                                </div>
                                                <div class="bank-detail-item">
                                                    <span class="bank-label">Account Number</span>
                                                    <span class="bank-value">1000660461</span>
                                                </div>
                                                <div class="bank-detail-item">
                                                    <span class="bank-label">Branch</span>
                                                    <span class="bank-value">Piliyandala Branch</span>
                                                </div>
                                                <div class="bank-detail-item">
                                                    <span class="bank-label">Plan</span>
                                                    <span class="bank-value">Grow Plan</span>
                                                </div>
                                                <div class="bank-detail-item highlight-row">
                                                    <span class="bank-label">Payable Amount</span>
                                                    <span class="bank-value amount-highlight">LKR 5,500</span>
                                                </div>
                                            </div>

                                            <div class="payment-note-card mt-4">
                                                <div class="payment-note-title">
                                                    <iconify-icon icon="solar:danger-triangle-bold-duotone" width="18"></iconify-icon>
                                                    Important Note
                                                </div>
                                                <ul class="payment-note-list mb-0">
                                                    <li>Transfer the exact plan amount.</li>
                                                    <li>Add your bank reference number correctly.</li>
                                                    <li>Upload a clear payment slip for faster verification.</li>
                                                    <li>Your plan will be activated after payment review.</li>
                                                </ul>
                                            </div>

                                        </div>
                                    </div>

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