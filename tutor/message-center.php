<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsTutor()) {
    header("Location: ./index");
} else {

    $sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Message Center - Admin Dashboard | <?= Project::getBaseName() ?></title>

        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/communication.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
        <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/richtexteditor/rte_theme_default.css" />

        <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script type="text/javascript" src="<?= Project::getAssetsPath() ?>/plugins/richtexteditor/rte.js"></script>
        <script type="text/javascript" src='<?= Project::getAssetsPath() ?>/plugins/richtexteditor/plugins/all_plugins.js'></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
        <link href="<?= Project::getAssetsPath() ?>/plugins/tom-select/tom-select.bootstrap5.min.css" rel="stylesheet">

        <script src="<?= Project::getAssetsPath() ?>/plugins/tom-select/tom-select.complete.min.js"></script>

    </head>

    <body class="overflow-hidden" onload="initializeMessages()">
        <div class="notifications"></div>
        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
            <div id="appContainer" class="row p-0">
                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                    <?php
                    include "includes/side-menu.php";
                    load_side_menu::load("Message Center");
                    ?>
                </div>
                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                    <div class="row p-0 m-0 g-0">
                        <div class="col-12 p-0 m-0 g-0">
                            <?php
                            include "includes/header.php";
                            selectItem::setSelected("Message Center");
                            ?>
                        </div>
                        <div class="container p-4 m-0" id="skeleton-list">
                            <?php
                            include '../includes/components/skeleton-header.php';
                            include '../includes/components/skeleton-class-card.php'; ?>
                        </div>
                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                            <div class="row p-4 m-0 g-0">
                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                    <div class="row align-items-center p-0 m-0 g-0">
                                        <div class="col-12 col-lg-8">
                                            <div class="row">
                                                <span class="main-topic lh-lg" data-i18n="">Message Center</span>
                                                <span class="text-14 sub-text lh-base mt-1" data-i18n="">Connect email, SMS, notifications, and external messaging services to streamline communication across the system.</span>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                            <div class="row gx-3 justify-content-end">

                                                <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                    <button class="custom-btn col-12" data-bs-target="#sendMessage" data-bs-toggle="modal" data-i18n="">Send Message</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="row g-3">

                                        <?php
                                        $messages = Database::search("SELECT * FROM `message_center`
                                    INNER JOIN `message_audience` ON `message_audience`.`message_audience_id` = `message_center`.`message_audience_message_audience_id`
                                    INNER JOIN `message_source` ON `message_source`.`message_source_id` = `message_center`.`message_source_message_source_id`
                                    WHERE `users_user_id`='" . $sessionUserObject['user_id'] . "'");

                                        if ($messages->num_rows > 0) {
                                            while ($message = $messages->fetch_assoc()) {
                                                $source = strtolower($message['message_source']);
                                        ?>
                                                <div class="col-12 col-md-4">
                                                    <div class="tutor-class-card h-100">

                                                        <div class="position-relative cursor-pointer">
                                                            <span class="class-badge <?= $source ?>">
                                                                <?= $message['message_source'] ?>
                                                            </span>
                                                        </div>

                                                        <div class="card-body-custom cursor-pointer">
                                                            <span class="subject-text">
                                                                <?= date('M d, Y g:i A', strtotime($message['date_added'])) ?>
                                                            </span>
                                                            <h5 class="class-title">
                                                                Bulk Message
                                                            </h5>

                                                            <div class="meta-item">
                                                                <span class="sub-text"><?= $message['message'] ?></span>
                                                            </div>
                                                            <div class="mx-classes-row flex-grow-1">
                                                                <?php
                                                                $classes = Database::search("
    SELECT * FROM message_send_classes
    INNER JOIN classes 
        ON classes.class_id = message_send_classes.classes_class_id
    WHERE message_send_classes.message_center_message_center_id = '" . $message['message_center_id'] . "'
");

                                                                $totalClasses = $classes->num_rows;

                                                                if ($totalClasses > 0) {

                                                                    $count = 0;

                                                                    while (($class = $classes->fetch_assoc()) && $count < 3) {
                                                                ?>
                                                                        <span class="mx-class-chip">
                                                                            <iconify-icon icon="solar:document-text-linear"></iconify-icon>
                                                                            <?= $class['class_name'] ?>
                                                                        </span>
                                                                <?php
                                                                        $count++;
                                                                    }

                                                                    if ($totalClasses > 3) {
                                                                        $otherClasses = $totalClasses - 3;
                                                                        echo '<span class="mx-class-more">+' . $otherClasses . ' more</span>';
                                                                    }
                                                                }
                                                                ?>

                                                            </div>

                                                            <div class="class-meta d-flex justify-content-between">
                                                                <div class="meta-item">
                                                                    <iconify-icon icon="lets-icons:user-alt-duotone"></iconify-icon>
                                                                    <span class="sub-text"><?= $message['message_audience'] ?></span>
                                                                </div>

                                                                <div class="meta-item">
                                                                    <iconify-icon icon="lets-icons:send-fill"></iconify-icon>
                                                                    <span class="sub-text"><?= $message['send_messages_count'] ?> Messages</span>
                                                                </div>
                                                            </div>
                                                            <!-- <div class="card-footer-premium mt-auto">
                                                                <button class="btn-watch-premium bg-dark-theme">
                                                                    <iconify-icon icon="solar:eye-broken" width="18"></iconify-icon>
                                                                    <span>View Message</span>
                                                                </button>

                                                                <div class="footer-icon-group">
                                                                    <button class="icon-premium" onclick="openEditClassModal(this)"
                                                                        data-tooltip="Edit Message"
                                                                        data-class='<?= json_encode($class, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                                                        <iconify-icon icon="line-md:edit-twotone" width="20"></iconify-icon>
                                                                    </button>
                                                                </div>
                                                            </div> -->
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php
                                            }
                                        } else {
                                            ?>
                                            <div class="col-12 px-4 h-100">
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
                            <div class="flex-grow-1"></div>
                            <footer>
                                <?php include '../includes/footer.php' ?>
                            </footer>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="sendMessage">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5>Send a Message</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="text-14 main-text required">Select Audience</label>

                            <select id="class-selector"
                                name="classes[]"
                                multiple
                                class="form-select custom-select mt-2 br-0">

                                <?php
                                $query = "SELECT * FROM `classes`";
                                if (SessionUser::ifUserIsTutor()) {
                                    $query .= " WHERE `classes`.`tutor_id` = '" . $sessionUserObject['user_id'] . "'";
                                }

                                $class = Database::search($query);
                                while ($data = $class->fetch_assoc()) {
                                ?>
                                    <option value="<?= $data["class_id"]; ?>">
                                        <?= $data["class_name"]; ?>
                                    </option>
                                <?php } ?>

                            </select>
                            <span id="class-feedback" class="text-dark-red text-12"></span>
                        </div>

                        <div class="mb-3">
                            <label class="text-14 main-text required">Send Message To</label>

                            <div class="d-flex gap-2 mt-2">

                                <!-- Students -->
                                <label class="audience-check d-flex align-items-center">
                                    <input type="checkbox"
                                        name="audience[]"
                                        value="students"
                                        class="form-check-input custom-input p-2 me-2" id="audienceStudents" checked>
                                    <span class="text-14">Students</span>
                                </label>

                                <!-- Parents -->
                                <label class="audience-check d-flex align-items-center">
                                    <input type="checkbox"
                                        name="audience[]"
                                        value="parents"
                                        class="form-check-input custom-input p-2 me-2" id="audienceParents">
                                    <span class="text-14 p-0">Parents</span>
                                </label>
                            </div>

                            <span id="audience-feedback" class="text-dark-red text-12"></span>
                        </div>


                        <div class="mb-3">
                            <label class="text-14 main-text required">Message</label>
                            <div id="content" class="mt-2 br-0"></div>
                            <span id="integration-desc-feedback" class="text-dark-red text-12"></span>
                        </div>
                  
                        <div class="mb-3">
                            <label class="text-14 main-text required">Send Via</label>

                            <div class="premium-select mt-2" id="messageSourceDropdown">
                                <button type="button" class="premium-select-trigger" id="messageSourceTrigger">
                                    <span class="selected-option">
                                        <iconify-icon icon="solar:letter-bold-duotone" width="18"></iconify-icon>
                                        <span>Select message source</span>
                                    </span>
                                    <iconify-icon icon="solar:alt-arrow-down-outline" width="18"></iconify-icon>
                                </button>

                                <div class="premium-select-menu" id="messageSourceMenu">
                                    <button type="button" class="premium-option active" data-value="1">
                                        <span class="option-left">
                                            <iconify-icon icon="solar:letter-bold-duotone" width="18"></iconify-icon>
                                            <span>Email</span>
                                        </span>
                                    </button>

                                    <button type="button" class="premium-option premium-locked" data-value="" disabled>
                                        <span class="option-left">
                                            <iconify-icon icon="solar:chat-round-dots-bold-duotone" width="18"></iconify-icon>
                                            <span>SMS</span>
                                        </span>
                                        <span class="pro-badge">
                                            <iconify-icon icon="solar:crown-bold-duotone" width="14"></iconify-icon>
                                            PRO
                                        </span>
                                    </button>

                                    <button type="button" class="premium-option premium-locked" data-value="" disabled>
                                        <span class="option-left">
                                            <iconify-icon icon="logos:whatsapp-icon" width="18"></iconify-icon>
                                            <span>WhatsApp</span>
                                        </span>
                                        <span class="pro-badge">
                                            <iconify-icon icon="solar:crown-bold-duotone" width="14"></iconify-icon>
                                            PRO
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <input type="hidden" id="message-source" name="message_source" value="">
                            <span id="integration-type-feedback" class="text-dark-red text-12"></span>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="custom-btn w-50" onclick="sendMessages(this)">Send Message</button>
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

?>