<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsTutor()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['class-id']) && !empty($_GET['class-id'])) {
        $class_id = $_GET["class-id"];
        $time_table_id = 0;
        $query = "SELECT * FROM `classes` WHERE `class_id` = '" . $class_id . "'";
        if (SessionUser::ifUserIsTutor()) {
            $query .= " AND `tutor_id`='" . $sessionUserObject['user_id'] . "'";
        }
        $class = Database::search($query);
        if ($class->num_rows == 0) {
            header("Location: ./classes");
        } else {
            $classData = $class->fetch_assoc();

            if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['time-table-id']) && !empty($_GET['time-table-id'])) {

                $time_table = Database::search("SELECT * FROM `time_table` 
                WHERE `time_table_id` = '" . $_GET['time-table-id'] . "'
                AND `classes_class_id` = '" . $class_id . "'");
                if ($time_table->num_rows > 0) {
                    $time_table_id = $_GET["time-table-id"];
                    $time_table_data =  $time_table->fetch_assoc();
                } else {
                    header("Location: ./add-recording?class-id=" . $class_id);
                }
            }

?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Add Recording - Tutor Dashboard | <?= Project::getBaseName(); ?></title>

                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
            </head>

            <body class="overflow-hidden" onload="initializeAddRecording();">
                <div class="notifications"></div>
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
                                    include "../includes/components/skeleton-header.php";
                                    include "../includes/components/skeleton-add-edit.php"; ?>
                                </div>
                                <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12 col-md-8">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n=""><?= $classData['class_name'] ?></span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="" href="./classes">Classes</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./class-details?class-id=<?= $class_id ?>">Class Details</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Class Recordings</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Add Recording</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 align-content-center">
                                                <div class="col-12 p-0 g-0">
                                                    <div class="row gx-4 gy-3">

                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                <label class="text-16 main-text required mb-0" for="link">Select Date</label>
                                                                <span id="select-date-guide-info"></span>
                                                            </div>
                                                            <input type="date" class="form-control custom-input mt-2" <?= $time_table_id == 0 ? "" : "value='" . $time_table_data['date'] . "' disabled" ?> onchange="filterConductedClasses(this.value);">
                                                            <span id="class-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Select Recorded Class</label>
                                                            <select name="" class="form-select custom-select mt-2" id="class" disabled onchange="setRecordingTitle(this)" <?= $time_table_id == 0 ? "" : "disabled" ?>>
                                                                <option value="0">- Select Class -</option>
                                                                <?php
                                                                if ($time_table_id == 0) {
                                                                    $timeTable = Database::search("SELECT * FROM `time_table` WHERE `classes_class_id` = '" . $class_id . "'");
                                                                    while ($class = $timeTable->fetch_assoc()) {
                                                                ?>
                                                                        <option value="<?= $class['time_table_id'] ?>" data-duration="<?= $class['duration'] ?>" data-title="<?= $class['lecture_title'] ?>" data-date="<?= $class['date'] ?>"><?= $class['lecture_title'] ?></option>
                                                                    <?php
                                                                    }
                                                                } else {
                                                                    ?>
                                                                    <option value="<?= $time_table_id ?>" selected data-duration="<?= $time_table_data['duration'] ?>" data-title="<?= $time_table_data['lecture_title'] ?>" data-date="<?= $time_table_data['date'] ?>"><?= $time_table_data['lecture_title'] ?></option>
                                                                <?php
                                                                }

                                                                ?>
                                                            </select>
                                                            <span id="class-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Recording Title</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="title" <?= $time_table_id == 0 ? "" : "value='" . $time_table_data['lecture_title'] . "- Recording'" ?>>
                                                            <span id="title-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-16 main-text" for="">Recording Description</label>
                                                            <textarea name="link" class="form-control custom-input mt-2" rows="3" id="description"></textarea>
                                                            <span id="description-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                <label class="text-16 main-text required mb-0" for="link">Duration</label>
                                                                <span id="duration-guide-info"></span>
                                                            </div>
                                                            <input type="number" class="form-control custom-input mt-2" id="duration" <?= $time_table_id == 0 ? "" : "value='" . $time_table_data['duration'] . "'" ?>>
                                                            <span id="duration-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                <label class="text-16 main-text not-required mb-0" for="link">Expire Date</label>
                                                                <span id="expire-date-guide-info"></span>
                                                            </div>
                                                            <input type="date" class="form-control custom-input mt-2" id="expire-date">
                                                            <span id="expire-date-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column">
                                                            <label for="" class="text-16 main-text">Recording Thumbnail</label>
                                                            <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                                                <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                                    <img src="<?= Project::getAssetsPath() ?>/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="class cover" id="rec-cover">
                                                                </div>
                                                                <div class="col-12 col-lg-4 d-flex mt-0 mt-lg-0">
                                                                    <div class="d-flex flex-column justify-content-end g-0 p-0 m-0 flex-grow-1">
                                                                        <div class="col-12">
                                                                            <p class="text-14 sub-text g-0 p-0 m-0">Select recording thumbnail here.
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
                                                                            <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="uploadCover('rec-cover-input','rec-cover');">
                                                                                Select Image
                                                                                <iconify-icon icon="ic:sharp-upload" width="20" height="20" class="mx-3"></iconify-icon>
                                                                            </button>
                                                                            <input type="file" id="rec-cover-input" class="d-none" accept="image/*" />
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <span class="text-12 text-dark-red" id="rec-cover-feedback"></span>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column mb-4">
                                                            <label class="text-16 main-text required" for="">Recording Type</label>

                                                            <div class="d-flex align-items-center justify-content-between px-2 mt-4 flex-wrap gap-3">
                                                                <div class="d-flex gap-4 align-items-center">
                                                                    <div class="form-check d-flex align-items-center">
                                                                        <input class="form-check-input text-20 mt-0 mb-0 me-2"
                                                                            type="checkbox"
                                                                            name="lectureType"
                                                                            id="youtube-check-box"
                                                                            checked>

                                                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                            <label class="text-16 main-text mb-0" for="link">Unsecured</label>
                                                                            <span id="unsecured-guide-info"></span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-check option-locked d-flex align-items-center">
                                                                        <input class="form-check-input text-20 mt-0 mb-0 me-2"
                                                                            type="checkbox"
                                                                            name="lectureType"
                                                                            id="video-check-box"
                                                                            disabled>

                                                                        <label class="text-16 sub-text mx-2 d-flex align-items-center gap-2">
                                                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                                <label class="text-16 main-text mb-0" for="link">Secured</label>
                                                                                <span id="secured-guide-info"></span>
                                                                            </div>
                                                                            <span class="pro-badge">
                                                                                <iconify-icon icon="solar:crown-bold-duotone" width="14"></iconify-icon>
                                                                                PRO
                                                                            </span>
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column mb-4" id="youtube-link-area">
                                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                <label class="text-16 main-text required mb-0" for="link">YouTube Iframe Link</label>
                                                                <span id="youtube-guide-info"></span>
                                                            </div>
                                                            <textarea name="link" class="form-control custom-input mt-2" rows="3" id="link"></textarea>
                                                            <span id="link-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column d-none" id="video-area">
                                                            <label class="text-16 main-text required">Recording Video</label>
                                                            <div class="p-5 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="video-drop-zone">
                                                                <img src="../assets/imgs/icons/cloud.png" alt="cloud">
                                                                <span>Drag & Drop your files here</span>
                                                                <span>OR</span>
                                                                <button class="custom-btn px-4" id="video-browse-btn">Select Files</button>
                                                                <input type="file" class="d-none" id="video-input-field" accept=".mp4">
                                                                <div class="col-12 px-1 mt-4">
                                                                    <div class="row" id="video-preview-list">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <span id="attachment-feedback" class="text-dark-red text-12"></span>
                                                            <!-- File upload input field for PDF, Word, and PowerPoint files -->
                                                        </div>

                                                    </div>
                                                </div>

                                                <div class="col-12 p-0 g-0 mt-3">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 col-md-12 d-flex flex-column align-items-end">
                                                            <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                <button class="custom-btn w-100" onclick="addRecording(this,<?= $class_id ?>);">Save Recording</button>
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
                            <div class="modal-body d-flex justify-content-center">
                                <img id="image" class="img-fluid" />
                            </div>
                            <div class="modal-footer">
                                <button id="crop-button" type="button" class="custom-btn px-5 pe-5">Crop</button>
                            </div>
                        </div>
                    </div>
                </div>

                <style>
                    .upload-container {
                        width: 100%;
                        margin-top: 15px;
                    }

                    .progress-wrapper {
                        width: 100%;
                        height: 14px;
                        background: #e9ecef;
                        border-radius: 50px;
                        overflow: hidden;
                    }

                    .progress-bar {
                        height: 100%;
                        width: 0%;
                        border-radius: 50px;
                        background: linear-gradient(90deg,
                                #4facfe,
                                #00f2fe,
                                #4facfe);
                        background-size: 200% 100%;
                        animation: shimmer 2s infinite linear;
                        transition: width 0.3s ease-in-out;
                    }

                    .progress-text {
                        margin-top: 8px;
                        font-weight: 600;
                        text-align: right;
                    }

                    @keyframes shimmer {
                        0% {
                            background-position: 200% 0;
                        }

                        100% {
                            background-position: -200% 0;
                        }
                    }
                </style>

                <div id="upload-container" class="upload-container d-none">
                    <div class="progress-wrapper">
                        <div id="upload-progress-bar" class="progress-bar"></div>
                    </div>
                    <div id="upload-progress-text" class="progress-text">0%</div>
                </div>
                <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="js/createInfo.js?v=<?= Project::getVersion() ?>"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/videoFileChooser.js?v=<?= Project::getVersion() ?>"></script>


            </body>

            </html>
<?php

        }
    }
}
?>