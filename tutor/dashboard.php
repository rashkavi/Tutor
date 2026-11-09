<?php

require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once '../includes/connection.php';
require_once '../includes/Project.php';

require_once './api/automatedClassFee.php';
require_once './api/automatedPaneltyPayment.php';
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

    $alertDisplay = false;
    if (isset($_COOKIE['enviiz_lms_user_login'])) {
        setcookie('enviiz_lms_user_login', '', time() - 3600, '/');
        unset($_COOKIE['enviiz_lms_user_login']);
        $alertDisplay = true;
    }

    $card = Database::search("
    SELECT
        COUNT(DISTINCT c.class_id) AS total_class_count,
        COUNT(DISTINCT shc.students_student_id) AS total_engaged_students_count,
    
         SUM(
                            CASE 
                                WHEN sp.payment_status_payment_status_id = '3' THEN 
                                    (sp.amount + IFNULL(spp.amount,0)
                                    - CASE 
                                        WHEN sp.discount_type_discount_type_id = '2' THEN sp.discount
                                        WHEN sp.discount_type_discount_type_id = '3' THEN (sp.amount * sp.discount / 100)
                                        ELSE 0
                                    END)
                            END
                        ) AS total_income,
    
        SUM(
                            CASE 
                                WHEN sp.payment_status_payment_status_id = '1' THEN 
                                    (sp.amount + IFNULL(spp.amount,0)
                                    - CASE 
                                        WHEN sp.discount_type_discount_type_id = '2' THEN sp.discount
                                        WHEN sp.discount_type_discount_type_id = '3' THEN (sp.amount * sp.discount / 100)
                                        ELSE 0
                                    END)
                            END
                        ) AS awaiting_total
    
    FROM classes c
   
    LEFT JOIN students_has_classes shc
        ON shc.classes_class_id = c.class_id
        AND shc.status_status_id = 1
    
    LEFT JOIN student_has_payments sp
        ON sp.students_has_class_students_has_class_id = shc.students_has_class_id
     LEFT JOIN student_has_penalty_payments spp 
                    ON sp.student_has_payment_id = spp.student_has_payments_student_has_payment_id
    WHERE c.tutor_id = '" . $sessionUserObject['user_id'] . "'
    ");

    $cardData = $card->fetch_assoc();

?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Dashboard - Tutor Dashboard | <?= Project::getBaseName() ?></title>

        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/dashboard.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
    </head>

    <body class="overflow-hidden" onload="initializeDshboard();">
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
                        include "includes/header.php";
                        selectItem::setSelected("Dashboard");
                        ?>
                    </div>

                    <div class="col-12 p-4 w-100 m-0" id="skeleton-list">
                        <div class="row g-4">
                            <?php
                            for ($i = 0; $i < 8; $i++) {
                            ?>
                                <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                    <div class="post-card-skeleton skeleton w-100 px-5 py-5"></div>
                                </div>
                            <?php
                            }
                            ?>
                        </div>
                        <div class="row g-4 mt-2 mb-4">
                            <?php
                            for ($i = 0; $i < 2; $i++) {
                            ?>
                                <div class="col-12 col-md-6 d-flex align-items-stretch">
                                    <?php include "../includes/components/skeleton-details-card.php"; ?>
                                </div>
                            <?php
                            }
                            ?>
                        </div>
                        <?php
                        include "../includes/components/skeleton-table.php";
                        ?>
                    </div>

                    <div class="col-12 d-flex flex-column contentBodyArea vh-100 d-none" id="real-list">
                        <div class="row m-0">
                            <?php
                            if ($alertDisplay) {
                            ?>
                                <div class="alert-container px-4 pt-4 m-0 g-0">
                                    <div class="alert alert-dismissible component-theme br-0 custom-alert" role="alert" id="welcomeAlert">
                                        <div class="inner">
                                            <div class="app-card-body p-3 p-lg-4">
                                                <h3 class="mb-3">Welcome, Admin<?= $sessionUserObject['user_fname'] . " " . $sessionUserObject['user_lname'] ?>!</h3>
                                                <div class="row gx-5 gy-3">
                                                    <div class="col-12 col-lg-9">
                                                        <p>Welcome back! Access academic tools, manage student records, monitor course progress, and stay informed with the latest updates from the university.</p>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="hideAlert()"></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php
                            }
                            ?>

                            <div class="col-12 m-0">
                                <div class="row g-4 p-0 m-0">
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <div class="courier-kpi total">
                                            <div class="icon">
                                                <iconify-icon icon="eos-icons:product-classes-outlined" width="22"></iconify-icon>
                                            </div>
                                            <div>
                                                <div class="label">Classes</div>
                                                <div class="value"><?= $cardData['total_class_count'] ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <div class="courier-kpi delivered">
                                            <div class="icon">
                                                <iconify-icon icon="ph:student" width="22"></iconify-icon>
                                            </div>
                                            <div>
                                                <div class="label">Students</div>
                                                <div class="value"><?= $cardData['total_engaged_students_count'] ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <div class="courier-kpi transit">
                                            <div class="icon">
                                                <iconify-icon icon="ci:wavy-check" width="22"></iconify-icon>
                                            </div>
                                            <div>
                                                <div class="label">Total Income</div>
                                                <div class="value">LKR <?= number_format($cardData['total_income'], 2) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <div class="courier-kpi pending">
                                            <div class="icon">
                                                <iconify-icon icon="carbon:pending" width="22"></iconify-icon>
                                            </div>
                                            <div>
                                                <div class="label">Awaiting Payments</div>
                                                <div class="value">LKR <?= number_format($cardData['awaiting_total'], 2) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 mt-4">
                                <div class="row p-0 m-0">
                                    <div class="col-12 col-lg-5 d-flex align-items-stretch">
                                        <div class="today-card premium-card w-100">
                                            <?php
                                            date_default_timezone_set("Asia/Colombo");
                                            $todayClasses = Database::search("SELECT * FROM `time_table`
                                            INNER JOIN classes ON classes.class_id = time_table.classes_class_id
                                            INNER JOIN lecture_type ON lecture_type.lecture_type_id = time_table.lecture_type_lecture_type_id
                                            WHERE DATE(time_table.date) = CURDATE() 
                                            AND classes.tutor_id = '" . $sessionUserObject['user_id'] . "'
                                            AND time_table.time_table_status_time_table_status_id = 1");
                                            ?>
                                            <!-- Header -->
                                            <div class="today-top">
                                                <div>
                                                    <div class="sub-topic">Today's Classes</div>
                                                    <div class="today-sub">Your scheduled sessions</div>
                                                </div>
                                                <?php
                                                if ($todayClasses->num_rows > 3) {
                                                ?>
                                                    <div class="today-badge" onclick="window.location = 'time-table'">
                                                        View All
                                                    </div>
                                                <?php
                                                }
                                                ?>

                                            </div>

                                            <!-- Class List -->
                                            <div class="today-class-list">
                                                <?php
                                                if ($todayClasses->num_rows > 0) {
                                                    $loopCount = $todayClasses->num_rows > 3 ? 3 : $todayClasses->num_rows;
                                                    for ($i = 0; $i < $loopCount; $i++) {
                                                        $class = $todayClasses->fetch_assoc();
                                                ?>
                                                        <div class="today-class-item">
                                                            <div class="class-left">
                                                                <div class="class-name"><?= $class['class_name'] ?></div>
                                                                <div class="class-meta">
                                                                    <iconify-icon icon="mdi:laptop" width="15" class="me-1"></iconify-icon>
                                                                    <?= $class['lecture_type'] ?> • <?= date("h:i A", strtotime($class['start_time'])) ?> - <?= date("h:i A", strtotime($class['end_time'])) ?>
                                                                </div>
                                                            </div>
                                                            <div class="footer-icon-group">
                                                                <button class="icon-premium" data-tooltip="View Class"
                                                                    onclick="window.location = 'time-table-attendance?time-table-id=<?= $class['time_table_id'] ?>'">
                                                                    <iconify-icon icon="solar:eye-broken" width="20"></iconify-icon>
                                                                </button>
                                                            </div>
                                                        </div>
                                                <?php
                                                    }
                                                } else {
                                                    ?>
                                                  
                                                        <div class="d-flex justify-content-center">
                                                            <img src="<?= Project::getAssetsPath() ?>/imgs/common/no-data.png"  class="no-data-icon-sm">
                                                        </div>
                                                   
                                                    <?php
                                                }


                                                ?>

                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-lg-4 d-flex align-items-stretch">
                                        <div class="d-flex flex-column component-theme p-4 w-100">
                                            <div class="d-flex flex-row">
                                                <div class="col-8 d-flex flex-column">
                                                    <span class="sub-topic" data-i18n="">Weekly Attendance</span>
                                                </div>
                                            </div>
                                            <div class="component-theme d-flex flex-row align-items-center justify-content-center p-0 mt-2 w-100">
                                                <canvas id="deliveryLineChart" class="w-100 h-auto"></canvas>
                                            </div>
                                            <div class="today-class-item mt-2">

                                                <div class="class-left d-flex align-items-center">
                                                    <iconify-icon icon="solar:cup-bold" width="30" class="text-dark-yellow"></iconify-icon>
                                                    <div class="d-flex flex-column px-3 pe-0">
                                                        <div class="class-name">Grade 10 - Science</div>
                                                        <div class="class-meta text-dark-green">
                                                            <iconify-icon icon="tabler:chart-line" width="15" class="me-1"></iconify-icon>
                                                            Highest Attendance Class
                                                        </div>
                                                    </div>

                                                </div>

                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-lg-3 d-flex align-items-stretch">
                                        <div class="pro-cal">
                                            <div class="pro-cal__top">
                                                <button class="pro-cal__nav" id="calPrev">
                                                    <iconify-icon icon="tabler:chevron-left" width="18"></iconify-icon>
                                                </button>

                                                <div class="sub-topic" id="calTitle"></div>

                                                <button class="pro-cal__nav" id="calNext">
                                                    <iconify-icon icon="tabler:chevron-right" width="18"></iconify-icon>
                                                </button>
                                            </div>

                                            <div class="pro-cal__dow">
                                                <div>Sun</div>
                                                <div>Mon</div>
                                                <div>Tue</div>
                                                <div>Wed</div>
                                                <div>Thu</div>
                                                <div>Fri</div>
                                                <div>Sat</div>
                                            </div>

                                            <div class="pro-cal__scroll">
                                                <div class="pro-cal__grid" id="calGrid"></div>
                                            </div>
                                        </div>


                                    </div>
                                </div>
                            </div>

                            <div class="col-12 mt-4">
                                <div class="row p-0 m-0">
                                    <div class="col-12 col-lg-9">
                                        <div class="row component-theme p-4 m-0 g-0 align-content-center">
                                            <div class="today-top">
                                                <div>
                                                    <div class="sub-topic">Recent Payments</div>
                                                </div>
                                                <div class="today-badge" onclick="window.location = 'payments' ">
                                                    View All
                                                </div>
                                            </div>

                                            <div class="table-responsive-wrapper">
                                                <div class="table-responsive" id="payments-table-container">
                                                    <table class="table bl-solid m-0 p-0 g-0" id="payments-table">
                                                        <thead>
                                                            <tr class="br-t-5">
                                                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center cursor-pointer">
                                                                    <div class="d-flex flex-row align-items-center">
                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                        <div class="d-flex justify-content-between align-items-center w-100" onclick="handleStudentsPaymentsPagination('sortByName')">
                                                                            <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                        </div>
                                                                    </div>
                                                                </th>

                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Amount</th>

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
                                                                       WHERE `ps`.`payment_status_id` != '1'
                                                                        
                                                                        ";

                                                            if (SessionUser::ifUserIsTutor()) {
                                                                $query .= " AND `tutor_id`='" . $sessionUserObject['user_id'] . "'";
                                                            }

                                                            $query .= "GROUP BY `cpp`.`student_has_payment_id`
                                                                        ORDER BY `cpp`.`date_paid` DESC LIMIT 5";


                                                            $results = Database::search($query);

                                                            if ($results->num_rows > 0) {
                                                                while ($data = $results->fetch_assoc()) {
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
                                                                                <?php
                                                                                if (!empty($data["profile_img"]) && file_exists('<?= Project::getAssetsPath() ?>/imgs/students/' . $data["profile_img"])) {
                                                                                ?>
                                                                                    <img src="<?= Project::getAssetsPath() ?>/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                <?php
                                                                                } else {
                                                                                    $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                                                    $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                                                ?>
                                                                                    <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                <?php
                                                                                }
                                                                                ?>
                                                                                <span class="text-14 main-text mx-2"><?= $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                                            </div>
                                                                        </td>
                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= number_format($data["amount"], 2); ?></span></td>
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
                                                                                    "1"  => "table-btn-pink",
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

                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="student-payment?payment-id=<?php echo $data['student_has_payment_id']; ?>">
                                                                                            <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                            View</a></li>
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
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-lg-3">
                                        <div class="note-card">
                                            <div class="note-header">
                                                <iconify-icon icon="solar:notes-bold" width="18"></iconify-icon>
                                                <span class="sub-topic">Quick Notes</span>
                                            </div>
                                            <textarea
                                                class="note-area"
                                                placeholder="Write something here..."></textarea>
                                            <div class="note-footer">
                                                Auto saved
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

        <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/chart.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/functions.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>

        <link rel="stylesheet"
            href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>


        <script>
            const classData = {
                "2026-02-04": {
                    name: "Grade 11 English",
                    time: "09:00 AM"
                },
                "2026-02-16": {
                    name: "ICT Class",
                    time: "11:00 AM"
                },
                "2026-02-20": {
                    name: "Science Physical",
                    time: "02:00 PM"
                },
                "2026-05-19": {
                    name: "Math Revision",
                    time: "08:30 AM"
                },
                "2026-05-23": {
                    name: "Physics Online",
                    time: "01:00 PM"
                },
            };

            const monthNames = [
                "January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];

            // ===== state =====
            const now = new Date();
            let calYear = now.getFullYear();
            let calMonth = now.getMonth();

            // ===== helpers =====
            const pad2 = (n) => String(n).padStart(2, "0");
            const toKey = (y, m, d) => `${y}-${pad2(m + 1)}-${pad2(d)}`;

            // ===== tooltip (one element) =====
            const proTooltip = document.createElement("div");
            proTooltip.className = "pro-tooltip";
            document.body.appendChild(proTooltip);

            const showTooltip = (x, y, dateKey, data) => {
                proTooltip.innerHTML = `
      <div class="tt-title">${data.name}</div>
      <div class="tt-row"><span>Date</span><strong>${dateKey}</strong></div>
      <div class="tt-row"><span>Time</span><strong>${data.time}</strong></div>
      <div class="tt-pill">
        <span style="width:8px;height:8px;border-radius:50%;background:#0d6efd;display:inline-block"></span>
        Class Day
      </div>
    `;

                // keep tooltip inside viewport
                const pad = 12;
                const rect = proTooltip.getBoundingClientRect();
                const w = rect.width || 260;
                const h = rect.height || 140;

                let left = x + 14;
                let top = y - 10;

                if (left + w > window.innerWidth - pad) left = x - w - 14;
                if (top + h > window.innerHeight - pad) top = window.innerHeight - h - pad;
                if (top < pad) top = pad;

                proTooltip.style.left = left + "px";
                proTooltip.style.top = top + "px";
                proTooltip.classList.add("show");
            };

            const hideTooltip = () => {
                proTooltip.classList.remove("show");
            };

            // ===== render =====
            function renderCalendar(year, month) {
                const calTitle = document.getElementById("calTitle");
                const calGrid = document.getElementById("calGrid");

                if (!calTitle || !calGrid) return;

                calTitle.textContent = `${monthNames[month]} ${year}`;
                calGrid.innerHTML = "";

                const first = new Date(year, month, 1);
                const startDay = first.getDay();
                const daysInMonth = new Date(year, month + 1, 0).getDate();
                const prevMonthDays = new Date(year, month, 0).getDate();
                const totalCells = 42;

                for (let i = 0; i < totalCells; i++) {
                    let dNum, dMonth = month,
                        dYear = year;
                    let isOut = false;

                    if (i < startDay) {
                        dNum = prevMonthDays - (startDay - 1 - i);
                        dMonth = month - 1;
                        if (dMonth < 0) {
                            dMonth = 11;
                            dYear--;
                        }
                        isOut = true;
                    } else if (i >= startDay + daysInMonth) {
                        dNum = i - (startDay + daysInMonth) + 1;
                        dMonth = month + 1;
                        if (dMonth > 11) {
                            dMonth = 0;
                            dYear++;
                        }
                        isOut = true;
                    } else {
                        dNum = i - startDay + 1;
                    }

                    const key = toKey(dYear, dMonth, dNum);
                    const data = classData[key] || null;
                    const hasClass = !!data;

                    const cell = document.createElement("div");
                    cell.className = `pro-day ${isOut ? "is-out" : ""} ${hasClass ? "has-class" : ""}`;
                    cell.dataset.date = key;

                    // If you want remove chip, delete the pro-events section
                    cell.innerHTML = `
          <span>${dNum}</span>
         

       
      `;

                    // Hover tooltip only if has class
                    if (hasClass) {
                        cell.addEventListener("mouseenter", (e) => showTooltip(e.clientX, e.clientY, key, data));
                        cell.addEventListener("mousemove", (e) => showTooltip(e.clientX, e.clientY, key, data));
                        cell.addEventListener("mouseleave", hideTooltip);
                    }

                    // Click (you can open modal)
                    cell.addEventListener("click", () => {
                        console.log("Clicked:", key, data);
                        // showLectureModal(key, hasClass ? [data] : []);
                    });

                    calGrid.appendChild(cell);
                }
            }

            // ===== navigation binding =====
            function bindCalendarNav() {
                const prevBtn = document.getElementById("calPrev");
                const nextBtn = document.getElementById("calNext");

                if (prevBtn) {
                    prevBtn.onclick = () => {
                        calMonth--;
                        if (calMonth < 0) {
                            calMonth = 11;
                            calYear--;
                        }
                        renderCalendar(calYear, calMonth);
                    };
                }

                if (nextBtn) {
                    nextBtn.onclick = () => {
                        calMonth++;
                        if (calMonth > 11) {
                            calMonth = 0;
                            calYear++;
                        }
                        renderCalendar(calYear, calMonth);
                    };
                }
            }

            // ===== init =====
            document.addEventListener("DOMContentLoaded", () => {
                bindCalendarNav();
                renderCalendar(calYear, calMonth);
            });
        </script>


    </body>

    </html>
<?php
}
?>