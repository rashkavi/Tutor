<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Student');

    if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['class-id']) && !empty($_GET['class-id']))) {
        header("Location: ./level-details");
    } else {
        $class_id = $_GET['class-id'];
        $isClassValid = Database::search("SELECT * FROM `students_has_classes`
        INNER JOIN `classes` ON `classes_class_id`=`class_id`
        WHERE `students_student_id`='" . $sessionUserObject['student_id'] . "' AND `classes_class_id`='" . $class_id . "'");
        if (!($isClassValid->num_rows > 0)) {
            header("Location: ./course-overview");
        } else {
            $class = $isClassValid->fetch_assoc();
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Class Time Table - <?= Project::getPortalBasePath() ?></title>
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/lecture-card.css?v=<?= Project::getVersion() ?>">
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
                                                <span class="main-topic lh-lg">Class Time Table</span>
                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                    <ol class="breadcrumb m-0">
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./classes">Classes</a></li>
                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $class['class_name'] ?></a></li>
                                                        <li class="breadcrumb-item active">Class Time Table</li>
                                                    </ol>
                                                </nav>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="container p-0 m-0 g-0">
                                                    <div class="row g-4">

                                                        <?php
                                                        $now = (new DateTime())->format('Y-m-d');
                                                        $dateModfy = (new DateTime())->modify('+7 days')->format('Y-m-d');

                                                        $query = "
                                                        SELECT 
                                                           `class`.`class_name`,
                                                           `class`.`class_cover`,
                                                           `et`.`exam_type`,
                                                           `y`.`year`,
                                                           `m`.`medium`,
                                                           `subj`.`subject`,
                                                            `tt`.`time_table_id`,
                                                            `tt`.`date`,
                                                            `tt`.`start_time`,
                                                            `tt`.`end_time`,
                                                            `tt`.`lecture`,
                                                            `tt`.`lecture_title`,
                                                            `tts`.`time_table_status`,
                                                            `tts`.`time_table_status_id`,
                                                            `lt`.`lecture_type`,
                                                            `u`.`user_fname`,
                                                            `u`.`user_lname`,
                                                            `u`.`profile_img`,
                                                            `t`.`title`
                                                        FROM                    
                                                            `time_table` AS `tt`
                                                        INNER JOIN 
                                                            `classes` AS `class` ON `tt`.`classes_class_id` = `class`.`class_id`
                                                        INNER JOIN 
                                                            `subjects` AS `subj` ON `class`.`subjects_subject_id` = `subj`.`subject_id`
                                                        INNER JOIN 
                                                            `exam_type` AS `et` ON `class`.`exam_type_exam_type_id` = `et`.`exam_type_id`
                                                        INNER JOIN
                                                            `year` AS `y` ON `class`.`year_year_id` = `y`.`year_id`
                                                        INNER JOIN 
                                                            `medium` AS `m` ON `class`.`medium_medium_id` = `m`.`medium_id`
                                                        INNER JOIN
                                                            `time_table_status` AS `tts` ON `tt`.`time_table_status_time_table_status_id` = `tts`.`time_table_status_id`
                                                        INNER JOIN
                                                            `lecture_type` AS `lt` ON `tt`.`lecture_type_lecture_type_id` = `lt`.`lecture_type_id`        
                                                        
                                                        INNER JOIN 
                                                            `users` AS `u` ON `class`.`tutor_id` = `u`.`user_id`
                                                        INNER JOIN 
                                                            `title` AS `t` ON `u`.`title_title_id` = `t`.`title_id`
                                                        INNER JOIN `students_has_classes` AS `shc` ON `class`.`class_id` = `shc`.`classes_class_id`
                                                        WHERE 
                                                            `shc`.`students_student_id` = '" . $sessionUserObject["student_id"] . "'
                                                            AND `shc`.`status_status_id` = 1
                                                            AND lt.lecture_type_id != 3
                                                            AND (`tt`.`date` <= '" . $now . "' OR `tt`.`date` <= '" . $dateModfy . "')
                                                            ORDER BY tt.date DESC
                                                    ";

                                                        $lectureSchedule = Database::search($query);

                                                        if ($lectureSchedule->num_rows > 0) {
                                                            date_default_timezone_set('Asia/Colombo');
                                                            $now = new DateTime();

                                                            while ($lectureScheduleData = $lectureSchedule->fetch_assoc()) {
                                                                $formattedEndTime = DateTime::createFromFormat('H:i:s', $lectureScheduleData["end_time"])->format('h:i A');
                                                                $formattedStartTime = DateTime::createFromFormat('H:i:s', $lectureScheduleData["start_time"])->format('h:i A');

                                                                $startDateTime = new DateTime($lectureScheduleData['date'] . ' ' . $lectureScheduleData['start_time']);
                                                                $endDateTime = new DateTime($lectureScheduleData['date'] . ' ' . $lectureScheduleData['end_time']);

                                                                $isOngoingLecture = false;

                                                                if ($lectureScheduleData["time_table_status_id"] == 1 && $lectureScheduleData["lecture_type"] == 2) {
                                                                    if ($now > $startDateTime && $now < $endDateTime) {
                                                                        $isOngoingLecture = true;
                                                                    }
                                                                }
                                                        ?>

                                                                <div class="col-12 col-md-4 col-lg-3 mb-4">
                                                                    <div class="component-theme lecture-card <?= $isOngoingLecture ? 'active-lecture' : '' ?>">
                                                                        <!-- Card Header -->
                                                                        <div class="card-header d-flex justify-content-between align-items-center">
                                                                            <div>
                                                                                <h3 class="text-16"><?= $lectureScheduleData["class_name"] ?></h3>
                                                                                <p class="card-subtitle"><?= $lectureScheduleData['medium'] ?> - <?= $lectureScheduleData['year'] ?>/<?= $lectureScheduleData['exam_type'] ?></p>
                                                                            </div>
                                                                            <?php if ($isOngoingLecture): ?>
                                                                                <div class="live-badge">
                                                                                    <span class="live-pulse"></span>
                                                                                    LIVE
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>

                                                                        <!-- Card Body -->
                                                                        <div class="card-body">
                                                                            <!-- Date Row -->
                                                                            <div class="info-row">
                                                                                <div class="d-flex align-items-center justify-content-center hwr-35 me-3 bg-light-theme">
                                                                                    <iconify-icon icon="uil:calender" class="dark-theme"></iconify-icon>
                                                                                </div>
                                                                                <span class="info-text"><?= date('F j, Y', strtotime($lectureScheduleData["date"])) ?></span>
                                                                            </div>

                                                                            <!-- Time Row -->
                                                                            <div class="info-row">
                                                                                <div class="d-flex align-items-center justify-content-center hwr-35 me-3 bg-light-theme">
                                                                                    <iconify-icon icon="mingcute:time-fill" class="dark-theme"></iconify-icon>
                                                                                </div>
                                                                                <span class="info-text">
                                                                                    <?= date('h:i A', strtotime($lectureScheduleData["start_time"])) ?> -
                                                                                    <?= date('h:i A', strtotime($lectureScheduleData["end_time"])) ?>
                                                                                </span>
                                                                            </div>

                                                                            <!-- Lecturer Row -->
                                                                            <div class="info-row">
                                                                                <?php if (!empty($lectureScheduleData["profile_img"]) && file_exists(Project::getAssetsPath() . "/imgs/users/" . $lectureScheduleData['profile_img'])): ?>
                                                                                    <img src="<?= Project::getAssetsPath() ?>/imgs/users/<?= $lectureScheduleData["profile_img"] ?>"
                                                                                        class="hwr-35 me-3"
                                                                                        alt="<?= $lectureScheduleData["user_fname"] ?>">
                                                                                <?php else: ?>
                                                                                    <div class="d-flex align-items-center justify-content-center hwr-35 me-3 bg-light-theme text-14 fw-semibold dark-theme">
                                                                                        <?= strtoupper(substr($lectureScheduleData["user_fname"], 0, 1)) . strtoupper(substr($lectureScheduleData["user_lname"], 0, 1)) ?>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                                <span class="text-16">
                                                                                    <?= $lectureScheduleData["title"] ?>. <?= $lectureScheduleData["user_fname"] ?> <?= $lectureScheduleData["user_lname"] ?>
                                                                                </span>
                                                                            </div>

                                                                            <!-- Status Row -->
                                                                            <div class="info-row">
                                                                                <?php
                                                                                if ($lectureScheduleData['time_table_status_id'] == 1) {
                                                                                    switch ($lectureScheduleData["lecture_type"]):
                                                                                        case 1: ?>
                                                                                            <div class="d-flex align-items-center justify-content-center hwr-35 me-3 bg-light-green">
                                                                                                <iconify-icon icon="material-symbols:home-work" class="text-dark-green"></iconify-icon>
                                                                                            </div>
                                                                                            <span class="text-16 text-dark-green">Physical Class</span>
                                                                                            <?php break; ?>
                                                                                        <?php
                                                                                        case 2: ?>
                                                                                            <div class="d-flex align-items-center justify-content-center hwr-35 me-3 bg-light-purple">
                                                                                                <iconify-icon icon="heroicons-solid:status-online" class="text-dark-purple"></iconify-icon>
                                                                                            </div>
                                                                                            <span class="text-16 text-dark-purple">Online Class</span>
                                                                                            <?php break; ?>
                                                                                        <?php
                                                                                        case 3: ?>
                                                                                            <div class="d-flex align-items-center justify-content-center hwr-35 me-3 bg-light-orange">
                                                                                                <iconify-icon icon="heroicons-solid:video-camera" class="text-dark-orange"></iconify-icon>
                                                                                            </div>
                                                                                            <span class="text-16 text-dark-orange">Recording</span>
                                                                                            <?php break; ?>
                                                                                        <?php endswitch;
                                                                                } else {
                                                                                    switch ($lectureScheduleData["time_table_status_id"]):
                                                                                        case 2: ?>
                                                                                            <div class="d-flex align-items-center justify-content-center hwr-35 me-3 bg-light-red">
                                                                                                <iconify-icon icon="proicons:filter-cancel" class="text-dark-red"></iconify-icon>
                                                                                            </div>
                                                                                            <span class="text-16 text-dark-red">Cancelled</span>
                                                                                            <?php break; ?>
                                                                                        <?php
                                                                                        case 3: ?>
                                                                                            <div class="d-flex align-items-center justify-content-center hwr-35 me-3 bg-light-pink">
                                                                                                <iconify-icon icon="icon-park-solid:leaves-one" class="text-dark-pink"></iconify-icon>
                                                                                            </div>
                                                                                            <span class="text-16 text-dark-pink">Postponed</span>
                                                                                            <?php break; ?>
                                                                                        <?php
                                                                                        case 4: ?>
                                                                                            <div class="d-flex align-items-center justify-content-center hwr-35 me-3 bg-light-purple">
                                                                                                <iconify-icon icon="hugeicons:study-lamp" class="text-dark-purple"></iconify-icon>
                                                                                            </div>
                                                                                            <span class="text-16 text-dark-purple">Study Leave</span>
                                                                                            <?php break; ?>
                                                                                <?php endswitch;
                                                                                }
                                                                                ?>
                                                                            </div>
                                                                        </div>

                                                                        <!-- Card Footer -->
                                                                        <div class="card-footer">
                                                                            <button class="table-btn table-btn-dark-theme" onclick="window.location.href='time-table-class-details?time-table-id=<?= $lectureScheduleData['time_table_id'] ?>'">View Details</button>
                                                                            <?php if ($isOngoingLecture): ?>
                                                                                <button class="table-btn bg-dark-theme text-white mx-3 me-0"
                                                                                    onclick="window.open('<?= $lectureScheduleData['lecture'] ?>', '_blank')">
                                                                                    Join Now
                                                                                </button>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php
                                                            }
                                                        } else {
                                                            ?>
                                                            <div class="col-12 h-100">
                                                                <div class="row p-5 component-theme p-0 m-0 align-items-center justify-content-center h-100">
                                                                    <img src="<?= Project::getAssetsPath() ?>/imgs/no-data.png" class="no-data-icon" alt="No data" class="empty-icon" />
                                                                </div>
                                                            </div>
                                                        <?php
                                                        }

                                                        ?>
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

                <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/functions.js?v=<?= Project::getVersion() ?>"></script>
            </body>

            </html>
<?php


        }
    }
}
?>