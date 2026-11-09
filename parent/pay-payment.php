<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsParent()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Parent');

    if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['payment-id']) && !empty($_GET['payment-id']))) {
        header("Location: ./payments");
    } else {
        $payment_id = $_GET["payment-id"];

        $query = "
        SELECT 
            `cpp`.`student_has_payment_id`,
         
            `cpp`.`discount`,
            `cpp`.`discount_type_discount_type_id`,
            `cpp`.`due_date`,
            `cpp`.`amount`,
            `cpp`.`refference`,
            `cpp`.`date_added`,

            `ps`.`payment_status`,
            `ps`.`payment_status_id`,
          
            `pm`.`payment_method`,
            `pm`.`payment_method_id`,
            `cpp`.`amount`,
            `cpp`.`date_paid`,
        
            `ld`.`first_name`,
            `ld`.`last_name`,

            `c`.`class_name`,

            `dt`.`discount_type`,
            `dt`.`discount_type_id`,
            
            `s`.`profile_img`,
            `s`.`student_id` AS `aId`,
           SUM(`penalty`.`amount`) AS `panelty_charge`
                                                                              
        FROM `student_has_payments` `cpp`
       
        INNER JOIN `payment_status` `ps` 
            ON `cpp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
        LEFT JOIN `payment_method` `pm` 
            ON `cpp`.`payment_method_payment_method_id` = `pm`.`payment_method_id`
        INNER JOIN `students_has_classes` `shi` 
            ON `cpp`.`students_has_class_students_has_class_id` = `shi`.`students_has_class_id`
        INNER JOIN `classes` `c` 
            ON `shi`.`classes_class_id` = `c`.`class_id`
        INNER JOIN `students` `s` 
            ON `shi`.`students_student_id` = `s`.`student_id`
        INNER JOIN `login_details` `ld`
            ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`    
       
        INNER JOIN `discount_type` `dt`
            ON `cpp`.`discount_type_discount_type_id` = `dt`.`discount_type_id`                                                                       
       LEFT JOIN student_has_penalty_payments penalty
            ON penalty.student_has_payments_student_has_payment_id = cpp.student_has_payment_id
       WHERE  `cpp`.`student_has_payment_id`='" . $payment_id . "'
        AND `cpp`.`payment_status_payment_status_id` = 1
        ";

        $payment = Database::search($query);

        if ($payment->num_rows == 0) {
            header("Location: ./payments");
        } else {
            $payment = $payment->fetch_assoc();

            $discount = $payment["discount"];
            if ($payment["discount_type_discount_type_id"] == 3) {
                $discount = $payment["amount"] * ($payment["discount"] / 100);
            }

            if ($payment['panelty_charge'] > 0) {
                $isHasPaneltyCharge = true;
            } else {
                $isHasPaneltyCharge = false;
            }
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Pay Payment - Student Portal | <?= Project::getBaseName() ?></title>
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css">
                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
            </head>

            <body class="overflow-hidden">
                <div class="notifications"></div>
                <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                    <div id="appContainer" class="row p-0">
                        <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                            <?php
                            include "includes/side-menu.php";
                            load_side_menu::load("Payments");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 align-content-center m-0">
                            <div class="row p-0 m-0 g-0 h-100">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "../includes/header.php";
                                    selectItem::setSelected("Payments");
                                    ?>
                                </div>
                                <div class="container p-4 m-0" id="skeleton-list">
                                    <?php
                                    include '../includes/components/skeleton-header.php';
                                    include '../includes/components/skeleton-add-edit.php'; ?>
                                </div>
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 py-4 px-4 pe-4 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <span class="main-topic lh-lg">Pay Now</span>
                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                    <ol class="breadcrumb m-0">
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./payments'>Payments</a></li>
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./course-payments'>Course Payments</a></li>
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer">Pay Now</a></li>
                                                    </ol>
                                                </nav>
                                            </div>
                                        </div>

                                        <div class="payment-card-container">
                                            <div class="payment-card">
                                                <!-- Background elements -->
                                                <div class="card-background"></div>

                                                <!-- Card content -->
                                                <div class="card-content">
                                                    <div class="payment-header">
                                                        <!-- Reference on left -->
                                                        <div class="reference-section">
                                                            <span class="reference-label">PAYMENT REFERENCE</span>
                                                            <h2 class="reference-number"><?= $payment["refference"]; ?></h2>
                                                            <hr>
                                                        </div>

                                                        <!-- Amount on right -->
                                                        <div class="amount-section">
                                                            <div class="amount-display">
                                                                <span class="amount-label">Total Payable Amount</span>
                                                                <?php
                                                                if ($isHasPaneltyCharge) {
                                                                ?>
                                                                    <span class="amount-value"><?= number_format($payment["amount"] - $discount + $paneltyChargeData["amount"]); ?></span>
                                                                <?php
                                                                } else {
                                                                ?>
                                                                    <span class="amount-value"><?= number_format($payment["amount"] - $discount); ?></span>
                                                                <?php
                                                                }
                                                                ?>

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
                                                                <span class="detail-label">Payment Scheduled Month</span>
                                                                <?= date("F Y", strtotime($payment["date_added"])) ?>
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <!-- Method -->
                                                        <div class="detail-item">
                                                            <div class="detail-icon">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                    <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v1h14V4a1 1 0 0 0-1-1H2zm13 4H1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7z" />
                                                                    <path d="M2 10a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1z" />
                                                                </svg>
                                                            </div>
                                                            <div class="detail-content">
                                                                <span class="detail-label">Payment Method</span>
                                                                <span class="detail-value"><?= $payment["payment_method"] == null ? 'N/A' : $payment["payment_method"]; ?></span>
                                                            </div>
                                                        </div>

                                                        <!-- Discount -->
                                                        <div class="detail-item">
                                                            <div class="detail-icon">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                    <path d="M2 2a1 1 0 0 1 1-1h4.586a1 1 0 0 1 .707.293l7 7a1 1 0 0 1 0 1.414l-4.586 4.586a1 1 0 0 1-1.414 0l-7-7A1 1 0 0 1 2 6.586V2zm3.5 4a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z" />
                                                                    <path d="M1.293 7.793A1 1 0 0 1 1 7.086V2a1 1 0 0 0-1 1v4.586a1 1 0 0 0 .293.707l7 7a1 1 0 0 0 1.414 0l.043-.043-7.457-7.457z" />
                                                                </svg>
                                                            </div>
                                                            <div class="detail-content">
                                                                <span class="detail-label">Discount</span>
                                                                <span class="detail-value">
                                                                    <?php
                                                                    if ($payment["discount_type_id"] == 1) {
                                                                        echo "No Discount";
                                                                    } else if ($payment["discount_type_id"] == 2) {
                                                                        echo $payment["code"] . number_format($payment["discount"], $payment["decimal_places"]) . " | " . $payment["discount_type"];
                                                                    } else {
                                                                        echo  number_format($payment["discount"]) . "% | " . $payment["discount_type"];
                                                                    }
                                                                    ?>

                                                                </span>
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
                                                                <span class="detail-label">Paid Date</span>
                                                                <span class="detail-value"><?= $payment["date_paid"] ?? '--/--/----'; ?></span>
                                                            </div>
                                                        </div>

                                                        <!-- Type -->
                                                        <div class="detail-item">
                                                            <div class="detail-icon">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16">
                                                                    <path fill="none" stroke="currentColor" stroke-linejoin="round" stroke-miterlimit="10" d="M6 10h2.5c.55 0 1-.45 1-1s-.45-1-1-1h-1c-.55 0-1-.45-1-1s.45-1 1-1H10M8 4.5v1.167M8 9.5v2M14.5 8a6.5 6.5 0 1 1-13 0a6.5 6.5 0 0 1 13 0Z" stroke-width="1" />
                                                                </svg>
                                                            </div>
                                                            <div class="detail-content">
                                                                <span class="detail-label">Amount</span>
                                                                <span class="detail-value"><?= number_format($payment["amount"]); ?></span>
                                                            </div>
                                                        </div>

                                                        <!-- Method -->
                                                        <div class="detail-item">
                                                            <div class="detail-icon">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                                                    <g fill="none" stroke="currentColor" stroke-width="1">
                                                                        <path stroke-width="2" d="M10.51 3.665a2 2 0 0 1 2.98 0l.7.782a2 2 0 0 0 1.601.663l1.05-.058a2 2 0 0 1 2.107 2.108l-.058 1.049a2 2 0 0 0 .663 1.6l.782.7a2 2 0 0 1 0 2.981l-.782.7a2 2 0 0 0-.663 1.601l.058 1.05a2 2 0 0 1-2.108 2.107l-1.049-.058a2 2 0 0 0-1.6.663l-.7.782a2 2 0 0 1-2.981 0l-.7-.782a2 2 0 0 0-1.601-.663l-1.05.058a2 2 0 0 1-2.107-2.108l.058-1.049a2 2 0 0 0-.663-1.6l-.782-.7a2 2 0 0 1 0-2.981l.782-.7a2 2 0 0 0 .663-1.601l-.058-1.05A2 2 0 0 1 7.16 5.053l1.049.058a2 2 0 0 0 1.6-.663z" />
                                                                        <path stroke-linejoin="round" stroke-width="3" d="M9.5 9.5h.01v.01H9.5zm5 5h.01v.01h-.01z" />
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 9l-6 6" />
                                                                    </g>
                                                                </svg>
                                                            </div>
                                                            <div class="detail-content">
                                                                <span class="detail-label">Discount</span>
                                                                <span class="detail-value"><?= number_format($discount); ?></span>
                                                            </div>
                                                        </div>

                                                        <!-- Discount -->
                                                        <div class="detail-item">
                                                            <div class="detail-icon">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 2048 2048">
                                                                    <path fill="currentColor" d="M1792 384h-128V256H475l768 768l-768 768h1189v-128h128v256H256v-91l805-805l-805-805v-91h1536z" />
                                                                </svg>
                                                            </div>
                                                            <div class="detail-content">
                                                                <span class="detail-label">Payable Amount</span>
                                                                <span class="detail-value"><?= number_format($payment["amount"] - $payment["discount"]); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <?php
                                                    if ($isHasPaneltyCharge) {
                                                        date_default_timezone_set('Asia/Colombo');
                                                        $date = new DateTime($payment['date_added']);
                                                        $date->modify('last day of this month');
                                                        $date->setTime(23, 59, 59); // last time of the day

                                                        $lastDay = $date->format('M d, Y H:i a');

                                                        $now = new DateTime();

                                                        $diff = $now->diff($date);
                                                    ?>
                                                        <hr>
                                                        <span class="reference-label">PANELTY PAYMENT</span>
                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                            <div class="detail-item">
                                                                <div class="detail-icon">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                        <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v1h14V4a1 1 0 0 0-1-1H2zm13 4H1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7z" />
                                                                        <path d="M2 10a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1z" />
                                                                    </svg>
                                                                </div>
                                                                <div class="detail-content">
                                                                    <span class="detail-label">Your Payment Scheduled Date</span>
                                                                    <span class="detail-value"><?= date("M d, Y", strtotime($payment['date_added'])); ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="detail-item">
                                                                <div class="detail-icon">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                        <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v1h14V4a1 1 0 0 0-1-1H2zm13 4H1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7z" />
                                                                        <path d="M2 10a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1z" />
                                                                    </svg>
                                                                </div>
                                                                <div class="detail-content">
                                                                    <span class="detail-label">Payment Deadline</span>
                                                                    <span class="detail-value"><?= $lastDay ?></span>
                                                                </div>
                                                            </div>

                                                            <div class="detail-item">
                                                                <div class="detail-icon">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                        <path d="M11 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6zM5 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H5z" />
                                                                        <path d="M8 14a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
                                                                    </svg>
                                                                </div>
                                                                <div class="detail-content">
                                                                    <span class="detail-label">Days Overdue</span>
                                                                    <span class="detail-value"><?= $diff->days ?> days</span>
                                                                </div>
                                                            </div>

                                                            <!-- Date -->
                                                            <div class="detail-item">
                                                                <div class="detail-icon">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                        <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                    </svg>
                                                                </div>
                                                                <div class="detail-content">
                                                                    <span class="detail-label">Panelty Charge</span>
                                                                    <span class="detail-value"><?= number_format($paneltyChargeData['amount']); ?></span>
                                                                </div>
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
                                                <div class="card-background-cancelled"></div>
                                                <div class="card-content">
                                                    <div class="details-row d-flex flex-column flex-lg-row">
                                                        <!-- Date -->
                                                        <div class="detail-item">
                                                            <div class="detail-icon">
                                                                <iconify-icon id="sort-icon" icon="hugeicons:note" width="24" height="24"></iconify-icon>
                                                            </div>
                                                            <div class="detail-content">
                                                                <strong>Important:</strong><br>
                                                                When making your payment, please <strong>clearly include your Payment Reference Number</strong>
                                                                (<span class="ref-highlight"><?= $payment["refference"]; ?></span>)
                                                                in the payment description or remarks field.
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 py-4 px-4 pe-4 component-theme mt-4">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12">
                                                    <div class="row g-4">
                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                            <label for="">Payment Method</label>
                                                            <select name="payment_method" class="form-select custom-select mt-2" id="payment-method">
                                                                <option value="0">- Select -</option>
                                                                <?php
                                                                $methods = Database::search("SELECT * FROM `payment_method`");
                                                                while ($method = $methods->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?= $method['payment_method_id'] ?>"><?= $method['payment_method'] ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span class="text-12 text-danger" id="method-feedback"></span>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column">
                                                            <label for="" class="text-14 main-text">Payment Slip</label>
                                                            <div class="p-2 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                                <img src="<?= Project::getAssetsPath() ?>/imgs/icons/cloud.png" alt="cloud">
                                                                <span>Drag & Drop your files here</span>
                                                                <span>OR</span>
                                                                <button class="custom-btn px-4" id="file-selector">Browse Files</button>
                                                                <input type="file" class="d-none" id="file-selector-input" accept=".pdf,image/*" multiple>
                                                                <div class="col-12 px-1 mt-4">
                                                                    <div class="row" id="list-section"></div>
                                                                </div>
                                                            </div>
                                                            <div class="mt-2" id="file-feedback"></div>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column justify-content-end align-items-end">
                                                            <div class="col-12 d-flex justify-content-end">
                                                                <button class="custom-btn col-12 col-lg-4" onclick="savePayment('<?php echo $payment_id ?>')">Save Payment</button>
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
                <!-- cover crop modal => Start -->
                <div id="crop-modal" tabindex="-1" aria-hidden="true" class="modal fade" data-backdrop="static">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Crop Image</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center d-flex  align-items-center justify-content-center">
                                <img id="image" class="img-fluid bg-black" style="width: 75%;height: auto;" />
                            </div>
                            <div class="modal-footer">
                                <button id="crop-button" type="button" class="custom-btn px-5 pe-5">Crop</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- cover crop modal => End -->
                <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="js/fileChooser.js"></script>

            </body>

            </html>
<?php

        }
    }
}
?>