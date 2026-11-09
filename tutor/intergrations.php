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
        <title>Intergrations - Admin Dashboard | <?= Project::getBaseName() ?></title>

        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/intergration-card.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/communication.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
        <link href="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.css" rel="stylesheet">

        <script src="<?= Project::getAssetsPath() ?>/plugins/cropperjs/cropper.min.js"></script>
        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
    </head>

    <body class="overflow-hidden" onload="initializeIntergrations();">
        <div class="notifications"></div>
        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
            <div id="appContainer" class="row p-0">
                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                    <?php
                    include "includes/side-menu.php";
                    load_side_menu::load("Intergrations");
                    ?>
                </div>
                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                    <div class="row p-0 m-0 g-0">
                        <div class="col-12 p-0 m-0 g-0">
                            <?php
                            include "includes/header.php";
                            selectItem::setSelected("Intergrations");
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
                                                <span class="main-topic lh-lg" data-i18n="">Intergrations</span>
                                                <span class="text-14 sub-text lh-base mt-1" data-i18n="classes-desc">Manage and connect payment gateways, communication tools, and external services in one place.</span>
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                            <div class="row gx-3 justify-content-end">
                                                <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                    <button class="custom-btn col-12" data-bs-target="#addIntegrationModal" data-bs-toggle="modal" data-i18n="">Add Intergration</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="row g-3">

                                        <?php
                                        $intergrations = Database::search("SELECT * FROM `integrations` WHERE `users_user_id`='" . $sessionUserObject["user_id"] . "'");

                                        if ($intergrations->num_rows > 0) {
                                            while ($intergration = $intergrations->fetch_assoc()) {
                                                $ifIsConnected = Database::search("SELECT * FROM `integration_connections`
                                                     WHERE `integration_id`='" . $intergration['integration_id'] . "'");

                                                if ($ifIsConnected->num_rows > 0) {
                                                    $data = $ifIsConnected->fetch_assoc();
                                        ?>
                                                    <div class="col-12 col-md-6 col-xxl-4">
                                                        <div class="integration-card" id="connectedCard">
                                                            <div class="d-flex align-items-start gap-4">
                                                                <div class="integration-image-container">
                                                                    <img src="<?= Project::getAssetsPath() ?>/imgs/common/zoom.png" alt="Zoom" class="integration-image">
                                                                </div>

                                                                <div class="flex-grow-1">
                                                                    <div class="d-flex justify-content-between align-items-start">
                                                                        <h6 class="integration-title">Zoom</h6>
                                                                    </div>
                                                                    <p class="integration-desc">
                                                                        Connect Zoom to host and manage live online classes, webinars, and meetings directly from your dashboard.
                                                                    </p>

                                                                    <div class="integration-meta">
                                                                        <span class="status-badge active">
                                                                            <i class="fas fa-circle"></i> Active
                                                                        </span>
                                                                        <span class="status-badge connected">
                                                                            <i class="fas fa-plug"></i> Connected
                                                                        </span>
                                                                        <span class="status-badge">
                                                                            <i class="fas fa-sync"></i> Auto-sync
                                                                        </span>
                                                                    </div>

                                                                    <div class="integration-stats">
                                                                        <div class="stat-item">
                                                                            <span class="stat-value">0</span>
                                                                            <span class="stat-label">Meetings</span>
                                                                        </div>
                                                                        <div class="stat-item">
                                                                            <span class="stat-value">0</span>
                                                                            <span class="stat-label">Participants</span>
                                                                        </div>
                                                                        <div class="stat-item">
                                                                            <span class="stat-value">0%</span>
                                                                            <span class="stat-label">Uptime</span>
                                                                        </div>
                                                                    </div>

                                                                    <div class="integration-footer">
                                                                        <div class="last-synced">
                                                                            <i class="far fa-clock"></i> Last synced: 2 hours ago
                                                                        </div>

                                                                        <div class="integration-actions">
                                                                            <!-- <button class="action-btn" title="Settings">
                                                                                <iconify-icon icon="cil-settings" width="20" height="20" class="p-0 m-0 nav-icon"></iconify-icon>
                                                                            </button> -->
                                                                            <button class="action-btn"
                                                                                title="Disconnect"
                                                                                onclick="openDisconnectModal(<?= (int)$data['integration_connection_id']; ?>)">
                                                                                <iconify-icon icon="fluent:plug-disconnected-20-regular"
                                                                                    width="20" height="20"
                                                                                    class="p-0 m-0 nav-icon"></iconify-icon>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php
                                                } else {
                                                ?>
                                                    <div class="col-12 col-md-6 col-xxl-4">
                                                        <div class="integration-card" id="disconnectedCard">
                                                            <div class="d-flex align-items-start gap-4">
                                                                <!-- Image Container -->
                                                                <div class="integration-image-container">
                                                                    <img src="<?= Project::getAssetsPath() ?>/imgs/common/zoom.png" alt="Zoom" class="integration-image">
                                                                </div>

                                                                <!-- Content -->
                                                                <div class="flex-grow-1">
                                                                    <div class="d-flex justify-content-between align-items-start">
                                                                        <h6 class="integration-title">Zoom</h6>
                                                                    </div>
                                                                    <p class="integration-desc">
                                                                        Connect Zoom to host and manage live online classes, webinars, and meetings directly from your dashboard.
                                                                    </p>

                                                                    <div class="integration-meta">
                                                                        <span class="status-badge" style="color: #6b7280; background: #f3f4f6; border-color: #d1d5db;">
                                                                            <i class="fas fa-circle"></i> Inactive
                                                                        </span>
                                                                    </div>

                                                                    <div class="integration-stats">
                                                                        <div class="stat-item">
                                                                            <span class="stat-value">-</span>
                                                                            <span class="stat-label">Meetings</span>
                                                                        </div>
                                                                        <div class="stat-item">
                                                                            <span class="stat-value">-</span>
                                                                            <span class="stat-label">Participants</span>
                                                                        </div>
                                                                        <div class="stat-item">
                                                                            <span class="stat-value">-</span>
                                                                            <span class="stat-label">Uptime</span>
                                                                        </div>
                                                                    </div>

                                                                    <div class="integration-footer">
                                                                        <div class="last-synced">
                                                                            <i class="fas fa-info-circle"></i> Not connected
                                                                        </div>

                                                                        <button class="btn-connect" onclick="connectZoom(<?= $intergration['integration_id']  ?>)">
                                                                            <i class="fas fa-plug"></i> Connect Zoom
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                            <?php
                                                }
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

        <div class="modal fade" id="addIntegrationModal">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5>Add Integration</h5>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="text-14 main-text required">Integration Name</label>
                            <input type="text" class="form-control custom-input mt-2" id="integration-name" placeholder="Zoom">
                            <span id="integration-name-feedback" class="text-dark-red text-12"></span>
                        </div>

                        <div class="mb-3">
                            <label class="text-14 main-text required">Short Description</label>
                            <textarea class="form-control custom-input mt-2" rows="2"
                                placeholder="Connect Zoom to host and manage online classes" id="integration-desc"></textarea>
                            <span id="integration-desc-feedback" class="text-dark-red text-12"></span>
                        </div>

                        <div class="mb-3">
                            <label class="text-14 main-text required">Integration Type</label>

                            <div class="premium-select mt-2" id="messageSourceDropdown">
                                <button type="button" class="premium-select-trigger" id="messageSourceTrigger">
                                    <span class="selected-option">
                                        <iconify-icon icon="oui:integration-general" width="18"></iconify-icon>
                                        <span>Integration Type</span>
                                    </span>
                                    <iconify-icon icon="solar:alt-arrow-down-outline" width="18"></iconify-icon>
                                </button>

                                <div class="premium-select-menu" id="messageSourceMenu">
                                    <button type="button" class="premium-option active" data-value="1">
                                        <span class="option-left">
                                            <iconify-icon icon="hugeicons:zoom" width="18"></iconify-icon>
                                            <span>Video Conferencing</span>
                                        </span>
                                    </button>

                                    <button type="button" class="premium-option premium-locked" data-value="" disabled>
                                        <span class="option-left">
                                            <iconify-icon icon="icon-park-outline:pay-code-two" width="18"></iconify-icon>
                                            <span>Payment</span>
                                        </span>
                                        <span class="pro-badge">
                                            <iconify-icon icon="solar:crown-bold-duotone" width="14"></iconify-icon>
                                            PRO
                                        </span>
                                    </button>

                                    <button type="button" class="premium-option premium-locked" data-value="" disabled>
                                        <span class="option-left">
                                            <iconify-icon icon="logos:bunny-net-icon" width="18"></iconify-icon>
                                            <span>Communication</span>
                                        </span>
                                        <span class="pro-badge">
                                            <iconify-icon icon="solar:crown-bold-duotone" width="14"></iconify-icon>
                                            PRO
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <input type="hidden" id="integration-type" name="message_source" value="">
                            <span id="integration-type-feedback" class="text-dark-red text-12"></span>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="custom-btn w-50" onclick="addIntegration(this)">Add Integration</button>
                    </div>
                </div>
            </div>
        </div>


        <div class="modal fade" id="disconnectIntegrationModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">

                    <div class="modal-body text-center p-4">

                        <div class="mb-3">
                            <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center"
                                style="width:70px;height:70px;background:#FFF3CD;">
                                <iconify-icon icon="fluent:plug-disconnected-20-regular"
                                    width="38" height="38"
                                    style="color:#D97706;"></iconify-icon>
                            </div>
                        </div>

                        <h5 class="fw-bold main-text mb-2">Disconnect Integration?</h5>

                        <p class="text-14 sub-text mb-2">
                            This will permanently remove the connected integration account.
                        </p>

                        <div class="alert alert-warning text-start text-13 rounded-3 mb-4">
                            <strong>Important:</strong><br>
                            Already created Zoom meetings will not be deleted.
                            Only the saved integration connection and tokens will be removed.
                        </div>

                        <input type="hidden" id="disconnectIntegrationId">

                        <div class="d-flex gap-2 justify-content-center">
                            <button type="button" class="btn btn-light px-4 rounded-pill"
                                data-bs-dismiss="modal">
                                Cancel
                            </button>

                            <button type="button" class="btn btn-danger px-4 rounded-pill"
                                onclick="disconnectIntegration(this)">
                                Yes, Disconnect
                            </button>
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

?>