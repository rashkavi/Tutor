<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsTutor()) {
    header("Location: ./index");
} else {

    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    $timezone = new DateTimeZone("Asia/Colombo");
    $dateTime = new DateTime("now", $timezone);
    $date = $dateTime->format('Y-m-d H:i:s');

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
            `date_of_birth`,
            `status`.`status`,
            `gender`
        FROM 
            `students`
        INNER JOIN 
            `login_details` 
            ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
       
        INNER JOIN 
            `status` 
            ON `login_details`.`status_status_id` = `status`.`status_id`
        INNER JOIN `gender` 
            ON `students`.`gender_gender_id`=`gender`.`gender_id`
       INNER JOIN `students_has_classes` 
        ON `students`.`student_id` = `students_has_classes`.`students_student_id`
        INNER JOIN `classes`
        ON `classes`.`class_id` = `students_has_classes`.`classes_class_id`
        WHERE `classes`.`tutor_id` = '" . $sessionUserObject['user_id'] . "'
        ORDER BY 
            `students`.`student_id` DESC
    ";

  
    $result = Database::search("
    SELECT
        COUNT(DISTINCT eligible.class_id) AS total_classes_count,
        COUNT(DISTINCT eligible.students_student_id) AS total_students_count,
        COUNT(DISTINCT eligible.time_table_id) AS total_class_count,
        COUNT(*) AS total_eligible_records,

        SUM(CASE WHEN eligible.attendance_status_attendance_status_id = 2 THEN 1 ELSE 0 END) AS present_count,

        SUM(CASE WHEN eligible.attendance_status_attendance_status_id = 4 THEN 1 ELSE 0 END) AS recording_watched_count
       
    FROM (
        SELECT
            shc.students_student_id,
            tt.time_table_id,
            a.attendance_status_attendance_status_id,
            cl.class_id
        FROM students_has_classes shc
        INNER JOIN classes cl
            ON cl.class_id = shc.classes_class_id
        INNER JOIN time_table tt
            ON tt.classes_class_id = shc.classes_class_id
        LEFT JOIN attendance a
            ON a.time_table_time_table_id = tt.time_table_id
            AND a.students_student_id = shc.students_student_id
        WHERE cl.tutor_id = '" . $sessionUserObject['user_id'] . "'
          AND CONCAT(tt.date, ' ', tt.start_time) >= shc.added_at
          AND CONCAT(tt.date, ' ', tt.start_time) <= '" . $date . "'
          AND shc.status_status_id = 1
    ) AS eligible
");

    $cardData = $result->fetch_assoc();

    $totalEligibleRecords   = $cardData['total_eligible_records'] ?? 0;
    $totalPresentCount      = $cardData['present_count'] ?? 0;
    $totalRecordingWatched  = $cardData['recording_watched_count'] ?? 0;

    $totalAbsentCount = $totalEligibleRecords - $totalPresentCount;
    $noAnyAction = $totalAbsentCount - $totalRecordingWatched;

    $presentPrecentage = $totalEligibleRecords > 0
        ? ($totalPresentCount / $totalEligibleRecords) * 100
        : 0;

    $presentPrecentage = number_format($presentPrecentage, 2);

    $absentPrecentage = $totalEligibleRecords > 0
        ? ($totalAbsentCount / $totalEligibleRecords) * 100
        : 0;

    $absentPrecentage = number_format($absentPrecentage, 2);

    $watchedPrecentage = $totalAbsentCount > 0
        ? ($totalRecordingWatched / $totalAbsentCount) * 100
        : 0;

    $watchedPrecentage = number_format($watchedPrecentage, 2);

    $noAnyActionPrecentage = $totalAbsentCount > 0
        ? ($noAnyAction / $totalAbsentCount) * 100
        : 0;

    $noAnyActionPrecentage = number_format($noAnyActionPrecentage, 2);


?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Attendnace - Admin Dashboard | <?= Project::getBaseName() ?></title>

        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/attendance.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
    </head>

    <body class="overflow-hidden" onload="initializeAttendance(<?= $presentPrecentage ?>,<?= $absentPrecentage ?>,<?= $watchedPrecentage ?>);">
        <div class="notifications"></div>
        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
            <div id="appContainer" class="row p-0">
                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0  vh-100">
                    <?php
                    include "includes/side-menu.php";
                    load_side_menu::load("Attendance");
                    ?>
                </div>
                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                    <div class="row p-0 m-0 g-0">
                        <div class="col-12 p-0 m-0 g-0">
                            <?php
                            include "includes/header.php";
                            selectItem::setSelected("Attendance");

                            ?>
                        </div>
                        <div class="container p-4 m-0 g-0" id="skeleton-list">
                            <?php
                            include "../includes/components/skeleton-header.php";
                            include "../includes/components/skeleton-table.php"; ?>
                        </div>
                        <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                            <div class="row p-4 m-0 g-0">
                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                    <div class="row p-0 m-0 g-0">
                                        <span class="main-topic lh-lg" data-i18n="">Attendnace</span>
                                        <span class="text-14 sub-text lh-base mt-1" data-i18n="">Monitor attendance performance, track punctuality, and manage records effortlessly.</span>
                                    </div>
                                </div>

                                <div class="col-12 mb-4">
                                    <div class="row">
                                        <div class="col-12 col-lg-6">
                                            <div class="row g-4">

                                                <!-- Total Orders -->
                                                <div class="col-12 col-md-6">
                                                    <div class="courier-kpi total">
                                                        <div class="icon">
                                                            <iconify-icon icon="eos-icons:product-classes-outlined" width="22"></iconify-icon>
                                                        </div>
                                                        <div>
                                                            <div class="label">Classes</div>
                                                            <div class="value"><?= $cardData['total_classes_count'] == '' ? 0 : $cardData['total_classes_count'] ?></div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Delivered -->
                                                <div class="col-12 col-md-6">
                                                    <div class="courier-kpi delivered">
                                                        <div class="icon">
                                                            <iconify-icon icon="ph:student" width="22"></iconify-icon>
                                                        </div>
                                                        <div>
                                                            <div class="label">Students</div>
                                                            <div class="value"><?= $cardData['total_students_count'] == '' ? 0 : $cardData['total_students_count'] ?></div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- In Delivery -->
                                                <div class="col-12 col-md-6">
                                                    <div class="courier-kpi transit">
                                                        <div class="icon">
                                                            <iconify-icon icon="ci:wavy-check" width="22"></iconify-icon>
                                                        </div>
                                                        <div>
                                                            <div class="label">Average Class Present</div>
                                                            <div class="value"><?= number_format($presentPrecentage, 2) ?>%</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Pending -->
                                                <div class="col-12 col-md-6">
                                                    <div class="courier-kpi cancelled">
                                                        <div class="icon">
                                                            <iconify-icon icon="carbon:pending" width="22"></iconify-icon>
                                                        </div>
                                                        <div>
                                                            <div class="label">Average Class Absent</div>
                                                            <div class="value"><?= number_format($absentPrecentage, 2) ?>%</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 col-md-6">
                                                    <div class="courier-kpi watched">
                                                        <div class="icon">
                                                            <iconify-icon icon="solar:eye-broken" width="22"></iconify-icon>
                                                        </div>
                                                        <div>
                                                            <div class="label">Recording Watched</div>
                                                            <div class="value"><?= number_format($watchedPrecentage, 2) ?>%</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 col-md-6">
                                                    <div class="courier-kpi cancelled">
                                                        <div class="icon">
                                                            <iconify-icon icon="carbon:warning" width="22"></iconify-icon>
                                                        </div>
                                                        <div>
                                                            <div class="label">No Any Action</div>
                                                            <div class="value"><?= number_format($noAnyActionPrecentage, 2) ?>%</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-lg-6">
                                            <div class="att-chart-card h-100">
                                                <div class="att-chart-head">
                                                    <div>
                                                        <div class="att-chart-title">Attendance Overview</div>
                                                        <div class="att-chart-sub">Present, Absent & Recording Watch rate</div>
                                                    </div>
                                                </div>

                                                <div class="att-donut-grid">
                                                    <!-- Present -->
                                                    <div class="att-donut-item">
                                                        <div class="att-donut-wrap">
                                                            <canvas id="donutPresent" height="120"></canvas>
                                                            <div class="att-donut-center">
                                                                <div class="p" id="presentPct">55%</div>
                                                                <div class="t">Present</div>
                                                            </div>
                                                        </div>
                                                        <div class="att-donut-meta">
                                                            <span class="dot dot-present"></span>
                                                            <span>Present</span>
                                                        </div>
                                                    </div>

                                                    <!-- Absent -->
                                                    <div class="att-donut-item">
                                                        <div class="att-donut-wrap">
                                                            <canvas id="donutAbsent" height="120"></canvas>
                                                            <div class="att-donut-center">
                                                                <div class="p" id="absentPct">45%</div>
                                                                <div class="t">Absent</div>
                                                            </div>
                                                        </div>
                                                        <div class="att-donut-meta">
                                                            <span class="dot dot-absent"></span>
                                                            <span>Absent</span>
                                                        </div>
                                                    </div>

                                                    <!-- Watched -->
                                                    <div class="att-donut-item">
                                                        <div class="att-donut-wrap">
                                                            <canvas id="donutWatched" height="120"></canvas>
                                                            <div class="att-donut-center">
                                                                <div class="p" id="watchPct">38%</div>
                                                                <div class="t">Watched</div>
                                                            </div>
                                                        </div>
                                                        <div class="att-donut-meta">
                                                            <span class="dot dot-watch"></span>
                                                            <span>Recording Watched</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="att-chart-footer">
                                                    <div class="att-foot-item">
                                                        <span class="k">Total Students</span>
                                                        <span class="v"><?= $cardData['total_students_count'] ?></span>
                                                    </div>
                                                    <div class="att-foot-item">
                                                        <span class="k">Total Conducted Classes</span>
                                                        <span class="v"><?= $cardData['total_class_count'] ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 p-4 component-theme">
                                    <div class="row p-0 m-0 g-0 align-content-center">
                                        <div class="col-12 mb-4">
                                            <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                <div class="col-12 col-md-2 d-flex flex-column justify-content-start">
                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                        <span class="text-14 dark-theme fw-medium"><?php echo Database::search($query)->num_rows ?> <span data-i18n="students">Students</span></span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-10 col-lg-10 d-flex flex-column">
                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="search-text" onkeyup="handleAttendancePagination('filter')">
                                                    </div>
                                                </div>
                                               
                                                <div class="collapse" id="studentsFilter">
                                                    <div class="card card-body border-0 p-0">
                                                        <div class="col-12">
                                                            <div class="row justify-content-end">
                                                                <div class="col-12 offset-0 offset-md-2 offset-lg-6 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleAttendancePagination('sortByStatus')">
                                                                        <option selected value="0">- Filter Students Status -</option>
                                                                        <option value="1">Active</option>
                                                                        <option value="2">Inactive</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-6 d-none d-lg-block"></div>
                                                <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                    <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                    <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleAttendancePagination('filter')">
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
                                                                    <div class="d-flex justify-content-between align-items-center w-100" onclick="handleAttendancePagination('sortByName')">
                                                                        <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                        <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                    </div>
                                                                </div>
                                                            </th>

                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Scheduled Classes</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Present Classes</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Absent Classes</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Recording Watched Classes</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">
                                                                <div class="d-flex justify-content-between align-items-center w-100">
                                                                    <span class="text-14 main-text fw-medium">Active Precentage</span>
                                                                </div>
                                                            </th>
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
                                                            while ($data = $users->fetch_assoc()) {
                                                                $scheduledClass = Database::search("SELECT COUNT(`time_table`.`time_table_id`) AS total FROM `time_table` 
                                                                INNER JOIN `students_has_classes` 
                                                                ON `time_table`.`classes_class_id` = `students_has_classes`.`classes_class_id`
                                                                WHERE `students_has_classes`.`students_student_id` = '" . $data['sId'] . "' 
                                                                AND `time_table`.`lecture_type_lecture_type_id` != 3
                                                                AND CONCAT(`time_table`.`date`, ' ', `time_table`.`start_time`) <= '" . $date . "'");

                                                                $attendanceCount = Database::search("
                                                                SELECT 
                                                                 
                                                                    SUM(CASE WHEN attendance_status_attendance_status_id = 2 THEN 1 ELSE 0 END) AS present_count,
                                                              
                                                                    SUM(CASE WHEN attendance_status_attendance_status_id = 4 THEN 1 ELSE 0 END) AS watch_count
                                                                FROM attendance
                                                                WHERE students_student_id = '" . $data['sId'] . "'
                                                            ");

                                                                $scheduledClass = $scheduledClass->fetch_assoc();
                                                                $attendanceCount = $attendanceCount->fetch_assoc();

                                                                $absent_count =  $scheduledClass['total'] - ($attendanceCount['present_count']);
                                                                $present_precentage = $scheduledClass['total'] == 0 ? 0 : $attendanceCount['present_count'] + $attendanceCount['watch_count'] / $scheduledClass['total'] * 100;
                                                        ?>
                                                                <tr>
                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["sId"]; ?>" onchange="checkRowEvents(<?php echo $data['sId']; ?>);">
                                                                            <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['sId']; ?>)">
                                                                                <?php
                                                                                if (!empty($data["profile_img"]) && file_exists(Project::getAssetsPath() . '/imgs/students/' . $data["profile_img"])) {
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

                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $scheduledClass['total'] == '' ? 0 : $scheduledClass['total']; ?></span></td>
                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $attendanceCount['present_count'] == '' ? 0 : $attendanceCount['present_count']; ?></span></td>
                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $absent_count; ?></span></td>
                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $attendanceCount['watch_count'] == '' ? 0 : $attendanceCount['watch_count']; ?></span></td>
                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo number_format($present_precentage, 2); ?>%</span></td>
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
                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleAttendancePagination('previousPage');">
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
                                                                        $action = $isActive ? '' : "onclick=\"handleAttendancePagination('goToPage', '{$i}')\"";
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
                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleAttendancePagination('nextPage',<?= $number_of_pages ?>);">
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
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>

   
    </body>

    </html>

<?php



}

?>