<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
require_once "../includes/enlixo/recording-player.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
} else {
  
        $sessionUserObject = SessionUser::getCurrentUserObject('Student');


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
                        load_side_menu::load("Pre Recorded Classes");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "includes/header.php";
                                selectItem::setSelected("Pre Recorded Classes");
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
                                                    <span class="main-topic lh-lg" data-i18n="">Pre Recorded Classes</span>
                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                        <ol class="breadcrumb m-0">
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./classes">Classes</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Class Details</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Pre Recorded Classes</a></li>
                                                        </ol>
                                                    </nav>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="row g-3">

                                            <?php

                                            $recordings = Database::search("SELECT * FROM `pre_recordings` 
                                                INNER JOIN `classes` ON `pre_recordings`.`classes_class_id` = `classes`.`class_id`
                                                INNER JOIN `year` ON `classes`.`year_year_id` = `year`.`year_id`
                                                INNER JOIN `exam_type` ON `classes`.`exam_type_exam_type_id` = `exam_type`.`exam_type_id`
                                                 ");

                                            if ($recordings->num_rows > 0) {
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
                                                while ($recording = $recordings->fetch_assoc()) {
                                                    $classBage = getClassBage($recording['exam_type_exam_type_id']);
                                                    $isYouTube = $recording['integrations_integration_id'] == null ? true : false;
                                            ?>
                                                    <div class="col-12 col-lg-4 col-xxl-3">
                                                        <div class="tutor-class-card">
                                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                                <div class="col-12 d-flex flex-column align-items-center p-0 m-0 g-0">
                                                                    <div class="lecture-thumbnail card-image-wrapper position-relative"
                                                                        >
                                                                        <?php
                                                                        if (empty($recording['recording_cover']) || !file_exists(Project::getAssetsPath() . '/imgs/pre-recording-thumbnail/' . $recording['recording_cover'])) {
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
                                                                            <img src="<?= Project::getAssetsPath() ?>/imgs/pre-recording-thumbnail/<?= $recording['recording_cover'] ?>" alt="Lecture Thumbnail" class="thumbnail-img">
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                        <span class="class-badge available">
                                                                            Available
                                                                        </span>
                                                                    </div>
                                                                </div>

                                                                <div class="col-12 d-flex flex-column px-3 pb-3 mt-3 mb-0 gap-3 align-content-center justify-content-center">
                                                                    <span class="subject-text">
                                                                        <?= date("Y-m-d h:i A", strtotime($recording['added_at'])) ?>
                                                                    </span>
                                                                        <?= enlixo_recording_opens_html($recording['unlock_at'] ?? null) ?>
                                                                    <h5 class="class-title">
                                                                        <?= $recording["title"]; ?>
                                                                    </h5>

                                                                    <div class="card-footer-premium m-0">
                                                                        <button class="btn-watch-premium"
                                                                            onclick="window.location = 'watch-pre-recording?recording-id=<?= $recording['pre_recording_id'] ?>'">
                                                                            <span class="btn-glow"></span>
                                                                            <iconify-icon icon="line-md:play-filled" width="18"></iconify-icon>
                                                                            <span>Watch Now</span>
                                                                        </button>

                                                                        <div class="footer-icon-group">

                                                                            <div class="meta-item">
                                                                                <iconify-icon icon="mingcute:time-duration-line"></iconify-icon>
                                                                                <span class="sub-text"><?= formatDuration($recording['duration']); ?></span>
                                                                            </div>

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

            <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
            <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
            <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
            <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>

            <script>
                function openVideo(videoId, title) {
                    document.getElementById('videoTitle').innerText = title;

                    document.getElementById('videoFrame').src =
                        "https://iframe.mediadelivery.net/embed/594208/" + videoId;

                    new bootstrap.Modal(document.getElementById('videoModal')).show();
                }

                document.getElementById('videoModal').addEventListener('hidden.bs.modal', function() {
                    document.getElementById('videoFrame').src = "";
                });
            </script>

        </body>

        </html>
<?php

    
}
?>