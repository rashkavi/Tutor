<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
require_once("../../includes/Project.php");
require_once("../../tutor/includes/security-helpers.php");

if (SessionUser::hasValidSession()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $class_id = isset($_POST['class_id']) ? trim($_POST['class_id']) : '';
        $fname = isset($_POST['fname']) ? sanitizeInput(trim($_POST['fname']), 45) : '';
        $lname = isset($_POST['lname']) ? sanitizeInput(trim($_POST['lname']), 45) : '';
        $fullname = isset($_POST['fullname']) ? sanitizeInput(trim($_POST['fullname']), 150) : '';
        $gender = isset($_POST['gender']) ? trim($_POST['gender']) : 0;
        $dob = isset($_POST['dob']) ? trim($_POST['dob']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
        $whatsapp = isset($_POST['whatsapp']) ? trim($_POST['whatsapp']) : '';
        $address = isset($_POST['address']) ? sanitizeInput(trim($_POST['address']), 100) : '';
        $address2 = isset($_POST['address2']) ? sanitizeInput(trim($_POST['address2']), 100) : '';
        $city = isset($_POST['city']) ? trim($_POST['city']) : '';
        $pcode = isset($_POST['pcode']) ? trim($_POST['pcode']) : '';
        $gtitle = isset($_POST['gtitle']) ? trim($_POST['gtitle']) : 0;
        $gwhatsapp = isset($_POST['gwhatsapp']) ? trim($_POST['gwhatsapp']) : '';
        $grelationship = isset($_POST['grelationship']) ? trim($_POST['grelationship']) : 0;
        $gfname = isset($_POST['gfname']) ? sanitizeInput(trim($_POST['gfname']), 45) : '';
        $glname = isset($_POST['glname']) ? sanitizeInput(trim($_POST['glname']), 45) : '';
        $gemail = isset($_POST['gemail']) ? trim($_POST['gemail']) : '';
        $gmobile = isset($_POST['gmobile']) ? trim($_POST['gmobile']) : '';
        $image = null;


        if (empty($fname)) {
            $status->fname =  "Please enter student first name";
        } else if (strlen($fname) > 45) {
            $status->fname =  "First Name should be less than 45 characters";
        }
        if (empty($lname)) {
            $status->lname =  "Please enter student last name";
        } else if (strlen($lname) > 45) {
            $status->lname =  "Last Name should be less than 45 characters";
        }
        if (empty($fullname)) {
            $status->fullname =  "Please enter student full name";
        } else if (strlen($fullname) > 150) {
            $status->fullname =  "Full Name should be less than 150 characters";
        }

        if ($gender == 0) {
            $status->gender =  "Please select student gender";
        }

        if (empty($dob)) {
            $status->dob =  "Please enter student date of birth";
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
        if (empty($whatsapp)) {
            $status->whatsapp =  "Please enter student whatsapp number";
        } else if (!normalizePhoneNumber($whatsapp)) {
            $status->whatsapp =  "Please enter a valid whatsapp number";
        }

        if (empty($class_id)) {
            $status->class_id =  "Please select student class";
        } else {
            $class = Database::search("SELECT * FROM `classes` WHERE `class_id`='" . $class_id . "' AND `tutor_id`='" . $sessionUserObject['user_id'] . "'");
            if ($class->num_rows == 0) {
                $status->class_id =  "Class does not exist";
            }
        }

        $invalid_chars = '/[\'\";\-]/';

        if (!empty($address)) {
            if (strlen($address) > 100) {
                $status->address1 =  "Address should be less than 100 characters";
            } else if (preg_match($invalid_chars, $address)) {
                $status->address1 =  "Address lines contain invalid characters.";
            }
        }
        if (!empty($address2)) {
            if (strlen($address2) > 100) {
                $status->address2 =  "Address should be less than 100 characters";
            } else if (preg_match($invalid_chars, $address2)) {
                $status->address2 =  "Address lines contain invalid characters.";
            }
        }

        if (!empty($address) || !$city == 0 || !empty($pcode)) {
            if ($city == 0) {
                $status->city =  "Please select student city";
            }

            if (empty($pcode)) {
                $status->pcode =  "Please enter student city postal code";
            }
            if (empty($address)) {
                $status->address =  "Please enter student address";
            }
        }

        if (!empty($gwhatsapp) || $gtitle != 0 || $grelationship != 0 || !empty($gfname) || !empty($glname) || !empty($gemail) || !empty($gmobile)) {
            if ($gtitle == 0) {
                $status->gtitle =  "Please select student guardian title";
            }
            if ($grelationship == 0) {
                $status->grelationship =  "Please select student guardian relationship";
            }
            if (empty($gfname)) {
                $status->gfname =  "Please enter student guardian first name";
            } else if (strlen($gfname) > 45) {
                $status->gfname =  "First Name should be less than 45 characters";
            }
            if (empty($glname)) {
                $status->glname =  "Please enter student guardian last name";
            } else if (strlen($glname) > 45) {
                $status->glname =  "Last Name should be less than 45 characters";
            }
            if (!empty($gemail)) {
                if (strlen($gemail) > 100) {
                    $status->gemail =  "Email should be less than 100 characters";
                } else if (!filter_var($gemail, FILTER_VALIDATE_EMAIL)) {
                    $status->gemail =  "Please enter a valid email";
                }
            }
            if (empty($gmobile)) {
                $status->gmobile =  "Please enter student guardian mobile";
            } else if (!normalizePhoneNumber($gmobile)) {
                $status->gcontact =  "Please enter a valid contact number";
            }

            if (empty($gwhatsapp)) {
                $status->gwhatsapp =  "Please enter student guardian whatsapp number";
            } else if (!normalizePhoneNumber($gwhatsapp)) {
                $status->gwhatsapp =  "Please enter a valid whatsapp number";
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
            'id',
            'fname',
            'lname',
            'fullname',
            'gender',
            'dob',
            'email',
            'mobile',
            'whatsapp',
            'address1',
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
            'status',
        ];
        $allPropertiesExist = true;
        foreach ($requiredProperties as $property) {
            if (property_exists($status, $property)) {
                $allPropertiesExist = false;
                break;
            }
        }
        if ($allPropertiesExist) {
            $timezone = new DateTimeZone("Asia/Colombo");
            $dateTime = new DateTime("now", $timezone);
            $registerTime = $dateTime->format('Y-m-d H:i:s');
            $date = $dateTime->format('Y-m-d H:i:s');

            $timestamp = strtotime($dob);
            $dob = date("Y-m-d", $timestamp);

            $bytes = random_bytes(75);
            $token = substr(str_replace(['+', '/', '='], '', base64_encode($bytes)), 0, 100);

            $password = rand(1000, 9999);
            $hashPassword = password_hash($password, PASSWORD_BCRYPT);

            $login_details_columns = " `first_name`, `last_name`, `whatsapp`, `password`, `date_registered`,  `status_status_id`,`verification`";
            $login_details_values = "'" . $fname . "','" . $lname . "','" . normalizePhoneNumber($whatsapp) . "','" . $hashPassword . "','" . $registerTime . "','1','" . $token . "'";

            if (!empty($email)) {
                $login_details_columns .= ", `email`";
                $login_details_values .= ",'" . $email . "'";
            }

            Database::iud("INSERT INTO `login_details` (" . $login_details_columns . ") VALUES (" . $login_details_values . ")");
            $login_details_id = Database::$connection->insert_id;

            $student_table_insert_columns = "`full_name`,`date_of_birth`,`date_added`,`gender_gender_id`,`users_user_id`,`login_details_login_details_id`";
            $student_table_insert_values = "'" . $fullname . "','" . $dob . "','" . $registerTime . "','" . $gender . "','" . $sessionUserObject['user_id'] . "','" . $login_details_id . "'";

            if (!empty($nwi)) {
                $student_table_insert_columns .= ", `name_with_initials`";
                $student_table_insert_values .= ",'" . $nwi . "'";
            }
            if (!empty($mobile)) {
                $student_table_insert_columns .= ", `mobile`";
                $student_table_insert_values .= ",'" . normalizePhoneNumber($mobile) . "'";
            }

            if (isset($_FILES["image"])) {
                $student_table_insert_columns .= ", `profile_img`";
                $student_table_insert_values .= ",'" . $image . "'";
            }

            Database::iud("INSERT INTO `students` (" . $student_table_insert_columns . ") VALUES (" . $student_table_insert_values . ")");

            $studentId = Database::$connection->insert_id;

            if (!empty($address)) {

                $address_table_insert_columns = "`address_line_1`,`city_city_id`,`postal_code`,`students_student_id`";
                $address_table_insert_values = "'" . $address . "','" . $city . "','" . $pcode . "','" . $studentId . "'";

                if (!empty($address2)) {
                    $address_table_insert_columns .= ", `address_line_2`";
                    $address_table_insert_values .= ",'" . $address2 . "'";
                }

                Database::iud("INSERT INTO `student_has_address` (" . $address_table_insert_columns . ") VALUES (" . $address_table_insert_values . ")");
            }

            $students_class_table_insert_columns = "`classes_class_id`,`students_student_id`,`status_status_id`,`added_at`,`added_by`";
            $students_class_table_insert_values = "'" . $class_id . "','" . $studentId . "','1','" . $date . "','" . $sessionUserObject['user_id'] . "'";
            Database::iud("INSERT INTO `students_has_classes` (" . $students_class_table_insert_columns . ") VALUES (" . $students_class_table_insert_values . ")");

            if ($gtitle != 0) {
                $guardian = Database::search("SELECT * FROM `parents` WHERE (`whatsapp`='" . $gwhatsapp . "' OR `whatsapp`='" . normalizePhoneNumber($gwhatsapp) . "')");
                if ($guardian->num_rows > 0) {
                    $guardianData = $guardian->fetch_assoc();
                    Database::iud("INSERT INTO `parents_has_students` (`students_student_id`,`parents_parent_id`,`added_at`) VALUES ('" . $studentId . "','" . $guardianData['parent_id'] . "','" . $date . "')");
                } else {
                    $guardian_table_insert_columns = "`guardian_relation_guardian_relation_id`,`parent_fname`,`parent_lname`,`email`,`mobile`,`title_title_id`";
                    $guardian_table_insert_values = "'" . $grelationship . "','" . $gfname . "','" . $glname . "','" . normalizePhoneNumber($gemail) . "','" . $gmobile . "','" . $gtitle . "'";
                    Database::iud("INSERT INTO `parents` (" . $guardian_table_insert_columns . ") VALUES (" . $guardian_table_insert_values . ")");

                    $perantId = Database::$connection->insert_id;

                    Database::iud("INSERT INTO `parents_has_students` (`students_student_id`,`parents_parent_id`,`added_at`) VALUES ('" . $studentId . "','" . $perantId . "','" . $date . "')");
                }
            }

            if (isset($_FILES["image"])) {
                $qimg_path = Project::getApiAssetsPath() . "/imgs/students/" . $image;
                move_uploaded_file($_FILES["image"]["tmp_name"], $qimg_path);
            }

            $status->done =  "Student Added Successfully";
        }
    } else {
        http_response_code(400);
        $status->error = 'Invalid request method';
    }
} else {
    $status->error = 'Authentication Failed!';
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

function generateSecureUniqueId()
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    for ($i = 0; $i < 8; $i++) {
        $randomString .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $randomString;
}
