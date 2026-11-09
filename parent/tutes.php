<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsParent()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Parent');
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
        <title>Tutes - <?= Project::getPortalBasePath() ?></title>
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/tute-card.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/communication.css?v=<?= Project::getVersion() ?>">
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
                            include "../includes/header.php";
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
                                                <span class="main-topic lh-lg" data-i18n="class-students">Tutes</span>
                                                <span class="text-14 sub-text lh-base mt-1" data-i18n="">Organize and distribute study resources, handouts, and assignments for each class efficiently.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>


                                <div class="col-12">
                                    <div class="row g-3">

                                        <div class="col-12 col-md-4 col-xxl-3">
                                            <div class="tutor-class-card h-100">

                                                <div class="position-relative cursor-pointer">
                                                    <span class="class-badge online">
                                                        Online
                                                    </span>
                                                </div>

                                                <div class="card-body-custom cursor-pointer">
                                                    <span class="subject-text">
                                                        13 Feb 2026 • 09:15 AM
                                                    </span>
                                                    <span class="section-label">Tute</span>
                                                    <h5 class="class-title">
                                                        Algebra Revision Pack – Unit 03
                                                    </h5>

                                                    <div class="meta-item">
                                                        <span class="sub-text"> Includes practice questions, structured answers, and step-by-step explanations.</span>
                                                    </div>

                                                    <div class="tute-info mt-2 mb-0">

                                                        <div class="meta-item">
                                                            <iconify-icon icon="streamline-flex:pdf-reader-application-solid"></iconify-icon>
                                                            <span class="sub-text">Pdf • 10MB</span>
                                                        </div>
                                                    </div>
                                                    <div class="card-footer-premium">
                                                        <button class="btn-watch-premium bg-dark-theme">
                                                            <iconify-icon icon="solar:eye-broken" width="18"></iconify-icon>
                                                            <span>View Tute</span>
                                                        </button>

                                                        <div class="footer-icon-group">
                                                            <button class="icon-premium" onclick="openEditClassModal(this)"
                                                                data-tooltip="Download Tute"
                                                                data-class=''>
                                                                <iconify-icon icon="line-md:download-loop" width="20"></iconify-icon>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-4 col-xxl-3">
                                            <div class="tutor-class-card h-100">

                                                <div class="position-relative cursor-pointer">
                                                    <span class="class-badge couried">
                                                        Couried
                                                    </span>
                                                </div>

                                                <div class="card-body-custom cursor-pointer">
                                                    <span class="subject-text">
                                                        13 Feb 2026 • 09:15 AM
                                                    </span>
                                                    <span class="section-label">Tute</span>
                                                    <h5 class="class-title">
                                                        Algebra Revision Pack – Unit 03
                                                    </h5>

                                                    <div class="meta-item">
                                                        <span class="sub-text"> Includes practice questions, structured answers, and step-by-step explanations.</span>
                                                    </div>

                                                    <div class="tute-info mt-2 mb-0">

                                                        <div class="meta-item">
                                                            <iconify-icon icon="streamline-ultimate:delivery-truck-clock"></iconify-icon>
                                                            <span class="sub-text">2-3 Working Days</span>
                                                        </div>
                                                    </div>
                                                    <div class="card-footer-premium">
                                                        <button class="btn-watch-premium bg-dark-theme" onclick="window.location = 'couriered-tute-details'">
                                                            <iconify-icon icon="solar:eye-broken" width="18"></iconify-icon>
                                                            <span>View Tute</span>
                                                        </button>

                                                        <div class="footer-icon-group">
                                                            <button class="icon-premium"
                                                                data-tooltip="Actions"
                                                                data-class=''>
                                                                <iconify-icon icon="qlementine-icons:menu-dots-16" width="20"></iconify-icon>
                                                            </button>
                                                        </div>
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