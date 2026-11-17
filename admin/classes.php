<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsAdmin()) {
    header("Location: ./index");
} else {

    $sessionUserObject = SessionUser::getCurrentUserObject('Admin');

?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Classes - Admin Dashboard | <?= Project::getBaseName() ?></title>

        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>

    </head>

    <body class="overflow-hidden" onload="initializeClass();">
        <div class="notifications"></div>
        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
            <div id="appContainer" class="row p-0">
                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                    <?php
                    include "./includes/side-menu.php";
                    load_side_menu::load("Classes");
                    ?>
                </div>
                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                    <div class="row p-0 m-0 g-0">
                        <div class="col-12 p-0 m-0 g-0">
                            <?php
                            include "../includes/header.php";
                            selectItem::setSelected("Classes");
                            ?>
                        </div>
                        <div class="container p-4 m-0" id="skeleton-list">
                            <?php
                            include '../includes/components/skeleton-header.php';
                            include '../includes/components/skeleton-class-card.php'; ?>
                        </div>
                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                            <div class="row p-4 m-0 g-0">
                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                    <div class="row align-items-center p-0 m-0 g-0">
                                        <div class="col-12 col-lg-8">
                                            <div class="row">
                                                <span class="main-topic lh-lg" data-i18n="classes">Classes</span>
                                                <span class="text-14 sub-text lh-base mt-1" data-i18n="classes-desc">Organize classes, schedules, and instructors with ease and clarity.</span>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                            <div class="row gx-3 justify-content-end">
                                                <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                    <a class="custom-btn col-12 text-decoration-none d-inline-flex align-items-center justify-content-center" href="class-form" data-i18n="">Create a Class</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="row g-3">
                                        <?php
                                        $query = "
                                                SELECT 
                                                    classes.*,
                                                    subjects.subject,
                                                    year.year,
                                                    medium.medium,
                                                    exam_type.exam_type,
                                                    exam_type.exam_type_id,
                                                    COUNT(DISTINCT students_has_classes.students_has_class_id) AS student_count
                                                FROM classes
                                                INNER JOIN subjects ON classes.subjects_subject_id = subjects.subject_id
                                                INNER JOIN year ON classes.year_year_id = year.year_id
                                                INNER JOIN medium ON classes.medium_medium_id = medium.medium_id
                                                INNER JOIN exam_type ON classes.exam_type_exam_type_id = exam_type.exam_type_id
                                                LEFT JOIN students_has_classes 
                                                    ON classes.class_id = students_has_classes.classes_class_id
                                                ";

                                        $query .= " GROUP BY `classes`.`class_id` ORDER BY `classes`.`class_id` DESC";

                                        $classes = Database::search($query);

                                        if ($classes->num_rows > 0) {
                                            function getClassBage($exam_type_id)
                                            {
                                                switch ($exam_type_id) {
                                                    case 1:
                                                        return "scholarship";
                                                        break;
                                                    case 2:
                                                        return "o-l";
                                                        break;
                                                    case 3:
                                                        return "a-l";
                                                        break;
                                                    case 4:
                                                        return "london-o-l";
                                                        break;
                                                    case 5:
                                                        return "london-a-l";
                                                        break;
                                                }
                                            }
                                            while ($class = $classes->fetch_assoc()) {
                                                $classBage = 'scholarship';
                                                $classBage = getClassBage($class['exam_type_id']);
                                        ?>
                                                <div class="col-12 col-md-4 col-xl-3">
                                                    <div class="tutor-class-card h-100">

                                                        <div class="card-image-wrapper position-relative cursor-pointer" onclick="window.location.href='class-form?class-id=<?= $class['class_id']; ?>'">
                                                            <?php if (!empty($class['class_cover']) && file_exists(Project::getAssetsPath() . "/imgs/classes-covers/" . $class['class_cover'])) { ?>
                                                                <img src="<?= Project::getAssetsPath() ?>/imgs/classes-covers/<?= $class['class_cover'] ?>" class="w-100 h-auto" alt="Class Thumbnail">
                                                            <?php } else { ?>
                                                                <img src="<?= Project::getAssetsPath() ?>/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="Class Thumbnail">
                                                            <?php } ?>

                                                            <div class="image-overlay"></div>

                                                            <span class="class-badge <?= $classBage ?>">
                                                                <?= $class['year'] ?> • <?= $class['exam_type'] ?>
                                                            </span>
                                                        </div>

                                                        <div class="card-body-custom cursor-pointer">
                                                            <span class="subject-text">
                                                                <?= $class['subject'] ?>
                                                            </span>
                                                            <h5 class="class-title js-trim" data-full-title="<?= $class['class_name'] ?>">
                                                                <?= $class['class_name'] ?>
                                                            </h5>
                                                            <div class="class-meta d-flex justify-content-between" onclick="window.location.href='class-form?class-id=<?= $class['class_id']; ?>'">
                                                                <div class="meta-item">
                                                                    <iconify-icon icon="fontisto:language"></iconify-icon>
                                                                    <span class="sub-text"><?= $class['medium'] ?></span>
                                                                </div>
                                                                <div class="meta-item">
                                                                    <iconify-icon icon="lets-icons:user-alt-duotone"></iconify-icon>
                                                                    <span class="sub-text"><?= $class['student_count'] ?> Students</span>
                                                                </div>
                                                            </div>
                                                            <div class="card-footer-premium mt-0">
                                                                <button class="btn-watch-premium bg-dark-theme"
                                                                    onclick="window.location = 'class-form?class-id=<?= $class['class_id']; ?>'">
                                                                    <iconify-icon icon="solar:eye-broken" width="18"></iconify-icon>
                                                                    <span>View Class</span>
                                                                </button>

                                                                <div class="footer-icon-group">

                                                                    <button class="icon-premium" onclick="window.location = 'class-form?class-id=<?= (int) $class['class_id']; ?>'"
                                                                        data-tooltip="Edit Class"
                                                                        data-class='<?= json_encode($class, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                                                        <iconify-icon icon="line-md:edit-twotone" width="20"></iconify-icon>
                                                                    </button>

                                                                    <div class="dropdown">
                                                                        <button class="icon-premium"
                                                                            type="button"
                                                                            data-bs-toggle="dropdown"
                                                                            data-tooltip="Options"

                                                                            aria-expanded="false">
                                                                            <iconify-icon icon="qlementine-icons:menu-dots-16" width="20"></iconify-icon>
                                                                        </button>

                                                                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow br-5">
                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="class-students?class-id=<?= $class['class_id']; ?>">
                                                                                    <iconify-icon icon="lets-icons:user-alt-duotone" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                    Students</a></li>
                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="class-time-table?class-id=<?= $class['class_id']; ?>">
                                                                                    <iconify-icon icon="solar:calendar-date-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                    Time Table</a></li>
                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="class-recordings?class-id=<?= $class['class_id']; ?>">
                                                                                    <iconify-icon icon="gravity-ui:video" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                    Recordings</a></li>
                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="class-pre-recordings?class-id=<?= $class['class_id']; ?>">
                                                                                    <iconify-icon icon="streamline:live-video" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                    Pre-Recorded Classes</a></li>
                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="class-attendance?class-id=<?= $class['class_id']; ?>">
                                                                                    <iconify-icon icon="ci:wavy-check" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                    Attendance</a></li>
                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="class-tutes?class-id=<?= $class['class_id']; ?>">
                                                                                    <iconify-icon icon="lsicon:constitute-outline" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                    Tutes</a></li>
                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="class-payments?class-id=<?= $class['class_id']; ?>">
                                                                                    <iconify-icon icon="fluent:payment-28-regular" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                    Payments</a></li>
                                                                        </ul>

                                                                    </div>

                                                                </div>
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


        <!-- Create / Edit Class are pages now: class-form.php -->


        <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>
    </body>

    </html>
<?php

}
?>