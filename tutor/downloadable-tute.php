<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['class-id']) && !empty($_GET['class-id']) && isset($_GET['tute-id']) && !empty($_GET['tute-id']))) {
        header("Location: ./level-details");
    } else {

        $class_id = $_GET["class-id"];
        $tute_id = $_GET["tute-id"];

        $query = "SELECT * FROM `class_tutes` 
        INNER JOIN `classes` ON `classes_class_id`=`class_id`
        WHERE `classes_class_id` = '" . $class_id . "' AND `class_tute_id`='" . $_GET['tute-id'] . "'";
        if (SessionUser::ifUserIsTutor()) {
            $query .= " AND `tutor_id`='" . $sessionUserObject['user_id'] . "'";
        }
        $class = Database::search($query);
        if (!($class->num_rows > 0)) {
            header("Location: ./course-overview");
        } else {
            $classData = $class->fetch_assoc();

            function formatFileSize($bytes, $decimals = 2)
            {
                if ($bytes <= 0) {
                    return '0 B';
                }

                $units = ['B', 'KB', 'MB', 'GB', 'TB'];
                $factor = floor(log($bytes, 1000));

                return sprintf(
                    "%.{$decimals}f %s",
                    $bytes / pow(1000, $factor),
                    $units[$factor]
                );
            }
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Class Tute - <?= Project::getPortalBasePath() ?></title>
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/tute-card.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/communication.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css">
                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/lecture-card.css">
            </head>

            <body class="overflow-hidden">
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
                                    include Project::getIncludesPath() . '/components/skeleton-header.php';
                                    include Project::getIncludesPath() . '/components/skeleton-class-card.php'; ?>
                                </div>
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 py-4 px-4 pe-4 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12 col-lg-8">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n="class-students"><?= $classData['tute_title'] ?></span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./classes">Classes</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./class-details?class-id=<?= $class_id ?>">Class Details</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Class Tutes</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>


                                        <?php
                                        $attachment = Database::search("SELECT * FROM `class_tutes_has_attachments` WHERE `class_tutes_class_tute_id` = '" . $tute_id . "'");
                                        if ($attachment->num_rows > 0) {

                                        ?>
                                            <div class="col-12 component-theme p-4 d-flex flex-column">
                                                <label class="text-16 sub-topic" for="">Tute Pack Attachments</label>
                                                <div class="col-12 px-1 mt-4">
                                                    <div class="row" id="lectures-attachments">
                                                        <?php
                                                        while ($attachmentData = $attachment->fetch_assoc()) {
                                                            if (!empty($attachmentData["attachment"]) && file_exists(Project::getAssetsPath() . "/uploads/tutes-attachments/" . $attachmentData["attachment"])) {
                                                                $filePath = Project::getAssetsPath() . "/uploads/tutes-attachments/" . $attachmentData["attachment"];
                                                                $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
                                                                $fileSize = filesize($filePath);
                                                                $formattedSize = formatFileSize($fileSize);
                                                        ?>
                                                                <div class="col-12 col-lg-4 file-item cursor-pointer">
                                                                    <div class="bl-solid">
                                                                        <div class="row">
                                                                            <div class="col-10"
                                                                                onclick="viewLectureAttachment('<?php echo $attachmentData['attachment'] ?>','<?php echo $attachmentData['attachment_name'] ?>')">

                                                                                <div class="row p-3">
                                                                                    <div class="col-3">
                                                                                        <img src="../assets/imgs/icons/<?php echo $fileExtension ?>.png" alt="">
                                                                                    </div>

                                                                                    <div class="col-8">
                                                                                        <div class="file-name">
                                                                                            <div class="name"><?php echo $attachmentData["attachment_name"] ?></div>
                                                                                        </div>

                                                                                        <div class="file-size">
                                                                                            <div class="name">
                                                                                                <?php echo strtoupper($fileExtension) ?> • <?php echo $formattedSize ?>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>

                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                        <?php
                                                            }
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php
                                        }

                                        ?>

                                        <div class="col-12 p-4 component-theme mt-4">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <label class="text-16 sub-topic" for="">Tute Pack Access Students</label>
                                                <div class="col-12 my-4">
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
                                                        <div class="col-8 col-md-10 d-flex flex-column">
                                                            <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="search-text" onkeyup="handleDownloadableTuteAccessStudentsPagination(<?= $tute_id ?>,'filter')">
                                                            </div>
                                                        </div>
                                                      
                                                      
                                                        <div class="col-6 d-none d-lg-block"></div>
                                                        <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                            <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                            <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleDownloadableTuteAccessStudentsPagination(<?= $tute_id ?>,'filter')">
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
                                                                            <div class="d-flex justify-content-between align-items-center w-100" onclick="handleDownloadableTuteAccessStudentsPagination(<?= $tute_id ?>,'sortByName')">
                                                                                <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                                <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                            </div>
                                                                        </div>
                                                                    </th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Student Class ID</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">WhatsApp Number</th>
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

                                                                $query .= " WHERE `students_has_classes`.`classes_class_id` = '" . $class_id . "'
                                                                ORDER BY `students_has_classes`.`students_has_class_id` DESC";

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
                                                                $students = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                if ($students->num_rows > 0) {
                                                                    while ($data = $students->fetch_assoc()) {
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

                <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
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