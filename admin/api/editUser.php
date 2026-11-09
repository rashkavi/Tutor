<?php
$status = new stdClass();
require_once("../../includes/UserClass.php");
require_once("../../includes/connection.php");
require_once("../../includes/UserPermissionsClass.php");
if (SessionUser::ifUserIsAdmin()) {
    $sessionUserObject = SessionUser::getCurrentUserObject('Admin');
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        $id = isset($_POST['id']) ? $_POST['id'] : '';
        if (empty($id)) {
            $status->error = "Invalid user Id";
        } else if (!is_numeric($id)) {
            $status->error = "Invalid user Id";
        } else {
            $isValidUser = Database::search("SELECT COUNT(*) as 'count' FROM `users` WHERE `user_id`='" . $id . "'");
            $row = $isValidUser->fetch_assoc();
            if ($row['count'] == 0) {
                $status->error = "Invalid user Id";
            } else {

                $title = isset($_POST['title']) ? trim($_POST['title']) : '';
                $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
                $role = isset($_POST['role']) ? trim($_POST['role']) : '';
                $fname = isset($_POST['fname']) ? trim($_POST['fname']) : '';
                $lname = isset($_POST['lname']) ? trim($_POST['lname']) : '';
                $email = isset($_POST['email']) ? trim($_POST['email']) : '';
                $contact = isset($_POST['contact']) ? trim($_POST['contact']) : '';
                $whatsapp = isset($_POST['whatsapp']) ? trim($_POST['whatsapp']) : '';
                $nic = isset($_POST['nic']) ? trim($_POST['nic']) : '';
                $dob = isset($_POST['dob']) ? trim($_POST['dob']) : '';
                $address = isset($_POST['address']) ? trim($_POST['address']) : '';
                $address2 = isset($_POST['address2']) ? trim($_POST['address2']) : '';
                $city = isset($_POST['city']) ? trim($_POST['city']) : '';
                $pcode = isset($_POST['pcode']) ? trim($_POST['pcode']) : '';
                $account_name = isset($_POST['account_name']) ? trim($_POST['account_name']) : '';
                $account_number = isset($_POST['account_number']) ? trim($_POST['account_number']) : '';
                $bank = isset($_POST['bank']) ? trim($_POST['bank']) : '';
                $branch = isset($_POST['branch']) ? trim($_POST['branch']) : '';
                $about = isset($_POST['about']) ? $_POST['about'] : '';
                $bio = isset($_POST['bio']) ? $_POST['bio'] : '';
                $achivements = isset($_POST['achivements']) ? $_POST['achivements'] : '';
                $experience = isset($_POST['experience']) ? $_POST['experience'] : '';
                $image;
                $cv_file_name;

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
                if ($title == 0) {
                    $status->title =  "Please select user title";
                }
                if (empty($email)) {
                    $status->email =  "Please enter user email";
                } else if (strlen($email) > 100) {
                    $status->email =  "Email should be less than 45 characters";
                } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $status->test = $email;
                    $status->email =  "Please enter a valid email";
                } else {
                    $isValidUser = Database::search("SELECT COUNT(*) as 'count' FROM `users` WHERE `user_email`='" . $email . "' AND `user_id` != '" . $id . "'");
                    $row = $isValidUser->fetch_assoc();
                    if ($row['count'] > 0) {
                        $status->email =  "User email already exists";
                    }
                }
                if (empty($contact)) {
                    $status->contact =  "Please enter user Contact Number";
                } else if (!normalizePhoneNumber($contact)) {
                    $status->contact =  "Please enter a valid contact number";
                } else {
                    $user = Database::search("SELECT * FROM `users` WHERE (`user_mobile`='" . $contact . "' OR `user_mobile`='" . normalizePhoneNumber($contact) . "') AND `user_id` != '" . $id . "'");
                    if ($user->num_rows > 0) {
                        $status->contact =  "User already exists";
                    }
                }

                if (empty($whatsapp)) {
                    $status->whatsapp =  "Please enter user WhatsApp Number";
                } else if (!normalizePhoneNumber($whatsapp)) {
                    $status->whatsapp =  "Please enter a valid WhatsApp number";
                } else {
                    $user = Database::search("SELECT * FROM `users` WHERE (`user_whatsapp`='" . $whatsapp . "' OR `user_whatsapp`='" . normalizePhoneNumber($whatsapp) . "') AND `user_id` != '" . $id . "'");
                    if ($user->num_rows > 0) {
                        $status->whatsapp =  "WhatsApp number already exists";
                    }
                }
                if ($role == 0) {
                    $status->role =  "Please select user role";
                } else {
                    if ($role == 1 && $sessionUserRole != "Developer") {
                        $status->role =  "You can't add this user role";
                    }
                }
                if (!empty($nic)) {
                    if (!preg_match('/^(?:[0-9]{9}[vV]|[0-9]{12})$/', $nic)) {
                        $status->nic =  "Please enter a valid NIC number";
                    }
                }
                // if (!empty($dob)) {
                //     if (isValidBDDate($dob) == false) {
                //         $status->dob =  "Please enter a valid Date of Birth";
                //     }
                // }
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

                if (!empty($account_name) || !empty($account_number) || !empty($bank) || !empty($branch)) {
                    if (empty($account_name)) {
                        $status->account_name =  "Please enter account name";
                    }
                    if (empty($account_number)) {
                        $status->account_number =  "Please enter account number";
                    } elseif (!preg_match('/^[0-9]{10}$/', $account_number)) {
                        $status->account_number =  "Please enter a valid account number";
                    } else {
                        $isValidAccNumber = Database::search("SELECT COUNT(*) as 'count' FROM `bank_account` WHERE `account_number` = '" . $account_number . "' AND `users_user_id` != '" . $id . "'");
                        $row = $isValidAccNumber->fetch_assoc();
                        if ($row['count'] > 0) {
                            $status->account_number =  "Account number already exists";
                        }
                    }

                    if (empty($bank)) {
                        $status->bank =  "Please enter bank name";
                    }
                    if (empty($branch)) {
                        $status->branch =  "Please enter branch name";
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
                if (isset($_FILES["cv"])) {
                    $allowed_cv_extensions = array("application/pdf", "application/msword", "application/vnd.openxmlformats-officedocument.wordprocessingml.document");
                    $cv_ex = $_FILES["cv"]["type"];
                    if (!in_array($cv_ex, $allowed_cv_extensions)) {
                        $status->cv = "Only PDF, DOC, and DOCX files are allowed";
                    } else {
                        $new_cv_extension = '';
                        switch ($cv_ex) {
                            case "application/pdf":
                                $new_cv_extension = ".pdf";
                                break;
                            case "application/msword":
                                $new_cv_extension = ".doc";
                                break;
                            case "application/vnd.openxmlformats-officedocument.wordprocessingml.document":
                                $new_cv_extension = ".docx";
                                break;
                        }

                        if ($_FILES["cv"]["error"] === 0) {
                            if ($_FILES["cv"]["size"] <= 2 * 1024 * 1024) {
                                $cv_file_name = uniqid() . $new_cv_extension;
                            } else {
                                $status->cv = "Maximum allowed file size is 2MB";
                            }
                        } else {
                            $status->cv = "There is an error with the selected CV file";
                        }
                    }
                }

                $requiredProperties = [
                    'fname',
                    'lname',
                    'email',
                    'contact',
                    'title',
                    'role',
                    'image',
                    'address',
                    'address2',
                    'city',
                    'pcode',
                    'cv',
                    'nic',
                    'dob',
                    'gender',
                    'whatsapp'
                ];
                $allPropertiesDoesNotExist = true;
                foreach ($requiredProperties as $property) {
                    if (property_exists($status, $property)) {
                        $allPropertiesDoesNotExist = false;
                        break; // Exit loop on the first missing property
                    }
                }
                if ($allPropertiesDoesNotExist) {

                    $user = Database::search("SELECT * FROM `users` WHERE `user_id` = '" . $id . "'");
                    $user_data = $user->fetch_assoc();

                    $users_table_update_query = "`title_title_id`='" . $title . "',`user_fname`='" . $fname . "',`user_lname`='" . $lname . "',`user_email`='" . $email . "',`user_mobile`='" . normalizePhoneNumber($contact) . "',`user_whatsapp`='" . normalizePhoneNumber($whatsapp) . "',`gender_gender_id`='" . $gender . "',`user_roles_user_role_id`='" . $role . "'";

                    if (!empty($image)) {
                        $users_table_update_query .= ",`profile_img`='" . $image . "'";
                    }
                    if (!empty($nic)) {
                        $users_table_update_query .= ",`nic`='" . $nic . "'";
                    }
                    if (!empty($dob)) {
                        $timestamp = strtotime($dob);
                        $dob = date("Y-m-d", $timestamp);
                        $users_table_update_query .= ",`dob`='" . $dob . "'";
                    }

                    Database::iud("UPDATE `users` SET " . $users_table_update_query . " WHERE `user_id` = '" . $id . "'");

                    if (!empty($address)) {
                        $uAddress = Database::search("SELECT * FROM `user_has_address` WHERE `users_user_id` = '" . $id . "'");
                        $uAddress_data = $uAddress->fetch_assoc();
                        if ($uAddress_data != null) {
                            $address_table_update_query = "`address_line_1`='" . $address . "',`city_city_id`='" . $city . "',`postal_code`='" . $pcode . "'";
                            if (!empty($address2)) {
                                $address_table_update_query .= ",`address_line_2`='" . $address2 . "'";
                            }
                            Database::iud("UPDATE `user_has_address` SET " . $address_table_update_query . " WHERE `users_user_id` = '" . $id . "' AND `user_has_address_id` = '" . $uAddress_data["user_has_address_id"] . "'");
                        } else {
                            $address_table_insert_columns = "`users_user_id`, `address_line_1`, `city_city_id`, `postal_code`";
                            $address_table_insert_values = "'" . $id . "','" . $address . "','" . $city . "','" . $pcode . "'";

                            if (!empty($address2)) {
                                $address_table_insert_columns .= ", address_line_2";
                                $address_table_insert_values .= ",'" . $address2 . "'";
                            }

                            Database::iud("INSERT INTO `user_has_address` (" . $address_table_insert_columns . ") VALUES (" . $address_table_insert_values . ")");
                        }
                    }

                    if (!empty($cv_file_name) || !empty($about) || !empty($bio) || !empty($achievements) || !empty($experience)) {
                        $uOptional = Database::search("SELECT * FROM `optional_details` WHERE `users_user_id` = '" . $id . "'");
                        $uOptional_data = $uOptional->fetch_assoc();
                        if ($uOptional_data != null) {
                            $optional_details_table_update_query = "`about`='" . $about . "',`bio`='" . $bio . "',`achivements`='" . $achivements . "',`experience`='" . $experience . "'";
                            if (!empty($cv_file_name)) {
                                $optional_details_table_update_query .= ",`cv`='" . $cv_file_name . "'";
                            }
                            Database::iud("UPDATE `optional_details` SET " . $optional_details_table_update_query . " WHERE `users_user_id` = '" . $id . "' AND `optional_details_id` = '" . $uOptional_data["optional_details_id"] . "'");
                        } else {
                            $optional_details_table_insert_columns = "`users_user_id`, `about`, `bio`, `experience`, `achivements`";
                            $optional_details_table_insert_values = "'" . $id . "','" . $about . "','" . $bio . "','" . $experience . "','" . $achivements . "'";

                            if (!empty($cv_file_name)) {
                                $optional_details_table_insert_columns .= ", `cv`";
                                $optional_details_table_insert_values .= ",'" . $cv_file_name . "'";
                            }

                            Database::iud("INSERT INTO `optional_details` (" . $optional_details_table_insert_columns . ") VALUES (" . $optional_details_table_insert_values . ")");
                        }
                    }

                    if (!empty($bank)) {
                        $uBankAccount = Database::search("SELECT * FROM `bank_account` WHERE `users_user_id` = '" . $id . "'");
                        if ($uBankAccount->num_rows == 0) {
                            $bank_table_insert_columns = "`users_user_id`, `account_name`,`account_number`,`branch`,`bank`";
                            $bank_table_insert_values = "'" . $id . "','" . $account_name . "','" . $account_number . "','" . $branch . "','" . $bank . "'";

                            Database::iud("INSERT INTO `bank_account` (" . $bank_table_insert_columns . ") VALUES (" . $bank_table_insert_values . ")");
                        } else {
                            $uBankAccount_data = $uBankAccount->fetch_assoc();

                            $update_query = "`account_name`='" . $account_name . "',`account_number`='" . $account_number . "',`branch`='" . $branch . "',`bank`='" . $bank . "'";
                            Database::iud("UPDATE `bank_account` SET " . $update_query . " WHERE `users_user_id` = '" . $id . "'");
                        }
                    }

                    if (isset($_FILES["image"])) {
                        $image_path = Project::getApiAssetsPath() . "/imgs/users/";
                        $qimg_path =  $image_path . $image;
                        move_uploaded_file($_FILES["image"]["tmp_name"], $qimg_path);

                        if (!empty($user_data["profile_img"])) {
                            if (file_exists($image_path . $user_data["profile_img"])) {
                                unlink($image_path . $user_data["profile_img"]);
                            }
                        }

                        if ($id == $sessionUserObject['user_id']) {
                            $sessionUserObject['user_image'] = $image;
                        }
                    }
                    if (isset($_FILES["cv"])) {
                        $cv_path = Project::getApiAssetsPath() . "/uploads/cv/";
                        $qcv_path = $cv_path . $cv_file_name;
                        move_uploaded_file($_FILES["cv"]["tmp_name"], $qcv_path);

                        if (!empty($uOptional_data["cv"]) && file_exists($cv_path . $uOptional_data["cv"])) {
                            if (file_exists($cv_path . $uOptional_data["cv"])) {
                                unlink($cv_path . $uOptional_data["cv"]);
                            }
                        }
                    }

                    if ($sessionUserObject["user_id"] == $id) {
                        $userData = [
                            "user_id" => $sessionUserObject["user_id"],
                            "user_fname" => $fname,
                            "user_lname" => $lname,
                            "user_email" => $email,
                            "user_image" => $sessionUserObject["user_image"],
                            "user_roles_user_role_id" => $role,
                            "user_permissions" => $sessionUserObject["user_permissions"]
                        ];

                        $role = Database::search("SELECT * FROM `user_roles` WHERE `user_role_id` = '" . $role . "'");
                        $role = $role->fetch_assoc();
                        SessionUser::destroySession('Admin');
                        SessionUser::startSession($role["user_role"], $userData);
                    }
                    $status->done =  "User Updated Successfully";
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
