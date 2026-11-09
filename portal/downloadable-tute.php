<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Student');

    if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['class-id']) && !empty($_GET['class-id']) && isset($_GET['tute-id']) && !empty($_GET['tute-id']))) {
        header("Location: ./level-details");
    } else {

        $class_id = $_GET["class-id"];
        $tute_id = $_GET["tute-id"];

        $query = "SELECT * FROM `class_tutes` 
        INNER JOIN `classes` ON `classes_class_id`=`class_id`
        WHERE `classes_class_id` = '" . $class_id . "' AND `class_tute_id`='" . $_GET['tute-id'] . "'";

        $class = Database::search($query);
        if (!($class->num_rows > 0)) {
            header("Location: ./course-overview");
        } else {
            $classData = $class->fetch_assoc();

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
                <title>Class Tute - <?= Project::getPortalBasePath() ?></title>
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
                                    include Project::getIncludesPath() . '/components/skeleton-header.php';
                                    include Project::getIncludesPath() . '/components/skeleton-class-card.php'; ?>
                                </div>
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 py-4 px-4 pe-4 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12 col-lg-8">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n="class-students"><?= $classData['tute_title'] ?></span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./classes">Classes</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./class-details?class-id=<?= $class_id ?>">Class Details</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Class Tutes</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <?php
                                        $attachment = Database::search("SELECT * FROM `class_tutes_has_attachments` WHERE `class_tutes_class_tute_id` = '" . $tute_id . "'");
                                        if ($attachment->num_rows > 0) {

                                        ?>
                                            <div class="col-12 component-theme p-4 d-flex flex-column">
                                                <label class="text-16 sub-topic" for="">Tute Pack Attachments</label>
                                                <div class="col-12 px-1 mt-4">
                                                    <div class="row" id="lectures-attachments">
                                                        <?php
                                                        while ($attachmentData = $attachment->fetch_assoc()) {
                                                            if (!empty($attachmentData["attachment"]) && file_exists(Project::getAssetsPath() . "/uploads/tutes-attachments/" . $attachmentData["attachment"])) {
                                                                $filePath = Project::getAssetsPath() . "/uploads/tutes-attachments/" . $attachmentData["attachment"];
                                                                $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
                                                                $fileSize = filesize($filePath);
                                                                $formattedSize = formatFileSize($fileSize);
                                                        ?>
                                                                <div class="col-12 col-lg-4 file-item cursor-pointer">
                                                                    <div class="bl-solid">
                                                                        <div class="row">
                                                                            <div class="col-10"
                                                                                onclick="viewTuteAttachment('<?php echo $attachmentData['attachment'] ?>','<?php echo $attachmentData['attachment_name'] ?>')">

                                                                                <div class="row p-3">
                                                                                    <div class="col-3">
                                                                                        <img src="../assets/imgs/icons/<?php echo $fileExtension ?>.png" alt="">
                                                                                    </div>

                                                                                    <div class="col-8">
                                                                                        <div class="file-name">
                                                                                            <div class="name"><?php echo $attachmentData["attachment_name"] ?></div>
                                                                                        </div>

                                                                                        <div class="file-size">
                                                                                            <div class="name">
                                                                                                <?php echo strtoupper($fileExtension) ?> • <?php echo $formattedSize ?>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>

                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                        <?php
                                                            }
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php
                                        }
                                        ?>

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
                <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="js/functions.js?v=<?= Project::getVersion() ?>"></script>
            </body>

            </html>
<?php


        }
    }
}
?>