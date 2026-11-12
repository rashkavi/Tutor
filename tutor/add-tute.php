<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
require_once "../includes/enlixo/page-header.php";
if (!SessionUser::ifUserIsTutor()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

// The form is drawn by assets/js/enlixo/pages/app-tutor-add-tute.js (GET /api/v1/tutor/tutes/form, POST /api/v1/tutor/tutes).
$class_id = (int) ($_GET['class-id'] ?? 0);
$class = Database::search("SELECT `class_id`, `class_name` FROM `classes` WHERE `class_id` = " . $class_id . " AND `tutor_id` = " . (int) $sessionUserObject['user_id']);
if (!$class || $class->num_rows === 0) {
    header("Location: ./classes");
    exit;
}
$classData = $class->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Add Tute - Tutor Dashboard | <?= Project::getBaseName(); ?></title>

    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/enlixo-ui.css?v=<?= Project::getVersion() ?>">

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
                    <div class="container p-4 m-0 d-none" id="skeleton-list"></div>
                    <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100" id="real-list">
                        <div class="row p-4 m-0 g-0">
                            <?php enlixo_form_header('Add Tute', [
                                ['Classes', 'classes'],
                                [$classData['class_name'], 'class-details?class-id=' . $class_id],
                                ['Tutes', 'class-tutes?class-id=' . $class_id],
                                ['Add Tute', null],
                            ], [
                                'intro' => 'Share a tute with this class: as a file students download, or as a printed tute posted to their home.',
                                'points' => [
                                    'Every student, or only students who paid the fee for a month: a student who pays later gets it then.',
                                    'Download: PDF or Word files, up to 10 MB each.',
                                    'Courier: a parcel is made for each student with their address. Follow them on the Deliveries page (packed, sent with a tracking number, delivered).',
                                ],
                            ], 'tute-actions'); ?>
                            <div class="col-12 p-4 component-theme" id="tute-form-root" data-class-id="<?= (int) $class_id ?>"></div>
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
    <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
    <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>
    <script type="module" src="<?= Project::getAssetsPath() ?>/js/enlixo/pages/app-tutor-add-tute.js?v=<?= Project::getVersion() ?>"></script>
</body>

</html>
