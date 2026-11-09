<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['class-id']) && !empty($_GET['class-id']))) {
        header("Location: ./level-details");
    } else {

        $class_id = $_GET["class-id"];

        $query = "SELECT * FROM `classes` WHERE `class_id` = '" . $class_id . "'";
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
                <title>Class Time Table - <?= Project::getPortalBasePath() ?></title>
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
                                                        <span class="main-topic lh-lg" data-i18n="class-students"><?= $classData['class_name'] ?></span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./classes">Classes</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./class-details?class-id=<?= $class_id ?>">Class Details</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Class Tutes</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                                    <div class="row gx-3 justify-content-end align-items-center">
                                                        <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <button class="custom-btn w-100" type="button" onclick="window.location = 'add-tute?class-id=<?= $class_id ?>'">
                                                                Add New Tute
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>


                                        <div class="col-12">
                                            <div class="row g-3">

                                                <?php
                                                $tutes = Database::search("SELECT `class_tutes`.*, `tute_types`.`tute_type`,COUNT(`class_tutes_has_attachments`.`class_tutes_class_tute_id`) AS attachments FROM `class_tutes`
                                                INNER JOIN `tute_types` ON `class_tutes`.`tute_types_tute_type_id` = `tute_types`.`tute_type_id` 
                                                INNER JOIN `class_tutes_has_attachments` ON `class_tutes`.`class_tute_id` = `class_tutes_has_attachments`.`class_tutes_class_tute_id`
                                                WHERE `classes_class_id`='" . $class_id . "'
                                                GROUP BY `class_tutes`.`class_tute_id`");
                                                if ($tutes->num_rows > 0) {
                                                    while ($tute = $tutes->fetch_assoc()) {
                                                        $tuteType = $tute['tute_types_tute_type_id'] == 1 ? "online" : "couried";

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

                                                        if (!empty($tute['payment_month'])) {

                                                            $paymentMonth = explode('-', $tute['payment_month']);
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

                                                        $query .= " WHERE `students_has_classes`.`classes_class_id` = '" . $tute['classes_class_id'] . "'
                                                                ORDER BY `students_has_classes`.`students_has_class_id` DESC";

                                                        $students = Database::search($query);
                                                        $studentCount = $students->num_rows;
                                                        if (3 < $students->num_rows) {
                                                            $studentCount = $studentCount - 3;
                                                        }

                                                ?>
                                                        <div class="col-12 col-md-4 col-xxl-3">
                                                            <div class="tutor-class-card h-100">

                                                                <div class="position-relative cursor-pointer">
                                                                    <span class="class-badge <?= $tuteType ?>">
                                                                        <?= $tute['tute_type'] ?>
                                                                    </span>
                                                                </div>

                                                                <div class="card-body-custom cursor-pointer">
                                                                    <span class="subject-text">
                                                                        <?= date('d M Y H:i', strtotime($tute['date_added'])) ?>
                                                                    </span>
                                                                    <span class="section-label">Tute</span>
                                                                    <h5 class="class-title">
                                                                        <?= $tute['tute_title'] ?>
                                                                    </h5>

                                                                    <div class="meta-item">
                                                                        <span class="sub-text"><?= $tute['tute_description'] ?></span>
                                                                    </div>

                                                                    <div class="tute-info mt-2 mb-0">
                                                                        <div class="info-item">
                                                                            <div class="value d-flex align-items-center justify-content-between w-100">
                                                                                <div class="avatar-group">
                                                                                    <?php
                                                                                    $loopCount = $students->num_rows > 3 ? 3 : $students->num_rows;
                                                                                    for ($i = 0; $i < $loopCount; $i++) {
                                                                                        $student = $students->fetch_assoc();
                                                                                        if (!empty($student["profile_img"]) && file_exists(Project::getAssetsPath() . '/imgs/students/' . $student["profile_img"])) {
                                                                                    ?>
                                                                                            <img src="<?= Project::getAssetsPath() ?>/imgs/students/<?php echo $student["profile_img"]; ?>" class="hwr-40" alt="Students image">
                                                                                        <?php
                                                                                        } else {
                                                                                            $firstNameInitial = strtoupper(substr($student["first_name"], 0, 1));
                                                                                            $lastNameInitial = strtoupper(substr($student["last_name"], 0, 1));
                                                                                        ?>
                                                                                            <div class="d-flex dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                        <?php
                                                                                        }
                                                                                        ?>

                                                                                    <?php
                                                                                    }
                                                                                    ?>

                                                                                    <span class="avatar-more"><?= $students->num_rows > 3 ? '+' : '' ?><?= $studentCount ?></span>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <div class="meta-item">
                                                                            <iconify-icon icon="streamline-flex:pdf-reader-application-solid"></iconify-icon>
                                                                            <span class="sub-text"><?= $tute['attachments'] ?> • Attachments</span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="card-footer-premium">
                                                                        <?php
                                                                        if ($tute['tute_types_tute_type_id'] == 2) {
                                                                        ?>
                                                                            <button class="btn-watch-premium bg-dark-theme" onclick="window.location = 'couriered-tute?class-id=<?= $class_id ?>&tute-id=<?= $tute['class_tute_id'] ?>'">
                                                                                <iconify-icon icon="solar:eye-broken" width="18"></iconify-icon>
                                                                                <span>View Tute</span>
                                                                            </button>
                                                                        <?php
                                                                        } else {
                                                                        ?>
                                                                            <button class="btn-watch-premium bg-dark-theme" onclick="window.location = 'downloadable-tute?class-id=<?= $class_id ?>&tute-id=<?= $tute['class_tute_id'] ?>'">
                                                                                <iconify-icon icon="solar:eye-broken" width="18"></iconify-icon>
                                                                                <span>View Tute</span>
                                                                            </button>
                                                                        <?php
                                                                        }
                                                                        ?>

                                                                        <!-- <div class="footer-icon-group">
                                                                            <button class="icon-premium" onclick="openEditClassModal(this)"
                                                                                data-tooltip="Edit Tute"
                                                                                data-class=''>
                                                                                <iconify-icon icon="line-md:edit-twotone" width="20"></iconify-icon>
                                                                            </button>
                                                                        </div> -->
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php
                                                    }
                                                } else {
                                                    ?>
                                                    <div class="col-12 px-4 mt-3 h-100">
                                                        <div class="row component-theme align-items-center justify-content-center h-100">
                                                            <img src="<?= Project::getAssetsPath() ?>/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
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

                <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
            </body>

            </html>
<?php


        }
    }
}
?>