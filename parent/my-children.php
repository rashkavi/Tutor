<?php

require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once '../includes/connection.php';
require_once '../includes/Project.php';
if (!SessionUser::ifUserIsParent()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Parent');
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>My Children - <?= Project::getParentBasePath() ?></title>

        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
    </head>

    <body class="overflow-hidden">
        <div class="notifications"></div>
        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
            <div id="appContainer" class="row p-0">
                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                    <?php
                    include "includes/side-menu.php";
                    load_side_menu::load("My Children");
                    ?>
                </div>
                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                    <div class="col-12 p-0 m-0 g-0">
                        <?php
                        include "../includes/header.php";
                        selectItem::setSelected("My Children");
                        ?>
                    </div>

                    <div class="col-12 p-4 w-100 m-0" id="skeleton-list">
                       
                        <?php
                        include "../includes/components/skeleton-card.php";
                        ?>
                    </div>

                    <style>
                        .child-card {
                            background: var(--custom-white);
                            overflow: hidden;
                            transition: all 0.3s ease;
                            height: 100%;
                        }

                        .child-card:hover {
                            transform: translateY(-6px);
                            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
                        }

                        /* Header */
                        .child-card-header {
                            position: relative;
                            height: 120px;
                            background: linear-gradient(135deg, var(--gradient-mid), var(--gradient-start));
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        }

                        .child-card-header img {
                            width: 72px;
                            height: 72px;
                            border-radius: 50%;
                            object-fit: cover;
                            border: 4px solid #ffffff;
                            background: #fff;
                        }

                        /* Status badge */
                        .status-badge {
                            position: absolute;
                            top: 12px;
                            right: 12px;
                            padding: 4px 10px;
                            font-size: 11px;
                            border-radius: 20px;
                            font-weight: 600;
                            color: #fff;
                        }

                        .status-badge.active {
                            background-color: var(--dark-green-blue);
                        }

                        .status-badge.inactive {
                            background-color: var(--dark-red);
                        }

                        /* Body */
                        .child-card-body {
                            padding: 18px;
                            text-align: center;
                        }

                        /* Info rows */
                        .info-row {
                            display: flex;
                            justify-content: space-between;
                            font-size: 13px;
                            color: var(--sub-text);
                            margin-bottom: 8px;
                        }

                        .info-row strong {
                            color: var(--dark-color);
                        }
                    </style>
                    <div class="col-12 d-flex flex-column contentBodyArea vh-100 d-none" id="real-list">
                        <div class="row m-0 p-4">

                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                <div class="row align-items-center p-0 m-0 g-0">
                                    <div class="col-12 col-lg-8">
                                        <div class="row">
                                            <span class="main-topic lh-lg" data-i18n="">My Children</span>
                                            <span class="text-14 sub-text lh-base mt-1">
                                                Monitor your children’s classes, activity, and system access.
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 d-flex flex-column p-0">
                                <div class="row g-3">

                                    <?php
                                    $childrens = Database::search("SELECT `login_details`.*,`students`.`student_id`,
                                    COUNT(`students_has_classes`.`students_has_class_id`) as 'classes_count',
                                   
                                    `students`.`profile_img`
                                    FROM `parents_has_students`
                                INNER JOIN `students` ON `parents_has_students`.`students_student_id`=`students`.`student_id`
                                INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id`
                                LEFT JOIN `students_has_classes` ON `parents_has_students`.`students_student_id`=`students_has_classes`.`students_student_id`
                                WHERE `parents_has_students`.`parents_parent_id`='" . $sessionUserObject['user_id'] . "'
                                GROUP BY `parents_has_students`.`students_student_id` ORDER BY `date_registered` DESC");

                                    if ($childrens->num_rows > 0) {
                                        while ($data = $childrens->fetch_assoc()) {
                                    ?>
                                            <div class="col-12 col-md-4 col-xl-3">
                                                <div class="child-card">
                                                    <!-- Header -->
                                                    <div class="child-card-header">
                                                        <?php
                                                        if (!empty($data["profile_img"]) && file_exists(Project::getAssetsPath() . '/imgs/students/' . $data["profile_img"])) {
                                                        ?>
                                                            <img src="<?= Project::getAssetsPath() ?>/imgs/students/<?php echo $data["profile_img"]; ?>" class="hwr-70" alt="Students image">
                                                        <?php
                                                        } else {

                                                        ?>
                                                            <img src="<?= Project::getAssetsPath() ?>/imgs/users/default-user.webp" class="hwr-70" alt="Students image">

                                                        <?php
                                                        }

                                                        if ($data['status_status_id'] == 1) {
                                                        ?>
                                                            <span class="status-badge active">Active</span>
                                                        <?php
                                                        } else {
                                                        ?>
                                                            <span class="status-badge inactive">Inactive</span>
                                                        <?php
                                                        }
                                                        ?>


                                                    </div>
                                                    <!-- Body -->
                                                    <div class="child-card-body">
                                                        <h6 class="student-name sub-topic mb-3"><?php echo $data["first_name"] . " " . $data["last_name"]; ?></h6>
                                                        <div class="info-row">
                                                            <span>WhatsApp Number</span>
                                                            <strong><?php echo $data["whatsapp"]; ?></strong>
                                                        </div>
                                                        <div class="info-row">
                                                            <span>Registered On</span>
                                                            <strong><?php echo date("M d, Y", strtotime($data["date_registered"])); ?></strong>
                                                        </div>

                                                        <div class="info-row">
                                                            <span>Classes</span>
                                                            <strong><?php echo $data["classes_count"]; ?></strong>
                                                        </div>

                                                        <div class="card-footer-premium">
                                                            <button class="btn-watch-premium bg-dark-theme"
                                                                onclick="window.location = 'student-details?student-id=<?= $data['student_id']; ?>'">
                                                                <iconify-icon icon="solar:eye-broken" width="18"></iconify-icon>
                                                                <span>View</span>
                                                            </button>

                                                            <div class="footer-icon-group">
                                                                <div class="dropdown">
                                                                    <button class="icon-premium"
                                                                        type="button"
                                                                        data-bs-toggle="dropdown"
                                                                        data-tooltip="Options"

                                                                        aria-expanded="false">
                                                                        <iconify-icon icon="qlementine-icons:menu-dots-16" width="20"></iconify-icon>
                                                                    </button>

                                                                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow br-5">
                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="child-classes?child-id=<?= $data['student_id']; ?>">
                                                                                <iconify-icon icon="mingcute:classify-add-line" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                                Classes</a></li>
                                                                      
                                                                    </ul>

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
                        <div class="flex-grow-1"></div>
                        <footer>
                            <?php include '../includes/footer.php' ?>
                        </footer>
                    </div>

                </div>
            </div>
        </div>

        <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/chart.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/functions.js?v=<?= Project::getVersion() ?>"></script>

    </body>

    </html>
<?php
}
?>