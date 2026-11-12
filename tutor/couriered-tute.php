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

// Deliveries of a posted (courier) tute, drawn by assets/js/enlixo/pages/app-tutor-tute-deliveries.js
// (GET /api/v1/tutor/classes/{id}/courier-tutes, GET/POST /api/v1/tutor/tutes/{id}/deliveries).
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
    <title>Tute Deliveries - Tutor Dashboard | <?= Project::getBaseName(); ?></title>

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
                            <?php enlixo_form_header('Tute Deliveries', [
                                ['Classes', 'classes'],
                                [$classData['class_name'], 'class-details?class-id=' . $class_id],
                                ['Tutes', 'class-tutes?class-id=' . $class_id],
                                ['Deliveries', null],
                            ], [
                                'intro' => 'Every parcel of a posted tute: who it goes to, where, and how far it has got.',
                                'points' => [
                                    'Steps: To pack, Packed, Sent (with the courier and tracking number), Delivered. Returned is for parcels that came back.',
                                    'Tick parcels to move them to the next step together, or use the button on a row.',
                                    'A student with no address is marked. Edit the parcel to add one before posting.',
                                    'Students who pay the fee after the tute was added: press "Add students who paid later".',
                                    'Print labels makes address labels for the ticked parcels, or for every parcel still to send.',
                                ],
                            ], 'deliveries-actions'); ?>
                            <div class="col-12 p-0" id="deliveries-root" data-class-id="<?= (int) $class_id ?>" data-tute-id="<?= (int) ($_GET['tute-id'] ?? $_GET['tute'] ?? 0) ?>"></div>
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
    <script type="module" src="<?= Project::getAssetsPath() ?>/js/enlixo/pages/app-tutor-tute-deliveries.js?v=<?= Project::getVersion() ?>"></script>
</body>

</html>
