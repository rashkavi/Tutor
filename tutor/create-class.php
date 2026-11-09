<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
require_once "../tutor/includes/security-helpers.php";
require_once "../includes/enlixo/page-header.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
    $sessionUserRole = SessionUser::getCurrentUserRole('Tutor');
    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
    // The form is drawn by assets/js/enlixo/pages/app-tutor-create-class.js from GET /api/v1/tutor/class-form
    // and saved with POST /api/v1/tutor/classes (class + weekly times + monthly fee in one go).
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Create a Class - Tutor Dashboard | <?= Project::getBaseName() ?></title>

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

                        <!-- toggleSkeleton() in assets/js/script.js swaps these two; the list has its own skeleton -->
                        <div class="container p-4 m-0 d-none" id="skeleton-list"></div>

                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100" id="real-list">
                            <div class="row p-4 m-0 g-0">
                                <?php enlixo_form_header('Create a Class', [['Classes', 'classes'], ['Create a Class', null]], [
                                    'intro' => 'Set up a class in one go: what you teach, when and where, the monthly fee and who can join.',
                                    'points' => [
                                        'Fields with a red star are required. Everything else can be added later.',
                                        'Leave the class name empty and we name it for you, e.g. "2027 A/L Physics Theory (Sinhala)".',
                                        'Weekly times are added to your timetable every week. Online times get a Zoom link automatically.',
                                        'Use the gear icon to hide fields you never use or add your own fields.',
                                    ],
                                ], 'class-actions'); ?>
                                <div class="col-12 p-4 component-theme" id="class-form-root"></div>
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
        <script type="module" src="<?= Project::getAssetsPath() ?>/js/enlixo/pages/app-tutor-create-class.js?v=<?= Project::getVersion() ?>"></script>
    </body>

    </html>
<?php
}
?>
