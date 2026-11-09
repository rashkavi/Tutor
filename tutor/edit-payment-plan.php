<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {

    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

    if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['class-id']) && !empty($_GET['class-id']))) {
        header("Location: ./class");
    } else {
        $class_id = $_GET["class-id"];

        $query = "SELECT * FROM `classes` WHERE `class_id` = '" . $class_id . "'";
        if (SessionUser::ifUserIsTutor()) {
            $query .= " AND `tutor_id`='" . $sessionUserObject['user_id'] . "'";
        }
        $class = Database::search($query);
        if ($class->num_rows == 0) {
            header("Location: ./classes");
        } else {
            $classData = $class->fetch_assoc();

            $classPaymentPlan = Database::search("SELECT * FROM `class_payment_plans` 
                INNER JOIN `panelty_payment_option` ON `class_payment_plans`.`panelty_payment_option_panelty_payment_option_id` = `panelty_payment_option`.`panelty_payment_option_id`
                INNER JOIN `discount_type` ON `class_payment_plans`.`discount_type_discount_type_id` = `discount_type`.`discount_type_id`
                WHERE `classes_class_id`='" . $class_id . "'");

            if ($classPaymentPlan->num_rows == 0) {
                header("Location: ./class-payment-plans?class-id=" . $class_id);
            } else {

                $classPaymentPlanData = $classPaymentPlan->fetch_assoc();
                $isEnablePenalty = $classPaymentPlanData['panelty_payment_option_panelty_payment_option_id'] != 1 ? true : false;

?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Edit Payments Plan - Admin Dashboard | <?= Project::getBaseName() ?></title>

                    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/info.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                    <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                    <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
                    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                    <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
                </head>

                <body class="overflow-hidden" onload="initializeEditPaymentPlan();">
                    <div class="notifications"></div>
                    <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                        <div id="appContainer" class="row p-0">
                            <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                <?php
                                include "includes/side-menu.php";
                                load_side_menu::load("Classes");
                                ?>
                            </div>
                            <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                <div class="row p-0 m-0 g-0">
                                    <div class="col-12 p-0 m-0 g-0">
                                        <?php
                                        include "includes/header.php";
                                        selectItem::setSelected("Classes");
                                        ?>
                                    </div>
                                    <div class="container p-4 m-0" id="skeleton-list">
                                        <?php
                                        include '../includes/components/skeleton-header.php';
                                        include '../includes/components/skeleton-add-edit.php'; ?>
                                    </div>
                                    <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                        <div class="row p-4 m-0 g-0">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row align-items-center p-0 m-0 g-0">
                                                    <div class="col-12 col-lg-8">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg" data-i18n="class-students">Edit Payment Plan</span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./classes">Classes</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./class-details?class-id=<?= $class_id ?>">Class Details</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./class-payments?class-id=<?= $class_id ?>">Class Payments</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./class-payment-plans?class-id=<?= $class_id ?>">Class Payments Plans</a></li>
                                                                    <li class="breadcrumb-item active" aria-current="page">Edit Payment Plan</li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="payment-card-container">
                                                <div class="payment-card">
                                                    <!-- Background elements -->
                                                    <div class="card-background-cancelled"></div>
                                                    <div class="card-content">

                                                        <div class="payment-header">
                                                            <!-- Reference on left -->
                                                            <div class="reference-section">
                                                                <span class="reference-label">SPECIAL NOTE</span>
                                                                <h2 class="reference-number"></h2>
                                                                <hr>
                                                            </div>
                                                        </div>
                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                            <!-- Date -->
                                                            <div class="detail-item">
                                                                <div class="detail-icon">
                                                                    <iconify-icon id="sort-icon" icon="hugeicons:note" width="24" height="24"></iconify-icon>
                                                                </div>
                                                                <div class="detail-content">
                                                                    <p class="detail-value text-18 p-0 m-0">Updated class payment changes will be effective from next month onward and will apply only to students who register during the current month.
                                                                        </br>
                                                                        Previously scheduled payments for existing students will remain unchanged.
                                                                        </br>
                                                                        <strong> All students will be scheduled under the updated payment plan starting from the following month.</strong>
                                                                        </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 p-4 mt-4 component-theme">
                                                <div class="row p-0 m-0 align-content-center">
                                                    <div class="col-12 p-0 g-0" id="add-payment-plan-form">
                                                        <div class="row gx-4 gy-3">
                                                            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                <label for="" class="text-14 main-text required">Monthly Fee</label>
                                                                <input type="number" class="form-control custom-input mt-2" id="amount" min="0" value="<?= $classPaymentPlanData['amount'] ?>">
                                                                <span class="text-dark-red text-12" id="amount-feedback"></span>
                                                            </div>
                                                            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                <label class="text-14 main-text not-required" for="">Discount Type</label>
                                                                <select id="discount-type" class="form-select custom-select mt-2" data-installment-id="1" onchange="changeDiscountType(this);">
                                                                    <?php
                                                                    $discountTypes = Database::search("SELECT * FROM `discount_type`");
                                                                    while ($discountType = $discountTypes->fetch_assoc()) {
                                                                    ?>
                                                                        <option value="<?php echo $discountType['discount_type_id'] ?>" <?php echo $discountType['discount_type_id'] == $classPaymentPlanData['discount_type_id'] ? 'selected' : '' ?>><?php echo $discountType['discount_type'] ?></option>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </select>
                                                                <span id="discount-type-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column <?= $classPaymentPlanData['discount_type_id'] == 1 ? 'd-none' : '' ?>" id="discount-container">
                                                                <label class="text-14 main-text required" id="discount-label" for="">Discount</label>
                                                                <input type="number" class="form-control custom-input mt-2" id="discount" min="0" value="<?= $classPaymentPlanData['discount'] ?>">
                                                                <span id="discount-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 d-flex flex-column <?= $classPaymentPlanData['discount_type_id'] == 1 ? 'd-none' : '' ?>" id="discount-note-container">
                                                                <label class="text-14 main-text" id="discount-note-label" for="">Discount Note</label>
                                                                <textarea id="discount-note" class="form-control custom-input mt-2" rows="5"><?= $classPaymentPlanData['discount_note'] ?></textarea>
                                                                <span id="discount-note-feedback" class="text-dark-red text-12"></span>
                                                            </div>

                                                            <div class="col-12 col-md-3 d-flex flex-column">
                                                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                    <label class="text-16 main-text required mb-0" for="link">Due Date</label>
                                                                    <span id="due-date-info"></span>
                                                                </div>
                                                                <input type="number" class="form-control custom-input mt-2" id="due-date" min="0" max="31" value="<?= $classPaymentPlanData['due_date'] ?>">
                                                                <span id="due-date-feedback" class="text-dark-red text-12"></span>
                                                            </div>

                                                            <div class="col-12 d-flex align-items-center">
                                                                <input type="checkbox" class="form-check-input hw-20 m-0 p-0" id="enable-panelty" <?= $isEnablePenalty ? 'checked' : '' ?> data-installment-id="1" onchange="enablePanelty(this)">
                                                                <label class="text-14 main-text px-3" for="">Enable Penalty Payment</label>
                                                            </div>

                                                            <div class="col-12 col-lg-4 d-flex flex-column <?= $isEnablePenalty ? '' : 'd-none' ?>" data-installment-id="1" id="panelty-option-area">
                                                                <label class="text-14 main-text required" for="">Panelty Options</label>
                                                                <select id="panelty-option" class="form-select custom-select mt-2" data-installment-id="1" onchange="paneltyOptionChange(this)">
                                                                    <option selected value="0">- Select -</option>
                                                                    <?php
                                                                    $options = Database::search("SELECT * FROM `panelty_payment_option` WHERE `panelty_payment_option_id` != 1");
                                                                    while ($option = $options->fetch_assoc()) {
                                                                    ?>
                                                                        <option value="<?php echo $option['panelty_payment_option_id'] ?>" <?php echo $option['panelty_payment_option_id'] == $classPaymentPlanData['panelty_payment_option_panelty_payment_option_id'] ? 'selected' : '' ?>><?php echo $option['panelty_payment_option'] ?></option>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </select>
                                                                <span id="panelty-option-feedback" data-installment-id="1" class="text-dark-red text-12"></span>
                                                            </div>

                                                            <div class="col-12 <?= $classPaymentPlanData['panelty_payment_option_panelty_payment_option_id'] == 2 || $classPaymentPlanData['panelty_payment_option_panelty_payment_option_id'] == 3 ? '' : 'd-none' ?>" id="panelty-flat-area" data-installment-id="1">
                                                                <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                    <?php
                                                                    if ($classPaymentPlanData['panelty_payment_option_panelty_payment_option_id'] == 2 || $classPaymentPlanData['panelty_payment_option_panelty_payment_option_id'] == 3) {
                                                                        $penalty_rs = Database::search("SELECT * FROM `class_payment_plans_penalties` WHERE `class_payment_plans_class_payment_plan_id`='" . $classPaymentPlanData['class_payment_plan_id'] . "'");
                                                                        if ($penalty_rs->num_rows > 0) {
                                                                            $penalty = $penalty_rs->fetch_assoc();

                                                                    ?>
                                                                            <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                <label for="" class="text-14 main-text required">Penalty Amount Type</label>
                                                                                <select class="form-select custom-select mt-2" id="flat-type">
                                                                                    <option value="0">- Select -</option>
                                                                                    <?php
                                                                                    $types = Database::search("SELECT * FROM `panelty_payments_type`");
                                                                                    while ($type = $types->fetch_assoc()) {
                                                                                    ?>
                                                                                        <option value="<?php echo $type['panelty_payments_type_id']; ?>" <?php echo $type['panelty_payments_type_id'] == $penalty['panelty_payment_type_panelty_payment_type_id'] ? 'selected' : '' ?>><?php echo $type['panelty_payments_type']; ?></option>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                </select>
                                                                                <span class="text-12 text-dark-red" id="flat-type-feedback"></span>
                                                                            </div>
                                                                            <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                <label for="" class="text-14 main-text required" id="amount-label">Amount</label>
                                                                                <input type="number" class="form-control custom-input mt-2" id="flat-amount" min="0" value="<?= $penalty['amount'] ?>">
                                                                                <span class="text-12 text-dark-red" id="flat-amount-feedback"></span>
                                                                            </div>
                                                                        <?php
                                                                        }
                                                                    } else {
                                                                        ?>
                                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                                            <label for="" class="text-14 main-text required">Penalty Amount Type</label>
                                                                            <select class="form-select custom-select mt-2" id="flat-type">
                                                                                <option value="0">- Select -</option>
                                                                                <?php
                                                                                $types = Database::search("SELECT * FROM `panelty_payments_type`");
                                                                                while ($type = $types->fetch_assoc()) {
                                                                                ?>
                                                                                    <option value="<?php echo $type['panelty_payments_type_id']; ?>"><?php echo $type['panelty_payments_type']; ?></option>
                                                                                <?php
                                                                                }
                                                                                ?>
                                                                            </select>
                                                                            <span class="text-12 text-dark-red" id="flat-type-feedback"></span>
                                                                        </div>
                                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                                            <label for="" class="text-14 main-text required" id="amount-label">Amount</label>
                                                                            <input type="number" class="form-control custom-input mt-2" id="flat-amount">
                                                                            <span class="text-12 text-dark-red" id="flat-amount-feedback"></span>
                                                                        </div>
                                                                    <?php
                                                                    }
                                                                    ?>

                                                                </div>
                                                            </div>

                                                            <div class="col-12 d-flex flex-column <?= $classPaymentPlanData['panelty_payment_option_panelty_payment_option_id'] == 4 ? '' : 'd-none' ?>" id="panelty-custom-area" data-installment-id="1">
                                                                <div class="row p-0 m-0" id="custom-slots">

                                                                    <?php
                                                                    if ($classPaymentPlanData['panelty_payment_option_panelty_payment_option_id'] == 4) {
                                                                        $penalty_rs = Database::search("SELECT * FROM `class_payment_plans_penalties` WHERE `class_payment_plans_class_payment_plan_id`='" . $classPaymentPlanData['class_payment_plan_id'] . "'");
                                                                        if ($penalty_rs->num_rows > 0) {
                                                                            for ($i = 0; $i < $penalty_rs->num_rows; $i++) {
                                                                                $penalty = $penalty_rs->fetch_assoc();

                                                                    ?>
                                                                                <div class="col-12 bl-solid p-0 m-0 mt-4 custom-slot">
                                                                                    <div class="row p-4 gy-3 gx-3 align-content-center ">
                                                                                        <div class="d-flex justify-content-between">
                                                                                            <span class="text-14 sub-topic">Custom Date Slot - <?= $i + 1 ?></span>
                                                                                        </div>

                                                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                            <label class="text-14 main-text required">Penalty Amount Type</label>
                                                                                            <select class="form-select custom-select mt-2 panelty-type">
                                                                                                <option value="0">- Select -</option>
                                                                                                <option value="1" <?= $penalty['panelty_payment_type_panelty_payment_type_id'] == 1 ? 'selected' : '' ?>>Fixed</option>
                                                                                                <option value="2" <?= $penalty['panelty_payment_type_panelty_payment_type_id'] == 2 ? 'selected' : '' ?>>Percentage</option>
                                                                                            </select>
                                                                                        </div>

                                                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                            <label class="text-14 main-text required">Amount</label>
                                                                                            <input type="number" class="form-control custom-input mt-2 panelty-amount" value="<?= $penalty['amount'] ?>">
                                                                                        </div>

                                                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                            <label class="text-14 main-text required">Days</label>
                                                                                            <input type="number" class="form-control custom-input mt-2 panelty-days" value="<?= $penalty['days'] ?>">
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                        <?php
                                                                            }
                                                                        }
                                                                    } else {
                                                                        ?>
                                                                        <div class="col-12 bl-solid p-0 m-0 mt-4 custom-slot">
                                                                            <div class="row p-4 gy-3 gx-3 align-content-center ">
                                                                                <div class="d-flex justify-content-between">
                                                                                    <span class="text-14 sub-topic">Custom Date Slot - 1</span>
                                                                                </div>

                                                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text required">Penalty Amount Type</label>
                                                                                    <select class="form-select custom-select mt-2 panelty-type">
                                                                                        <option value="0">- Select -</option>
                                                                                        <option value="1">Fixed</option>
                                                                                        <option value="2">Percentage</option>
                                                                                    </select>
                                                                                </div>

                                                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text required">Amount</label>
                                                                                    <input type="number" class="form-control custom-input mt-2 panelty-amount">
                                                                                </div>

                                                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text required">Days</label>
                                                                                    <input type="number" class="form-control custom-input mt-2 panelty-days">
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    <?php
                                                                    }
                                                                    ?>


                                                                </div>

                                                                <div class="col-12 d-flex justify-content-end mt-3">
                                                                    <button class="custom-btn col-6 col-lg-2" onclick="addNewCustomPaneltyArea(this, 1);">Add New</button>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">Note</label>
                                                                <textarea class="custom-input form-control mt-2" id="note" rows="6"></textarea>
                                                                <span id="note-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                        </div>



                                                        <div class="col-12 p-0 g-0 mt-5">
                                                            <div class="row gx-4 gy-3">
                                                                <div class="col-12 col-md-12 d-flex flex-column align-items-end">
                                                                    <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                        <button class="custom-btn w-100" onclick="editPaymentPlan(this,<?= $class_id ?>);">Save Payment</button>
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

                        <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
                        <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="js/createInfo.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>
                </body>

                </html>
<?php
            }
        }
    }
}
?>