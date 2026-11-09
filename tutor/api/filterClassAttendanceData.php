<?php
$status = new stdClass();
require_once '../../includes/UserClass.php';
require_once '../../includes/UserPermissionsClass.php';
require_once "../../includes/connection.php";
require_once "../../includes/Project.php";
if (SessionUser::ifUserIsTutor()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
            $content = trim(file_get_contents('php://input'));
            $decoded = json_decode($content, true);
            if (json_last_error() == JSON_ERROR_NONE) {

                $time_table_id = isset($decoded['time_table_id']) ? $decoded['time_table_id'] : 0;
                $class_id = isset($decoded['class_id']) ? $decoded['class_id'] : '';

                $query = " SELECT 
                                                 *                                
                                                FROM classes
                                              
                                                INNER JOIN `time_table` ON `classes`.`class_id` = `time_table`.`classes_class_id`
                                                
                                                WHERE classes.class_id = '" . $class_id . "'
                                                AND `time_table`.`lecture_type_lecture_type_id` != 3
                                                AND `time_table`.`time_table_status_time_table_status_id` = 1
                                                AND `time_table`.`time_table_id` = '" . $time_table_id . "'
                                                AND `tutor_id`='" . $sessionUserObject['user_id'] . "'
                                                ";

                $class = Database::search($query);

                if ($class->num_rows == 0) {
                    $status->error = 'Invalid Class Id';
                } else {
                    $classData = $class->fetch_assoc();

                    $dateTimeString = $classData['date'] . ' ' . $classData['start_time'];

                    $dateObj = new DateTime($dateTimeString, new DateTimeZone("Asia/Colombo"));
                    $formattedDate = $dateObj->format('Y-m-d H:i:s');
                    $query = "SELECT 
                    students_has_classes.student_class_id AS sId,
                    students.student_id,
                    login_details.first_name,
                    login_details.last_name,
                    login_details.email,
                    login_details.whatsapp,
                    students.profile_img,
                    status.status,
                    att.attendance_status_attendance_status_id AS attendanceStatus,
                    att.updated_at
                  
                  FROM students
                  
                  INNER JOIN login_details 
                    ON students.login_details_login_details_id = login_details.login_details_id
                  
                  INNER JOIN status 
                    ON login_details.status_status_id = status.status_id
                  
                  INNER JOIN students_has_classes 
                    ON students.student_id = students_has_classes.students_student_id
                  
                  INNER JOIN classes 
                    ON students_has_classes.classes_class_id = classes.class_id
                  
                  LEFT JOIN attendance AS att
                    ON att.students_student_id = students.student_id
                    AND att.time_table_time_table_id = '" . $time_table_id . "'
                  
                  WHERE students_has_classes.status_status_id = '1'
                  AND classes.tutor_id = '" . $sessionUserObject['user_id'] . "'
                  AND classes.class_id = '" . $class_id . "'
                                  
                  GROUP BY students.student_id
                  ORDER BY students.student_id ASC
        ";
                    $results = Database::search($query);
                    $pageno = 1;
                    $results_count = $results->num_rows;
                    $result_per_page = 10;

                    if ($results_count > $result_per_page) {
                        $number_of_pages = ceil($results_count / $result_per_page);
                    } else {
                        $number_of_pages = 1;
                    }

                    $page_results = ($pageno - 1) * $result_per_page;
                    $students = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                    $attendance = Database::search("
                    SELECT
                        COUNT(CASE WHEN attendance_status_attendance_status_id = 2 THEN 1 END) AS present_count,
                        COUNT(CASE WHEN attendance_status_attendance_status_id = 3 THEN 1 END) AS absent_count,
                        COUNT(CASE WHEN attendance_status_attendance_status_id = 4 THEN 1 END) AS watched_count,
                        COUNT(*) AS total
                    FROM attendance
                    WHERE time_table_time_table_id = '" . $time_table_id . "'
                ");

                    $attendanceData = $attendance->fetch_assoc();

                    $presentCount = $attendanceData['present_count'];
                    $absent = $results_count - $presentCount;
                    $watched = $attendanceData['watched_count'];
?>
                    <div class="row m-0 mt-sm-4 mt-lg-0">
                        <div class="col-12 m-0 p-0">
                            <div class="row gy-4">
                                <div class="col-12">
                                    <div class="card border-0 rounded-0">
                                        <div class="card-body p-4">
                                            <div class="row align-items-center g-3">
                                                <div class="col-12 col-lg-9">
                                                    <small class="text-muted text-uppercase fw-semibold">
                                                        Class Details
                                                    </small>
                                                    <h5 class="fw-bold mb-1 mt-1">
                                                        <?= $classData['lecture_title'] ?>
                                                    </h5>
                                                    <div class="text-muted small">
                                                        <?= date("d M Y", strtotime($classData['date'])) ?> | <?= date("h:i A", strtotime($classData['start_time'])) ?> - <?= date("h:i A", strtotime($classData['end_time'])) ?>
                                                    </div>
                                                </div>

                                                <div class="col-12 col-lg-3 d-flex justify-content-end">
                                                    <button class="custom-btn w-100" type="button" onclick="downloadClassAttendanceReport(<?= $time_table_id ?>)">
                                                        <iconify-icon icon="line-md:download-loop" width="20" height="20" class="pe-2 m-0 text-white"></iconify-icon>
                                                        Attendance Report
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-4">
                                    <div class="courier-kpi total">
                                        <div class="icon">
                                            <iconify-icon icon="eos-icons:product-classes-outlined" width="22"></iconify-icon>
                                        </div>
                                        <div>
                                            <div class="label">Present Students</div>
                                            <div class="value"><?= $presentCount ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-lg-4">
                                    <div class="courier-kpi delivered">
                                        <div class="icon">
                                            <iconify-icon icon="ph:student" width="22"></iconify-icon>
                                        </div>
                                        <div>
                                            <div class="label">Absent Students</div>
                                            <div class="value"><?= $absent ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-lg-4">
                                    <div class="courier-kpi delivered">
                                        <div class="icon">
                                            <iconify-icon icon="ph:student" width="22"></iconify-icon>
                                        </div>
                                        <div>
                                            <div class="label">Watched Recording</div>
                                            <div class="value"><?= $watched ?></div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="col-12 component-theme p-4 mt-4" id="attendance-table-container">
                            <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                                <thead>
                                    <tr class="br-t-5">
                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center cursor-pointer w-25">
                                            <div class="d-flex flex-row align-items-center">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                <div class="d-flex justify-content-between align-items-center w-100" onclick="handleClassLectureAttendancePagination(<?= $time_table_id ?>,'sortByName')">
                                                    <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                    <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                </div>
                                            </div>
                                        </th>

                                        <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer align-content-center" onclick="handleClassLectureAttendancePagination(<?= $time_table_id ?>,'sortById')">
                                            <div class="d-flex justify-content-between align-items-center w-100">
                                                <span class="text-14 main-text fw-medium">Student ID</span>
                                                <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                            </div>
                                        </th>
                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Email Address</th>
                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">WhatsApp Number</th>
                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Attendance Status</th>

                                    </tr>
                                </thead>
                                <tbody id="user-table-data">
                                    <?php
                                    if ($students->num_rows > 0) {
                                        while ($data = $students->fetch_assoc()) {
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

                                                <td class="py-3 pe-0 align-middle">
                                                    <div class="flex-grow-1 d-flex align-items-center cursor-pointer">
                                                        <span class="text-14 main-text"><?php echo $data["sId"]; ?></span>
                                                    </div>
                                                </td>
                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["email"]; ?></span></td>
                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data['whatsapp']; ?></span></td>


                                                <?php
                                                $attendStatus = "Pending";
                                                $attendanceId = 0;
                                                if ($data['attendanceStatus'] != null) {
                                                    if ($data['attendanceStatus'] == 2) {
                                                        $attendStatus = "Present";
                                                    } else if ($data['attendanceStatus'] == 3) {
                                                        $attendStatus = "Absent";
                                                    } else if ($data['attendanceStatus'] == 1) {
                                                        $attendStatus = "Joined";
                                                    } else if ($data['attendanceStatus'] == 4) {
                                                        $attendStatus = "Recording Watched";
                                                        if ($classData['lecture_type_lecture_type_id'] == 3) {
                                                            $attendStatus = "Watch";
                                                        }
                                                    }

                                                    $attendanceId = $data['attendanceStatus'];
                                                }

                                                if ($classData['lecture_type_lecture_type_id'] != 3 && $attendanceId == 0) {
                                                    $attendStatus = "Absent";
                                                } else if ($classData['lecture_type_lecture_type_id'] == 3 && $attendanceId == 0) {
                                                    $attendStatus = "Not Watch";
                                                }

                                                ?>
                                                <td class="px-4 py-2 align-middle">
                                                    <?php
                                                    $status_classes = [
                                                        "Pending"     => "table-btn-yellow",
                                                        "Present"     => "table-btn-success",
                                                        "Absent"  => "table-btn-danger",
                                                        "Recording Watched"  => "table-btn-pink",
                                                        "Not Watch"  => "table-btn-danger",
                                                        "Watch"  => "table-btn-success",
                                                    ];
                                                    $sbutton_class = $status_classes[$attendStatus];
                                                    ?>
                                                    <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $attendStatus ?></button>
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
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleClassLectureAttendancePagination(<?= $time_table_id ?>,'previousPage');">
                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                            <span class="text-14 main-text fw-semibold">Previous</span>
                                        </button>
                                        <!-- Page Numbers (col-10, centered) -->
                                        <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                            <ul class="pagination-list d-flex mb-0">
                                                <?php
                                                $shudderPages1 = false;
                                                $shudderPages2 = false;

                                                function renderPageButton($i, $isActive = false, $lectureId)
                                                {
                                                    $activeClass = $isActive ? 'active' : '';
                                                    $action = $isActive ? '' : "onclick=\"handleClassLectureAttendancePagination({$lectureId},'goToPage', '{$i}')\"";
                                                    echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                                }

                                                for ($i = 1; $i <= $number_of_pages; $i++) {
                                                    if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                        renderPageButton($i, $pageno == $i, $time_table_id);
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
                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleClassLectureAttendancePagination(<?= $time_table_id ?>,'nextPage',<?= $number_of_pages ?>);">
                                            <span class="text-14 main-text fw-semibold">Next</span>
                                            <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                        </button>
                                    </div>
                                </nav>
                            </div>
                        </div>
                    </div>

<?php
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid JSON';
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid Content-Type';
        }
    } else {
        http_response_code(400);
        $status->error = 'Invalid request method';
    }
} else {
    $status->error = 'Authentication Failed!';
}

if (property_exists($status, 'error')) {
    echo (json_encode($status));
}
