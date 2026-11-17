<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsAdmin()) {
    header("Location: ./index");
} else {

    $sessionUserObject = SessionUser::getCurrentUserObject('Admin');
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Users - Admin Dashboard | <?= Project::getBaseName() ?></title>

        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
    </head>

    <body onload="initializeUsers();">
        <div class="notifications"></div>
        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
            <div id="appContainer" class="row p-0">
                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                    <?php
                    include "./includes/side-menu.php";
                    load_side_menu::load("Users");
                    ?>
                </div>
                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                    <div class="row p-0 m-0 g-0">
                        <div class="col-12 p-0 m-0 g-0">
                            <?php
                            include "../includes/header.php";
                            selectItem::setSelected("Users");
                            ?>
                        </div>

                        <div class="container p-4 m-0" id="skeleton-list">
                            <?php
                            include "../includes/components/skeleton-header.php";
                            include "../includes/components/skeleton-table.php"; ?>
                        </div>

                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                            <div class="row p-4 m-0 g-0">
                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 col-md-8">
                                            <div class="row">
                                                <span class="main-topic lh-lg" data-i18n="users">Users</span>
                                                <span class="text-14 sub-text lh-base mt-1" data-i18n="users-desc">Effortlessly manage users, access profiles, and maintain oversight</span>
                                            </div>
                                        </div>

                                        <div class="col-12 col-lg-4 mt-3 mt-lg-0">
                                            <div class="row align-items-center justify-content-end gx-3 h-100">
                                                <div class="col-6 mt-3 mt-lg-0 m-md-0 text-end">
                                                    <a class="bg-transparent border-0 align-middle" href="share-registration-link" title="Share Registration Link" aria-label="Share Registration Link">
                                                        <iconify-icon icon="ion:share-social" width="30" height="30" class="dark-theme p-0 m-0"></iconify-icon>
                                                    </a>
                                                </div>
                                                <div class="col-6 mt-3 mt-lg-0 m-md-0">
                                                    <button class="custom-btn w-100" data-i18n="add-new" type="button" onclick="window.location.href='add-user'">
                                                        Add User
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 px-4 py-3 component-theme mb-10">
                                    <div class="row p-0 m-0 g-0 align-content-center">
                                        <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                            <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent-emoji-high-contrast:student" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                            <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                <span class="text-16 text-dark-color fw-bold lh-lg">Registered Users</span>
                                                <span class="text-14 main-text">Manage user accounts, details, and activities in one place.</span>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                            <div class="row gx-3 h-100 align-content-center justify-content-end">

                                                <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                    <a href="./registered-users" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                        <button class="custom-btn w-100" data-i18n="view">View</button>
                                                    </a>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 px-4 py-3 component-theme mb-10">
                                    <div class="row p-0 m-0 g-0 align-content-center">
                                        <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                            <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="charm:git-request" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                            <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                <span class="text-16 text-dark-color fw-bold lh-lg">Registration Requests</span>
                                                <span class="text-14 main-text">Handle pending registration requests in one place</span>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                            <div class="row gx-3 h-100 align-content-center justify-content-end">

                                                <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                    <a href="./requested-users.php" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                        <button class="custom-btn w-100" data-i18n="view">View</button>
                                                    </a>
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

        <!-- Share Registration Link is a page now: share-registration-link.php -->

        <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/functions.js?v=<?= Project::getVersion() ?>"></script>
    </body>

    </html>
<?php
}

?>