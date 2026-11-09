<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
if (SessionUser::ifUserIsTutor()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
            $content = trim(file_get_contents('php://input'));
            $decoded = json_decode($content, true);
            if (json_last_error() == JSON_ERROR_NONE) {

                if (!isset($decoded['classId']) || $decoded['classId'] == 0) {
                    $status->error = 'Invalid intake id';
                } else {
                    $sValidIntake = Database::search("SELECT COUNT(*) as 'count' FROM `classes` 
                    WHERE `class_id`='" . $decoded['classId'] . "' AND `tutor_id`='" . $sessionUserObject['user_id'] . "'");
                    $row = $sValidIntake->fetch_assoc();
                    if ($row['count'] == 0) {
                        $status->error = 'Invalid class id or you are not a tutor of this class';
                    } else {

                        if (!isset($decoded['update']) && $decoded["update"] != 0) {
                            http_response_code(400);
                            $status->error = 'Invalid user update request';
                        }

                        if (!isset($decoded['checkedStudents']) && !is_array($decoded['checkedStudents'])) {
                            $status->error = 'Please select atleast one user';
                        }

                        if (!isset($decoded['uncheckedStudents']) && !is_array($decoded['uncheckedStudents'])) {
                            $status->error = 'Please select atleast one user';
                        }

                        if (!property_exists($status, 'error')) {
                            $classId = $decoded['classId'];
                            $checkedStudents = $decoded['checkedStudents'];
                            $uncheckedStudents = $decoded['uncheckedStudents'];
                            $update = $decoded['update'];

                            if ($update == 1 || $update == 2) {
                                $changeStatusId = $update;
                                $activeStatusId = ($changeStatusId == 1 ? 2 : 1);

                                if (count($checkedStudents) > 0) {
                                    $query = "UPDATE `students_has_classes` SET `status_status_id` = '" . $changeStatusId . "' WHERE `classes_class_id`='" . $classId . "' AND `status_status_id` = '" . $activeStatusId . "' AND `students_has_class_id`  IN (" . implode(",", $checkedStudents) . ")";
                                }

                                if (count($uncheckedStudents) > 0) {
                                    $query = "UPDATE `students_has_classes` SET `status_status_id` = '" . $changeStatusId . "' WHERE `classes_class_id`='" . $classId . "' AND `status_status_id` = '" . $activeStatusId . "' AND `students_has_class_id`  NOT IN (" . implode(",", $uncheckedStudents) . ")";
                                }

                                if (count($checkedStudents) == 0 && count($uncheckedStudents) == 0) {
                                    $currentPage = $decoded['currentPage'];
                                    $resultPerPage = $decoded['resultPerPage'];
                                    $sort = $decoded['sortSelector'];
                                    $searchText = $decoded['searchText'];
                                    $pageResults = ($currentPage - 1) * $resultPerPage;

                                    $query = "UPDATE `students_has_classes` JOIN(SELECT `students_has_class_id` FROM `students_has_classes` INNER JOIN `students` 
                                                                ON `students_has_classes`.`students_student_id`=`students`.`student_id`
                                                                WHERE `classes_class_id`='" . $classId . "'";

                                    if ($searchText != "") {
                                        $query .= " AND `first_name` LIKE '%" . $searchText . "%' OR `last_name` LIKE '%" . $searchText . "%' OR `email` LIKE '%" . $searchText . "%' OR `mobile` LIKE '%" . $searchText . "%'";
                                    }

                                    if ($sort != 0) {
                                        if (strpos($query, 'WHERE') == false) {
                                            $query .= " WHERE `students_has_classes`.`status_status_id` = '" . $sort . "'";
                                        } else {
                                            $query .= " AND `students_has_classes`.`status_status_id` = '" . $sort . "'";
                                        }
                                    }

                                    $query .= " LIMIT " . $resultPerPage . " OFFSET " . $pageResults . ") AS paginated_students
                                                            ON `students_has_classes`.`students_has_class_id` = paginated_students.`students_has_class_id`
                                                            SET `students_has_classes`.`status_status_id` = '" . $changeStatusId . "'
                                                            WHERE `students_has_classes`.`classes_class_id` = '" . $classId . "' AND `students_has_classes`.`status_status_id` = '" . $activeStatusId . "'";
                                }

                                Database::iud($query);
                                if (count($checkedStudents) == 1 || count($uncheckedStudents) == 1) {
                                    $status->done = 'Selected Student is now' . " " . ($update == 1 ? 'activated' : 'deactivated');
                                } else {
                                    $status->done = 'All selected Students are now' . " " . ($update == 1 ? 'activated' : 'deactivated');
                                }
                            }
                        }
                    }
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

echo (json_encode($status));
