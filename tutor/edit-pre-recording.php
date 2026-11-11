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

    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['recording-id']) && !empty($_GET['recording-id'])) {

        $recording_id = (int) $_GET["recording-id"]; // int cast: used in SQL below

        $query = "SELECT *,`integrations_integration_id` AS 'bunny_lib' FROM `pre_recordings`
        INNER JOIN `classes` ON `pre_recordings`.`classes_class_id` = `classes`.`class_id` 
        WHERE `pre_recording_id` = '" . $recording_id . "' AND `classes`.`tutor_id`='" . $sessionUserObject['user_id'] . "'";

        $recording = Database::search($query);
        if ($recording->num_rows == 0) {
            header("Location: ./class-pre-recordings");
        } else {
            $recordingData = $recording->fetch_assoc();

?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Edit Pre Recording - Tutor Dashboard | <?= Project::getBaseName(); ?></title>

                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/info.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/enlixo-ui.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/enlixo-recordings.css?v=<?= Project::getVersion() ?>">
</head>

            <body class="overflow-hidden" onload="initializeAddPreRecording();">
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
                                                        <span class="main-topic lh-lg" data-i18n=""><?= $recordingData['class_name'] ?></span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="" href="./classes">Classes</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./class-details?class-id=<?= $recordingData['class_id'] ?>">Class Details</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Pre Recorded Classes</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Edit Pre Recorded Class</a></li>
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

                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Recording Title</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="title" value="<?= $recordingData['title'] ?>">
                                                            <span id="title-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-16 main-text" for="">Recording Description</label>
                                                            <textarea name="link" class="form-control custom-input mt-2" rows="3" id="description"><?= $recordingData['description'] ?></textarea>
                                                            <span id="description-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                <label class="text-16 main-text required mb-0" for="link">Duration</label>
                                                                <span id="duration-guide-info"></span>
                                                            </div>
                                                            <input type="number" class="form-control custom-input mt-2" id="duration" value="<?= $recordingData['duration'] ?>">
                                                            <span id="duration-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column">
                                                            <label for="" class="text-16 main-text">Recording Thumbnail</label>
                                                            <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                                                <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                                    <?php
                                                                    if (!empty($recordingData['recording_cover']) && file_exists(Project::getAssetsPath() . '/imgs/pre-recording-thumbnail/' . $recordingData['recording_cover'])) {
                                                                    ?>
                                                                        <img src="<?= Project::getAssetsPath() ?>/imgs/pre-recording-thumbnail/<?= $recordingData['recording_cover'] ?>" class="w-100 h-auto" alt="class cover" id="rec-cover">
                                                                    <?php
                                                                    } else {
                                                                    ?>
                                                                        <img src="<?= Project::getAssetsPath() ?>/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="class cover" id="rec-cover">
                                                                    <?php
                                                                    }
                                                                    ?>
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
                                                        <!-- Where the video is: Settings -> Recording Types (assets/js/enlixo/pages/app-tutor-pre-recording-link.js) -->
                                                        <div class="col-12" id="rec-link-field" data-type="<?= (int) ($recordingData['tutor_recording_types_id'] ?? 0) ?>"
                                                            data-link="<?= htmlspecialchars((string) ($recordingData['recording_url'] ?? ''), ENT_QUOTES) ?>"
                                                            data-old-link="<?= htmlspecialchars(empty($recordingData['recording_url']) && empty($recordingData['bunny_lib']) ? (string) ($recordingData['recording_link'] ?? '') : '', ENT_QUOTES) ?>"
                                                            data-passcode="<?= htmlspecialchars((string) ($recordingData['access_passcode'] ?? ''), ENT_QUOTES) ?>"
                                                            data-unlock="<?= !empty($recordingData['unlock_at']) ? date('Y-m-d\TH:i', strtotime($recordingData['unlock_at'])) : '' ?>"
                                                            data-upload="<?= !empty($recordingData['bunny_lib']) && empty($recordingData['tutor_recording_types_id']) ? '1' : '0' ?>">
                                                            <div class="sub-text text-14">Loading recording types…</div>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-16 main-text" for="">Recording Attachments</label>
                                                            <div class="col-12 px-1 mt-2">
                                                                <div class="row" id="lectures-attachments">
                                                                    <?php
                                                                    $attachment = Database::search("SELECT * FROM `pre_recordings_attachments` WHERE `pre_recordings_pre_recording_id` = '" . $recording_id . "'");
                                                                    if ($attachment->num_rows > 0) {
                                                                        while ($attachmentData = $attachment->fetch_assoc()) {
                                                                            if (!empty($attachmentData["attachment"]) && file_exists(Project::getAssetsPath() . "/uploads/recordings-attachments/" . $attachmentData["attachment"])) {
                                                                    ?>
                                                                                <div class="col-12 col-lg-4 file-item cursor-pointer" data-attachment-id="<?= $attachmentData['pre_recordings_attachment_id'] ?>">
                                                                                    <div class="bl-solid">
                                                                                        <div class="row">
                                                                                            <div class="col-10" onclick="viewLectureAttachment('<?php echo $attachmentData['attachment'] ?>', '<?php echo $attachmentData['attachment_name'] ?>')">
                                                                                                <div class="row p-3">
                                                                                                    <div class="col-3">
                                                                                                        <img src="../assets/imgs/icons/pdf.png" alt="">
                                                                                                    </div>
                                                                                                    <div class="col-8">
                                                                                                        <div class="file-name">
                                                                                                            <div class="name"><?php echo $attachmentData["attachment_name"] ?></div>
                                                                                                        </div>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>

                                                                                            <div class="col-2">
                                                                                                <!-- Container -->
                                                                                                <div class="d-flex align-items-center justify-content-center p-0 m-0 table-btn-danger h-100 w-100"
                                                                                                    onclick="removeRecordingAttachment(<?= $attachmentData['pre_recordings_attachment_id'] ?>)">
                                                                                                    <iconify-icon icon="pajamas:remove-all" width="20" height="20"
                                                                                                        class="text-danger p-0 m-0 text-center">
                                                                                                    </iconify-icon>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                    <?php
                                                                            }
                                                                        }
                                                                    }

                                                                    ?>
                                                                </div>
                                                            </div>
                                                            <div class="p-5 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                                <img src="../assets/imgs/icons/cloud.png" alt="cloud">
                                                                <span>Drag & Drop your files here</span>
                                                                <span>OR</span>
                                                                <button class="custom-btn px-4" id="file-selector">Select Files</button>
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

                                                <div class="col-12 p-0 g-0 mt-3">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 col-md-12 d-flex flex-column align-items-end">
                                                            <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                <button class="custom-btn w-100" onclick="editPreRecording(this,<?= $recording_id ?>);">Save Changes</button>
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
                <script src="<?= Project::getAssetsPath() ?>/js/fileChooser.js?v=<?= Project::getVersion() ?>"></script>
                <script type="module" src="<?= Project::getAssetsPath() ?>/js/enlixo/pages/app-tutor-pre-recording-link.js?v=<?= Project::getVersion() ?>"></script>
            </body>

            </html>
<?php


        }
    }
}
?>