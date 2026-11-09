<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
require_once "../tutor/includes/security-helpers.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {

    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
    if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['time-table-id']) && !empty($_GET['time-table-id']))) {
        header("Location: ./lecture-schedule");
    } else {

        $lecture_id = $_GET['time-table-id'];
        $subjectId = 0;

        $query = "SELECT * FROM `time_table`
            INNER JOIN `classes` ON `time_table`.`classes_class_id` = `classes`.`class_id`
            INNER JOIN `users` ON `classes`.`tutor_id` = `users`.`user_id`
            INNER JOIN `lecture_type` ON `time_table`.`lecture_type_lecture_type_id` = `lecture_type`.`lecture_type_id`
            INNER JOIN `time_table_status` ON `time_table`.`time_table_status_time_table_status_id` = `time_table_status`.`time_table_status_id`
            WHERE `time_table`.`time_table_id`='" . $lecture_id . "'";

        if (SessionUser::ifUserIsTutor()) {
            $query .= " AND `classes`.`tutor_id`='" . $sessionUserObject['user_id'] . "'";
        }

        if (isset($_GET['class-id']) && !empty($_GET['class-id'])) {
            $subjectId = $_GET['class-id'];
            $query .= " AND `classes`.`class_id`='" . $subjectId . "'";
        }

        $lecture = Database::search($query);
        if ($lecture->num_rows == 0) {
            header("Location: ./lecture-schedule");
        } else {
            $lectureData = $lecture->fetch_assoc();

            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

            $timezone = new DateTimeZone("Asia/Colombo");
            $today = new DateTime("now", $timezone);
            $todayDate = $today->format('Y-m-d');
            $nowTime = $today->format('H:i:s');
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Edit Lecture - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

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
                            if (isset($_GET['class-id']) && !empty($_GET['class-id'])) {
                                load_side_menu::load("Classes");
                            } else {
                                load_side_menu::load("Time Table");
                            }
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "includes/header.php";
                                    if (isset($_GET['class-id']) && !empty($_GET['class-id'])) {
                                        selectItem::setSelected("Classes");
                                    } else {
                                        selectItem::setSelected("Time Table");
                                    }
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
                                                <?php
                                                if ($subjectId == 0) {
                                                ?>
                                                    <span class="main-topic lh-lg">Edit Lecture</span>
                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                        <ol class="breadcrumb m-0">
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./lecture-schedule">Lecture</a></li>
                                                            <li class="breadcrumb-item sub-text text-decoration-none">Edit Lecture</li>
                                                        </ol>
                                                    </nav>
                                                <?php
                                                } else {
                                                ?>
                                                 <span class="main-topic lh-lg"><?= $lectureData['class_name'] ?></span>
                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                        <ol class="breadcrumb m-0">
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./classes">Classes</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none">...</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="">Lecture</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="">Edit Lecture</a></li>
                                                        </ol>
                                                    </nav>
                                                <?php
                                                }
                                                ?>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme" id="real-list">
                                            <div class="row p-0 m-0 align-content-center">
                                                <div class="col-12 p-0 g-0">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Title</label>
                                                            <input type="text" class="form-control custom-input mt-2" value="<?php echo escape($lectureData["lecture_title"]); ?>" id="title">
                                                            <span id="title-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Date</label>
                                                            <input type="date" class="form-control custom-input mt-2" value="<?php echo escape($lectureData["date"]); ?>" id="lecture-date">
                                                            <span id="date-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Pick Start Time</label>
                                                            <input type="time" class="form-control custom-input mt-2" value="<?php echo escape($lectureData["start_time"]); ?>" id="start-time">
                                                            <span id="start-time-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Pick End Time</label>
                                                            <input type="time" class="form-control custom-input mt-2" value="<?php echo escape($lectureData["end_time"]); ?>" id="end-time">
                                                            <span id="end-time-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Lecturer Status</label>
                                                            <select name="" id="status-selector" class="form-select custom-select mt-2">
                                                                <?php
                                                                $lectureStatus = Database::search("SELECT * FROM `time_table_status`");
                                                                while ($status = $lectureStatus->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?= $status['time_table_status_id'] ?>" <?= $lectureData["time_table_status_time_table_status_id"] == $status['time_table_status_id'] ? 'selected' : '' ?>><?= $status['time_table_status'] ?></option>
                                                                <?php
                                                                }
                                                                ?>

                                                            </select>
                                                            <span id="lecturer-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-14 main-text" for="">Lecture Attachment</label>
                                                            <div class="col-12 px-1 mt-2">
                                                                <div class="row" id="lectures-attachments">
                                                                    <?php
                                                                    $attachment = Database::search("SELECT * FROM `attachments` WHERE `time_table_time_table_id` = '" . $lecture_id . "'");
                                                                    if ($attachment->num_rows > 0) {
                                                                        while ($attachmentData = $attachment->fetch_assoc()) {
                                                                            if (!empty($attachmentData["attachment"]) && file_exists(Project::getAssetsPath() . "/uploads/lectures-attachments/" . $attachmentData["attachment"])) {
                                                                    ?>
                                                                                <div class="col-12 col-lg-4 file-item cursor-pointer" data-attachment-id="<?= $attachmentData['attachment_id'] ?>">
                                                                                    <div class="bl-solid">
                                                                                        <div class="row">
                                                                                            <div class="col-10" onclick="viewLectureAttachment('<?php echo escape($attachmentData['attachment'], 'js'); ?>', '<?php echo escape($attachmentData['attachment_name'], 'js'); ?>')">
                                                                                                <div class="row p-3">
                                                                                                    <div class="col-3">
                                                                                                        <img src="../assets/imgs/icons/pdf.png" alt="">
                                                                                                    </div>
                                                                                                    <div class="col-8">
                                                                                                        <div class="file-name">
                                                                                                            <div class="name"><?php echo escape($attachmentData["attachment_name"]); ?></div>
                                                                                                        </div>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>

                                                                                            <div class="col-2">
                                                                                                <!-- Container -->
                                                                                                <div class="d-flex align-items-center justify-content-center p-0 m-0 table-btn-danger h-100 w-100"
                                                                                                    onclick="removeLectureAttachment(<?= $attachmentData['attachment_id'] ?>)">
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


                                                        <div class="col-12 col-md-6 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Lecture Type</label>
                                                            <div class="col-12 d-flex flex-wrap mt-3">
                                                                <div class="col-6 col-md-3 d-flex flex-column">
                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                        <input type="checkbox"
                                                                            id="physical-checkbox"
                                                                            name="lecture_type[]"
                                                                            value="physical"
                                                                            class="form-check-input text-20 mt-0 mb-0 me-2 lecture-type-checkbox"
                                                                            <?= $lectureData['lecture_type_lecture_type_id'] == "1" ? "checked" : '' ?>
                                                                            data-dependent-field="lecture-link-darea"
                                                                            data-dependent-action="hide">

                                                                        <label class="text-14 sub-text mx-2">Physical</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-6 col-md-3 d-flex flex-column">
                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                        <input type="checkbox"
                                                                            id="online-checkbox"
                                                                            name="lecture_type[]"
                                                                            value="online"
                                                                            <?= $lectureData['lecture_type_lecture_type_id'] == "2" ? "checked" : '' ?>
                                                                            class="form-check-input text-20 mt-0 mb-0 me-2 lecture-type-checkbox"
                                                                            data-dependent-field="lecture-link-darea"
                                                                            data-dependent-action="show">
                                                                        <label class="text-14 sub-text mx-2">Online</label>
                                                                    </div>
                                                                </div>

                                                            </div>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column <?php echo $lectureData["lecture_type_lecture_type_id"] != "1" ? "" : "d-none"; ?>" id="lecture-link-darea">
                                                            <label class="text-14 main-text" for="">Lecture Link</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="link-input" value="<?php echo escape($lectureData["lecture"]); ?>">
                                                            <span id="link-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-12 d-flex flex-column align-items-end">
                                                            <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                <button class="custom-btn w-100" onclick="editLecture(this,<?php echo $lecture_id ?>);">Save Changes</button>
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

                <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/fileChooser.js"></script>
            </body>

            </html>
    <?php                }
    }
    ?>
<?php
}
?>