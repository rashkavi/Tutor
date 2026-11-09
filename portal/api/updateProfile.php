<?php
$status = new stdClass();
require_once('../../includes/UserClass.php');
require_once('../../includes/connection.php');
require_once('../../includes/Project.php');
if (SessionUser::ifUserIsStudent()) {
    $sessionStudentObject = SessionUser::getCurrentUserObject('Student');

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        $fname = isset($_POST['fname']) ? trim($_POST['fname']) : '';
        $lname = isset($_POST['lname']) ? trim($_POST['lname']) : '';
        $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
        $gender = isset($_POST['gender']) ? trim($_POST['gender']) : 0;
        $dob = isset($_POST['dob']) ? trim($_POST['dob']) : '';
        $nameWithInitial = isset($_POST['nameWithInitial']) ? trim($_POST['nameWithInitial']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
        $whatsapp = isset($_POST['whatsapp']) ? trim($_POST['whatsapp']) : '';
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';
        $address2 = isset($_POST['address2']) ? trim($_POST['address2']) : '';
        $city = isset($_POST['city']) ? trim($_POST['city']) : '';
        $pcode = isset($_POST['pcode']) ? trim($_POST['pcode']) : '';

        $image;

        if (empty($fname)) {
            $status->fname =  "Please enter user first name";
        } else if (strlen($fname) > 45) {
            $status->fname =  "First Name should be less than 45 characters";
        }
        if (empty($lname)) {
            $status->lname =  "Please enter user last name";
        } else if (strlen($lname) > 45) {
            $status->lname =  "Last Name should be less than 45 characters";
        }
        if (empty($fullname)) {
            $status->fullname =  "Please enter user full name";
        } else  if (strlen($fullname) > 150) {
            $status->fullname =  "Full Name should be less than 150 characters";
        }
        if (!empty($nameWithInitial))
            if (strlen($nameWithInitial) > 50) {
                $status->nameWithInitial =  "Name with Initial should be less than 50 characters";
            }


        if ($gender == 0) {
            $status->gender =  "Please select user gender";
        }

        if (!empty($email)) {
            if (strlen($email) > 100) {
                $status->email =  "Email should be less than 100 characters";
            } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $status->test = $email;
                $status->email =  "Please enter a valid email";
            }
        }
        if (!empty($mobile)) {
            if (!normalizePhoneNumber($mobile)) {
                $status->mobile =  "Please enter a valid contact number";
            }
        }

        $invalid_chars = '/[\'\";\-]/';
        if (!empty($address) || !empty($address2) || !empty($city) || !empty($pcode)) {
            if (!empty($address)) {
                if (!strlen($address) > 100) {
                    $status->address1 =  "Address should be less than 100 characters";
                } else if (preg_match($invalid_chars, $address)) {
                    $status->address1 =  "Address lines contain invalid characters.";
                }
            }
            if (!empty($address2)) {
                if (!strlen($address2) > 100) {
                    $status->address2 =  "Address should be less than 100 characters";
                } else if (preg_match($invalid_chars, $address2)) {
                    $status->address2 =  "Address lines contain invalid characters.";
                }
            }
            if (!empty($address) || !$city == 0 || !empty($pcode)) {
                if ($city == 0) {
                    $status->city =  "Please select user city";
                }

                if (empty($pcode)) {
                    $status->pcode =  "Please enter user city postal code";
                }
                if (empty($address)) {
                    $status->address =  "Please enter user address";
                }
            }

            if (!empty($pcode) && !strlen($pcode) > 5) {
                $status->pcode =  "Postcode should be less than 5 characters";
            }
        }



        if (isset($_FILES["image"])) {
            $allowed_image_extentions = array("image/jpg", "image/jpeg", "image/png", "image/svg+xml");
            $file_ex = $_FILES["image"]["type"];
            if (!in_array($file_ex, $allowed_image_extentions)) {
                $status->image = "Only jpg,jpeg,png and svg allowed";
            } else {
                $new_file_extention;
                switch ($file_ex) {
                    case "image/jpg":
                        $new_file_extention = ".jpg";
                        break;
                    case "image/jpeg":
                        $new_file_extention = ".jpeg";
                        break;
                    case "image/png":
                        $new_file_extention = ".png";
                        break;
                    case "image/svg+xml":
                        $new_file_extention = ".svg";
                        break;
                }

                if ($_FILES["image"]["error"] === 0) {
                    if ($_FILES["image"]["size"] <= 2 * 1024 * 1024) {
                        if (getimagesize($_FILES["image"]["tmp_name"])) {
                            $image = uniqid() . $new_file_extention;
                        } else {
                            $status->image = "Invalid image file!";
                        }
                    } else {
                        $status->image = "Maximum allowed file size is 2MB";
                    }
                } else {
                    $status->image = "There is an error with the selected image";
                }
            }
        }

        $requiredProperties = [
            'fname',
            'lname',
            'fullname',
            'nwi',
            'title',
            'gender',
            'marital_status',
            'nationality',
            'nic',
            'cob',
            'cor',
            'dob',
            'address',
            'address2',
            'city',
            'pcode',
            'image',
            'gtitle',
            'grelationship',
            'gfname',
            'glname',
            'gemail',
            'gmobile',
        ];
        $allFieldsPresent = true;
        foreach ($requiredProperties as $property) {
            if (property_exists($status, $property)) {
                $allFieldsPresent = false;
                break; // Exit loop on the first missing property
            }
        }
        if ($allFieldsPresent) {
            $timezone = new DateTimeZone("Asia/Colombo");
            $dateTime = new DateTime("now", $timezone);
            $resetTime = $dateTime->format('Y-m-d H:i:s');

            $login_table_update = "`first_name`='" . $fname . "',`last_name`='" . $lname . "',`email`='" . $email . "'";

            $student_table_update = "`full_name`='" . $fullname . "'
                        ,`date_of_birth`='" . $dob . "'
                        ,`mobile`='" . normalizePhoneNumber($mobile) . "',`gender_gender_id`='" . $gender . "'
                        ";

            if (!empty($nameWithInitial)) {
                $student_table_update .= ",`name_with_initials`='" . $nameWithInitial . "'";
            }

            if (isset($_FILES["image"])) {
                $student_table_update .= ",`profile_img`='" . $image . "'";
            }

            try {
                Database::iud("UPDATE `login_details` SET " . $login_table_update . " WHERE `login_details_id` = '" . $sessionStudentObject["login_details_id"] . "'");
                Database::iud("UPDATE `students` SET " . $student_table_update . " WHERE `student_id` = '" . $sessionStudentObject["student_id"] . "'");

                if (!empty($address)) {
                    $studentHasAddress = Database::search("SELECT * FROM `student_has_address` WHERE `students_student_id` = '" . $sessionStudentObject["student_id"] . "'")->fetch_assoc();

                    if (empty($studentHasAddress)) {
                        $address_insert_columns = "`students_student_id`,`address_line_1`,`address_line_2`,`city_city_id`,`postal_code`";
                        $address_insert_values = "'" . $sessionStudentObject["student_id"] . "','" . $address . "','" . $address2 . "','" . $city . "','" . $pcode . "'";
                        Database::iud("INSERT INTO `student_has_address` (" . $address_insert_columns . ") VALUES (" . $address_insert_values . ")");
                    } else {
                        Database::iud("UPDATE `student_has_address` SET
                            `address_line_1`='" . $address . "',
                            `address_line_2`='" . $address2 . "',
                            `city_city_id`='" . $city . "',
                            `postal_code`='" . $pcode . "'
                            WHERE `students_student_id`='" . $sessionStudentObject["student_id"] . "'");
                    }
                }


                if (isset($_FILES["image"])) {
                    $image_path = Project::getApiAssetsPath() . "/imgs/students/" . $image;
                    move_uploaded_file($_FILES["image"]["tmp_name"], $image_path);

                    if (!empty($student["profile_img"])) {
                        if (file_exists(Project::getApiAssetsPath() . "/imgs/students/" . $student["profile_img"])) {
                            unlink(Project::getApiAssetsPath() . "/imgs/students/" . $student["profile_img"]);
                        }
                    }
                }

                $studentData = Database::search("SELECT * FROM `students`
                    INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id` 
                    WHERE `student_id` = '" . $sessionStudentObject["student_id"] . "'")->fetch_assoc();

                SessionUser::updateSession("Student", [
                    "student_id" => $studentData["student_id"],
                    "login_details_id" => $studentData["login_details_id"],
                    "user_fname" => $studentData["first_name"],
                    "user_lname" => $studentData["last_name"],
                    "user_image" => $studentData["profile_img"],
                    "email" => $studentData["email"],
                    "mobile" => $studentData["mobile"]
                ]);

                $status->done =  "Student Update Successfully";
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
    } else {
        http_response_code(400);
        $status->error = 'Invalid request method';
    }
} else {
    $status->error = 'Authentication Failed1!';
}


echo (json_encode($status));

function normalizePhoneNumber(string $number, string $defaultCountryCode = '94')
{
    $number = preg_replace('/[^\d+]/', '', $number);

    if (preg_match('/^\+[1-9][0-9]{7,14}$/', $number)) {
        return $number;
    }

    if ($defaultCountryCode === '94' && preg_match('/^0[7][0-9]{8}$/', $number)) {
        return '+94' . substr($number, 1);
    }

    if (preg_match('/^[1-9][0-9]{7,14}$/', $number)) {
        return '+' . $number;
    }
    if (preg_match('/^\+[1-9][0-9]{7,14}$/', $number)) {
        return $number;
    }

    return false;
}
