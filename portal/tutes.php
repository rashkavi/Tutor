<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Student');


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
                                                <span class="main-topic lh-lg" data-i18n="class-students">Tutes</span>
                                                <span class="text-14 sub-text lh-base mt-1" data-i18n="">Organize and distribute study resources, handouts, and assignments for each class efficiently.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>


                                <div class="col-12">
                                    <div class="row g-3">

                                        <div class="col-12">
                                            <div class="row g-3">

                                                <?php
                                                $tutes = Database::search("SELECT 
                                                `class_tutes`.*,
                                                 `tute_types`.`tute_type`,
                                                 COUNT(`class_tutes_has_attachments`.`class_tutes_class_tute_id`) AS attachments FROM `class_tutes`
                                                INNER JOIN `tute_types` ON `class_tutes`.`tute_types_tute_type_id` = `tute_types`.`tute_type_id` 
                                                INNER JOIN `class_tutes_has_attachments` ON `class_tutes`.`class_tute_id` = `class_tutes_has_attachments`.`class_tutes_class_tute_id`
                                                WHERE `classes_class_id` IN (SELECT `classes_class_id` FROM `students_has_classes` 
                                                WHERE `students_has_classes`.`students_student_id` = '" . $sessionUserObject['student_id'] . "')
                                                ");
                                                if ($tutes->num_rows > 0) {
                                                    while ($tute = $tutes->fetch_assoc()) {
                                                        $tuteType = $tute['tute_types_tute_type_id'] == 1 ? "online" : "couried";

                                                ?>
                                                        <div class="col-12 col-md-4 col-xxl-3">
                                                            <div class="tutor-class-card h-100">

                                                                <div class="position-relative cursor-pointer">
                                                                    <span class="class-badge <?= $tuteType ?>">
                                                                        <?= $tute['tute_type'] ?>
                                                                    </span>
                                                                </div>

                                                                <div class="card-body-custom cursor-pointer">
                                                                    <span class="subject-text">
                                                                        <?= date('d M Y H:i', strtotime($tute['date_added'])) ?>
                                                                    </span>
                                                                    <span class="section-label">Tute</span>
                                                                    <h5 class="class-title">
                                                                        <?= $tute['tute_title'] ?>
                                                                    </h5>

                                                                    <div class="meta-item">
                                                                        <span class="sub-text"><?= $tute['tute_description'] ?></span>
                                                                    </div>

                                                                    <?php
                                                                    if ($tuteType == "couried") {
                                                                    ?>
                                                                        <div class="meta-item">
                                                                            <iconify-icon icon="streamline-ultimate:delivery-truck-clock"></iconify-icon>
                                                                            <span class="sub-text">2-3 Working Days</span>
                                                                        </div>
                                                                    <?php
                                                                    } else {
                                                                    ?>
                                                                        <div class="tute-info mt-2 mb-0">
                                                                            <div class="meta-item">
                                                                                <iconify-icon icon="streamline-flex:pdf-reader-application-solid"></iconify-icon>
                                                                                <span class="sub-text"><?= $tute['attachments'] ?> • Attachments</span>
                                                                            </div>
                                                                        </div>

                                                                    <?php
                                                                    }
                                                                    ?>

                                                                    <div class="card-footer-premium">
                                                                        <button class="btn-watch-premium bg-dark-theme" onclick="window.location = 'downloadable-tute?class-id=<?= $tute['classes_class_id'] ?>&tute-id=<?= $tute['class_tute_id'] ?>' ">
                                                                            <iconify-icon icon="solar:eye-broken" width="18"></iconify-icon>
                                                                            <span>View Tute</span>
                                                                        </button>
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