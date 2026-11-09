<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
} else {

    $sessionUserObject = SessionUser::getCurrentUserObject('Student');

    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['recording-id']) && !empty($_GET['recording-id'])) {

        $recording_id = $_GET["recording-id"];

        $query = "SELECT * FROM `pre_recordings` 
            INNER JOIN `classes` ON `pre_recordings`.`classes_class_id` = `classes`.`class_id`
            WHERE `pre_recording_id` = '" . $recording_id . "'";

        $recording = Database::search($query);
        if ($recording->num_rows == 0) {
            header("Location: ./pre-recordings");
        } else {
            $recordingData = $recording->fetch_assoc();
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
                <title>Recording - Admin Dashboard | <?= Project::getBaseName() ?></title>

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

            <body class="overflow-hidden" onload="initializeWatchPreRecordings(true)">
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
                                                        <span class="main-topic lh-lg" data-i18n="">Watch Recordings</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="" href="./pre-recordings">Pre Recordings</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="">Watch Recording</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <?php
                                        $attachment = Database::search("SELECT * FROM `pre_recordings_attachments` WHERE `pre_recordings_pre_recording_id` = '" . $recording_id . "'");
                                        if ($attachment->num_rows > 0) {
                                        ?>
                                            <div class="col-12 component-theme p-3 mb-3">
                                                <div class="d-flex">
                                                    <div class="flex-grow-1">
                                                        <span class="sub-topic">Recording Attachments</span>
                                                        <div class="col-12 px-1 mt-2">
                                                            <div class="row">
                                                                <?php
                                                                while ($attachmentData = $attachment->fetch_assoc()) {
                                                                    if (!empty($attachmentData["attachment"]) && file_exists(Project::getAssetsPath() . "/uploads/recordings-attachments/" . $attachmentData["attachment"])) {
                                                                ?>
                                                                        <div class="col-12 col-md-6 my-3 file-item cursor-pointer" onclick="viewLectureAttachment('<?php echo $attachmentData['attachment'] ?>', '<?php echo $attachmentData['attachment_name'] ?>')">
                                                                            <div class="p-3 bl-solid">
                                                                                <div class="d-flex">
                                                                                    <img src="assets/imgs/icons/pdf.png" alt="Lecture Attachment" class="h-48 w-auto">
                                                                                    <div class="d-flex flex-column px-4">
                                                                                        <span class="name"><?php echo $attachmentData["attachment_name"] ?></span>
                                                                                        <span class="text-muted text-12">Click to view</span>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                <?php
                                                                    }
                                                                }
                                                                ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php
                                        }
                                        ?>

                                        <div class="col-12 py-4 px-4 pe-4 component-theme">
                                            <div class="row p-0 m-0 g-0 align-content-center">

                                                <?php if ($recordingData['integrations_integration_id'] == null): ?>
                                                    <?= $recordingData["recording_link"]; ?>
                                                    <?php else:

                                                    $bunnyData = Database::search("SELECT * FROM `video_intergration` 
                                                WHERE `integrations_integration_id` = '" . $recordingData["integrations_integration_id"] . "'");

                                                    if ($bunnyData->num_rows > 0) {
                                                        $bunnyData = $bunnyData->fetch_assoc();
                                                    ?>
                                                        <div class="video-wrapper">
                                                            <div class="video-loader" id="videoLoader">
                                                                <div class="modern-spinner"></div>
                                                            </div>

                                                            <!-- Iframe (hidden initially) -->
                                                            <iframe
                                                                id="videoIframe"
                                                                src="https://iframe.mediadelivery.net/embed/<?= $bunnyData['lib_id'] ?>/<?= $recordingData['recording_link'] ?>"
                                                                style="width:100%; height:500px; border:0; display:none;"
                                                                allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;"
                                                                allowfullscreen>
                                                            </iframe>
                                                        </div>
                                                    <?php
                                                    }
                                                    ?>


                                                <?php endif; ?>
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
                    document.addEventListener("DOMContentLoaded", function() {
                        toggleSkeleton();
                        const iframe = document.getElementById("videoIframe");
                        const loader = document.getElementById("videoLoader");

                        if (iframe) {
                            iframe.onload = function() {
                                loader.style.opacity = "0";
                                setTimeout(() => loader.style.display = "none", 400);
                                iframe.style.display = "block";
                            };
                        }
                    });
                </script>

            </body>

            </html>
<?php
        }
    }
}
?>