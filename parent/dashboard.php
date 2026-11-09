<?php

require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once '../includes/connection.php';
require_once '../includes/Project.php';
if (!SessionUser::ifUserIsParent()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Parent');

    $alertDisplay = false;
    if (isset($_COOKIE['enviiz_lms_parent_login'])) {
        setcookie('enviiz_lms_parent_login', '', time() - 3600, '/');
        unset($_COOKIE['enviiz_lms_parent_login']);
        $alertDisplay = true;
    }

?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Dashboard - <?= Project::getParentBasePath() ?></title>

        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/dashboard.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
    </head>

    <body class="overflow-hidden">
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
                        include "../includes/header.php";
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
                        <div class="row m-0 p-4 g-4">
                            <?php
                            if ($alertDisplay) {
                            ?>
                                <div class="alert-container p-0 m-0 g-0">
                                    <div class="alert alert-dismissible component-theme mb-4 br-0 custom-alert" role="alert" id="welcomeAlert">
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

                            <div class="col-12 p-0 m-0">
                                <div class="row g-3">

                                    <div class="col-12">
                                        <div class="row g-3">
                                            <!-- Total Orders -->
                                            <div class="col-12 col-md-6 col-xl-3">
                                                <div class="courier-kpi total">
                                                    <div class="icon">
                                                        <iconify-icon icon="eos-icons:product-classes-outlined" width="22"></iconify-icon>
                                                    </div>
                                                    <div>
                                                        <div class="label">Childrens</div>
                                                        <div class="value">1</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 col-md-6 col-xl-3">
                                                <div class="courier-kpi delivered">
                                                    <div class="icon">
                                                        <iconify-icon icon="bi:calendar3-week" width="22"></iconify-icon>
                                                    </div>
                                                    <div>
                                                        <div class="label">Total Clases</div>
                                                        <div class="value">5</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 col-md-6 col-xl-3">
                                                <div class="courier-kpi transit">
                                                    <div class="icon">
                                                        <iconify-icon icon="ci:wavy-check" width="22"></iconify-icon>
                                                    </div>
                                                    <div>
                                                        <div class="label">Weekly Upcoming Classes</div>
                                                        <div class="value">4</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-6 col-xl-3">
                                                <div class="courier-kpi transit">
                                                    <div class="icon">
                                                        <iconify-icon icon="ci:wavy-check" width="22"></iconify-icon>
                                                    </div>
                                                    <div>
                                                        <div class="label">Pending Payments</div>
                                                        <div class="value">4</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-4 d-flex align-items-stretch">
                                        <div class="today-card premium-card w-100">
                                            <!-- Header -->
                                            <div class="today-top">
                                                <div>
                                                    <div class="sub-topic">Today's Classes</div>
                                                    <div class="today-sub">Your scheduled sessions</div>
                                                </div>
                                                <div class="today-badge">
                                                    View All
                                                </div>
                                            </div>

                                            <!-- Class List -->
                                            <div class="today-class-list">
                                                <!-- Item -->
                                                <div class="today-class-item">
                                                    <div class="class-left">
                                                        <div class="class-name">Grade 11 - English</div>
                                                        <div class="class-meta">
                                                            <iconify-icon icon="mdi:laptop" width="15" class="me-1"></iconify-icon>
                                                            Online • 09:00 AM - 10:30 AM
                                                        </div>
                                                    </div>
                                                    <div class="footer-icon-group">
                                                        <button class="icon-premium" data-tooltip="View Class">
                                                            <iconify-icon icon="solar:eye-broken" width="20"></iconify-icon>
                                                        </button>
                                                    </div>

                                                </div>

                                                <div class="today-class-item">
                                                    <div class="class-left">
                                                        <div class="class-name">Grade 10 - Science</div>
                                                        <div class="class-meta">
                                                            <iconify-icon icon="mdi:school-outline" width="15" class="me-1"></iconify-icon>
                                                            Physical • 11:00 AM - 12:30 PM
                                                        </div>
                                                    </div>
                                                    <div class="footer-icon-group">
                                                        <button class="icon-premium" data-tooltip="View Class">
                                                            <iconify-icon icon="solar:eye-broken" width="20"></iconify-icon>
                                                        </button>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-lg-5 d-flex align-items-stretch">
                                        <div class="d-flex flex-column component-theme p-4 w-100">
                                            <div class="d-flex flex-row">
                                                <div class="col-8 d-flex flex-column">
                                                    <span class="sub-topic" data-i18n="">Weekly Attendance</span>
                                                </div>
                                            </div>
                                            <div class="component-theme d-flex flex-row align-items-center justify-content-center p-0 mt-2 w-100">
                                                <canvas id="attendanceLineChart"></canvas>
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

                                    <div class="col-12">
                                        <div class="row component-theme p-4 m-0 g-0 align-content-center">
                                            <div class="today-top">
                                                <div>
                                                    <div class="sub-topic">Recent Payments</div>
                                                </div>
                                                <div class="today-badge">
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
                                                                        <div class="d-flex justify-content-between align-items-center w-100">
                                                                            <span class="text-14 main-text fw-medium mx-2">Student</span>
                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                        </div>
                                                                    </div>
                                                                </th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Class</th>
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
                                                                        WHERE `s`.`student_id` IN (SELECT `students_student_id` FROM `parents_has_students` WHERE `parents_parent_id` = '" . $sessionUserObject["user_id"] . "')
                                                                        
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
                                                                                <span class="text-14 main-text mx-2"><?= $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                                            </div>
                                                                        </td>
                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["class_name"] ?></span></td>
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
                        </div>
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

        <script>
            new Chart(document.getElementById('attendanceLineChart'), {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr'],
                    datasets: [{
                            label: 'Alex',
                            data: [4, 3, 4, 2],
                            tension: 0.3
                        },
                        {
                            label: 'Emma',
                            data: [2, 4, 3, 2],
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    animation: {
                        duration: 0
                    }
                }
            });
        </script>

        <link rel="stylesheet"
            href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>


        <script>
            // ===== Example data (your data) =====
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