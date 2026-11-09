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
        <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">

        <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
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
                                                    <button class="custom-btn col-12" data-bs-target="#addSubject" data-bs-toggle="modal" data-i18n="">Create a Class</button>
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

                                                        <div class="card-image-wrapper position-relative cursor-pointer" onclick="window.location.href='class-details?class-id=<?= $class['class_id']; ?>'">
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
                                                            <div class="class-meta d-flex justify-content-between" onclick="window.location.href='class-details?class-id=<?= $class['class_id']; ?>'">
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
                                                                    onclick="window.location = 'class-details?class-id=<?= $class['class_id']; ?>'">
                                                                    <iconify-icon icon="solar:eye-broken" width="18"></iconify-icon>
                                                                    <span>View Class</span>
                                                                </button>

                                                                <div class="footer-icon-group">

                                                                    <button class="icon-premium" onclick="openEditClassModal(this)"
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

        <!-- class Add Model => Start -->
        <div class="modal fade br-0" id="addSubject" aria-hidden="true" aria-labelledby="addSubjectLabel" tabindex="-1">
            <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                <div class="modal-content p-4">
                    <div class="modal-header p-0 border-0">
                        <h1 class="modal-title text-16 text-dark-color" id="addclassLabel">Create a Class</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0 mt-3">
                        <div class="col-12 p-0">
                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                <div class="col-12 d-flex flex-column">
                                    <label class="text-14 main-text required">Tutor</label>
                                    <select name="" class="form-select custom-select mt-2" id="tutor">
                                        <option value="0">- Select -</option>
                                        <?php
                                        $tutors = Database::search("SELECT * FROM `users` WHERE `user_roles_user_role_id`='2' AND `status_status_id`='1'");
                                        while ($tutor = $tutors->fetch_assoc()) {
                                        ?>
                                            <option value="<?= $tutor['user_id'] ?>"><?= $tutor['user_fname'] ?> <?= $tutor['user_lname'] ?></option>
                                        <?php
                                        }
                                        ?>
                                    </select>
                                    <span class="text-12 text-dark-red" id="medium-feedback"></span>
                                </div>
                                <div class="col-12 col-lg-6 d-flex flex-column">
                                    <label class="text-14 main-text required">Subject</label>

                                    <div class="custom-search-select mt-2" data-input="subject">
                                        <input type="text"
                                            class="form-control custom-input search-input"
                                            placeholder="- Select -"
                                            autocomplete="off">

                                        <input type="hidden" id="subject" value="">

                                        <div class="search-dropdown">
                                            <?php
                                            $subjects = Database::search("SELECT * FROM `subjects`");
                                            while ($subject = $subjects->fetch_assoc()) {
                                            ?>
                                                <div class="search-option"
                                                    data-value="<?= $subject['subject_id'] ?>">
                                                    <?= $subject['subject'] ?>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <span class="text-12 text-dark-red" id="subject-feedback"></span>
                                </div>

                                <div class="col-12 col-lg-6 d-flex flex-column">
                                    <label for="" class="text-14 main-text required">Medium</label>
                                    <select name="" class="form-select custom-select mt-2" id="medium">
                                        <option value="0">- Select -</option>
                                        <?php
                                        $mediums = Database::search("SELECT * FROM `medium`");
                                        while ($medium = $mediums->fetch_assoc()) {
                                        ?>
                                            <option value="<?= $medium['medium_id'] ?>"><?= $medium['medium'] ?></option>
                                        <?php
                                        }
                                        ?>
                                    </select>
                                    <span class="text-12 text-dark-red" id="medium-feedback"></span>
                                </div>
                                <div class="col-12 col-lg-6 d-flex flex-column">
                                    <label for="" class="text-14 main-text required">Year</label>
                                    <select name="" class="form-select custom-select mt-2" id="year">
                                        <option value="0">- Select -</option>
                                        <?php
                                        $years = Database::search("SELECT * FROM `year`");
                                        while ($year = $years->fetch_assoc()) {
                                        ?>
                                            <option value="<?= $year['year_id'] ?>"><?= $year['year'] ?></option>
                                        <?php
                                        }
                                        ?>
                                    </select>
                                    <span class="text-12 text-dark-red" id="year-feedback"></span>
                                </div>
                                <div class="col-12 col-lg-6 d-flex flex-column">
                                    <label for="" class="text-14 main-text required">Exam Type</label>
                                    <select name="" class="form-select custom-select mt-2" id="exam-type">
                                        <option value="0">- Select -</option>
                                        <?php
                                        $exam_type = Database::search("SELECT * FROM `exam_type`");
                                        while ($type = $exam_type->fetch_assoc()) {
                                        ?>
                                            <option value="<?= $type['exam_type_id'] ?>"><?= $type['exam_type'] ?></option>
                                        <?php
                                        }
                                        ?>
                                    </select>
                                    <span class="text-12 text-dark-red" id="exam-type-feedback"></span>
                                </div>

                                <div class="col-12 d-flex flex-column">
                                    <label for="" class="text-14 main-text">Class Name</label>
                                    <input type="text" class="form-control custom-input mt-2" id="class-name">
                                    <span class="text-12 text-dark-red" id="class-name-feedback"></span>
                                </div>
                                <div class="col-12 d-flex flex-column">
                                    <label for="" class="text-14 main-text">Class Thumbnail</label>
                                    <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                            <img src="<?= Project::getAssetsPath() ?>/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="subject cover" id="subject-cover">
                                        </div>
                                        <div class="col-12 col-lg-4 d-flex mt-0 mt-lg-0">
                                            <div class="d-flex flex-column justify-content-end g-0 p-0 m-0 flex-grow-1">
                                                <div class="col-12">
                                                    <p class="text-14 sub-text g-0 p-0 m-0">Select class cover here.
                                                        </br>
                                                        Important guidelines:
                                                        </br>
                                                        Image size must be 2MB or less,
                                                        </br>
                                                        with a 16:9 aspect ratio for optimal quality.
                                                        </br>
                                                        Accepted formats: JPG, PNG.</p>
                                                </div>
                                                <div class="col-12 d-flex justify-content-end mt-1 mt-md-3">
                                                    <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="uploadCover('cover-input','subject-cover');">
                                                        Select Image
                                                        <iconify-icon icon="ic:sharp-upload" width="20" height="20" class="mx-3"></iconify-icon>
                                                    </button>
                                                    <input type="file" id="cover-input" class="d-none" accept="image/*" />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-5 mt-3 mt-lg-0 d-flex align-items-md-end justify-content-end">
                                            <button class="col-12 col-lg-8 custom-btn m-0" onclick="addClass(this)">Save Class</button>
                                        </div>
                                    </div>
                                    <span class="text-12 text-dark-red" id="cover-feedback"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- class Add Model => End -->

        <!-- class Edit Model => Start -->
        <div class="modal fade br-0" id="editClass" aria-hidden="true" aria-labelledby="editClassLabel" tabindex="-1">
            <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                <div class="modal-content p-4">
                    <div class="modal-header p-0 border-0">
                        <h1 class="modal-title text-16 text-dark-color" id="EditclassLabel">Edit Class</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0 mt-3">
                        <div class="col-12 p-0">
                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                <div class="col-12 col-lg-6 d-flex flex-column">
                                    <label class="text-14 main-text required">Subject</label>

                                    <div class="custom-search-select mt-2" data-input="edit-subject">
                                        <input type="text"
                                            class="form-control custom-input search-input"
                                            placeholder="- Select -"
                                            autocomplete="off">

                                        <input type="hidden" id="edit-subject" value="">

                                        <div class="search-dropdown">
                                            <?php
                                            $subjects = Database::search("SELECT * FROM `subjects`");
                                            while ($subject = $subjects->fetch_assoc()) {
                                            ?>
                                                <div class="search-option"
                                                    data-value="<?= $subject['subject_id'] ?>">
                                                    <?= $subject['subject'] ?>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <span class="text-12 text-dark-red" id="edit-subject-feedback"></span>
                                </div>
                                <div class="col-12 col-lg-6 d-flex flex-column">
                                    <label for="" class="text-14 main-text required">Medium</label>
                                    <select name="" class="form-select custom-select mt-2" id="edit-medium">
                                        <option value="0">- Select -</option>
                                        <?php
                                        $mediums = Database::search("SELECT * FROM `medium`");
                                        while ($medium = $mediums->fetch_assoc()) {
                                        ?>
                                            <option value="<?= $medium['medium_id'] ?>"><?= $medium['medium'] ?></option>
                                        <?php
                                        }
                                        ?>
                                    </select>
                                    <span class="text-12 text-dark-red" id="edit-medium-feedback"></span>
                                </div>
                                <div class="col-12 col-lg-6 d-flex flex-column">
                                    <label for="" class="text-14 main-text required">Year</label>
                                    <select name="" class="form-select custom-select mt-2" id="edit-year">
                                        <option value="0">- Select -</option>
                                        <?php
                                        $years = Database::search("SELECT * FROM `year`");
                                        while ($year = $years->fetch_assoc()) {
                                        ?>
                                            <option value="<?= $year['year_id'] ?>"><?= $year['year'] ?></option>
                                        <?php
                                        }
                                        ?>
                                    </select>
                                    <span class="text-12 text-dark-red" id="edit-year-feedback"></span>
                                </div>
                                <div class="col-12 col-lg-6 d-flex flex-column">
                                    <label for="" class="text-14 main-text required">Exam Type</label>
                                    <select name="" class="form-select custom-select mt-2" id="edit-exam-type">
                                        <option value="0">- Select -</option>
                                        <?php
                                        $exam_type = Database::search("SELECT * FROM `exam_type`");
                                        while ($type = $exam_type->fetch_assoc()) {
                                        ?>
                                            <option value="<?= $type['exam_type_id'] ?>"><?= $type['exam_type'] ?></option>
                                        <?php
                                        }
                                        ?>
                                    </select>
                                    <span class="text-12 text-dark-red" id="edit-exam-type-feedback"></span>
                                </div>

                                <div class="col-12 d-flex flex-column">
                                    <label for="" class="text-14 main-text">Class Name</label>
                                    <input type="text" class="form-control custom-input mt-2" id="edit-class-name">
                                    <span class="text-12 text-dark-red" id="edit-class-name-feedback"></span>
                                </div>
                                <div class="col-12 d-flex flex-column">
                                    <label for="" class="text-14 main-text">Class Thumbnail</label>
                                    <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                            <img src="<?= Project::getAssetsPath() ?>/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="class cover" id="edit-class-cover">
                                        </div>
                                        <div class="col-12 col-lg-4 d-flex mt-0 mt-lg-0">
                                            <div class="d-flex flex-column justify-content-end g-0 p-0 m-0 flex-grow-1">
                                                <div class="col-12">
                                                    <p class="text-14 sub-text g-0 p-0 m-0">Select class cover here.
                                                        </br>
                                                        Important guidelines:
                                                        </br>
                                                        Image size must be 2MB or less,
                                                        </br>
                                                        with a 16:9 aspect ratio for optimal quality.
                                                        </br>
                                                        Accepted formats: JPG, PNG.</p>
                                                </div>
                                                <div class="col-12 d-flex justify-content-end mt-1 mt-md-3">
                                                    <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="uploadCover('edit-cover-input','edit-class-cover');">
                                                        Select Image
                                                        <iconify-icon icon="ic:sharp-upload" width="20" height="20" class="mx-3"></iconify-icon>
                                                    </button>
                                                    <input type="file" id="edit-cover-input" class="d-none" accept="image/*" />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-5 mt-3 mt-lg-0 d-flex align-items-md-end justify-content-end">
                                            <button class="col-12 col-lg-8 custom-btn m-0" id="save-edit">Save Class</button>
                                        </div>
                                    </div>
                                    <span class="text-12 text-dark-red" id="edit-cover-feedback"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- class Edit Model => End -->

        <!-- cover crop modal => Start -->
        <div id="crop-modal" tabindex="-1" aria-hidden="true" class="modal fade" data-backdrop="static">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Crop Image</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center d-flex  align-items-center justify-content-center">
                        <img id="image" class="img-fluid bg-black" style="width: 75%;height: auto;" />
                    </div>
                    <div class="modal-footer">
                        <button id="crop-button" type="button" class="custom-btn px-5 pe-5">Crop</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- cover crop modal => End -->

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