<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {

    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

    $class_id = 0;

    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['class-id']) && !empty($_GET['class-id'])) {

        $class_id = $_GET["class-id"];

        $query = "SELECT * FROM `classes` WHERE `class_id` = '" . $class_id . "'";
        if (SessionUser::ifUserIsTutor()) {
            $query .= " AND `tutor_id`='" . $sessionUserObject['user_id'] . "'";
        }
        $class = Database::search($query);
        if ($class->num_rows == 0) {
            header("Location: ./classes");
        } else {
            $classData = $class->fetch_assoc();
        }
    }

    $selectedDate = "";
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['date']) && !empty($_GET['date'])) {
        $selectedDate = $_GET["date"];
    }
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Schedule a Class - Admin Dashboard | <?= Project::getBaseName(); ?></title>

        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
        <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">

        <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
    </head>

    <body class="overflow-hidden" onload="initializeScheduleLecture();">
        <div class="notifications"></div>
        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
            <div id="appContainer" class="row p-0">
                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                    <?php
                    include "includes/side-menu.php";
                    $class_id == 0 ? load_side_menu::load("Time Table") :  load_side_menu::load("Classes");
                    ?>
                </div>
                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                    <div class="row p-0 m-0 g-0">
                        <div class="col-12 p-0 m-0 g-0">
                            <?php
                            include "includes/header.php";
                            $class_id == 0 ? selectItem::setSelected("Time Table") : selectItem::setSelected("Classes");
                            ?>
                        </div>
                        <div class="container p-4 m-0" id="skeleton-list">
                            <?php
                            include "../includes/components/skeleton-header.php";
                            include "../includes/components/skeleton-add-edit.php"; ?>
                        </div>
                        <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                            <div class="row p-4 m-0 g-0">
                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 col-md-8">
                                            <div class="row">
                                                <?php
                                                if ($class_id == 0) {
                                                ?>
                                                    <span class="main-topic lh-lg" data-i18n="">Schedule a Class</span>
                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                        <ol class="breadcrumb m-0">
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="time-table" href="./time-table">Time Table</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="">Schedule a Class</a></li>
                                                        </ol>
                                                    </nav>
                                                <?php
                                                } else {
                                                ?>
                                                    <span class="main-topic lh-lg" data-i18n=""><?= $classData['class_name'] ?></span>
                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                        <ol class="breadcrumb m-0">
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="" href="./classes">Classes</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="">Class Time Table</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="">Schedule a Class</a></li>
                                                        </ol>
                                                    </nav>
                                                <?php
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 p-4 component-theme">
                                    <div class="row p-0 m-0 align-content-center">
                                        <div class="col-12 p-0 g-0">
                                            <div class="row gx-4 gy-3">

                                                <div class="col-12 col-md-6 d-flex flex-column <?= $class_id != 0 ? 'd-none' : '' ?>">
                                                    <label class="text-16 main-text required" for="">Select Class</label>
                                                    <select name="" id="class-selector" class="form-select custom-select mt-2">
                                                        <option value="0" selected>- Select -</option>
                                                        <?php
                                                        $query = "SELECT * FROM `classes`";

                                                        if (SessionUser::ifUserIsTutor()) {
                                                            $query .= " WHERE `tutor_id`='" . $sessionUserObject['user_id'] . "'";
                                                        }

                                                        $classes = Database::search($query);
                                                        if ($classes->num_rows > 0) {
                                                            while ($class = $classes->fetch_assoc()) {
                                                        ?>
                                                                <option value="<?= $class['class_id'] ?>" <?= $class_id == $class['class_id'] ? 'selected' : '' ?>><?= $class['class_name'] ?></option>
                                                        <?php
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                    <span id="class-feedback" class="text-dark-red text-12"></span>
                                                </div>

                                                <div class="col-12 mt-3">
                                                    <label class="text-16 fw-semibold main-text mb-2 d-block required">
                                                        Lecture Schedule Type
                                                    </label>

                                                    <div class="d-flex gap-4 px-2 mt-4">
                                                        <div class="form-check">
                                                            <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" name="lectureType" id="oneTimeLecture" checked>
                                                            <label class="form-check-label" for="oneTimeLecture">
                                                                One-Time Lecture
                                                            </label>
                                                        </div>

                                                        <div class="form-check d-flex align-items-center">
                                                            <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" name="lectureType" id="recurringLecture">

                                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                <label class="form-check-label" for="recurringLecture">
                                                                    Recurring Lecture
                                                                </label>
                                                                <span id="add-recurring-lecture-guide-info"></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="container mt-4 d-none" id="recurringLectureArea">
                                                    <div class="card shadow-none p-0 border-0">
                                                        <div class="card-body p-0">
                                                            <!-- Date Range -->
                                                            <div class="row g-3 mb-4">
                                                                <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                    <label class="text-16 main-text required" for="">Lecture Start Date</label>
                                                                    <input type="date" class="form-control custom-input mt-2" id="start-date">
                                                                    <span id="start-date-feedback" class="text-dark-red text-12"></span>
                                                                </div>

                                                                <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                    <label class="text-16 main-text required" for="">Lecture End Date</label>
                                                                    <input type="date" class="form-control custom-input mt-2" id="end-date">
                                                                    <span id="end-date-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                    <label class="text-16 main-text required" for="">Lecture Start Time</label>
                                                                    <input type="time" class="form-control custom-input mt-2" id="r-start-time">
                                                                    <span id="r-start-time-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                    <label class="text-16 main-text required" for="">Lecture End Time</label>
                                                                    <input type="time" class="form-control custom-input mt-2" id="r-end-time">
                                                                    <span id="r-end-time-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                            </div>
                                                            <!-- Recurring Days -->
                                                            <div class="mb-4">
                                                                <label class="form-label fw-medium required">Recurring Days</label>
                                                                <div class="d-flex flex-wrap gap-2 mt-1">
                                                                    <?php
                                                                    $data = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

                                                                    for ($i = 0; $i < count($data); $i++) {
                                                                    ?>
                                                                        <div class="chip align-content-center" data-day-id="<?= $i + 1 ?>" onclick="dateChipBoxAction(this);">
                                                                            <span class="chip-label"><?php echo $data[$i]; ?></span>
                                                                            <span class="chip-check-icon">
                                                                                <iconify-icon icon="mdi:check"></iconify-icon>
                                                                            </span>
                                                                        </div>
                                                                    <?php
                                                                    }
                                                                    ?>

                                                                </div>
                                                                <span id="days-feedback" class="text-dark-red text-12"></span>
                                                            </div>

                                                            <!-- Skip Options -->
                                                            <div class="mb-3">
                                                                <label class="form-label fw-medium">Skip Lectures On</label>
                                                                <div class="mt-2">
                                                                    <div class="form-check mb-2">
                                                                        <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" id="skip-poya">
                                                                        <label class="form-check-label" for="skip-poya">
                                                                            Skip Poya Days (Sri Lanka public holidays)
                                                                        </label>
                                                                    </div>
                                                                    <div class="form-check mb-2">
                                                                        <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" id="skip-holidays">
                                                                        <label class="form-check-label" for="skip-holidays">
                                                                            Skip Special / Public Holidays
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Custom Skip Dates -->
                                                            <div class="mt-3 d-none" id="custom-skip-dates">
                                                                <label class="form-label fw-medium">Select Custom Skip Dates</label>
                                                                <input type="date" class="form-control mb-2">
                                                                <input type="date" class="form-control">
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                        <div class="col-12 p-0 g-0 mt-4" id="onetimeLectureArea">
                                            <div class="row gx-4 gy-3">
                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                    <label class="text-16 main-text required" for="">Date</label>
                                                    <input type="date" class="form-control custom-input mt-2" id="lecture-date" value="<?= $selectedDate ?>">
                                                    <span id="date-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                    <label class="text-16 main-text required" for="">Pick Start Time</label>
                                                    <input type="time" class="form-control custom-input mt-2" id="start-time">
                                                    <span id="start-time-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                    <label class="text-16 main-text required" for="">Pick End Time</label>
                                                    <input type="time" class="form-control custom-input mt-2" id="end-time">
                                                    <span id="end-time-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                    <label class="text-16 main-text required" for="">Lecture Title</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="title">
                                                    <span id="title-feedback" class="text-dark-red text-12"></span>
                                                </div>
                                                <div class="col-12 d-flex flex-column">
                                                    <label class="text-16 main-text" for="">Lecture Attachment</label>
                                                    <div class="p-5 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                        <img src="../assets/imgs/icons/cloud.png" alt="cloud">
                                                        <span>Drag & Drop your files here</span>
                                                        <span>OR</span>
                                                        <button class="custom-btn px-4" id="file-selector">Browse Files</button>
                                                        <input type="file" class="d-none" id="file-selector-input" multiple accept=".pdf">
                                                        <div class="col-12 px-1 mt-4">
                                                            <div class="row" id="list-section">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <span id="attachment-feedback" class="text-dark-red text-12"></span>
                                                    <!-- File upload input field for PDF, Word, and PowerPoint files -->
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 p-0 g-0 mt-3" id="lectureTypeArea">
                                            <div class="row gx-4 gy-3">
                                                <div class="col-12 d-flex flex-column mb-4">
                                                    <label class="text-16 main-text required" for="">Lecture Type</label>
                                                    <div class="d-flex gap-4 px-2 mt-4">

                                                        <div class="form-check">
                                                            <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" name="lectureType" id="physical-check-box" checked>
                                                            <label class="text-16 sub-text mx-2">Physical</label>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" name="lectureType" id="online-check-box">
                                                            <label class="text-16 sub-text mx-2">Online</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php
                                                $availableZoomIntergration = Database::search("SELECT * FROM `integrations` 
                                                INNER JOIN `integration_connections` ON `integration_connections`.`integration_id` = `integrations`.`integration_id`
                                                WHERE `users_user_id`='" . $sessionUserObject["user_id"] . "' AND `integration_type_integration_type_id`='1'");

                                                if ($availableZoomIntergration->num_rows == 0) {
                                                ?>
                                                    <div class="col-12 d-flex flex-column mb-4 d-none" id="lecture-link-area">
                                                        <label class="text-16 main-text" for="">Lecture Link</label>
                                                        <input type="text" class="form-control custom-input mt-2" id="link-input">
                                                        <span id="link-feedback" class="text-dark-red text-12"></span>
                                                    </div>
                                                <?php
                                                }
                                                ?>

                                            </div>
                                        </div>
                                        <div class="col-12 p-0 g-0">
                                            <div class="row gx-4 gy-3">
                                                <div class="col-12 col-md-12 d-flex flex-column align-items-end">
                                                    <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                        <button class="custom-btn w-100" onclick="scheduleLectureToClass(this);">Save Lecture</button>
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

        <div id="crop-modal" tabindex="-1" aria-hidden="true" class="modal fade" data-backdrop="static">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Crop Image</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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

        <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/createInfo.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/fileChooser.js?v=<?= Project::getVersion() ?>"></script>
    </body>

    </html>
<?php



}
?>