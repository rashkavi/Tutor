<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsTutor()) {
    header("Location: ./index");
} else {

    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
    if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['class-id']) && !empty($_GET['class-id']))) {
        header("Location: ./classes");
    } else {
        date_default_timezone_set('Asia/Colombo');
        $class_id = $_GET["class-id"];

        $query = " SELECT 
                                                    classes.*,
                                                    subjects.subject,
                                                    year.year,
                                                    medium.medium,
                                                    exam_type.exam_type,
                                                    exam_type.exam_type_id,
                                                    student_count.student_count,
                                                    class_count.class_count
                                                    
                                                FROM classes
                                                INNER JOIN subjects ON classes.subjects_subject_id = subjects.subject_id
                                                INNER JOIN year ON classes.year_year_id = year.year_id
                                                INNER JOIN medium ON classes.medium_medium_id = medium.medium_id
                                                INNER JOIN exam_type ON classes.exam_type_exam_type_id = exam_type.exam_type_id
                                                INNER JOIN (SELECT COUNT(*) AS student_count, classes_class_id FROM `students_has_classes` WHERE `status_status_id` = '1' GROUP BY classes_class_id) AS student_count ON classes.class_id = student_count.classes_class_id
                                                INNER JOIN (SELECT COUNT(*) AS class_count,classes_class_id FROM `time_table` WHERE `lecture_type_lecture_type_id` != '3' 
                                                AND `time_table_status_time_table_status_id`='1'
                                                AND CONCAT(`date`, ' ', `start_time`) < now()
                                                 GROUP BY classes_class_id) AS class_count ON classes.class_id = class_count.classes_class_id
                                                WHERE classes.class_id = '" . $class_id . "'
                                                AND `tutor_id`='" . $sessionUserObject['user_id'] . "'
                                                ";

        $class = Database::search($query);
        if ($class->num_rows == 0) {
            header("Location: ./classes");
        } else {
            $classData = $class->fetch_assoc();

            $presentCount = 0;
            $absent = 0;
            $watched = 0;
            $noAction = 0;


            $query = "SELECT 
             SUM(CASE 
            WHEN attendance_status_attendance_status_id = 2  THEN 1
                                                ELSE 0
                                            END) AS present_count,
                                            SUM(CASE 
            WHEN attendance_status_attendance_status_id = 3  THEN 1
                                                ELSE 0
                                            END) AS absent_count,
                                            SUM(CASE 
            WHEN attendance_status_attendance_status_id = 4  THEN 1
                                                ELSE 0
                                            END) AS watched_count,

                                            COUNT(students_has_class_id) AS students_count 
             FROM
            `time_table` 
            INNER JOIN `students_has_classes` ON `time_table`.`classes_class_id` = `students_has_classes`.`classes_class_id` 
            LEFT JOIN `attendance` ON `time_table`.`time_table_id` = `attendance`.`time_table_time_table_id`
            WHERE `time_table`.`classes_class_id` = '" . $class_id . "' 
            AND `time_table`.`lecture_type_lecture_type_id` != 3 AND `students_has_classes`.`status_status_id` = '1'
            AND CONCAT(`time_table`.`date`, ' ', `time_table`.`start_time`) >= `students_has_classes`.`added_at`
            AND CONCAT(`date`, ' ', `start_time`) < now()
            GROUP BY `time_table`.`time_table_id`
            ORDER BY `time_table`.`date` ASC";

            $attendance = Database::search($query);

            while ($attendanceData = $attendance->fetch_assoc()) {
                $presentCount += $attendanceData['present_count'];
                $absentCount = $attendanceData['absent_count'];
                $watched += $attendanceData['watched_count'];

                if (($presentCount + $absentCount) != $attendanceData['students_count']) {
                    $absentCount = $attendanceData['students_count'] - ($presentCount);
                }

                $absent += $absentCount;

                if ($absentCount != $attendanceData['watched_count']) {
                    $noAction += $absentCount - $attendanceData['watched_count'];
                }
            }

            $totalPossible = ($classData['student_count'] ?? 0) * ($classData['class_count'] ?? 0);

            if ($totalPossible > 0) {
                $presentAverage  = ($presentCount / $totalPossible) * 100;
                $absentAverage   = ($absent / $totalPossible) * 100;
                $watchedAverage  = ($watched / $totalPossible) * 100;
                $noActionAverage = ($noAction / $totalPossible) * 100;
            } else {
                $presentAverage  = 0;
                $absentAverage   = 0;
                $watchedAverage  = 0;
                $noActionAverage = 0;
            }

            $last7 = Database::search("SELECT `date`,`time_table_id`,`lecture_title`,`start_time`,`end_time`,`lecture_type`,`class_id` FROM `time_table`
            INNER JOIN `classes` ON `time_table`.`classes_class_id` = `classes`.`class_id`
            INNER JOIN `lecture_type` ON `time_table`.`lecture_type_lecture_type_id` = `lecture_type`.`lecture_type_id`
            WHERE `classes_class_id` = '" . $class_id . "'
           
            AND `time_table`.`time_table_status_time_table_status_id` = 1 AND `time_table`.`lecture_type_lecture_type_id` != 3
            ORDER BY `time_table`.`date` DESC LIMIT 20");

            $last7Array = [];

            while ($row = $last7->fetch_assoc()) {
                $last7Array[] = $row;
            }

?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Class Attendnace - Tutor Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="../assets/css/dashboard.css">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
            </head>

            <body class="overflow-hidden" onload="initializeClassAttendance(<?= $class_id ?>);">
                <div class="notifications"></div>
                <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                    <div id="appContainer" class="row p-0">
                        <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0  vh-100">
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
                                <div class="container p-4 m-0 g-0" id="skeleton-list">
                                    <?php
                                    include "../includes/components/skeleton-header.php";
                                    include "../includes/components/skeleton-table.php"; ?>
                                </div>
                                <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <span class="main-topic lh-lg" data-i18n=""><?= $classData['class_name'] ?></span>
                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                    <ol class="breadcrumb m-0">
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./classes">Classes</a></li>
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Class Attendance</a></li>
                                                    </ol>
                                                </nav>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="row gx-4">

                                                <div class="col-12 col-lg-4 col-xl-3">
                                                    <div class="row gy-4">
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

                                                        <div class="col-12">
                                                            <div class="courier-kpi total">
                                                                <div class="icon">
                                                                    <iconify-icon icon="eos-icons:product-classes-outlined" width="22"></iconify-icon>
                                                                </div>
                                                                <div>
                                                                    <div class="label">Completed Classes</div>
                                                                    <div class="value"><?= $classData['class_count'] ?></div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Delivered -->
                                                        <div class="col-12">
                                                            <div class="courier-kpi delivered">
                                                                <div class="icon">
                                                                    <iconify-icon icon="ph:student" width="22"></iconify-icon>
                                                                </div>
                                                                <div>
                                                                    <div class="label">Students</div>
                                                                    <div class="value"><?= $classData['student_count'] ?></div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                    </div>
                                                </div>

                                                <div class="col-12 col-lg-8 col-xl-9 overflow-scroll" id="attendance-container">
                                                    <div class="row h-100 p-4 m-0 component-theme 
                                            justify-content-center align-items-center">

                                                        <img src="<?= Project::getAssetsPath() ?>/imgs/common/callendar.png"
                                                            class="no-data-icon"
                                                            alt="">
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

                <div class="modal fade br-0" id="csvDownload" aria-hidden="true" aria-labelledby="csvDownloadLabel" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                        <div class="modal-content p-4">
                            <div class="modal-header p-0 border-0">
                                <h1 class="modal-title text-16 text-dark-color" id="csvDownloadLabel" data-i18n="download-students">Download Students CSV</h1>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-0 mt-3">
                                <!-- Waiting Message and Animation -->
                                <div id="waitingMessage" class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden" data-i18n="loading">Loading...</span>
                                    </div>
                                    <h3 class="mt-3" data-i18n="preparing-csv">Preparing Your CSV File...</h3>
                                    <p class="text-muted" data-i18n="please-wait-preparing-csv">Please wait while we generate your download.</p>
                                </div>

                                <!-- Success Message (Hidden by Default) -->
                                <div id="successMessage" class="text-center" class="d-none">
                                    <div class="checkmark-circle">
                                        <div class="checkmark"></div>
                                    </div>
                                    <h3 class="mt-3" data-i18n="download-ready">Download Ready!</h3>
                                    <p class="text-muted" data-i18n="csv-ready">Your CSV file has been successfully generated.</p>
                                    <button class="custom-btn dark-theme px-5 mt-3 text-white" data-bs-dismiss="modal">Close</button>
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
        ?>
<?php
    }
}

?>