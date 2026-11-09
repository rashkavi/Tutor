<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

        $class_id = isset($_POST['class_id']) ? trim($_POST['class_id']) : '';

        if (!isset($_FILES["file"])) {
            $status->file = "Please select a file";
        } else {
            $csvMimes = array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'text/plain');
            if (empty($_FILES['file']['name']) && !in_array($_FILES['file']['type'], $csvMimes)) {
                $status->file = "Please select a valid CSV file";
            } else {
                if (!is_uploaded_file($_FILES['file']['tmp_name'])) {
                    $status->file = "Please select a valid CSV file";
                } else {
                    $csvFile = fopen($_FILES['file']['tmp_name'], 'r');
                    fgetcsv($csvFile);

                    $approvedStudebtCount = 0;

                    $csvData = [];

                    while (($line = fgetcsv($csvFile)) !== FALSE) {
                        if (count(array_filter($line)) != 0) {
                            $csvData[] = $line;
                        }
                    }
                    fclose($csvFile);
                    if (count($csvData) == 0) {
                        $status->file = "Please select a valid CSV file";
                    }

                    if (!property_exists($status, 'file') && !property_exists($status, 'error')) {
                        $rowNumber = 1;

                        $linesErrors = [];
                        $mobileNumbers = [];
                        $emailAddresses = [];
                        $whatsappNumbers = [];
                        $nicNumbers = [];

                        foreach ($csvData as $data) {

                            $fname = $data[0];
                            $lname = $data[1];
                            $fullname = $data[2];
                            $nwi = $data[3];
                            $gender = $data[4];
                            $dob = $data[5];
                            $whatsapp = $data[6];

                            $lineErrors = [];

                            if (empty($fname)) {
                                $lineErrors[] =  "Data Row " . $rowNumber . ": student first name cannot be empty";
                            } else if (strlen($fname) > 45) {
                                $lineErrors[] =  "Data Row " . $rowNumber . ": First Name should be less than 45 characters";
                            }
                            if (empty($lname)) {
                                $lineErrors[] =  "Data Row " . $rowNumber . ": student last name cannot be empty";
                            } else if (strlen($lname) > 45) {
                                $lineErrors[] =  "Data Row " . $rowNumber . ": Last Name should be less than 45 characters";
                            }
                            if (empty($fullname)) {
                                $lineErrors[] =  "Data Row " . $rowNumber . ": student full name cannot be empty";
                            } else if (strlen($fullname) > 150) {
                                $lineErrors[] =  "Data Row " . $rowNumber . ": Full Name should be less than 150 characters";
                            }
                            if (empty($nwi)) {
                                $lineErrors[] =  "Data Row " . $rowNumber . ": Please enter student name with initials";
                            } else if (strlen($nwi) > 45) {
                                $lineErrors[] =  "Data Row " . $rowNumber . ": Name with initials should be less than 45 characters";
                            }

                            if ($gender == 0) {
                                $lineErrors[] =  "Data Row " . $rowNumber . ": Please add student gender";
                            } else {
                                $isValidGender = Database::search("SELECT * FROM `gender` WHERE `gender_id` = '" . $gender . "'");
                                if ($isValidGender->num_rows == 0) {
                                    $lineErrors[] =  "Data Row " . $rowNumber . ": Please add valid student gender";
                                }
                            }

                            if (empty($dob)) {
                                $lineErrors[] =  "Data Row " . $rowNumber . ": Please enter student date of birth";
                            } else {
                                $result = validateDateFlexible($dob, $rowNumber);

                                if (is_string($result)) {
                                    $lineErrors[] = $result;
                                }
                            }

                            $formattedWhatsapp = normalizePhoneNumber($whatsapp);
                            if (empty($whatsapp)) {
                                $lineErrors[] =  "Data Row " . $rowNumber . ": Please enter student whatsapp number";
                            } else if (!$formattedWhatsapp) {
                                $lineErrors[] =  "Data Row " . $rowNumber . ": Please enter a valid whatsapp number";
                            } else if (in_array($formattedWhatsapp, $whatsappNumbers)) {
                                $duplicateIndex = array_search($formattedWhatsapp, $whatsappNumbers);
                                $lineErrors[] = "Data Row " . $rowNumber . ": Whatsapp number already has been used in this CSV at line " . ($duplicateIndex + 1) . " student";
                            } else {
                                $whatsappHasStudent = Database::search("SELECT COUNT(*) as 'count' FROM `login_details` WHERE `whatsapp` = '" . $formattedWhatsapp . "'");
                                $row = $whatsappHasStudent->fetch_assoc();
                                if ($row['count'] > 0) {
                                    $lineErrors[] =  "Data Row " . $rowNumber . ": Whatsapp number already exists";
                                }
                            }

                            $rowNumber++;
                            if (count($lineErrors) > 0) {
                                $linesErrors[] = $lineErrors;
                            }
                        }
                        if (!empty($linesErrors)) {
                            $status->dataErrors = $linesErrors;
                        } else {

                            foreach ($csvData as $data) {
                                $fname = $data[0];
                                $lname = $data[1];
                                $fullname = $data[2];
                                $nwi = $data[3];
                                $gender = $data[4];
                                $dob = $data[5];
                                $whatsapp = $data[6];

                                $timezone = new DateTimeZone("Asia/Colombo");
                                $dateTime = new DateTime("now", $timezone);
                                $registerTime = $dateTime->format('Y-m-d H:i:s');

                                $timestamp = strtotime($dob);
                                $dob = date("Y-m-d", $timestamp);

                                $login_details_columns = " `first_name`, `last_name`, `whatsapp`,`date_registered`,  `status_status_id`";
                                $login_details_values = "'" . $fname . "','" . $lname . "','" . normalizePhoneNumber($whatsapp) . "','" . $registerTime . "','1'";

                                Database::iud("INSERT INTO `login_details` (" . $login_details_columns . ") VALUES (" . $login_details_values . ")");
                                $login_details_id = Database::$connection->insert_id;

                                $student_table_insert_columns = "`full_name`,`name_with_initials`,`date_of_birth`,`date_added`,`gender_gender_id`,`users_user_id`,`login_details_login_details_id`";
                                $student_table_insert_values = "'" . $fullname . "','" . $nwi . "','" . $dob . "','" . $registerTime . "','" . $gender . "','" . $sessionUserObject['user_id'] . "','" . $login_details_id . "'";

                                Database::iud("INSERT INTO `students` (" . $student_table_insert_columns . ") VALUES (" . $student_table_insert_values . ")");

                                $student_id = Database::$connection->insert_id;
                                if ($class_id != 0) {

                                    $query = "SELECT * FROM `classes`
                                        INNER JOIN `year` ON classes.year_year_id = year.year_id
                                        INNER JOIN `exam_type` ON classes.exam_type_exam_type_id = exam_type.exam_type_id 
                                        WHERE `class_id` = '" . $class_id . "'";

                                    $classData = Database::search($query)->fetch_assoc();

                                    $classStudentsCount = Database::search("SELECT * FROM `students_has_classes` WHERE `classes_class_id`='" . $class_id . "'");
                                    $classStudentsCount = $classStudentsCount->num_rows;
                                    $classStudentsCount = $classStudentsCount + 1;

                                    $studentNumber = str_pad($classStudentsCount, 3, "0", STR_PAD_LEFT);

                                    $classSudentId = $classData['exam_type'] . "/" . $classData['year'] . "/" . $studentNumber;

                                    $class_insert_col = "`student_class_id`,`students_student_id`,`classes_class_id`,`status_status_id`,`added_at`,`added_by`";
                                    $class_insert_val = "'" . $classSudentId . "','" . $student_id . "','" . $class_id . "','1','" . $registerTime . "','" . $sessionUserObject['user_id'] . "'";

                                    Database::iud("INSERT INTO `students_has_classes` (" . $class_insert_col . ") VALUES (" . $class_insert_val . ")");
                                }
                            }

                            $status->done = "All students added successfully";
                        }
                    }
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

echo (json_encode($status));

function normalizePhoneNumber($number)
{
    // Remove spaces, dashes, brackets, etc. (keep +)
    $number = trim($number);
    $number = preg_replace('/[^\d+]/', '', $number);

    // Already in international format
    if (preg_match('/^\+\d{8,15}$/', $number)) {
        return $number;
    }

    // Sri Lanka: +947XXXXXXXX
    if (preg_match('/^\+947\d{8}$/', $number)) {
        return $number;
    }

    // Sri Lanka: 947XXXXXXXX
    if (preg_match('/^947\d{8}$/', $number)) {
        return '+' . $number;
    }

    // Sri Lanka: 07XXXXXXXX
    if (preg_match('/^07\d{8}$/', $number)) {
        return '+94' . substr($number, 1);
    }

    // Sri Lanka: 7XXXXXXXX
    if (preg_match('/^7\d{8}$/', $number)) {
        return '+94' . $number;
    }

    // Any other international number (8-15 digits)
    if (preg_match('/^\d{8,15}$/', $number)) {
        return '+' . $number;
    }

    // Invalid phone number
    return false;
}

function validateDateFlexible($dateString, $rowNumber)
{
    $timezone = new DateTimeZone("Asia/Colombo");

    $formats = [
        'Y/m/d',   // 2025/11/02
        'Y/m/j',   // 2025/11/2
        'd/m/Y',   // 02/11/2025
        'j/m/Y',   // 2/11/2025 or 24/10/2025
        'm/d/Y',   // 11/02/2025
    ];

    foreach ($formats as $format) {

        $date = DateTime::createFromFormat($format, $dateString);
        $errors = DateTime::getLastErrors();

        if ($date && empty($errors['warning_count']) && empty($errors['error_count'])) {
            return new DateTime($date->format('Y-m-d'), $timezone);
        }
    }

    return "Data Row $rowNumber: Invalid date of birth format";
}
