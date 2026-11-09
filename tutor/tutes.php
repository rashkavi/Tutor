<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');

    function formatFileSize($bytes, $decimals = 2)
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor(log($bytes, 1000));

        return sprintf(
            "%.{$decimals}f %s",
            $bytes / pow(1000, $factor),
            $units[$factor]
        );
    }
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Class Time Table - <?= Project::getPortalBasePath() ?></title>
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/tute-card.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css">
        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/lecture-card.css">
    </head>

    <body class="overflow-hidden">
        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
            <div id="appContainer" class="row p-0">
                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                    <?php
                    include "includes/side-menu.php";
                    load_side_menu::load("Tutes");
                    ?>
                </div>
                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                    <div class="row p-0 m-0 g-0">
                        <div class="col-12 p-0 m-0 g-0">
                            <?php
                            include "includes/header.php";
                            selectItem::setSelected("Tutes");
                            ?>
                        </div>

                        <div class="container p-4 m-0" id="skeleton-list">
                            <?php
                            include Project::getIncludesPath() . '/components/skeleton-header.php';
                            include Project::getIncludesPath() . '/components/skeleton-class-card.php'; ?>
                        </div>
                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                            <div class="row p-4 m-0 g-0">
                                <div class="col-12 py-4 px-4 pe-4 component-theme mb-4">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 col-lg-8">
                                            <div class="row">
                                                <span class="main-topic lh-lg" data-i18n="">Tutes</span>
                                                <span class="text-14 sub-text lh-base mt-1" data-i18n="">Upload, organize, and share tutorial materials and learning resources with students efficiently.</span>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                            <div class="row gx-3 justify-content-end">
                                                <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                    <button class="custom-btn col-12" data-bs-target="#addIntegrationModal" data-bs-toggle="modal" data-i18n="">Add Tute</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="row g-3">
                                        <div class="col-12 col-md-6 col-lg-4">
                                            <div class="tute-card">
                                                <!-- Top Badge Section -->
                                                <div class="tute-top">
                                                    <span class="tute-type online">
                                                        <i class="fas fa-cloud-upload-alt"></i> Online
                                                    </span>
                                                    <span class="tute-date">
                                                        12 Feb 2026
                                                    </span>
                                                </div>

                                                <!-- Title -->
                                                <h5 class="tute-title">
                                                    Algebra Revision Pack – Unit 03
                                                </h5>

                                                <!-- Description -->
                                                <p class="tute-desc">
                                                    Includes practice questions, structured answers, and step-by-step explanations.
                                                </p>

                                                <!-- Info Grid -->
                                                <div class="tute-info">
                                                    <div class="info-item">
                                                        <span class="label">Class</span>
                                                        <span class="value">Grade 10 - Maths</span>
                                                    </div>

                                                    <div class="info-item">
                                                        <span class="label">Format</span>
                                                        <span class="value">PDF • 2.4MB</span>
                                                    </div>
                                                </div>

                                                <!-- Footer -->
                                                <div class="tute-footer">
                                                    <span class="status available">
                                                        <i class="fas fa-check-circle"></i> Available
                                                    </span>


                                                    <div class="footer-icon-group">
                                                        <button class="icon-premium" data-tooltip="View/Download"
                                                            onclick="window.location = 'edit-lecture-recording?lecture-recording-id=<?= $recording['pre_recording_id'] ?>'">
                                                            <iconify-icon icon="solar:eye-broken" width="20"></iconify-icon>
                                                        </button>
                                                        <button class="icon-premium" data-tooltip="Edit"
                                                            onclick="window.location = 'edit-lecture-recording?lecture-recording-id=<?= $recording['pre_recording_id'] ?>'">
                                                            <iconify-icon icon="line-md:edit-twotone" width="20"></iconify-icon>
                                                        </button>

                                                        <button class="icon-premium delete-premium" data-tooltip="Delete"
                                                            onclick="deleteLecture(<?= $recording['pre_recording_id'] ?>)">
                                                            <iconify-icon icon="line-md:trash" width="20"></iconify-icon>
                                                        </button>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>

                                        <div class="col-12 col-md-6 col-lg-4">
                                            <div class="tute-card">

                                                <div class="tute-top">
                                                    <span class="tute-type courier">
                                                        <i class="fas fa-truck"></i> Courier
                                                    </span>
                                                    <span class="tute-date">
                                                        10 Feb 2026
                                                    </span>
                                                </div>

                                                <h5 class="tute-title">
                                                    Physics Theory Notes – Motion & Force
                                                </h5>

                                                <p class="tute-desc">
                                                    Printed booklet delivered to students’ home addresses.
                                                </p>

                                                <div class="tute-info">
                                                    <div class="info-item">
                                                        <span class="label">Class</span>
                                                        <span class="value">Grade 11 - Science</span>
                                                    </div>

                                                    <div class="info-item">
                                                        <span class="label">Delivery</span>
                                                        <span class="value">2–3 Working Days</span>
                                                    </div>
                                                </div>

                                                <div class="tute-footer">
                                                    <span class="status processing">
                                                        <i class="fas fa-clock"></i> In Delivery
                                                    </span>

                                                    <div class="footer-icon-group">
                                                        <button class="icon-premium" data-tooltip="View"
                                                            onclick="window.location = 'couriered-tute'">
                                                            <iconify-icon icon="solar:eye-broken" width="20"></iconify-icon>
                                                        </button>
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

        <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/functions.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
    </body>

    </html>
<?php

}
?>