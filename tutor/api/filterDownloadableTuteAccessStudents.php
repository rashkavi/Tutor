<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
require_once("../../includes/Project.php");
if (SessionUser::ifUserIsTutor()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
            $content = trim(file_get_contents('php://input'));
            $decoded = json_decode($content, true);
            if (json_last_error() == JSON_ERROR_NONE) {
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                $tute_id = isset($decoded['tute_id']) ? $decoded['tute_id'] : '';

                $query = "SELECT * FROM `class_tutes` 
        INNER JOIN `classes` ON `classes_class_id`=`class_id`
        WHERE `class_tute_id`='" . $tute_id . "'";
                if (SessionUser::ifUserIsTutor()) {
                    $query .= " AND `tutor_id`='" . $sessionUserObject['user_id'] . "'";
                }

                $isValidClassId = Database::search($query);
                if ($isValidClassId->num_rows == 0) {
                    $status->error = 'Class does not exist';
                } else {
                    $classData = $isValidClassId->fetch_assoc();
                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $sortByName = isset($decoded['sortByName']) ? $decoded['sortByName'] : 0;
                    $sortByDate = isset($decoded['sortByDate']) ? $decoded['sortByDate'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;


                    $query = "
                                                           SELECT 
                                                               `students`.`student_id` AS 'sId', 
                                                                `students_has_classes`.`students_has_class_id` AS 'cId',
                                                                `students_has_classes`.`student_class_id`,
                                                               `first_name`, 
                                                               `last_name`, 
                                                               `email`, 
                                                               `mobile`, 
                                                               `whatsapp`, 
                                                               `profile_img`, 
                                                               `date_registered`, 
                                                               `date_of_birth`,
                                                               `gender`.`gender`,
                                                               `status`.`status`
                                                           FROM `students_has_classes`
                                                           INNER JOIN 
                                                                `students`
                                                               ON `students_has_classes`.`students_student_id` = `students`.`student_id`
                                                           INNER JOIN 
                                                               `login_details` 
                                                               ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                                                            INNER JOIN 
                                                               `gender` 
                                                               ON `students`.`gender_gender_id` = `gender`.`gender_id`
                                                           INNER JOIN 
                                                               `status` 
                                                               ON `students_has_classes`.`status_status_id` = `status`.`status_id`
                                                             
                                                          
                                                       ";

                    if (!empty($classData['payment_month'])) {

                        $paymentMonth = explode('-', $classData['payment_month']);
                        $year = $paymentMonth[0];
                        $month = $paymentMonth[1];

                        $query .= "
                                                        INNER JOIN student_has_payments 
                                                            ON student_has_payments.students_has_class_students_has_class_id = students_has_classes.students_has_class_id
                                                            AND YEAR(student_has_payments.date_added) = '" . $year . "'
                                                            AND MONTH(student_has_payments.date_added) = '" . $month . "'
                                                            AND student_has_payments.payment_status_payment_status_id = '3'
                                                        ";
                    }

                    $query .= " WHERE `students_has_classes`.`classes_class_id` = '" . $classData['class_id'] . "'";

                    if ($search_text != "") {
                        $query .= " AND (`student_id` LIKE '%" . $search_text . "%' OR `first_name` LIKE '%" . $search_text . "%' OR `last_name` LIKE '%" . $search_text . "%' OR `email` LIKE '%" . $search_text . "%' OR `mobile` LIKE '%" . $search_text . "%')";
                    }


                    if ($sortByName != 0) {
                        $sort = $sortByName == 1 ? 'ASC' : 'DESC';
                        if (strpos($query, 'ORDER BY') === false) {
                            $query .= " ORDER BY `first_name` " . $sort;
                        } else {
                            $query .= ", `first_name` " . $sort;
                        }
                    }

                    if ($sortByName == 0) {
                        $query .= " ORDER BY `students_has_classes`.`students_has_class_id` DESC";
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
                                        <div class="d-flex justify-content-between align-items-center w-100" onclick="handleDownloadableTuteAccessStudentsPagination(<?= $tute_id ?>,'sortByName')">
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
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Student Class ID</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">WhatsApp Number</th>
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
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["cId"]; ?>" onchange="checkRowEvents(<?php echo $data['cId']; ?>);">
                                                <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['cId']; ?>)">
                                                    <?php
                                                    if (!empty($data["profile_img"]) && file_exists(Project::getApiAssetsPath() . '/imgs/students/' . $data["profile_img"])) {
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

                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["student_class_id"]; ?></span></td>

                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["whatsapp"]; ?></span></td>

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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleDownloadableTuteAccessStudentsPagination(<?= $tute_id ?>,'previousPage');">
                                    <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                    <span class="text-14 main-text fw-semibold">Previous</span>
                                </button>
                                <!-- Page Numbers (col-10, centered) -->
                                <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                    <ul class="pagination-list d-flex mb-0">
                                        <?php
                                        $shudderPages1 = false;
                                        $shudderPages2 = false;

                                        function renderPageButton($i, $isActive = false, $tute_id)
                                        {
                                            $activeClass = $isActive ? 'active' : '';
                                            $action = $isActive ? '' : "onclick=\"handleDownloadableTuteAccessStudentsPagination('{$tute_id}','goToPage', '{$i}')\"";
                                            echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                        }

                                        for ($i = 1; $i <= $number_of_pages; $i++) {
                                            if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                renderPageButton($i, $pageno == $i, $tute_id);
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
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleDownloadableTuteAccessStudentsPagination(<?= $tute_id ?>,'nextPage',<?= $number_of_pages ?>);">
                                    <span class="text-14 main-text fw-semibold">Next</span>
                                    <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                </button>
                            </div>
                        </nav>
                    </div>
<?php
                }
            }
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
