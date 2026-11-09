<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsTutor()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
    if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['class-id']) && !empty($_GET['class-id']))) {
        header("Location: ./class");
    } else {
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
                    header("Location: ./class-recordings?class-id=" . $class_id);
                }
            }

            function formatDuration(int $minutes): string
            {
                if ($minutes <= 0) {
                    return '0m';
                }

                $hours = intdiv($minutes, 60);
                $remainingMinutes = $minutes % 60;

                if ($hours > 0 && $remainingMinutes > 0) {
                    return "{$hours}h {$remainingMinutes}m";
                }

                if ($hours > 0) {
                    return "{$hours}h";
                }

                return "{$remainingMinutes}m";
            }
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Pre-Recorded Classes - Tutor Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/intergration-card.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
            </head>

            <body class="overflow-hidden">
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
                                    include '../includes/components/skeleton-header.php';
                                    include '../includes/components/skeleton-class-card.php'; ?>
                                </div>
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-lg-8">
                                                    <div class="row">
                                                        <?php
                                                        if ($time_table_id == 0) {
                                                        ?>
                                                            <span class="main-topic lh-lg" data-i18n=""><?= $classData['class_name'] ?></span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./classes">Classes</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./class-details?class-id=<?= $class_id ?>">Class Details</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Class Recordings</a></li>
                                                                </ol>
                                                            </nav>
                                                        <?php
                                                        } else {
                                                        ?>
                                                            <span class="main-topic lh-lg" data-i18n=""><?= $time_table_data['lecture_title'] ?></span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./time-table">Time Table</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href=""><?= $time_table_data['lecture_title'] ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Class Recordings</a></li>
                                                                </ol>
                                                            </nav>
                                                        <?php
                                                        }
                                                        ?>

                                                    </div>
                                                </div>

                                                <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                                    <div class="row gx-3 justify-content-end">
                                                        <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <?php
                                                            if ($time_table_id == 0) {
                                                            ?>
                                                                <button class="custom-btn col-12" onclick="window.location.href='./add-recording?class-id=<?= $class_id ?>'">Add Recording</button>
                                                            <?php
                                                            } else {
                                                            ?>
                                                                <button class="custom-btn col-12" onclick="window.location.href='./add-recording?class-id=<?= $class_id ?>&time-table-id=<?= $time_table_id ?>'">Add Recording</button>
                                                            <?php
                                                            }
                                                            ?>

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="row g-3">

                                                <?php

                                                $query = "SELECT 
                                                `exam_type_id`,
                                                `class_id`,
                                                `time_table_id`,
                                                `recording_title`,
                                                `recording_cover`,
                                                `class_cover`,
                                                `lecture_recording`.`duration`,
                                                `integrations_integration_id` AS 'bunny_lib',
                                                `lecture_recording_id`,
                                                `lecture_recording`.`added_at`

                                                 FROM `lecture_recording` 
                                                INNER JOIN `time_table` ON `lecture_recording`.`time_table_time_table_id` = `time_table`.`time_table_id`
                                                INNER JOIN `classes` ON `time_table`.`classes_class_id` = `classes`.`class_id`
                                                INNER JOIN `year` ON `classes`.`year_year_id` = `year`.`year_id`
                                                INNER JOIN `exam_type` ON `classes`.`exam_type_exam_type_id` = `exam_type`.`exam_type_id`
                                                WHERE `classes`.`tutor_id` = '" . $sessionUserObject["user_id"] . "'
                                                AND `classes`.`class_id` = '" . $class_id . "'";

                                                if (!empty($time_table_id)) {
                                                    $query .= " AND `time_table`.`time_table_id` = '" . $time_table_id . "'";
                                                }

                                                $recordings = Database::search($query);

                                                if ($recordings->num_rows > 0) {
                                                    while ($recording = $recordings->fetch_assoc()) {
                                                        $classBage = $recording['exam_type_id'] == 1 ? "scholarship" : ($recording['exam_type_id'] == 2 ? "o-l" : "a-l");
                                                        $isYouTube =  $recording['bunny_lib'] == null ? true : false;
                                                ?>
                                                        <div class="col-12 col-lg-4 col-xxl-3">
                                                            <div class="tutor-class-card">
                                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                                    <div class="col-12 d-flex flex-column align-items-center p-0 m-0 g-0">
                                                                        <div class="lecture-thumbnail card-image-wrapper position-relative">
                                                                            <?php
                                                                            if (empty($recording['recording_cover']) || !file_exists(Project::getAssetsPath() . '/imgs/recording-thumbnail/' . $recording['recording_cover'])) {
                                                                                if (!empty($recording['class_cover']) && file_exists(Project::getAssetsPath() . '/imgs/classes-covers/' . $recording['class_cover'])) {
                                                                            ?>
                                                                                    <img src="<?= Project::getAssetsPath() ?>/imgs/classes-covers/<?= $recording['class_cover'] ?>" alt="Lecture Thumbnail" class="thumbnail-img">
                                                                                <?php
                                                                                } else {
                                                                                ?>
                                                                                    <img src="<?= Project::getAssetsPath() ?>/imgs/common/empty-thumbnail.webp" alt="Lecture Thumbnail" class="thumbnail-img">
                                                                                <?php
                                                                                }
                                                                            } else {
                                                                                ?>
                                                                                <img src="<?= Project::getAssetsPath() ?>/imgs/recording-thumbnail/<?= $recording['recording_cover'] ?>" alt="Lecture Thumbnail" class="thumbnail-img">
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                        </div>
                                                                    </div>

                                                                    <div class="col-12 d-flex flex-column px-3 pb-3 mt-3 gap-3 align-content-center justify-content-center">
                                                                        <span class="subject-text">
                                                                            <?= date("Y-m-d h:i A", strtotime($recording['added_at'])) ?>
                                                                        </span>
                                                                        <h5 class="class-title js-trim" data-full-title="<?= $recording['recording_title'] ?>">
                                                                            <?= $recording["recording_title"]; ?>
                                                                        </h5>
                                                                        <div class="class-meta d-flex justify-content-between">
                                                                            <div class="meta-item">
                                                                                <?php
                                                                                if ($isYouTube) {
                                                                                ?>
                                                                                    <iconify-icon icon="logos:youtube-icon" width="25" height="25"></iconify-icon>
                                                                                    <span class="sub-text">Youtube Video</span>
                                                                                <?php
                                                                                } else {
                                                                                ?>
                                                                                    <iconify-icon icon="logos:bunny-net-icon" width="25" height="25"></iconify-icon>
                                                                                    <span class="sub-text">Bunny Video</span>
                                                                                <?php
                                                                                }
                                                                                ?>
                                                                            </div>
                                                                            <div class="meta-item">
                                                                                <iconify-icon icon="mingcute:time-duration-line"></iconify-icon>
                                                                                <span class="sub-text"><?= formatDuration($recording['duration']); ?></span>
                                                                            </div>
                                                                        </div>

                                                                        <div class="card-footer-premium mt-0">
                                                                            <button class="btn-watch-premium" onclick="window.location = 'watch-recording?recording-id=<?= $recording['lecture_recording_id']; ?>'">
                                                                                <span class="btn-glow"></span>
                                                                                <iconify-icon icon="line-md:play-filled" width="18"></iconify-icon>
                                                                                <span>Watch Now</span>
                                                                            </button>

                                                                            <div class="footer-icon-group">
                                                                                <button class="icon-premium" data-tooltip="Edit" onclick="window.location = 'edit-recording?recording-id=<?= $recording['lecture_recording_id'] ?>'">
                                                                                    <iconify-icon icon="line-md:edit-twotone" width="20"></iconify-icon>
                                                                                </button>
                                                                                <button class="icon-premium delete-premium" data-tooltip="Delete" onclick="openDeleteRecordingModal(<?= $recording['lecture_recording_id'] ?>);">
                                                                                    <iconify-icon icon="line-md:trash" width="20"></iconify-icon>
                                                                                </button>
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

                <div class="modal fade" id="removeConfirmModal" tabindex="-1"
                    aria-labelledby="removeConfirmModalTitle" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow ">
                            <div class="d-flex justify-content-end pe-3 pt-3 position-relative">
                                <button type="button" class="btn-close text-end"
                                    data-bs-dismiss="modal" data-bs-target="#removeConfirmModal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center p-4">
                                <div class="mb-3">
                                    <iconify-icon
                                        icon="bx:error-circle"
                                        width="48"
                                        height="48"
                                        class="text-danger">
                                    </iconify-icon>
                                </div>
                                <h5 class="fw-semibold mb-2" id="removeConfirmModalTitle">
                                    Confirm Removal
                                </h5>
                                <p class="text-muted mb-3">
                                    You are about to remove this <strong>Class Recording</strong>.
                                </p>
                                <div class="alert alert-warning text-start small mb-4">
                                    <div class="d-flex align-items-start gap-2">
                                        <iconify-icon icon="mdi:alert-outline" width="20"></iconify-icon>
                                        <div>
                                            <strong>This action will:</strong>
                                            <ul class="mb-0 ps-3">
                                                <li>Permanently delete the selected recording</li>
                                                <li>Remove access for all students</li>
                                                <li>Delete any attached files/thumbnails (if available)</li>
                                                <li>This action <strong>cannot be undone</strong></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-center gap-3">
                                    <button type="button"
                                        class="btn btn-outline-secondary px-4 br-0"
                                        data-bs-dismiss="modal">
                                        Cancel
                                    </button>
                                    <button type="button"
                                        id="confirmRemove"
                                        class="btn btn-danger px-4 br-0">
                                        <iconify-icon icon="mdi:delete-outline" class="me-1"></iconify-icon>
                                        Remove
                                    </button>
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