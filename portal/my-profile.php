<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Student');

    $studentData = Database::search("SELECT * FROM `students`
    INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id` 
    WHERE `student_id`='" . $sessionUserObject['student_id'] . "'")->fetch_assoc();

    $isHasAddress = false;
    $student_rs = Database::search("SELECT * FROM `student_has_address`
    INNER JOIN `city` ON `city`.`city_id`=`student_has_address`.`city_city_id`
    INNER JOIN `district` ON `district`.`district_id`=`city`.`district_district_id`
    INNER JOIN `province` ON `province`.`province_id`=`district`.`province_province_id` 
    WHERE `students_student_id`='" . $sessionUserObject['student_id'] . "'");
    if ($student_rs->num_rows > 0) {
        $isHasAddress = true;
        $studentAddressData = $student_rs->fetch_assoc();
    }

    $isHasGuardian = false;
    $student_rs = Database::search("SELECT * FROM `parents_has_students` WHERE `students_student_id`='" . $sessionUserObject['student_id'] . "'");
    if ($student_rs->num_rows > 0) {
        $isHasGuardian = true;
        $studentGuardianData = $student_rs->fetch_assoc();
    }

?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>My Profile - Student Portal | <?= Project::getBaseName() ?></title>
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css">
        <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">
        <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
    </head>

    <body class="overflow-hidden">
        <div class="notifications"></div>
        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
            <div id="appContainer" class="row p-0">
                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                    <?php
                    include "includes/side-menu.php";
                    load_side_menu::load("My Profile");
                    ?>
                </div>
                <div id="app-wrapper" class="app-wrapper p-0 align-content-center m-0">
                    <div class="row p-0 m-0 g-0 h-100">
                        <div class="col-12 p-0 m-0 g-0">
                            <?php
                            include "includes/header.php";
                            selectItem::setSelected("My Profile");
                            ?>
                        </div>
                        <div class="col-12 p-0 m-0 g-0 bg-content-body contentBodyArea vh-100 d-flex flex-column">
                            <div class="row p-4 m-0 g-0">
                                <div class="col-12 py-4 px-4 component-theme mb-4">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 col-xl-10">
                                            <div class="row">
                                                <span class="main-topic lh-lg">My Profile</span>
                                                <span class="text-14 sub-text-color lh-base mt-1">Manage all your personal details, academic information, and account settings from one place.</span>
                                            </div>
                                        </div>
                                        <div class="col-12 col-xl-2 mt-3 mt-xl-0">
                                            <div class="row p-0 m-0 align-items-center h-100">
                                                <button class="custom-btn w-100" onclick="updateProfile(this);">Save Changes</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 p-4 component-theme ">
                                    <div class="row p-0 m-0 g-0 align-content-center">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <div class="row p-0 m-0 justify-content-center">
                                                <div class="col-10 col-lg-3 col-xxl-2 d-flex flex-column">
                                                    <div class="bl-solid p-3 m-0">
                                                        <div class="row m-0 p-0 position-relative justify-content-center align-content-center">
                                                            <?php
                                                            if ($studentData['profile_img'] != null && file_exists(Project::getAssetsPath() . '/imgs/students/' . $studentData['profile_img'])) {
                                                            ?>
                                                                <img src="<?= Project::getAssetsPath() ?>/imgs/students/<?= $studentData["profile_img"] ?>" alt="student profile image" id="student-profile-image" class="w-100 h-auto me-2 mx-2 mt-0 mb-0 p-0">
                                                            <?php

                                                            } else {
                                                                echo '<img src="' . Project::getAssetsPath() . '/imgs/common/empty-img.png" alt="student profile image" id="student-profile-image" class="w-100 h-auto me-2 mx-2 mt-0 mb-0 p-0">';
                                                            }
                                                            ?>

                                                            <div class="upload-image text-white p-2 bottom-0 start-0" onclick="uploadImage('file-input','student-profile-image','crop-file-input');">
                                                                <iconify-icon icon="ic:baseline-upload" width="20" height="20" class="text-white px-2"></iconify-icon>
                                                                <span>Upload Image</span>
                                                            </div>
                                                        </div>
                                                        <!-- Hiddedn file input -->
                                                        <input type="file" id="file-input" class="d-none" accept="image/*" />
                                                        <p class="text-center text-14 sub-text-color mt-2 mb-0">Image size should be under 1MB and image ration needs to be 1:1</p>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-lg-9 col-xxl-10 px-0 px-md-4 pe-0 pe-md-0 pe-lg-0 d-block">
                                                    <div class="row m-0 p-0">
                                                        <div class="col-12 mt-3 m-md-0 m-lg-0 p-0">
                                                            <div class="row gx-4 gy-4">
                                                                <span class="sub-topic">Personal Details</span>

                                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">First Name</label>
                                                                    <input type="text" class="form-control custom-input mt-2" id="first-name" maxlength="45" value="<?php echo $studentData['first_name']; ?>">
                                                                    <span id="fname-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Last Name</label>
                                                                    <input type="text" class="form-control custom-input mt-2" id="last-name" maxlength="45" value="<?php echo $studentData['last_name']; ?>">
                                                                    <span id="lname-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-7 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Full Name</label>
                                                                    <input type="text" class="form-control custom-input mt-2" id="full-name" maxlength="150" value="<?php echo $studentData['full_name'] ?>">
                                                                    <span id="fullname-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-5 d-flex flex-column">
                                                                    <label class="text-14 main-text not-required" for="">Name with Initial</label>
                                                                    <input type="text" class="form-control custom-input mt-2" id="name-with-initial" maxlength="50" value="<?php echo $studentData['name_with_initials'] ?>">
                                                                    <span id="name-with-initial-feedback" class="text-dark-red text-12"></span>
                                                                </div>

                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Select Gender</label>
                                                                    <select class="form-select custom-select mt-2" disabled aria-label="Default select example" id="gender-selector">
                                                                        <?php
                                                                        $genders = Database::search("SELECT * FROM `gender`");
                                                                        while ($data = $genders->fetch_assoc()) {
                                                                            if ($data['gender_id'] == $studentData['gender_gender_id']) {
                                                                        ?>
                                                                                <option selected value="<?php echo $data["gender_id"]; ?>"><?php echo $data["gender"]; ?></option>
                                                                            <?php
                                                                            } else {
                                                                            ?>
                                                                                <option value="<?php echo $data["gender_id"]; ?>"><?php echo $data["gender"]; ?></option>
                                                                        <?php
                                                                            }
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="gender-feedback" class="text-dark-red text-12"></span>
                                                                </div>

                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Date of Birth</label>
                                                                    <input type="date" class="form-control custom-input mt-2" id="dob" value="<?php echo $studentData['date_of_birth']; ?>">
                                                                    <span id="dob-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 p-0 g-0 mt-5">
                                            <div class="row gx-4 gy-3">
                                                <span class="sub-topic">Contact Details</span>
                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                    <label class="text-14 main-text not-required" for="">Email</label>
                                                    <input type="text" class="form-control custom-input mt-2" maxlength="100" id="email" value="<?php echo $studentData['email']; ?>">
                                                    <span id="email-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                                <div class="col-12 col-md-3 d-flex flex-column">
                                                    <label class="text-14 main-text not-required" for="">Mobile</label>
                                                    <input type="text" class="form-control custom-input mt-2" maxlength="15" id="mobile" value="<?php echo $studentData['mobile']; ?>">
                                                    <span id="mobile-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                                <div class="col-12 col-md-3 d-flex flex-column">
                                                    <label class="text-14 main-text required" for="">WhatsApp</label>
                                                    <input type="text" class="form-control custom-input mt-2" disabled maxlength="15" value="<?php echo $studentData['whatsapp']; ?>">
                                                    <span id="whatsapp-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                    <label class="text-14 main-text" for="">Address Line 1</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="address" maxlength="100" value="<?= $isHasAddress ? $studentAddressData['address_line_1'] : ''; ?>">
                                                    <span id="address-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                    <label class="text-14 main-text" for="">Address Line 2</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="address2" maxlength="100" value="<?= $isHasAddress ? $studentAddressData['address_line_2'] : ''; ?>">
                                                    <span id="address2-feedback" class="text-dark-red text-12"></span>
                                                </div>

                                                <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                    <label class="text-14 main-text" for="">Province</label>
                                                    <select name="" id="province" class="form-select custom-select mt-2" onchange="loadDistrict();">
                                                        <option value="0">- Select -</option>
                                                        <?php
                                                        $province = Database::search("SELECT * FROM `province`");
                                                        while ($data = $province->fetch_assoc()) {
                                                            if ($data['province_id'] == $isHasAddress ? $studentAddressData['province_id'] : '') {
                                                        ?>
                                                                <option selected value="<?php echo $data["province_id"]; ?>"><?php echo $data["province"]; ?></option>
                                                            <?php
                                                            } else {
                                                            ?>
                                                                <option value="<?php echo $data["province_id"]; ?>"><?php echo $data["province"]; ?></option>
                                                        <?php
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                    <span id="province-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                    <label class="text-14 main-text" for="">District</label>
                                                    <select name="" id="district" disabled class="form-select custom-select mt-2" onchange="loadCities();">
                                                        <option value="0">- Select -</option>
                                                        <?php
                                                        if ($isHasAddress) {
                                                        ?>
                                                            <option selected value="<?= $studentAddressData["district_id"]; ?>"><?= $studentAddressData["district"]; ?></option>
                                                        <?php
                                                        }
                                                        ?>
                                                    </select>
                                                    <span id="district-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                    <label class="text-14 main-text" for="">City</label>
                                                    <select name="" id="city" disabled class="form-select custom-select mt-2">
                                                        <option value="0">- Select -</option>
                                                        <?php
                                                        if ($isHasAddress) {
                                                        ?>
                                                            <option selected value="<?= $studentAddressData["city_id"]; ?>"><?= $studentAddressData["city"]; ?></option>
                                                        <?php
                                                        }
                                                        ?>
                                                    </select>
                                                    <span id="city-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                    <label class="text-14 main-text" for="">Postal Code</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="postal-code" maxlength="5" value="<?php echo $isHasAddress ? $studentAddressData['postal_code'] : ''; ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <?php
                                        $parents = Database::search("SELECT * FROM `parents_has_students` 
                                                            INNER JOIN `parents` ON `parents_has_students`.`parents_parent_id`=`parents`.`parent_id`
                                                            INNER JOIN `title` ON `parents`.`title_title_id`=`title`.`title_id`
                                                            INNER JOIN `guardian_relation` ON `parents`.`guardian_relation_guardian_relation_id`=`guardian_relation`.`guardian_relation_id`
                                                            WHERE `parents_has_students`.`students_student_id`='" . $sessionUserObject['student_id'] . "'");

                                        if ($parents->num_rows > 0) {
                                        ?>
                                            <div class="col-12 p-0 g-0 mt-5">
                                                <div class="row gx-4 gy-3">
                                                    <span class="sub-topic">Gurdian Details</span>

                                                    <div class="table-responsive-wrapper">
                                                        <div class="table-responsive" id="students-table-container">
                                                            <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                                                                <thead>
                                                                    <tr class="br-t-5">
                                                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                            <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all">
                                                                                <div class="d-flex justify-content-between align-items-center w-100">
                                                                                    <span class="text-14 main-text fw-medium mx-2">Parent Name</span>
                                                                                </div>
                                                                            </div>
                                                                        </th>

                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Relation</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Whatsapp Number</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Mobile Number</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Email</th>

                                                                    </tr>
                                                                </thead>
                                                                <tbody id="user-table-data">
                                                                    <?php

                                                                    while ($data = $parents->fetch_assoc()) {
                                                                    ?>
                                                                        <tr>
                                                                            <td class="px-4 py-3 pe-0 align-middle">
                                                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                    <div class="flex-grow-1 d-flex align-items-center cursor-pointer">
                                                                                        <?php
                                                                                        if (!empty($data["profile_img"]) && file_exists(Project::getAssetsPath() . '/imgs/parents/' . $data["profile_img"])) {
                                                                                        ?>
                                                                                            <img src="<?= Project::getAssetsPath() ?>/imgs/parents/<?php echo $data["profile_img"]; ?>" class="hwr-40" alt="Parents image">
                                                                                        <?php
                                                                                        } else {
                                                                                            $firstNameInitial = strtoupper(substr($data["parent_fname"], 0, 1));
                                                                                            $lastNameInitial = strtoupper(substr($data["parent_lname"], 0, 1));
                                                                                        ?>
                                                                                            <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                        <span class="text-14 main-text mx-2"><?php echo $data["parent_fname"] . " " . $data["parent_lname"]; ?></span>
                                                                                    </div>
                                                                                </div>
                                                                            </td>

                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["guardian_relation"]; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["whatsapp"]; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["mobile"]; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo date("Y-m-d h:i A", strtotime($data["registered_at"])); ?></span></td>
                                                                        </tr>
                                                                    <?php
                                                                    }

                                                                    ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php
                                        }
                                        ?>
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

        <div id="crop-modal" tabindex="-1" aria-hidden="true" class="modal fade" data-backdrop="static">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Crop Image</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="crop-model-close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img id="image" class="img-fluid" />
                    </div>
                    <div class="modal-footer">
                        <button id="crop-button" type="button" class="custom-btn px-5 pe-5">Crop</button>
                    </div>
                </div>
            </div>
        </div>

        <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="./js/functions.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/fileChooser.js"></script>
    </body>

    </html>
<?php
}
?>