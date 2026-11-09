<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $id = isset($_POST['id']) ? trim($_POST['id']) : '';
        if (empty($id)) {
            $status->error = "Invalid student Id";
        } else {
            $isHasId = Database::search("SELECT `student_id`,`profile_img`,`login_details_login_details_id` FROM `students` WHERE `student_id`='" . $id . "'");

            if ($isHasId->num_rows == 0) {
                $status->error = "Invalid student Id";
            } else {

                $student = $isHasId->fetch_assoc();

                $fname = isset($_POST['fname']) ? trim($_POST['fname']) : '';
                $lname = isset($_POST['lname']) ? trim($_POST['lname']) : '';
                $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
                $nwi = isset($_POST['nwi']) ? trim($_POST['nwi']) : '';
                $gender = isset($_POST['gender']) ? trim($_POST['gender']) : 0;
                $dob = isset($_POST['dob']) ? trim($_POST['dob']) : '';
                $email = isset($_POST['email']) ? trim($_POST['email']) : '';
                $mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
                $whatsapp = isset($_POST['whatsapp']) ? trim($_POST['whatsapp']) : '';
                $address = isset($_POST['address']) ? trim($_POST['address']) : '';
                $address2 = isset($_POST['address2']) ? trim($_POST['address2']) : '';
                $city = isset($_POST['city']) ? trim($_POST['city']) : '';
                $pcode = isset($_POST['pcode']) ? trim($_POST['pcode']) : '';
                $gtitle = isset($_POST['gtitle']) ? trim($_POST['gtitle']) : 0;
                $grelationship = isset($_POST['grelationship']) ? trim($_POST['grelationship']) : 0;
                $gfname = isset($_POST['gfname']) ? trim($_POST['gfname']) : '';
                $glname = isset($_POST['glname']) ? trim($_POST['glname']) : '';
                $gemail = isset($_POST['gemail']) ? trim($_POST['gemail']) : '';
                $gmobile = isset($_POST['gmobile']) ? trim($_POST['gmobile']) : '';
                $gwhatsapp = isset($_POST['gwhatsapp']) ? trim($_POST['gwhatsapp']) : '';
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
                } else if (strlen($fullname) > 150) {
                    $status->fullname =  "Full Name should be less than 150 characters";
                }
                if (!empty($nwi) && (strlen($nwi) > 45)) {
                    $status->nwi =  "Name with initials should be less than 45 characters";
                }

                if ($gender == 0) {
                    $status->gender =  "Please select user gender";
                }

                if (empty($dob)) {
                    $status->dob =  "Please enter user date of birth";
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

                $invalid_chars = '/[\'\";\-]/';
                if (!empty($address) || !empty($address2) || !$city == 0 || !empty($pcode)) {
                    if (empty($address)) {
                        $status->address =  "Please enter user address";
                    }

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

                    if ($city == 0) {
                        $status->city =  "Please select user city";
                    }
                    if (empty($pcode)) {
                        $status->pcode =  "Please enter user city postal code";
                    }
                    if (!empty($pcode) && !strlen($pcode) > 5) {
                        $status->pcode =  "Postcode should be less than 5 characters";
                    }
                }


                if ($gtitle != 0 || $grelationship != 0 || !empty($gfname) || !empty($glname) || !empty($gemail) || !empty($gmobile) || !empty($gwhatsapp)) {
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
                    'email',
                    'mobile',
                    'whatsapp',
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
                    'gwhatsapp',
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
                    $registerTime = $dateTime->format('Y-m-d H:i:s');
                    $date = $dateTime->format('Y-m-d H:i:s');

                    $timestamp = strtotime($dob);
                    $dob = date("Y-m-d", $timestamp);

                    $login_table_update = "`first_name`='" . $fname . "',`last_name`='" . $lname . "',
                    `whatsapp`='" . $whatsapp . "'";

                    if (!empty($email)) {
                        $login_table_update .= ",`email`='" . $email . "'";
                    } else {
                        $login_table_update .= ",`email`=NULL";
                    }
                    $student_table_update = "`full_name`='" . $fullname . "',`name_with_initials`='" . $nwi . "'
                        ,`date_of_birth`='" . $dob . "'
                        ,`gender_gender_id`='" . $gender . "',`mobile`='" . $mobile . "'
                        ";

                    if (isset($_FILES["image"])) {
                        $student_table_update .= ",`profile_img`='" . $image . "'";
                    }

                    Database::iud("UPDATE `login_details` SET " . $login_table_update . " WHERE `login_details_id` = '" . $student["login_details_login_details_id"] . "'");
                    Database::iud("UPDATE `students` SET " . $student_table_update . " WHERE `student_id` = '" . $student["student_id"] . "'");

                    if (!empty($address)) {
                        $studentHasAddress = Database::search("SELECT * FROM `student_has_address` WHERE `students_student_id`='" . $student["student_id"] . "'");
                        if ($studentHasAddress->num_rows == 0) {

                            $address_table_columns = "`students_student_id`,`address_line_1`,`city_city_id`,`postal_code`";
                            $address_table_values = "'" . $student["student_id"] . "','" . $address . "','" . $city . "','" . $pcode . "'";

                            if (!empty($address2)) {
                                $address_table_columns .= ",`address_line_2`";
                                $address_table_values .= ",'" . $address2 . "'";
                            }

                            Database::iud("INSERT INTO `student_has_address` (" . $address_table_columns . ") VALUES (" . $address_table_values . ")");
                        } else {
                            $address_table_update = "`address_line_1`='" . $address . "',`city_city_id`='" . $city . "',`postal_code`='" . $pcode . "'";

                            if (!empty($address2)) {
                                $address_table_update .= ",`address_line_2`='" . $address2 . "'";
                            }

                            Database::iud("UPDATE `student_has_address` SET " . $address_table_update . " WHERE `students_student_id` = '" . $student["student_id"] . "'");
                        }
                    }


                    if ($gtitle != 0) {
                        $guardian = Database::search("SELECT * FROM `parents` WHERE (`whatsapp`='" . $gwhatsapp . "' OR `whatsapp`='" . normalizePhoneNumber($gwhatsapp) . "')");
                        if ($guardian->num_rows > 0) {
                            $guardianData = $guardian->fetch_assoc();
                            Database::iud("INSERT INTO `parents_has_students` (`students_student_id`,`parents_parent_id`,`added_at`) VALUES ('" . $id . "','" . $guardianData['parent_id'] . "','" . $date . "')");
                        } else {
                            $guardian_table_insert_columns = "`guardian_relation_guardian_relation_id`,`parent_fname`,`parent_lname`,`email`,`mobile`,`title_title_id`";
                            $guardian_table_insert_values = "'" . $grelationship . "','" . $gfname . "','" . $glname . "','" . normalizePhoneNumber($gemail) . "','" . $gmobile . "','" . $gtitle . "'";
                            Database::iud("INSERT INTO `parents` (" . $guardian_table_insert_columns . ") VALUES (" . $guardian_table_insert_values . ")");

                            $perantId = Database::$connection->insert_id;

                            Database::iud("INSERT INTO `parents_has_students` (`students_student_id`,`parents_parent_id`,`added_at`) VALUES ('" . $id . "','" . $perantId . "','" . $date . "')");
                        }
                    }
                    if (isset($_FILES["image"])) {
                        $image_path = "../../assets/imgs/students/" . $image;
                        move_uploaded_file($_FILES["image"]["tmp_name"], $image_path);

                        if (!empty($student["profile_img"])) {
                            if (file_exists("../../assets/imgs/students/" . $student["profile_img"])) {
                                unlink("../../assets/imgs/students/" . $student["profile_img"]);
                            }
                        }
                    }

                    $status->done =  "Student Update Successfully";
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
