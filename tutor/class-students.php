<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsTutor()) {
    header("Location: ./index");
} else {

    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

    if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['class-id']) && !empty($_GET['class-id']))) {
        header("Location: ./class");
    } else {
        $class_id = $_GET["class-id"];

        $query = "SELECT * FROM `classes` 
        INNER JOIN `subjects` ON `classes`.`subjects_subject_id` = `subjects`.`subject_id`
        INNER JOIN `year` ON `classes`.`year_year_id` = `year`.`year_id`
        INNER JOIN `medium` ON `classes`.`medium_medium_id` = `medium`.`medium_id`
        INNER JOIN `exam_type` ON `classes`.`exam_type_exam_type_id` = `exam_type`.`exam_type_id`
        WHERE `class_id` = '" . $class_id . "'";
        if (SessionUser::ifUserIsTutor()) {
            $query .= " AND `tutor_id`='" . $sessionUserObject['user_id'] . "'";
        }
        $class = Database::search($query);
        if ($class->num_rows == 0) {
            header("Location: ./classes");
        } else {
            $classData = $class->fetch_assoc();
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Class Students - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
            </head>

            <body class="overflow-hidden">
                <ul class="notifications"></ul>
                <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                    <div id="appContainer" class="row p-0">
                        <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                            <?php
                            include "includes/side-menu.php";
                            load_side_menu::load("Classes");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "includes/header.php";
                                    selectItem::setSelected("Classes");
                                    ?>
                                </div>
                                <div class="container p-4 m-0" id="skeleton-list">
                                    <?php
                                    include '../includes/components/skeleton-header.php';
                                    include '../includes/components/skeleton-table.php'; ?>
                                </div>
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-lg-8">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n="class-students"><?= $classData['class_name'] ?></span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./classes">Classes</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./class-details?class-id=<?= $class_id ?>">Class Details</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Class Students</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                                    <div class="row gx-3 justify-content-end">
                                                        <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <div class="dropdown w-100">
                                                                <button class="custom-btn w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" data-i18n="action" aria-expanded="false">
                                                                    Action
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end w-100" style="min-width: max-content;">
                                                                    <li><a class="dropdown-item" href="add-student-to-class?class-id=<?= $class_id ?>" data-i18n="add-student">Add New Student</a></li>
                                                                    <li><a class="dropdown-item" href="upload-student-bulk?class-id=<?= $class_id ?>" data-i18n="add-students-bulk">Add New Students Bulk</a></li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 mb-4">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                        <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                            <div class="d-flex custom-div align-items-center justify-content-center">
                                                                <?php
                                                                $studentCount = Database::search("SELECT COUNT(DISTINCT `students_has_class_id`) AS unique_student_count FROM `students_has_classes` WHERE `classes_class_id` = $class_id");
                                                                $data = $studentCount->fetch_assoc();
                                                                ?>
                                                                <span class="text-14 dark-theme fw-medium"><?php echo $data["unique_student_count"]; ?> <span data-i18n="students">Students</span></span>
                                                            </div>
                                                        </div>
                                                        <div class="col-8 col-md-8 col-lg-7 d-flex flex-column">
                                                            <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="search-text" onkeyup="handleClassStudentsPagination(<?= $class_id ?>,'filter')">
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-2 col-lg-1 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#studentsFilter" aria-expanded="false" aria-controls="studentsFilter">
                                                                <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                            </button>
                                                        </div>
                                                        <div class="col-12 col-md-2 col-lg-2 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100 d-flex align-items-center justify-content-center" type="button" onclick="genarateClassStudentsReport(this, <?= $class_id ?>, '<?= $classData['class_name'] ?>')">
                                                                <span class="text-14 fw-semibold">Export Report</span>
                                                                <iconify-icon icon="line-md:download-loop" width="26" height="26" class="text-white px-3 pe-0"></iconify-icon>
                                                            </button>
                                                        </div>
                                                        <div class="collapse" id="studentsFilter">
                                                            <div class="card card-body border-0 p-0">
                                                                <div class="col-12">
                                                                    <div class="row">

                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" aria-label="Default select example" id="update-selector" onchange="filterClassStudents(<?= $class_id ?>)">
                                                                                <option selected value="0">- Update Students -</option>
                                                                                <option value="1">Active Students</option>
                                                                                <option value="2">Inactive Students</option>
                                                                            </select>
                                                                        </div>

                                                                        <div class="col-12 col-md-2 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <button class="custom-btn w-100" type="button" onclick="applyClassStudentsUpdate(this,<?= $class_id ?>)">Apply</button>
                                                                        </div>

                                                                        <div class="col-12 offset-0 offset-md-2 offset-lg-6 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleClassStudentsPagination(<?= $class_id ?>,'sortByStatus')">
                                                                                <option selected value="0">- Filter Students Status -</option>
                                                                                <option value="1">Active</option>
                                                                                <option value="2">Inactive</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 d-none d-lg-block"></div>
                                                        <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                            <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                            <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleClassStudentsPagination(<?= $class_id ?>,'filter')">
                                                                <option selected value="10">10</option>
                                                                <option value="25">25</option>
                                                                <option value="50">50</option>
                                                                <option value="100">100</option>
                                                                <option value="250">250</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="table-responsive-wrapper">
                                                    <div class="table-responsive" id="students-table-container">
                                                        <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                                                            <thead>
                                                                <tr class="br-t-5">
                                                                    <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                        <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                            <div class="d-flex justify-content-between align-items-center w-100" onclick="handleClassStudentsPagination(<?= $class_id ?>,'sortByName')">
                                                                                <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                                <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                            </div>
                                                                        </div>
                                                                    </th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Student ID</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Gender</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Date of Birth</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">WhatsApp Number</th>
                                                                    <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer" onclick="handleClassStudentsPagination(<?= $class_id ?>,'sortByDate')">
                                                                        <div class="d-flex justify-content-between align-items-center w-100">
                                                                            <span class="text-14 main-text fw-medium">Admission Date</span>
                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                        </div>
                                                                    </th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>

                                                                </tr>
                                                            </thead>
                                                            <tbody id="user-table-data">
                                                                <?php
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
                                                               WHERE `students_has_classes`.`classes_class_id` = '" . $class_id . "'
                                                           ORDER BY 
                                                               `students`.`student_id` DESC
                                                       ";

                                                                $results = Database::search($query);
                                                                $pageno = 1;
                                                                $results_count = $results->num_rows;
                                                                $result_per_page = 10;  // Consistent variable name

                                                                if ($results_count > $result_per_page) {
                                                                    $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                                                } else {
                                                                    $number_of_pages = 1;
                                                                }

                                                                $page_results = ($pageno - 1) * $result_per_page;
                                                                $users = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                if ($users->num_rows > 0) {
                                                                    while ($data = $users->fetch_assoc()) {
                                                                ?>
                                                                        <tr>
                                                                            <td class="px-4 py-3 pe-0 align-middle">
                                                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["cId"]; ?>" onchange="checkRowEvents(<?php echo $data['cId']; ?>);">
                                                                                    <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['cId']; ?>)">
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

                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["student_class_id"]; ?></span></td>

                                                                            <td class="py-2 align-middle">
                                                                                <?php
                                                                                $gender = $data["gender"];
                                                                                $isMale = ($gender === 'Male');
                                                                                $badgeClass = $isMale
                                                                                    ? 'table-btn-primary text-primary'
                                                                                    : 'table-btn-pink text-dark-pink';
                                                                                $icon = $isMale ? 'mdi:gender-male' : 'mdi:gender-female';
                                                                                ?>
                                                                                <span class="badge <?php echo $badgeClass; ?> br-0 w-100 py-2 d-inline-flex align-items-center justify-content-center gap-1 fw-normal">
                                                                                    <iconify-icon icon="<?php echo $icon; ?>" width="14"></iconify-icon>
                                                                                    <?php echo $gender; ?>
                                                                                </span>
                                                                            </td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["date_of_birth"]; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["whatsapp"]; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo date("Y-m-d h:i A", strtotime($data["date_registered"])); ?></span></td>

                                                                            <td class="py-2 align-middle">
                                                                                <?php
                                                                                $status_classes = [
                                                                                    "Active"     => "table-btn-success",
                                                                                    "Inactive"  => "table-btn-danger",
                                                                                ];
                                                                                $sbutton_class = $status_classes[$data["status"]];
                                                                                ?>
                                                                                <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["status"]; ?></button>
                                                                            </td>


                                                                            <td class="py-3 align-middle">
                                                                                <div class="dropdown">
                                                                                    <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                    <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="edit-student?student-id=<?php echo $data['sId']; ?>&class-id=<?= $class_id ?>">
                                                                                                <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                Edit</a></li>
                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                        <?php
                                                                                        if ($data["status"] == "Active") {
                                                                                        ?>
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateClassStudentStatus(2,'<?php echo $data['cId']; ?>','<?php echo $class_id ?>')">
                                                                                                    <iconify-icon icon="lsicon:minus-filled" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                    Deactivate</a></li>
                                                                                        <?php
                                                                                        } else {
                                                                                        ?>
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateClassStudentStatus(1,'<?php echo $data['cId']; ?>','<?php echo $class_id ?>')">
                                                                                                    <iconify-icon icon="dashicons:plus-alt" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                    Activate</a></li>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                    </ul>
                                                                                </div>
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
                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleClassStudentsPagination(<?= $class_id ?>,'previousPage');">
                                                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                        <span class="text-14 main-text fw-semibold">Previous</span>
                                                                    </button>
                                                                    <!-- Page Numbers (col-10, centered) -->
                                                                    <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                        <ul class="pagination-list d-flex mb-0">
                                                                            <?php
                                                                            $shudderPages1 = false;
                                                                            $shudderPages2 = false;

                                                                            function renderPageButton($i, $isActive = false, $class_id)
                                                                            {
                                                                                $activeClass = $isActive ? 'active' : '';
                                                                                $action = $isActive ? '' : "onclick=\"handleClassStudentsPagination('{$class_id}','goToPage', '{$i}')\"";
                                                                                echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                                                            }

                                                                            for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                                                    renderPageButton($i, $pageno == $i, $class_id);
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
                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleClassStudentsPagination(<?= $class_id ?>,'nextPage',<?= $number_of_pages ?>);">
                                                                        <span class="text-14 main-text fw-semibold">Next</span>
                                                                        <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                    </button>
                                                                </div>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                </div>
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

                <div class="modal fade br-0" id="csvDownload" aria-hidden="true" aria-labelledby="csvDownloadLabel" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                        <div class="modal-content p-4">
                            <div class="modal-header p-0 border-0">
                                <h1 class="modal-title text-16 text-dark-color" id="csvDownloadLabel" data-i18n="download-students">Download Students CSV</h1>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-0 mt-3">
                                <!-- Waiting Message and Animation -->
                                <div id="waitingMessage" class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden" data-i18n="loading">Loading...</span>
                                    </div>
                                    <h3 class="mt-3" data-i18n="preparing-csv">Preparing Your CSV File...</h3>
                                    <p class="text-muted" data-i18n="please-wait-preparing-csv">Please wait while we generate your download.</p>
                                </div>
                                <!-- Success Message (Hidden by Default) -->
                                <div id="successMessage" class="text-center" class="d-none">
                                    <div class="checkmark-circle">
                                        <div class="checkmark"></div>
                                    </div>
                                    <h3 class="mt-3" data-i18n="download-ready">Download Ready!</h3>
                                    <p class="text-muted" data-i18n="csv-ready">Your CSV file has been successfully generated.</p>
                                    <button class="custom-btn dark-theme px-5 mt-3 text-white" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>

            </body>

            </html>
<?php
        }
    }
}
?>