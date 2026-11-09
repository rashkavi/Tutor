<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsTutor()) {
    header("Location: ./index");
} else {

    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['class-id']) && !empty($_GET['class-id'])) {

        $class_id = $_GET["class-id"];

        $query = "SELECT * FROM `classes` WHERE `class_id` = '" . $class_id . "'";
        if (SessionUser::ifUserIsTutor()) {
            $query .= " AND `tutor_id`='" . $sessionUserObject['user_id'] . "'";
        }
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
                <title>Add Tute - Tutor Dashboard | <?= Project::getBaseName(); ?></title>

                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
            </head>

            <body class="overflow-hidden" onload="initializeAddTute()">
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
                                <div class="container p-4 m-0" id="skeleton-list">
                                    <?php
                                    include "../includes/components/skeleton-header.php";
                                    include "../includes/components/skeleton-add-edit.php"; ?>
                                </div>
                                <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12 col-md-8">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n=""><?= $classData['class_name'] ?></span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="" href="./classes">Classes</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./class-details?class-id=<?= $class_id ?>">Class Details</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Class Tutes</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Add Tute</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 align-content-center">
                                                <div class="col-12 p-0 g-0">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Tute Pack Title</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="title">
                                                            <span id="title-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-16 main-text" for="">Tute Pack Description</label>
                                                            <textarea name="link" class="form-control custom-input mt-2" rows="3" id="description"></textarea>
                                                            <span id="description-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-16 main-text required" for="">Tute Type</label>
                                                            <div class="d-flex gap-4 px-2 mt-4">

                                                                <div class="form-check">
                                                                    <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" name="lectureType" id="online-check-box" checked>
                                                                    <label class="text-16 sub-text mx-2">Downloadable (Online)</label>
                                                                </div>
                                                                <div class="form-check">
                                                                    <input class="form-check-input text-20 mt-0 mb-0 me-2" type="checkbox" name="lectureType" id="courier-check-box">
                                                                    <label class="text-16 sub-text mx-2">Courier Delivery</label>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column" id="attachment-area">
                                                            <label class="text-16 main-text" for="">Attchments</label>
                                                            <div class="p-5 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                                <img src="../assets/imgs/icons/cloud.png" alt="cloud">
                                                                <span>Drag & Drop your files here</span>
                                                                <span>OR</span>
                                                                <button class="custom-btn px-4" id="file-selector">Select Files</button>
                                                                <input type="file" class="d-none" id="file-selector-input" multiple accept=".pdf">
                                                                <div class="col-12 px-1 mt-4">
                                                                    <div class="row" id="list-section">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <span id="attachment-feedback" class="text-dark-red text-12"></span>
                                                            <!-- File upload input field for PDF, Word, and PowerPoint files -->
                                                        </div>

                                                        <div class="col-12 d-flex flex-column d-none" id="tute-title-wrap">
                                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                <label class="text-16 main-text required">
                                                                    Courier Tutes Titles
                                                                </label>
                                                                <span id="tute-titles-guide-info"></span>
                                                            </div>

                                                            <!-- first row -->
                                                            <div class="input-plus-row mt-2">
                                                                <input type="text" class="form-control custom-input" name="title[]" placeholder="Enter tute title">
                                                                <button type="button" class="input-plus-btn" id="add-title-btn" aria-label="Add title">
                                                                    <iconify-icon icon="ic:round-add" width="20"></iconify-icon>
                                                                </button>
                                                            </div>

                                                            <span id="title-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column">
                                                            <div class="form-check d-flex align-items-center">
                                                                <input class="form-check-input text-20 mt-0 mb-0 me-2"
                                                                    type="checkbox"
                                                                    name="payment-check"
                                                                    id="payment-check">


                                                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                    <label class="text-16 sub-text mx-2" for="payment-check">
                                                                        Allow access only for students who have paid for the selected payment month
                                                                    </label>
                                                                    <span id="tute-payment-guide-info"></span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-12 col-lg-4 d-flex flex-column d-none" id="payment-area">
                                                            <label class="text-16 main-text required">Payment Month</label>

                                                            <select name="payment" id="payment-month" class="form-select custom-select mt-2">
                                                                <option value="0">- Select Payment Month -</option>

                                                                <?php
                                                                $currentMonth = (int)date('n');
                                                                $currentYear  = (int)date('Y');

                                                                $prevMonth = $currentMonth - 1;
                                                                $prevYear  = $currentYear;

                                                                if ($prevMonth == 0) {
                                                                    $prevMonth = 12;
                                                                    $prevYear--;
                                                                }

                                                                $nextMonth = $currentMonth + 1;
                                                                $nextYear  = $currentYear;

                                                                if ($nextMonth == 13) {
                                                                    $nextMonth = 1;
                                                                    $nextYear++;
                                                                }

                                                                function printMonthOption($month, $year)
                                                                {
                                                                    $monthName = date("F", mktime(0, 0, 0, $month, 1));
                                                                    echo "<option value='{$year}-{$month}'>{$monthName} {$year}</option>";
                                                                }

                                                                printMonthOption($prevMonth, $prevYear);
                                                                printMonthOption($currentMonth, $currentYear);
                                                                printMonthOption($nextMonth, $nextYear);
                                                                ?>
                                                            </select>

                                                            <span id="month--feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                    </div>
                                                </div>

                                                <div class="col-12 p-0 g-0 mt-3">
                                                    <div class="row gx-4 gy-3">
                                                        <div class="col-12 col-md-12 d-flex flex-column align-items-end">
                                                            <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                <button class="custom-btn w-100" onclick="addTute(this,<?= $class_id ?>)">Add Tute</button>
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


                <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="js/createInfo.js?v=<?= Project::getVersion() ?>"></script>
                <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/fileChooser.js?v=<?= Project::getVersion() ?>"></script>
            </body>

            </html>
<?php

        }
    }
}
?>