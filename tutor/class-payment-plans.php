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
        header("Location: ./classes");
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


?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Class Payments Plans - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/calender.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
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
                                    include '../includes/components/skeleton-table.php'; ?>
                                </div>
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-lg-8">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n=""><?= $classData['class_name'] ?></span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./classes">Classes</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./class-details?class-id=<?= $class_id ?>">Class Details</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./class-payments">Class Payments</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Class Payments Plans</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                                    <div class="row gx-3 justify-content-end">
                                                        <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <?php
                                                            if ($classPaymentPlan->num_rows > 0) {
                                                            ?>
                                                                <button class="custom-danger-btn w-100" data-i18n="payment-plans" onclick="window.location = 'edit-payment-plan?class-id=<?= $class_id ?>'">Edit Payment Plan</button>
                                                            <?php
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <?php
                                        if ($classPaymentPlan->num_rows > 0) {
                                            $data = $classPaymentPlan->fetch_assoc();

                                        ?>
                                            <div class="payment-card-container">
                                                <div class="payment-card">
                                                    <!-- Background elements -->
                                                    <div class="card-background"></div>

                                                    <!-- Card content -->
                                                    <div class="card-content">
                                                        <div class="payment-header">
                                                            <!-- Reference on left -->
                                                            <div class="reference-section">
                                                                <span class="reference-label">CURRENT PAYMENT PLAN</span>
                                                                <h2 class="reference-number"></h2>
                                                                <hr>
                                                            </div>
                                                        </div>
                                                        <!-- Details in a row below -->
                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                            <!-- Date -->
                                                            <div class="detail-item">
                                                                <div class="detail-icon">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                        <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                    </svg>
                                                                </div>
                                                                <div class="detail-content">
                                                                    <span class="detail-label">Monthly Fee</span>
                                                                    <span class="detail-value"><?= number_format($data["amount"]); ?></span>
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
                                                                    <span class="detail-label">Discount Type</span>
                                                                    <span class="detail-value"><?= $data["discount_type"]; ?></span>
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
                                                                    <span class="detail-label">Discount</span>
                                                                    <span class="detail-value"><?= $data["discount"]; ?> <?= $data["discount_type_id"] == 3 ? "%" : "" ?></span>
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
                                                                    <span class="detail-label">Due Date</span>
                                                                    <span class="detail-value">
                                                                        <?= $data["due_date"] . ""; ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <?php
                                                        if ($data['panelty_payment_option_panelty_payment_option_id'] != 1) {

                                                        ?>
                                                            <span class="reference-label my-3">PENALTY PAYMENT ENABLED</span>
                                                            <?php
                                                            $panelties = Database::search("SELECT * FROM `class_payment_plans_penalties`
                                                                        INNER JOIN `panelty_payments_type` ON `class_payment_plans_penalties`.`panelty_payment_type_panelty_payment_type_id` = `panelty_payments_type`.`panelty_payments_type_id` 
                                                                        WHERE `class_payment_plans_class_payment_plan_id` = '" . $data['class_payment_plan_id'] . "'");
                                                            if ($panelties->num_rows > 0) {
                                                                while ($panelty = $panelties->fetch_assoc()) {
                                                            ?>
                                                                    <div class="details-row d-flex flex-column flex-lg-row">
                                                                        <!-- Date -->
                                                                        <div class="detail-item">
                                                                            <div class="detail-icon">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                    <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                </svg>
                                                                            </div>
                                                                            <div class="detail-content">
                                                                                <span class="detail-label">Option</span>
                                                                                <span class="detail-value"><?= $data["panelty_payment_option"] ?></span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="detail-item">
                                                                            <div class="detail-icon">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                    <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                                </svg>
                                                                            </div>
                                                                            <div class="detail-content">
                                                                                <span class="detail-label">Type</span>
                                                                                <span class="detail-value"><?= $panelty["panelty_payments_type"] ?></span>
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
                                                                                <span class="detail-label">Amount</span>
                                                                                <span class="detail-value"><?= number_format($panelty["amount"]); ?></span>
                                                                            </div>
                                                                        </div>

                                                                        <?php
                                                                        if (!empty($panelty["days"])) {
                                                                        ?>
                                                                            <div class="detail-item">
                                                                                <div class="detail-icon">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                        <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v1h14V4a1 1 0 0 0-1-1H2zm13 4H1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7z" />
                                                                                        <path d="M2 10a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1z" />
                                                                                    </svg>
                                                                                </div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Days</span>
                                                                                    <span class="detail-value"><?= $panelty["days"]; ?></span>
                                                                                </div>
                                                                            </div>
                                                                        <?php
                                                                        }
                                                                        ?>

                                                                    </div>
                                                        <?php
                                                                }
                                                            }
                                                        }
                                                        ?>

                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12 mt-4">
                                                <div class="row p-4 component-theme m-0 g-0">
                                                    <div class="col-12 mb-4">
                                                        <div class="row p-0 gy-3 gx-3 align-content-center">
                                                            <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                <div class="d-flex custom-div align-items-center justify-content-center">
                                                                    <?php
                                                                    $logs_count = Database::search("SELECT COUNT(*) as 'count' FROM `class_payment_plan_logs` 
                                                                    WHERE `class_payment_plans_class_payment_plan_id`='" . $data["class_payment_plan_id"] . "'");
                                                                    $countData = $logs_count->fetch_assoc();
                                                                    ?>
                                                                    <span class="text-14 dark-theme fw-medium"><?= $countData['count']; ?> Logs</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="row p-0 m-0 g-0 align-content-center">
                                                            <div class="overflow-x-auto p-0 m-0 g-0" id="users-table-container">
                                                                <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                                    <thead>
                                                                        <tr class="br-t-5">
                                                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                    <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                                                                </div>
                                                                            </th>

                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Date Updated</th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Note</th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Log</th>

                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="user-table-data">
                                                                        <?php
                                                                        $query = "SELECT * FROM `class_payment_plan_logs`
                                                                    INNER JOIN `users` ON `class_payment_plan_logs`.`users_user_id` = `users`.`user_id`
                                                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                    WHERE `class_payment_plans_class_payment_plan_id` = '" . $data['class_payment_plan_id'] . "' ORDER BY `date_updated` DESC";

                                                                        $users = Database::search($query);
                                                                        if ($users->num_rows > 0) {
                                                                            while ($data = $users->fetch_assoc()) {
                                                                        ?>
                                                                                <tr>
                                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?= $data["user_id"]; ?>" onchange="checkRowEvents(<?= $data['user_id']; ?>);">
                                                                                            <?php
                                                                                            if (!empty($data["profile_img"]) && file_exists('<?= Project::getAssetsPath() ?>/imgs/users/' . $data["profile_img"])) {
                                                                                            ?>
                                                                                                <img src="<?= Project::getAssetsPath() ?>/imgs/users/<?= $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                            <?php
                                                                                            } else {
                                                                                                $firstNameInitial = strtoupper(substr($data["user_fname"], 0, 1));
                                                                                                $lastNameInitial = strtoupper(substr($data["user_lname"], 0, 1));
                                                                                            ?>
                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                            <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["date_updated"]; ?></span></td>
                                                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["note"]; ?></span></td>
                                                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["log"]; ?></span></td>
                                                                                </tr>
                                                                            <?php
                                                                            }
                                                                        } else {
                                                                            ?>
                                                                            <tr class="justify-content-center align-middle">
                                                                                <td colspan="13" class="align-middle justify-content-center">
                                                                                    <div class="d-flex flex-column align-items-center">
                                                                                        <img src="<?= Project::getAssetsPath() ?>/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        <?php
                                        } else {
                                        ?>
                                            <div class="col-12 px-4 py-3 component-theme text-center">
                                                <div class="row align-items-center justify-content-center">
                                                    <img src="<?= Project::getAssetsPath() ?>/imgs/common/add-payment.png" class="no-data-icon" alt="No data" />
                                                </div>
                                                <button class="custom-btn px-5" onclick="window.location = 'add-payment-plan?class-id=<?= $class_id ?>'">Add Payment Plan</button>

                                            </div>
                                        <?php
                                        }
                                        ?>



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
                <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>

            </body>

            </html>
<?php
        }
    }
}
?>