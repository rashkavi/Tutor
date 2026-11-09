<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Student');
    if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['class-id']) && !empty($_GET['class-id']))) {
        header("Location: ./class");
    } else {
        $class_id = $_GET["class-id"];

        $query = "SELECT * FROM `students_has_classes` 
              INNER JOIN `classes` ON `classes_class_id`=`class_id`
        WHERE `classes_class_id` = '" . $class_id . "'
        AND `students_student_id`='" . $sessionUserObject['student_id'] . "'";

        $class = Database::search($query);
        if ($class->num_rows == 0) {
            header("Location: ./classes");
        } else {
            $classData = $class->fetch_assoc();

?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Payments - Student Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/intergration-card.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                                    include '../includes/components/skeleton-class-card.php'; ?>
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
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Class Payments</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12 mb-4">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                            <div class="d-flex custom-div align-items-center justify-content-center">
                                                                <?php
                                                                $query = "SELECT COUNT(*) as 'count' FROM `student_has_payments` 
                                                                    INNER JOIN `students_has_classes` ON `student_has_payments`.`students_has_class_students_has_class_id`=`students_has_classes`.`students_has_class_id`
                                                                    INNER JOIN `classes` ON `classes`.`class_id`=`students_has_classes`.`classes_class_id`
                                                                    WHERE `payment_status_payment_status_id`='1'
                                                                     AND `students_student_id`='" . $sessionUserObject['student_id'] . "'
                                                                    AND `classes_class_id`='" . $class_id . "'";

                                                                $payments_count = Database::search($query);
                                                                $data = $payments_count->fetch_assoc();
                                                                ?>
                                                                <span class="text-14 dark-theme fw-medium"><?= $data['count']; ?> Awaiting Payments</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                            <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="search-text" onkeyup="handleStudentsPaymentsPagination('filter')">
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-12 col-lg-1 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filter" aria-expanded="false" aria-controls="filter">
                                                                <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                            </button>
                                                        </div>
                                                        <div class="collapse" id="filter">
                                                            <div class="card card-body border-0 p-0">
                                                                <div class="col-12">
                                                                    <div class="row gy-3 justify-content-end">
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" aria-label="Default select example" id="status-selector" onchange="handleStudentsPaymentsPagination('filter')">
                                                                                <option selected value="0">- Select Payment Status -</option>
                                                                                <?php
                                                                                $paymentStatus = Database::search("SELECT * FROM `payment_status`");
                                                                                while ($data = $paymentStatus->fetch_assoc()) {
                                                                                ?>
                                                                                    <option value="<?= $data['payment_status_id'] ?>"><?= $data['payment_status'] ?></option>
                                                                                <?php
                                                                                }
                                                                                ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleStudentsPaymentsPagination('filter')">
                                                                                <option selected value="0">- Filter Data -</option>
                                                                                <option value="1">Bank Transfer</option>
                                                                                <option value="2">Card Payments</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" name="year" id="year" onchange="handleStudentsPaymentsPagination('filter')">
                                                                                <option value="0">- Select Year -</option>

                                                                                <?php

                                                                                $paymentsScheduled = Database::search("
                                                                                                SELECT 
                                                                                                    MIN(date_added) AS minDate, 
                                                                                                    MAX(date_added) AS maxDate 
                                                                                                FROM student_has_payments
                                                                                                ");

                                                                                $minYear = null;
                                                                                $maxYear = null;

                                                                                if ($paymentsScheduled->num_rows > 0) {
                                                                                    $row = $paymentsScheduled->fetch_assoc();

                                                                                    if (!empty($row["minDate"]) && !empty($row["maxDate"])) {
                                                                                        $minYear = date("Y", strtotime($row["minDate"]));
                                                                                        $maxYear = date("Y", strtotime($row["maxDate"]));
                                                                                    }
                                                                                }

                                                                                if ($minYear === null || $maxYear === null) {
                                                                                    $currentYear = date("Y");
                                                                                    $minYear = $currentYear;
                                                                                    $maxYear = $currentYear;
                                                                                }

                                                                                for ($y = $minYear; $y <= $maxYear; $y++) {
                                                                                    echo "<option value='$y'>$y</option>";
                                                                                }
                                                                                ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" name="month" id="month" onchange="handleStudentsPaymentsPagination('filter')">
                                                                                <option value="0">- Select Month -</option>
                                                                                <?php
                                                                                for ($m = 1; $m <= 12; $m++) {
                                                                                    echo "<option value='$m'>" . date("F", mktime(0, 0, 0, $m, 1)) . "</option>";
                                                                                }
                                                                                ?>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                            <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                            <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleStudentsPaymentsPagination('filter')">
                                                                <option selected value="10">10</option>
                                                                <option value="25">25</option>
                                                                <option value="50">50</option>
                                                                <option value="100">100</option>
                                                                <option value="250">250</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12">
                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                        <div class="table-responsive-wrapper">
                                                            <div class="table-responsive" id="payments-table-container">
                                                                <table class="table bl-solid m-0 p-0 g-0" id="payments-table">
                                                                    <thead>
                                                                        <tr class="br-t-5">
                                                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center cursor-pointer">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                    <div class="d-flex justify-content-between align-items-center w-100">
                                                                                        <span class="text-14 main-text fw-medium mx-2">Class</span>
                                                                                        <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                    </div>
                                                                                </div>
                                                                            </th>

                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Amount</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Discount</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Penalty Amount </th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">
                                                                                <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                                    <span class="text-14 main-text fw-medium me-2">Payable Amount</span>
                                                                                </div>
                                                                            </th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Due Date</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleStudentsPaymentsPagination('sortByDate')">
                                                                                <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                                    <span class="text-14 main-text fw-medium me-2">Paid Date</span>
                                                                                    <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                </div>
                                                                            </th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Payment method</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="user-table-data">
                                                                        <?php

                                                                        $query = "
                                                                        SELECT 
                                                                            `cpp`.`student_has_payment_id`,
                                                                         
                                                                            `cpp`.`discount`,
                                                                            `cpp`.`discount_type_discount_type_id`,
                                                                            `cpp`.`due_date`,
                                                                            `cpp`.`amount`,
                                                                            `ps`.`payment_status`,
                                                                            `ps`.`payment_status_id`,
                                                                          
                                                                            `pm`.`payment_method`,
                                                                            `pm`.`payment_method_id`,
                                                                            `cpp`.`amount`,
                                                                            `cpp`.`date_paid`,
                                                                        
                                                                            `ld`.`first_name`,
                                                                            `ld`.`last_name`,

                                                                            `c`.`class_name`,
                                                                            
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
                                                                       
                                                                                                                                               
                                                                       LEFT JOIN student_has_penalty_payments penalty
                                                                            ON penalty.student_has_payments_student_has_payment_id = cpp.student_has_payment_id
                                                                       WHERE `shi`.`students_student_id`='" . $sessionUserObject['student_id'] . "'
                                                                       AND `shi`.`classes_class_id`='" . $class_id . "'
                                                                        
                                                                        ";

                                                                        $query .= "GROUP BY `cpp`.`student_has_payment_id`
                                                                        ORDER BY `cpp`.`student_has_payment_id` DESC";


                                                                        $results = Database::search($query);
                                                                        $pageno = 1;
                                                                        $results_count = $results->num_rows;
                                                                        $result_per_page = 10;

                                                                        $number_of_pages = ($results_count > $result_per_page) ? ceil($results_count / $result_per_page) : 1;
                                                                        $page_results = ($pageno - 1) * $result_per_page;

                                                                        $query_paginated = $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results;
                                                                        $users = Database::search($query_paginated);

                                                                        if ($users->num_rows > 0) {
                                                                            while ($data = $users->fetch_assoc()) {
                                                                                $discount = $data['discount'];
                                                                                if ($data['discount_type_discount_type_id'] == 3) {
                                                                                    $discount = $data['amount'] * $data['discount'] / 100;
                                                                                }
                                                                                $payableAmount = $data['amount'] - $discount + ($data['panelty_charge'] == null ? 0 : $data['panelty_charge']);
                                                                        ?>
                                                                                <tr>
                                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                            <span class="text-14 main-text mx-2"><?= $data["class_name"]; ?></span>
                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= number_format($data["amount"], 2); ?></span></td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= number_format($discount, 2); ?></span></td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= number_format($data["panelty_charge"], 2); ?></span></td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= number_format($payableAmount, 2); ?></span></td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['due_date'] == '' ? "N/A" : $data['due_date'] ?></span></td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["date_paid"] == '' ? "N/A" : date("Y-m-d h:i A", strtotime($data["date_paid"])); ?>
                                                                                        </span></td>
                                                                                    <td class="py-2 align-middle">
                                                                                        <?php

                                                                                        if ($data['payment_method_id'] == null) {
                                                                                        ?>
                                                                                            <span class="text-14 main-text">N/A</span>
                                                                                        <?php
                                                                                        } else {
                                                                                            $status_classes = [
                                                                                                "1"     => "table-btn-orange",
                                                                                                "2"  => "table-btn-pink",
                                                                                                "3"  => "table-btn-purple",
                                                                                                "4"  => "table-btn-yellow-green",
                                                                                                "5"  => "table-btn-purle-pink",
                                                                                            ];
                                                                                            $sbutton_class = $status_classes[$data["payment_method_id"]];
                                                                                        ?>
                                                                                            <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["payment_method"]; ?></button>
                                                                                        <?php
                                                                                        }

                                                                                        ?>

                                                                                    </td>
                                                                                    <td class="py-2 align-middle">
                                                                                        <?php
                                                                                        $status_classes = [
                                                                                            "0" => "table-btn-purple",
                                                                                            "1" => "table-btn-yellow",
                                                                                            "2" => "table-btn-orange",
                                                                                            "3" => "table-btn-success",
                                                                                            "4" => "table-btn-danger",
                                                                                            "5" => "table-btn-orange",
                                                                                            "6" => "table-btn-success",
                                                                                            "7" => "table-btn-danger",
                                                                                            "8" => "table-btn-success",
                                                                                        ];
                                                                                        $sbutton_class = $status_classes[$data["payment_status_id"]];
                                                                                        ?>
                                                                                        <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["payment_status"]; ?></button>
                                                                                    </td>

                                                                                    <td class="py-3 align-middle">
                                                                                        <div class="dropdown">
                                                                                            <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                            <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">

                                                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="payment-details?payment-id=<?php echo $data['student_has_payment_id']; ?>">
                                                                                                        <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                        View</a></li>

                                                                                                <?php
                                                                                                if ($data['payment_status_id'] == 1) {
                                                                                                ?>
                                                                                                    <hr class="dropdown-divider mx-2 ">
                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="pay-payment?payment-id=<?php echo $data['student_has_payment_id']; ?>">
                                                                                                            <iconify-icon icon="arcticons:mts-pay" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            Pay Now</a></li>
                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                                                            </ul>
                                                                                        </div>
                                                                                    </td>

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
                                                                <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                                                                    <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                        <div class="row p-4 align-items-center">
                                                                            <!-- Previous Button (col-1) -->
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleStudentsPaymentsPagination('previousPage')">
                                                                                <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                <span class="text-14 main-text fw-semibold">Previous</span>
                                                                            </button>
                                                                            <!-- Page Numbers (col-10, centered) -->
                                                                            <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                                <ul class="pagination-list d-flex mb-0">
                                                                                    <?php
                                                                                    $shudderPages1 = false;
                                                                                    $shudderPages2 = false;

                                                                                    function renderPageButton($i, $isActive = false)
                                                                                    {
                                                                                        $activeClass = $isActive ? 'active' : '';
                                                                                        $action = $isActive ? '' : "onclick=\"handleStudentsPaymentsPagination('goToPage', '{$i}')\"";
                                                                                        echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                                                    }

                                                                                    for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                        if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                                                            renderPageButton($i, $pageno == $i);
                                                                                        } elseif (!$shudderPages1 && ($i < $pageno - 1 && $i > 1)) {
                                                                                            $shudderPages1 = true;
                                                                                            echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                        } elseif (!$shudderPages2 && ($i > $pageno + 1 && $i < $number_of_pages)) {
                                                                                            $shudderPages2 = true;
                                                                                            echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                        }
                                                                                    }
                                                                                    ?>
                                                                                </ul>
                                                                            </div>
                                                                            <!-- Next Button (col-1) -->
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleStudentsPaymentsPagination('nextPage',null,'<?= $number_of_pages ?>')">
                                                                                <span class="text-14 main-text fw-semibold">Next</span>
                                                                                <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                            </button>
                                                                        </div>
                                                                    </nav>
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

                <div class="modal fade" id="removeConfirmModal" tabindex="-1"
                    aria-labelledby="removeConfirmModalTitle" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow ">
                            <div class="d-flex justify-content-end pe-3 pt-3 position-relative">
                                <button type="button" class="btn-close text-end"
                                    data-bs-dismiss="modal" data-bs-target="#removeConfirmModal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center p-4">
                                <div class="mb-3">
                                    <iconify-icon
                                        icon="bx:error-circle"
                                        width="48"
                                        height="48"
                                        class="text-danger">
                                    </iconify-icon>
                                </div>
                                <h5 class="fw-semibold mb-2" id="removeConfirmModalTitle">
                                    Confirm Removal
                                </h5>
                                <p class="text-muted mb-3">
                                    You are about to remove this <strong>Class Recording</strong>.
                                </p>
                                <div class="alert alert-warning text-start small mb-4">
                                    <div class="d-flex align-items-start gap-2">
                                        <iconify-icon icon="mdi:alert-outline" width="20"></iconify-icon>
                                        <div>
                                            <strong>This action will:</strong>
                                            <ul class="mb-0 ps-3">
                                                <li>Permanently delete the selected recording</li>
                                                <li>Remove access for all students</li>
                                                <li>Delete any attached files/thumbnails (if available)</li>
                                                <li>This action <strong>cannot be undone</strong></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-center gap-3">
                                    <button type="button"
                                        class="btn btn-outline-secondary px-4 br-0"
                                        data-bs-dismiss="modal">
                                        Cancel
                                    </button>
                                    <button type="button"
                                        id="confirmRemove"
                                        class="btn btn-danger px-4 br-0">
                                        <iconify-icon icon="mdi:delete-outline" class="me-1"></iconify-icon>
                                        Remove
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
            </body>

            </html>
<?php
        }
    }
}
?>