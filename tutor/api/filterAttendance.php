<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
require_once("../../includes/Project.php");
if (SessionUser::ifUserIsTutor()) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
            $content = trim(file_get_contents('php://input'));
            $decoded = json_decode($content, true);
            if (json_last_error() == JSON_ERROR_NONE) {

                $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                $sortByName = isset($decoded['sortByName']) ? $decoded['sortByName'] : 0;
                $sortByActive = isset($decoded['sortByActive']) ? $decoded['sortByActive'] : 0;
                $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                $pageno = isset($decoded['page']) ? $decoded['page'] : 1;
                $selectStudents = isset($decoded['selectStudents']) ? $decoded['selectStudents'] : [];
                $unSelectStudents = isset($decoded['unSelectStudents']) ? $decoded['unSelectStudents'] : [];

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
                
            ";

                if ($search_text != "") {
                    $query .= " AND (`student_id` LIKE '%" . $search_text . "%' OR `first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `email` LIKE '%" . $search_text . "%' OR `mobile` LIKE '%" . $search_text . "%')";
                }



                if ($sortByName != 0) {
                    $sort = $sortByName == 1 ? 'ASC' : 'DESC';
                }
                if ($sortByName == 0) {
                    $query .= " ORDER BY `students`.`student_id` DESC";
                }

                $results = Database::search($query);
                $results_count = $results->num_rows;
                $result_per_page = (int)$resultPerPage;

                if ($results_count > $result_per_page) {
                    $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                } else {
                    $number_of_pages = 1;
                }

                $page_results = ($pageno - 1) * $result_per_page;
                $students = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");
?>
                <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                    <thead>
                        <tr class="br-t-5">
                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                <div class="d-flex flex-row align-items-center cursor-pointer">
                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                    <div class="d-flex justify-content-between align-items-center w-100" onclick="handleAttendancePagination('sortByName')">
                                        <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                        <?php
                                        if ($sortByName == 0) {
                                        ?>
                                            <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                        <?php
                                        } else {
                                        ?>
                                            <iconify-icon icon="<?= $sortByName == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                        <?php
                                        }
                                        ?>
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

                        if ($students->num_rows > 0) {
                            while ($data = $students->fetch_assoc()) {
                                $scheduledClass = Database::search("SELECT COUNT(`time_table`.`time_table_id`) AS total FROM `time_table` 
                                                                INNER JOIN `students_has_classes` 
                                                                ON `time_table`.`classes_class_id` = `students_has_classes`.`classes_class_id`
                                                                WHERE `students_has_classes`.`students_student_id` = '" . $data['sId'] . "' 
                                                                AND `time_table`.`lecture_type_lecture_type_id` != 3");

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
<?php
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
