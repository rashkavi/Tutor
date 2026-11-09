<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Student');

?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Attendance - <?= Project::getPortalBasePath() ?></title>
        
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/newCalender.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
        <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">

        <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
    </head>

    <body class="overflow-hidden" onload="getAttendanceData();">
        <div class="notifications"></div>
        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
            <div id="appContainer" class="row p-0">
                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
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
                                                <span class="main-topic lh-lg" data-i18n="">Attendance</span>
                                                <span class="text-14 sub-text lh-base mt-1">Access your scheduled classes with complete time and subject details.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <?php
                                $date = new DateTime("now", new DateTimeZone("Asia/Colombo"));
                                $totalAttendanceQuery = "
                                        SELECT 
                                            SUM(CASE 
                                                WHEN a.attendance_status_attendance_status_id = 2  THEN 1
                                                ELSE 0
                                            END) AS present_count,
                                            COUNT(tt.time_table_id) AS total_count                                                                                                                                                                
                                                                               
                                         FROM 
                                    classes AS class
                                INNER JOIN 
                                    time_table AS tt ON class.class_id = tt.classes_class_id
                                INNER JOIN 
                                    students_has_classes AS shc ON class.class_id = shc.classes_class_id
                                INNER JOIN
                                    subjects AS subj ON class.subjects_subject_id = subj.subject_id
                                INNER JOIN 
                                    exam_type AS et ON class.exam_type_exam_type_id = et.exam_type_id
                                INNER JOIN
                                    year AS y ON class.year_year_id = y.year_id         
                                LEFT JOIN 
                                    attendance AS a ON tt.time_table_id = a.time_table_time_table_id
                                        AND a.students_student_id = '" . $sessionUserObject["student_id"] . "'
                                WHERE 
                                    shc.status_status_id = 1
                                        AND TIMESTAMP(tt.date, tt.start_time) <= '" . $date->format('Y-m-d H:i:s') . "'
                                        AND tt.time_table_status_time_table_status_id = 1 AND lecture_type_lecture_type_id != 3

                                           
                                    ";

                                $totalAttendanceData = Database::search($totalAttendanceQuery)->fetch_assoc();
                                $totalLectures = $totalAttendanceData["total_count"] ? $totalAttendanceData["total_count"] : 0;
                                $totalPresent = $totalAttendanceData["present_count"] ? $totalAttendanceData["present_count"] : 0;
                                $totalAbsent = $totalAttendanceData["total_count"] - $totalPresent;


                                ?>

                            
                                <div class="col-12 mb-4">
                                    <div class="row gx-4 gy-4">
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <div class="courier-kpi delivered">
                                                <div class="icon">
                                                    <iconify-icon icon="ph:seal-check-bold" width="22"></iconify-icon>
                                                </div>
                                                <div>
                                                    <div class="label">Total Present Classes</div>
                                                    <div class="value"><?= $totalPresent ?></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6 col-xl-3">
                                            <div class="courier-kpi cancelled">
                                                <div class="icon">
                                                    <iconify-icon icon="carbon:close-outline" width="22"></iconify-icon>
                                                </div>
                                                <div>
                                                    <div class="label">Total Absent Classes</div>
                                                    <div class="value"><?= $totalAbsent ?></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6 col-xl-3">
                                            <div class="courier-kpi delivered">
                                                <div class="icon">
                                                    <iconify-icon icon="hugeicons:chart-up" width="22"></iconify-icon>
                                                </div>
                                                <div>
                                                    <div class="label">Class Present Percentage</div>
                                                    <div class="value"><?php echo $totalPresent > 0 ? round(($totalPresent / $totalLectures) * 100) : 0; ?>%</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <div class="courier-kpi cancelled">
                                                <div class="icon">
                                                    <iconify-icon icon="hugeicons:chart-down" width="22"></iconify-icon>
                                                </div>
                                                <div>
                                                    <div class="label">Class Absent Percentage</div>
                                                    <div class="value"><?php echo $totalAbsent > 0 ?  round(($totalAbsent / $totalLectures) * 100) : 0; ?>%</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 p-4 component-theme">
                                    <div class="row p-0 m-0 gy-4 gx-3">

                                        <div class="container">
                                            <div class="col-12 d-flex flex-column flex-lg-row justify-content-center mb-4 mt-2">
                                                <div class="col-2 col-lg-1 d-none d-lg-block">
                                                    <button class="w-100 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="prevMonthSchedule(2)">
                                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                        <span class="text-14 main-text fw-semibold">Previous</span>
                                                    </button>
                                                </div>
                                                <div class="col-12 col-lg-10 d-flex justify-content-center align-items-center gap-2">
                                                    <h4 class="text-center" data-i18n="">Attendance</h4>
                                                    <h4 class="text-center">-</h4>
                                                    <h4 class="text-center" id="month"></h4>
                                                    <h4 class="text-center" id="year"></h4>
                                                </div>
                                                <div class="col-12 col-lg-1 mt-3 d-block d-lg-none">
                                                    <div class="row">
                                                        <div class="col-6 d-flex justify-content-start">
                                                            <button class="py-2 px-3 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="prevMonthSchedule(2)">
                                                                <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                                <span class="text-14 main-text fw-semibold">Previous</span>
                                                            </button>
                                                        </div>
                                                        <div class="col-6 d-flex justify-content-end">
                                                            <button class="py-2 px-3 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="nextMonthSchedule(2)">
                                                                <span class="text-14 main-text fw-semibold">Next</span>
                                                                <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-lg-1 text-end d-none d-lg-block">
                                                    <button class="w-100 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="nextMonthSchedule(2)">
                                                        <span class="text-14 main-text fw-semibold">Next</span>
                                                        <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                    </button>
                                                </div>
                                            </div>

                                            <div id="calendar" class="calendar"></div>
                                        </div>

                                        <!-- Event Modal -->
                                        <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="eventModalLabel">Events on <span id="selectedDate"></span></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body z-1" id="eventDetails"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="modal fade" id="lectureDetailsModal" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-start-0 modal-lg">
                                                <div class="modal-content lecture-modal-content">
                                                    <div class="modal-header lecture-modal-header">
                                                        <h5 class="modal-title" id="lectureDetailsTitle">Lecture Details</h5>
                                                        <button type="button" class="btn-close text-dark-color" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body" id="lectureDetailsBody"></div>
                                                    <div class="modal-footer" id="lectureDetailsFooter">

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
        <script src="<?= Project::getAssetsPath() ?>/js/functions.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/eventCalender.js?v=<?= Project::getVersion() ?>"></script>
    </body>

    </html>
<?php


}
?>