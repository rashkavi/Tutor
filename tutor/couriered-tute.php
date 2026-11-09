<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsTutor()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['class-id']) && !empty($_GET['class-id']))) {
        header("Location: ./level-details");
    } else {

        $class_id = $_GET["class-id"];

        $query = "SELECT * FROM `classes` WHERE `class_id` = '" . $class_id . "'";
        if (SessionUser::ifUserIsTutor()) {
            $query .= " AND `tutor_id`='" . $sessionUserObject['user_id'] . "'";
        }
        $class = Database::search($query);
        if (!($class->num_rows > 0)) {
            header("Location: ./course-overview");
        } else {
            $classData = $class->fetch_assoc();

            $query = "
    SELECT 
        `students`.`student_id` AS 'sId', 
        `first_name`, 
        `last_name`, 
        `email`, 
        `mobile`, 
        `whatsapp`, 
        `profile_img`, 
        `date_registered`, 
    `status`.`status_id`,
        `status`.`status`
    FROM 
        `students`
    INNER JOIN 
        `login_details` 
        ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`

    INNER JOIN 
        `status` 
        ON `login_details`.`status_status_id` = `status`.`status_id`

    ";

?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Couriered Tute - <?= Project::getPortalBasePath() ?></title>
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/tute-card.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css">
                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/lecture-card.css">
            </head>

            <body class="overflow-hidden">
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

                                <div class="col-12 p-4 w-100 m-0" id="skeleton-list">
                                    <div class="row g-4">
                                        <?php
                                        for ($i = 0; $i < 4; $i++) {
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
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 py-4 px-4 pe-4 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12 col-lg-8">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg"><?= $classData['class_name'] ?></span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./classes">Classes</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Class Details</a></li>
                                                                <li class="breadcrumb-item active">Class Tutes</li>
                                                                <li class="breadcrumb-item active">Couried Tutes</li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="row gy-4">

                                                <div class="col-12">
                                                    <div class="row g-4">

                                                        <!-- Total Orders -->
                                                        <div class="col-12 col-md-6 col-xl-3">
                                                            <div class="courier-kpi total">
                                                                <div class="icon">
                                                                    <iconify-icon icon="solar:box-outline" width="22"></iconify-icon>
                                                                </div>
                                                                <div>
                                                                    <div class="label">Total Courier Orders</div>
                                                                    <div class="value">45</div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Delivered -->
                                                        <div class="col-12 col-md-6 col-xl-3">
                                                            <div class="courier-kpi delivered">
                                                                <div class="icon">
                                                                    <iconify-icon icon="solar:check-circle-bold" width="22"></iconify-icon>
                                                                </div>
                                                                <div>
                                                                    <div class="label">Delivered</div>
                                                                    <div class="value">38</div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- In Delivery -->
                                                        <div class="col-12 col-md-6 col-xl-3">
                                                            <div class="courier-kpi transit">
                                                                <div class="icon">
                                                                    <iconify-icon icon="mingcute:truck-line" width="22"></iconify-icon>
                                                                </div>
                                                                <div>
                                                                    <div class="label">In Delivery</div>
                                                                    <div class="value">5</div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Pending -->
                                                        <div class="col-12 col-md-6 col-xl-3">
                                                            <div class="courier-kpi pending">
                                                                <div class="icon">
                                                                    <iconify-icon icon="solar:clock-circle-outline" width="22"></iconify-icon>
                                                                </div>
                                                                <div>
                                                                    <div class="label">Pending Shipment</div>
                                                                    <div class="value">2</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12">
                                                    <div class="row g-4">
                                                        <div class="col-12 col-lg-6 d-flex">
                                                            <div class="d-flex flex-column component-theme p-4 w-100">
                                                                <div class="d-flex flex-row">
                                                                    <span class="sub-topic" data-i18n=""></span>
                                                                </div>
                                                                <div class="d-flex flex-column flex-xxl-row align-items-center justify-content-center my-4">
                                                                    <div class="h-auto p-0 m-0" id="chart-container">
                                                                        <canvas id="courierDonutChart"></canvas>
                                                                    </div>
                                                                    <div class="col-12 col-xxl-5 px-xxl-3 pe-0 mt-3 mt-xxl-0 align-items-center align-items-xxl-start justify-content-center justify-content-xxl-start">
                                                                        <div class="row w-100 align-items-center align-items-xxl-start justify-content-center justify-content-xxl-start">
                                                                            <div class="col-5 col-md-6 col-xxl-12 d-flex mb-xxl-3 align-items-center justify-content-start">
                                                                                <span class="hwr-10 grade-legend-item" style="background-color: var(--dark-green);"></span>
                                                                                <span data-i18n="">Delivered</span>
                                                                            </div>
                                                                            <div class="col-7 col-md-6 col-xxl-12 d-flex align-items-center justify-content-end justify-content-xxl-start">
                                                                                <span class="hwr-10 grade-legend-item" style="background-color: var(--dark-blue);"></span>
                                                                                <span data-i18n="">In Delivery</span>
                                                                            </div>
                                                                            <div class="col-5 col-md-6 col-xxl-12 d-flex mt-3 align-items-center justify-content-start">
                                                                                <span class="hwr-10 grade-legend-item" style="background-color: var(--dark-yellow);"></span>
                                                                                <span data-i18n="">Pending Shipment</span>
                                                                            </div>

                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-12 col-lg-6 d-flex">
                                                            <div class="d-flex flex-column component-theme p-4 w-100">
                                                                <div class="d-flex flex-row">
                                                                    <div class="col-8 d-flex flex-column">
                                                                        <span class="sub-topic" data-i18n=""></span>
                                                                    </div>
                                                                </div>
                                                                <div class="component-theme d-flex flex-row align-items-center justify-content-center p-0 mt-2 w-100">
                                                                    <canvas id="deliveryLineChart" class="w-100 h-auto"></canvas>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12">
                                                    <div class="row p-4 m-0 g-0 component-theme align-content-center">
                                                        <div class="col-12 mb-4">
                                                            <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                                <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                                    <div class="d-flex custom-div align-items-center justify-content-center">

                                                                        <span class="text-14 dark-theme fw-medium"><?php echo Database::search($query)->num_rows ?> <span data-i18n="students">Students</span></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-8 col-md-8 col-lg-9 d-flex flex-column">
                                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="search-text" onkeyup="handleStudentsPagination('filter')">
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-2 col-lg-1 d-flex flex-column justify-content-end">
                                                                    <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#studentsFilter" aria-expanded="false" aria-controls="studentsFilter">
                                                                        <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                                    </button>
                                                                </div>
                                                                <div class="collapse" id="studentsFilter">
                                                                    <div class="card card-body border-0 p-0">
                                                                        <div class="col-12">
                                                                            <div class="row justify-content-end">
                                                                                <div class="col-12 offset-0 offset-md-2 offset-lg-6 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                                    <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleStudentsPagination('sortByStatus')">
                                                                                        <option selected value="0">- Filter -</option>
                                                                                        <option value="1">Pending</option>
                                                                                        <option value="2">Confirmed</option>
                                                                                        <option value="3">Packed</option>
                                                                                        <option value="4">Dispatched</option>
                                                                                        <option value="5">In-Transit</option>
                                                                                        <option value="6">Delivered</option>
                                                                                    </select>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-6 d-none d-lg-block"></div>
                                                                <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                                    <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                                    <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleStudentsPagination('filter')">
                                                                        <option selected value="10">10</option>
                                                                        <option value="25">25</option>
                                                                        <option value="50">50</option>
                                                                        <option value="100">100</option>
                                                                        <option value="250">250</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="table-responsive-wrapper">
                                                            <div class="table-responsive" id="students-table-container">
                                                                <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                                                                    <thead>
                                                                        <tr class="br-t-5">
                                                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                                <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                    <div class="d-flex justify-content-between align-items-center w-100" onclick="handleStudentsPagination('sortByName')">
                                                                                        <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                                        <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                    </div>
                                                                                </div>
                                                                            </th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">WhatsApp Number</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Tracking Id</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Last Updated</th>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="user-table-data">
                                                                        <?php

                                                                        $results = Database::search($query);
                                                                        $pageno = 1;
                                                                        $results_count = $results->num_rows;
                                                                        $result_per_page = 10;  // Consistent variable name

                                                                        if ($results_count > $result_per_page) {
                                                                            $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                                                        } else {
                                                                            $number_of_pages = 1;
                                                                        }

                                                                        $page_results = ($pageno - 1) * $result_per_page;
                                                                        $users = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                        if ($users->num_rows > 0) {
                                                                            $deliver_status = 1;
                                                                            $deliver_status_text = ["Pending", "Confirmed", "Packed", "Dispatched", "In-Transit", "Out for Delivery", "Delivered"];
                                                                            while ($data = $users->fetch_assoc()) {
                                                                                $sbutton_class = "label-role-" . $deliver_status;
                                                                                $sbutton_text = $deliver_status_text[$deliver_status];
                                                                                if ($deliver_status == 6) {
                                                                                    $deliver_status = 1;
                                                                                }
                                                                                $deliver_status++;
                                                                        ?>
                                                                                <tr>
                                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["sId"]; ?>" onchange="checkRowEvents(<?php echo $data['sId']; ?>);">
                                                                                            <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['sId']; ?>)">
                                                                                                <?php
                                                                                                if (!empty($data["profile_img"]) && file_exists('<?= Project::getAssetsPath() ?>/imgs/students/' . $data["profile_img"])) {
                                                                                                ?>
                                                                                                    <img src="<?= Project::getAssetsPath() ?>/imgs/students/<?php echo $data["profile_img"]; ?>" class="hwr-40" alt="Students image">
                                                                                                <?php
                                                                                                } else {
                                                                                                    $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                                                                    $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                                                                ?>
                                                                                                    <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                                                                <span class="text-14 main-text mx-2"><?php echo $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </td>

                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["whatsapp"]; ?></span></td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"> TD_<?php echo str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT); ?></span></td>
                                                                                    <td class="py-2 align-middle">
                                                                                        <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $sbutton_text; ?></button>
                                                                                    </td>
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo date("Y-m-d h:i A", strtotime($data["date_registered"])); ?></span></td>
                                                                                    <td class="py-3 align-middle">
                                                                                        <div class="dropdown">
                                                                                            <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                            <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="couriered-tute-view.php?id=<?= $data['sId']; ?>">
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
                                                                <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                                                                    <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                        <div class="row p-4 align-items-center">
                                                                            <!-- Previous Button (col-1) -->
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleStudentsPagination('previousPage');">
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
                                                                                        $action = $isActive ? '' : "onclick=\"handleStudentsPagination('goToPage', '{$i}')\"";
                                                                                        echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                                                                    }

                                                                                    for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                        if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                                                            renderPageButton($i, $pageno == $i);
                                                                                        } elseif (!$shudderPages1 && ($i < $pageno - 2 && $i > 1)) {
                                                                                            $shudderPages1 = true;
                                                                                            echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                        } elseif (!$shudderPages2 && ($i > $pageno + 2 && $i < $number_of_pages)) {
                                                                                            $shudderPages2 = true;
                                                                                            echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                        }
                                                                                    }
                                                                                    ?>

                                                                                </ul>
                                                                            </div>
                                                                            <!-- Next Button (col-1) -->
                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleStudentsPagination('nextPage',<?= $number_of_pages ?>);">
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

                <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>

                <script>
                    document.addEventListener("DOMContentLoaded", function() {

                        /* ===============================
                           DONUT CHART – Courier Status
                        ================================ */

                        const donutCtx = document.getElementById('courierDonutChart');

                        new Chart(donutCtx, {
                            type: 'doughnut',
                            data: {
                                labels: ['Delivered', 'In Delivery', 'Pending Shipment'],
                                datasets: [{
                                    data: [38, 5, 2], // <-- Replace with PHP values
                                    backgroundColor: [
                                        '#28a745',
                                        '#2d8cff',
                                        '#f59e0b'
                                    ],
                                    borderWidth: 0,
                                    hoverOffset: 8
                                }]
                            },
                            options: {
                                responsive: true,
                                cutout: '70%',
                                plugins: {
                                    legend: {
                                        display: false

                                    },
                                    tooltip: {
                                        backgroundColor: '#111827',
                                        padding: 12,
                                        titleFont: {
                                            size: 14
                                        },
                                        bodyFont: {
                                            size: 13
                                        }
                                    }
                                },
                                animation: {
                                    duration: 0
                                },

                            }
                        });


                        /* ===============================
                           LINE CHART – Delivery Timeline
                        ================================ */

                        const lineCtx = document.getElementById('deliveryLineChart');

                        new Chart(lineCtx, {
                            type: 'line',
                            data: {
                                labels: ['1 Feb', '5 Feb', '10 Feb', '15 Feb', '20 Feb', '25 Feb'],
                                datasets: [{
                                        label: 'Delivered',
                                        data: [5, 8, 12, 20, 28, 38],
                                        borderColor: '#28a745',
                                        backgroundColor: 'rgba(40,167,69,0.1)',
                                        tension: 0.4,
                                        fill: true,
                                        pointRadius: 4,
                                        pointHoverRadius: 6
                                    },
                                    {
                                        label: 'In Delivery',
                                        data: [3, 4, 6, 8, 6, 5],
                                        borderColor: '#2d8cff',
                                        backgroundColor: 'rgba(45,140,255,0.08)',
                                        tension: 0.4,
                                        fill: true,
                                        pointRadius: 4,
                                        pointHoverRadius: 6
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                interaction: {
                                    mode: 'index',
                                    intersect: false
                                },
                                plugins: {
                                    legend: {
                                        position: 'top',
                                        labels: {
                                            usePointStyle: true,
                                            padding: 15
                                        }
                                    },
                                    tooltip: {
                                        backgroundColor: '#111827'
                                    }
                                },
                                scales: {
                                    x: {
                                        grid: {
                                            display: false
                                        }
                                    },
                                    y: {
                                        grid: {
                                            color: 'rgba(0,0,0,0.05)'
                                        },
                                        beginAtZero: true
                                    }

                                },
                                animation: {
                                    duration: 0
                                },
                            }
                        });

                    });
                </script>

            </body>

            </html>
<?php
        }
    }
}
?>