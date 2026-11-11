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
    // The tutor's own plan, invoices and payment history (docs/ADMIN-PLAN.md), the same data the admin sees.
    // Drawn by assets/js/enlixo/pages/app-tutor-subscription.js from GET /api/v1/tutor/subscription.
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>My Subscription - Tutor Dashboard | <?= Project::getBaseName() ?></title>

        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/enlixo-ui.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/enlixo-admin.css?v=<?= Project::getVersion() ?>">

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
                    load_side_menu::load("My Subscription");
                    ?>
                </div>
                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                    <div class="row p-0 m-0 g-0">
                        <div class="col-12 p-0 m-0 g-0">
                            <?php
                            include "includes/header.php";
                            selectItem::setSelected("My Subscription");
                            ?>
                        </div>

                        <!-- toggleSkeleton() in assets/js/script.js swaps these two; the list has its own skeleton -->
                        <div class="container p-4 m-0 d-none" id="skeleton-list"></div>

                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100" id="real-list">
                            <div class="row p-4 m-0 g-0">
                                <?php enlixo_form_header('My Subscription', [['Finance', null], ['My Subscription', null]], [
                                    'intro' => 'Your ENLIXO Tutor plan, your invoices and every payment you made.',
                                    'points' => [
                                        'Your plan card shows the plan, when it renews (or when the free trial ends) and how many students you have.',
                                        'Pay an invoice by bank transfer and upload the slip. ENLIXO checks it within one working day.',
                                        'Payment History shows each slip and whether it was approved; a rejected slip shows the reason.',
                                    ],
                                ], 'subscription-actions'); ?>

                                <div class="col-12 p-0" id="tutor-subscription"></div>
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
        <script type="module" src="<?= Project::getAssetsPath() ?>/js/enlixo/pages/app-tutor-subscription.js?v=<?= Project::getVersion() ?>"></script>
    </body>

    </html>
<?php
}
?>
