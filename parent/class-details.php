<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsParent()) {
    header("Location: ./index");
} else {

    $sessionUserObject = SessionUser::getCurrentUserObject('Parent');

    if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['class-id']) && !empty($_GET['class-id']))) {
        header("Location: ./class");
    } else {
        $class_id = $_GET["class-id"];

        $query = " SELECT 
                                                    classes.*,
                                                    subjects.subject,
                                                    year.year,
                                                    medium.medium,
                                                    exam_type.exam_type,
                                                    exam_type.exam_type_id
                                                    
                                                FROM classes
                                                INNER JOIN subjects ON classes.subjects_subject_id = subjects.subject_id
                                                INNER JOIN year ON classes.year_year_id = year.year_id
                                                INNER JOIN medium ON classes.medium_medium_id = medium.medium_id
                                                INNER JOIN exam_type ON classes.exam_type_exam_type_id = exam_type.exam_type_id
                                                WHERE classes.class_id = '" . $class_id . "'";

        $class = Database::search($query);
        if ($class->num_rows == 0) {
            header("Location: ./classes");
        } else {
            $classData = $class->fetch_assoc();
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Class Details - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>

            </head>

            <body class="overflow-hidden" onload="initializeClass()">
                <ul class="notifications"></ul>
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
                                    include "../includes/header.php";
                                    selectItem::setSelected("Classes");
                                    ?>
                                </div>
                                <div class="container p-4 m-0" id="skeleton-list">
                                    <?php
                                    include '../includes/components/skeleton-header.php';
                                    include '../includes/components/skeleton-courses.php'; ?>
                                </div>
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-lg-8">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg"><?= $classData['class_name'] ?></span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./classes">Classes</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Class Details</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 px-4 py-3 component-theme mb-10">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="solar:calendar-date-broken" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="">Time Table</span>
                                                        <span class="text-14 main-text" data-i18n="">Organize classes and schedules with ease and flexibility</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                        <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <a href="./class-time-table?class-id=<?= $class_id ?>" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                                <button class="custom-btn w-100" data-i18n="">View</button>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 px-4 py-3 component-theme mb-10">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="solar:money-bag-broken" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg" data-i18n="">Payments</span>
                                                        <span class="text-14 main-text" data-i18n="">View and manage all payment records in one place</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                        <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <a href="./class-payments?class-id=<?= $class_id ?>" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                                <button class="custom-btn w-100" data-i18n="">View</button>
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

                <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>

            </body>

            </html>
<?php
        }
    }
}
?>