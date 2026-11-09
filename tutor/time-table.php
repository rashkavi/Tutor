<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {

    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Time Table - Admin Dashboard | <?= Project::getBaseName() ?></title>

        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/calender.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
        <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">

        <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
    </head>

    <body class="overflow-hidden" onload="initializeLecturerSchedule();">
        <div class="notifications"></div>
        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
            <div id="appContainer" class="row p-0">
                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                    <?php
                    include "includes/side-menu.php";
                    load_side_menu::load("Time Table");
                    ?>
                </div>
                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                    <div class="row p-0 m-0 g-0">
                        <div class="col-12 p-0 m-0 g-0">
                            <?php
                            include "includes/header.php";
                            selectItem::setSelected("Time Table");
                            ?>
                        </div>
                        <div class="container p-4 m-0" id="skeleton-list">
                            <?php
                            include '../includes/components/skeleton-header.php';
                            include '../includes/components/skeleton-calender.php'; ?>
                        </div>
                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                            <div class="row p-4 m-0 g-0">
                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                    <div class="row align-items-center p-0 m-0 g-0">
                                        <div class="col-12 col-lg-8">
                                            <div class="row">
                                                <span class="main-topic lh-lg" data-i18n="class-students">Time Table</span>
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
                                                    <button class="custom-btn w-100" data-i18n="schedule-class" onclick="window.location = 'schedule-class'">Schedule a Class</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 p-4 component-theme">
                                    <div class="row p-0 m-0 gy-4 gx-3">
                                        <div class="col-12">
                                            <div class="col-12 d-flex flex-column flex-lg-row justify-content-center mb-4 mt-2">
                                                <div class="col-2 col-lg-1 d-none d-lg-block">
                                                    <button class="w-100 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="prevMonthSchedule()">
                                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                        <span class="text-14 main-text fw-semibold">Previous</span>
                                                    </button>
                                                </div>
                                                <div class="col-12 col-lg-10 d-flex justify-content-center align-items-center gap-2">
                                                    <h4 class="text-center" data-i18n="lecture-schedule">Time Table</h4>
                                                    <h4 class="text-center">-</h4>
                                                    <h4 class="text-center" id="month"></h4>
                                                    <h4 class="text-center" id="year"></h4>
                                                </div>
                                                <div class="col-12 col-lg-1 mt-3 d-block d-lg-none">
                                                    <div class="row">
                                                        <div class="col-6 d-flex justify-content-start">
                                                            <button class="py-2 px-3 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="prevMonthSchedule()">
                                                                <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                                <span class="text-14 main-text fw-semibold">Previous</span>
                                                            </button>
                                                        </div>
                                                        <div class="col-6 d-flex justify-content-end">
                                                            <button class="py-2 px-3 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="nextMonthSchedule()">
                                                                <span class="text-14 main-text fw-semibold">Next</span>
                                                                <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-lg-1 text-end d-none d-lg-block">
                                                    <button class="w-100 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="nextMonthSchedule()">
                                                        <span class="text-14 main-text fw-semibold">Next</span>
                                                        <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="dark-color px-1"></iconify-icon>
                                                    </button>
                                                </div>
                                            </div>

                                            <div id="calendar" class="calendar"></div>
                                        </div>

                                        <!-- Event Modal -->
                                        <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="eventModalLabel">Events on <span id="selectedDate"></span></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body z-1" id="eventDetails"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="modal fade" id="lectureDetailsModal" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-start-0 modal-lg">
                                                <div class="modal-content lecture-modal-content">
                                                    <div class="modal-header lecture-modal-header">
                                                        <h5 class="modal-title" id="lectureDetailsTitle">Lecture Details</h5>
                                                        <button type="button" class="btn-close text-dark-color" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body" id="lectureDetailsBody"></div>
                                                    <div class="modal-footer" id="lectureDetailsFooter">

                                                    </div>
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

        <!-- faculty Add Model => Start -->
        <div class="modal fade br-0" id="addSubject" aria-hidden="true" aria-labelledby="addSubjectLabel" tabindex="-1">
            <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                <div class="modal-content p-4">
                    <div class="modal-header p-0 border-0">
                        <h1 class="modal-title text-16 text-dark-color" id="addFacultyLabel">Add Class</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0 mt-3">
                        <div class="col-12 p-0">
                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                <div class="col-12 col-lg-6 d-flex flex-column">
                                    <label for="" class="text-14 main-text required">Subject</label>
                                    <select name="" class="form-select custom-select mt-2" id="subject">
                                        <option value="0">- Select -</option>
                                        <?php
                                        $subjects = Database::search("SELECT * FROM `subjects`");
                                        while ($subject = $subjects->fetch_assoc()) {
                                        ?>
                                            <option value="<?= $subject['subject_id'] ?>"><?= $subject['subject'] ?></option>
                                        <?php
                                        }
                                        ?>
                                    </select>
                                    <span class="text-12 text-dark-red" id="subject-feedback"></span>
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
                                <div class="col-12 d-flex flex-column">
                                    <label for="" class="text-14 main-text required">Class Name</label>
                                    <input type="text" class="form-control custom-input mt-2" id="class-name">
                                    <span class="text-12 text-dark-red" id="class-name-feedback"></span>
                                </div>
                                <div class="col-12 d-flex flex-column">
                                    <label for="" class="text-14 main-text required">Class Cover</label>
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
                                                    <!-- Hidden file input -->
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
        <!-- faculty Add Model => End -->

        <!-- faculty Edit Modal => Start -->
        <div class="modal fade br-0" id="editSubject" aria-hidden="true" aria-labelledby="editSubjectLabel" tabindex="-1">
            <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                <div class="modal-content p-4">
                    <div class="modal-header p-0 border-0">
                        <h1 class="modal-title text-16 text-dark-color required" id="editSubjectLabel">Edit Subject</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0 mt-3">
                        <div class="col-12 p-0">
                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                <div class="col-12 d-flex flex-column">
                                    <label for="" class="text-14 main-text required">Subject Name</label>
                                    <input type="text" class="form-control custom-input mt-2" value="Portal Payment" id="edit-subject-name">
                                    <span class="text-12 text-dark-red" id="es-name-feedback"></span>
                                </div>
                                <div class="col-12 d-flex flex-column">
                                    <label for="" class="text-14 main-text">Subject Cover</label>
                                    <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                            <img src="<?= Project::getAssetsPath() ?>/imgs/courses-thumbnails/image.png" class="w-100 h-auto" alt="edit-subject-cover" id="edit-subject-cover">
                                        </div>
                                        <div class="col-12 col-lg-4 d-flex mt-3 mt-lg-0">
                                            <div class="d-flex flex-column justify-content-end g-0 p-0 m-0 flex-grow-1">
                                                <div class="col-12">
                                                    <p class="text-14 sub-text g-0 p-0 m-0">Select subject cover here.</p>
                                                    <p class="text-14 sub-topic g-0 p-0 m-0">Important guidelines:</p>
                                                    <p class="text-14 sub-text g-0 p-0 m-0">Image size must be 2MB or less, with a 16:9 aspect ratio for optimal quality. Accepted formats: JPG, PNG.</p>

                                                </div>
                                                <div class="col-12 d-flex justify-content-end mt-3">
                                                    <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="uploadCover('edit-cover-input','edit-subject-cover');">
                                                        Select another Image
                                                        <iconify-icon icon="ic:sharp-upload" width="20" height="20" class="mx-3"></iconify-icon>
                                                        <!-- Hidden file input -->
                                                        <input type="file" id="edit-cover-input" class="d-none" accept="image/*" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-5 mt-3 mt-lg-0 d-flex align-items-md-end justify-content-end">
                                            <button class="col-12 col-lg-8 custom-btn m-0" id="save-edit">Save Changes</button>
                                        </div>
                                    </div>
                                    <span class="text-12 text-dark-red" id="es-cover-feedback"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Payment Edit Modal => End -->

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
        <script src="<?= Project::getAssetsPath() ?>/js/functions.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/eventCalender.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/fileChooser.js?v=<?= Project::getVersion() ?>"></script>
    </body>

    </html>
<?php

}
?>