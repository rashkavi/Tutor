<?php

require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once '../includes/connection.php';
require_once '../includes/Project.php';

if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Student');

    $alertDisplay = false;
    if (isset($_COOKIE['enviiz_lms_student_login'])) {
        setcookie('enviiz_lms_student_login', '', time() - 3600, '/');
        unset($_COOKIE['enviiz_lms_student_login']);
        $alertDisplay = true;
    }

    $startOfWeek = date('Y-m-d', strtotime('monday this week'));
    $endOfWeek = date('Y-m-d', strtotime('sunday this week'));

    $query = "
    SELECT 
      
    (
        SELECT COUNT(*) 
        FROM students_has_classes shc
        WHERE shc.students_student_id = '" . $sessionUserObject['student_id'] . "'
        AND shc.status_status_id = '1'
    ) AS total_classes,
        
    (
        SELECT COUNT(*)
        FROM time_table tt
        INNER JOIN students_has_classes shc 
            ON tt.classes_class_id = shc.classes_class_id
        WHERE shc.students_student_id = '" . $sessionUserObject['student_id'] . "'
        AND shc.status_status_id = '1'
        AND tt.date BETWEEN '$startOfWeek' AND '$endOfWeek'
    ) AS weekly_classes,
    
    (
        SELECT COUNT(*)
        FROM attendance a
        WHERE a.students_student_id = '" . $sessionUserObject['student_id'] . "'
        AND a.attendance_status_attendance_status_id = '2'
        AND a.updated_at BETWEEN '$startOfWeek' AND '$endOfWeek'
    ) AS weekly_attendance
    ";

    $result = Database::search($query);
    $data = $result->fetch_assoc();


    $totalClasses = $data['total_classes'];
    $weeklyClasses = $data['weekly_classes'];
    $weeklyAttendance = $data['weekly_attendance'];

    $attendancePercentage = ($weeklyClasses > 0)
        ? ($weeklyAttendance / $weeklyClasses) * 100
        : 0;

?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Dashboard - <?= Project::getPortalBasePath() ?></title>

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
                                                <h3 class="mb-3">Welcome, <?= $sessionUserObject['user_fname'] . " " . $sessionUserObject['user_lname'] ?>!</h3>
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

                                    <div class="col-12 col-xl-9">
                                        <div class="row gx-4 gy-4">
                                            <!-- Total Orders -->
                                            <div class="col-12 col-md-6 col-xl-4">
                                                <div class="courier-kpi total">
                                                    <div class="icon">
                                                        <iconify-icon icon="eos-icons:product-classes-outlined" width="22"></iconify-icon>
                                                    </div>
                                                    <div>
                                                        <div class="label">Classes</div>
                                                        <div class="value"><?= $totalClasses ?></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Delivered -->
                                            <div class="col-12 col-md-6 col-xl-4">
                                                <div class="courier-kpi delivered">
                                                    <div class="icon">
                                                        <iconify-icon icon="bi:calendar3-week" width="22"></iconify-icon>
                                                    </div>
                                                    <div>
                                                        <div class="label">Weekly Classes</div>
                                                        <div class="value"><?= $weeklyClasses ?></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- In Delivery -->
                                            <div class="col-12 col-md-6 col-xl-4">
                                                <div class="courier-kpi transit">
                                                    <div class="icon">
                                                        <iconify-icon icon="ci:wavy-check" width="22"></iconify-icon>
                                                    </div>
                                                    <div>
                                                        <div class="label">Weekly Attendance</div>
                                                        <div class="value"><?= $weeklyAttendance ?></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 col-lg-6 d-flex align-items-stretch">
                                                <div class="today-card premium-card w-100">
                                                    <?php
                                                    date_default_timezone_set("Asia/Colombo");
                                                    $todayClasses = Database::search("SELECT * FROM `time_table`
                                            INNER JOIN classes ON classes.class_id = time_table.classes_class_id
                                            INNER JOIN students_has_classes ON classes.class_id = students_has_classes.classes_class_id
                                            INNER JOIN lecture_type ON lecture_type.lecture_type_id = time_table.lecture_type_lecture_type_id
                                            WHERE DATE(time_table.date) = CURDATE() 
                                            AND students_has_classes.students_student_id = '" . $sessionUserObject['student_id'] . "'
                                            AND students_has_classes.status_status_id = 1
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
                                                                <img src="<?= Project::getAssetsPath() ?>/imgs/common/no-data.png" class="no-data-icon-sm">
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 col-lg-6 d-flex align-items-stretch">
                                                <div class="d-flex flex-column component-theme p-4 w-100">
                                                    <div class="d-flex flex-row">
                                                        <div class="col-8 d-flex flex-column">
                                                            <span class="sub-topic" data-i18n="">Weekly Attendance</span>
                                                        </div>
                                                    </div>
                                                    <div class="component-theme d-flex flex-row align-items-center justify-content-center p-0 mt-2 w-100">
                                                        <canvas id="deliveryLineChart" class="w-100 h-auto"></canvas>
                                                    </div>

                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="row component-theme p-4 m-0 g-0 align-content-center">
                                                    <div class="today-top">
                                                        <div>
                                                            <div class="sub-topic">Recent Payments</div>
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
                                                                        AND `shi`.`students_student_id`='" . $sessionUserObject['student_id'] . "'
                                                                        ";



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

                                        </div>
                                    </div>

                                    <div class="col-12 col-xl-3">
                                        <div class="row gx-4 gy-4">

                                            <div class="col-12">
                                                <div class="st-card">

                                                    <!-- top -->
                                                    <div class="st-top">
                                                        <div class="st-info">
                                                            <div class="st-name"><?= $sessionUserObject['user_fname'] . " " . $sessionUserObject['user_lname'] ?></div>
                                                            <div class="st-sub">
                                                                <span class="st-id">STU-<?= $sessionUserObject['student_id'] ?></span>
                                                            </div>
                                                        </div>

                                                        <div class="st-badge active">
                                                            <iconify-icon icon="solar:check-circle-broken"></iconify-icon>
                                                            Active
                                                        </div>
                                                    </div>

                                                    <!-- stats -->
                                                    <div class="st-stats">
                                                        <div class="st-stat">
                                                            <div class="st-label">Classes</div>
                                                            <div class="st-value"><?= $totalClasses ?></div>
                                                        </div>

                                                        <div class="st-stat">
                                                            <div class="st-label">Attendance</div>
                                                            <div class="st-value"><?= $attendancePercentage ?>%</div>
                                                        </div>

                                                        <div class="st-stat">
                                                            <div class="st-label">Payments</div>
                                                            <div class="st-value pay ok">OK</div>
                                                        </div>
                                                    </div>

                                                    <!-- footer -->
                                                    <div class="st-actions">
                                                        <button class="st-btn-primary" onclick="window.location = 'my-profile'">
                                                            <iconify-icon icon="solar:user-id-broken"></iconify-icon>
                                                            View Profile
                                                        </button>

                                                        <button class="st-btn-icon" data-tooltip="Message">
                                                            <iconify-icon icon="solar:chat-round-dots-broken"></iconify-icon>
                                                        </button>

                                                        <button class="st-btn-icon" data-tooltip="More">
                                                            <iconify-icon icon="solar:menu-dots-bold"></iconify-icon>
                                                        </button>
                                                    </div>

                                                </div>
                                            </div>

                                            <div class="col-12 d-flex align-items-stretch">
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

    </body>

    </html>
<?php
}
?>