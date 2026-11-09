<?php
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
} else {
    $sessionUserObject = SessionUser::getCurrentUserObject('Student');

    if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['time-table-id']) && !empty($_GET['time-table-id']))) {
        header("Location: ./news");
    } else {
        $id = $_GET["time-table-id"];
        $dateTime = new DateTime('now', new DateTimeZone('Asia/Colombo'));
        $lecture = Database::search("
                                                        SELECT 
                                                            `tt`.`time_table_id`,
                                                            `s`.`subject`,
                                                            `t`.`title`,
                                                            `u`.`user_fname`,
                                                            `u`.`user_lname`,
                                                            `s`.`subject`,
                                                            `tt`.`date`,
                                                            `tt`.`start_time`,
                                                            `tt`.`end_time`,
                                                            `tt`.`lecture`,
                                                            `tt`.`lecture_type_lecture_type_id` AS `lecture_type`,
                                                            `tt`.`time_table_status_time_table_status_id` AS `time_table_status`,
                                                            `u`.`profile_img`,
                                                            `tts`.`time_table_status`,
                                                            `tts`.`time_table_status_id`
                                                            
                                                        FROM `time_table` AS `tt`
                                                        INNER JOIN `classes` AS `class` 
                                                            ON `tt`.`classes_class_id` = `class`.`class_id`
                                                        INNER JOIN `users` AS `u` 
                                                            ON `class`.`tutor_id` = `u`.`user_id`
                                                        INNER JOIN `title` AS `t` 
                                                            ON `u`.`title_title_id` = `t`.`title_id`
                                                        INNER JOIN `students_has_classes` AS `shc` 
                                                            ON `class`.`class_id` = `shc`.`classes_class_id`
                                                        INNER JOIN `subjects` AS `s` 
                                                            ON `class`.`subjects_subject_id` = `s`.`subject_id`
                                                        INNER JOIN `time_table_status` AS `tts` 
                                                            ON `tt`.`time_table_status_time_table_status_id` = `tts`.`time_table_status_id`
                                                        AND `tt`.`time_table_id` = '" . $id . "' 
                                                        
                                                    ");
        if ($lecture->num_rows == 0) {
            header("Location: ./class-schedule");
        } else {
            $lectureData = $lecture->fetch_assoc();

            function formatDuration($minutes)
            {
                $hours = floor($minutes / 60);
                $mins = $minutes % 60;

                if ($hours > 0 && $mins > 0) {
                    return "{$hours}h {$mins}min";
                } elseif ($hours > 0) {
                    return "{$hours}h";
                } else {
                    return "{$mins}min";
                }
            }
            function timeAgo($datetime)
            {
                $timestamp = strtotime($datetime);
                $difference = time() - $timestamp;

                if ($difference < 60) {
                    return $difference . " seconds ago";
                } elseif ($difference < 3600) {
                    return floor($difference / 60) . " minutes ago";
                } elseif ($difference < 86400) {
                    return floor($difference / 3600) . " hours ago";
                } else {
                    return floor($difference / 86400) . " days ago";
                }
            }
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Class Time Table - <?= Project::getPortalBasePath() ?></title>
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/lecture-card.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css">
                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>

                <style>
                    .lecture-card {
                        border-radius: 0px !important;
                        border: none !important;
                    }

                    .lecturer-img {
                        width: 80px;
                        height: 80px;
                        border-radius: 50%;
                        object-fit: cover;
                    }

                    .detail-icon {
                        width: 24px;
                        height: 24px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        border-radius: 6px;
                        background: rgba(13, 110, 253, 0.1);
                        color: #0d6efd;
                    }

                    .status-badge {
                        padding: 6px 12px;
                        border-radius: 20px;
                        font-size: 12px;
                        font-weight: 500;
                    }

                    .lecture-type {
                        padding: 6px 12px;
                        border-radius: 6px;
                        font-size: 12px;
                        font-weight: 500;
                        background: #f8f9fa;
                    }

                    .time-badge {
                        background: var(--body-color);
                        border-radius: 6px;
                        padding: 8px 12px;
                        font-size: 14px;
                    }

                    .lecture-content {
                        border-left: 3px solid #0d6efd;
                        background: #f8fafc;
                        border-radius: 0 8px 8px 0;
                    }

                    .back-btn {
                        transition: all 0.2s ease;
                    }

                    .back-btn:hover {
                        transform: translateX(-3px);
                    }
                </style>
            </head>

            <body class="overflow-hidden">
                <!-- Loader Section -->

                <!-- Main Content Section (Initially Hidden) -->
                <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                    <div id="appContainer" class="row p-0">
                        <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                            <?php
                            include "includes/side-menu.php";
                            load_side_menu::load("Time Table");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("Time Table");
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
                                            <div class="row p-0 m-0 g-0 align-items-center">

                                                <div class="col">
                                                    <span class="main-topic lh-lg">Lecture Details</span>
                                                    <span class="text-14 sub-text-color lh-base mt-1 d-block">View all details about this scheduled lecture</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Main Content -->
                                        <div class="col-12 p-0 m-0 g-0">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-lg-8 p-0 pe-lg-3 mb-4 mb-lg-0">
                                                    <div class="card lecture-card">
                                                        <div class="card-body p-4">
                                                            <div class="d-flex align-items-start mb-4">
                                                                <?php if (!empty($lectureData["profile_img"]) && file_exists(Project::getAssetsPath() . "/users/" . $lectureData['profile_img'])): ?>
                                                                    <img src="<?= Project::getAssetsPath() . "/users/" . $lectureData['profile_img'] ?>"
                                                                        class="lecturer-img me-3"
                                                                        alt="<?= $lectureData["user_fname"] ?>">
                                                                <?php else: ?>
                                                                    <div class="d-flex align-items-center justify-content-center lecturer-img me-3 bg-light-theme text-24 fw-semibold dark-theme">
                                                                        <?= strtoupper(substr($lectureData["user_fname"], 0, 1)) . strtoupper(substr($lectureData["user_lname"], 0, 1)) ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div class="flex-grow-1">
                                                                    <h4 class="mb-1"><?= htmlspecialchars($lectureData['title'] . '.' . $lectureData['user_fname'] . ' ' . $lectureData['user_lname']) ?></h4>

                                                                    <div class="d-flex align-items-center flex-wrap gap-2 mt-3">
                                                                        <span class="table-btn table-btn-primary"><?= htmlspecialchars($lectureData['subject']) ?></span>

                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row mt-4">
                                                                <div class="col-md-6 mb-3">
                                                                    <div class="d-flex align-items-center">
                                                                        <div class="d-flex align-items-center justify-content-center hwr-40 bg-light-theme me-3">
                                                                            <iconify-icon icon="uil:calender" class="dark-theme"></iconify-icon>
                                                                        </div>
                                                                        <div>
                                                                            <small class="text-muted d-block">Date</small>
                                                                            <strong><?= date('F j, Y', strtotime($lectureData['date'])) ?></strong>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-6 mb-3">
                                                                    <div class="d-flex align-items-center">
                                                                        <div class="d-flex align-items-center justify-content-center hwr-40 bg-light-theme me-3">
                                                                            <iconify-icon icon="mingcute:time-fill" class="dark-theme"></iconify-icon>
                                                                        </div>
                                                                        <div>
                                                                            <small class="text-muted d-block">Time</small>
                                                                            <strong><?= date('h:i A', strtotime($lectureData['start_time'])) ?> - <?= date('h:i A', strtotime($lectureData['end_time'])) ?></strong>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-6 mb-3">
                                                                    <div class="d-flex align-items-center">
                                                                        <?php
                                                                        if ($lectureData['lecture_type'] == 1) {
                                                                        ?>
                                                                            <div class="d-flex align-items-center justify-content-center hwr-40 bg-light-green me-3">
                                                                                <iconify-icon icon="material-symbols:home-work" class="text-dark-green"></iconify-icon>
                                                                            </div>
                                                                            <div>
                                                                                <small class="text-muted d-block">Lecture Type</small>
                                                                                <strong class="text-dark-green">Physical</strong>
                                                                            </div>
                                                                        <?php
                                                                        } else {
                                                                        ?>
                                                                            <div class="d-flex align-items-center justify-content-center hwr-40 bg-light-purple me-3">
                                                                                <iconify-icon icon="heroicons-solid:status-online" class="text-dark-purple"></iconify-icon>
                                                                            </div>
                                                                            <div>
                                                                                <small class="text-muted d-block">Lecture Type</small>
                                                                                <strong class="text-dark-purple">Online</strong>
                                                                            </div>
                                                                        <?php
                                                                        }
                                                                        ?>

                                                                    </div>
                                                                </div>

                                                                <div class="col-md-6 mb-3">
                                                                    <div class="d-flex align-items-center">
                                                                        <?php
                                                                        switch ($lectureData["time_table_status_id"]):
                                                                            case 1: ?>
                                                                                <div class="d-flex align-items-center justify-content-center hwr-40 bg-light-green me-3">
                                                                                    <iconify-icon icon="flowbite:bell-active-outline" class="text-dark-green"></iconify-icon>
                                                                                </div>
                                                                                <div>
                                                                                    <small class="text-muted d-block">Lecture Status</small>
                                                                                    <strong class="text-dark-green">Active</strong>
                                                                                </div>
                                                                                <?php break; ?>
                                                                            <?php
                                                                            case 2: ?>
                                                                                <div class="d-flex align-items-center justify-content-center hwr-40 bg-light-red me-3">
                                                                                    <iconify-icon icon="proicons:filter-cancel" class="text-dark-red"></iconify-icon>
                                                                                </div>
                                                                                <div>
                                                                                    <small class="text-muted d-block">Lecture Status</small>
                                                                                    <strong class="text-dark-red">Cancelled</strong>
                                                                                </div>
                                                                                <?php break; ?>
                                                                            <?php
                                                                            case 3: ?>
                                                                                <div class="d-flex align-items-center justify-content-center hwr-40 bg-light-pink me-3">
                                                                                    <iconify-icon icon="icon-park-solid:leaves-one" class="text-dark-pink"></iconify-icon>
                                                                                </div>
                                                                                <div>
                                                                                    <small class="text-muted d-block">Lecture Status</small>
                                                                                    <strong class="text-dark-pink">Postpond</strong>
                                                                                </div>
                                                                                <?php break; ?>
                                                                            <?php
                                                                            case 4: ?>
                                                                                <div class="d-flex align-items-center justify-content-center hwr-40 bg-light-purple me-3">
                                                                                    <iconify-icon icon="hugeicons:study-lamp" class="text-dark-purple"></iconify-icon>
                                                                                </div>
                                                                                <div>
                                                                                    <small class="text-muted d-block">Lecture Status</small>
                                                                                    <strong class="text-dark-purple">Study Leave</strong>
                                                                                </div>
                                                                                <?php break; ?>
                                                                        <?php endswitch;
                                                                        ?>
                                                                    </div>
                                                                </div>

                                                                <?php

                                                                if ($lectureData['lecture_type'] == 2 && $lectureData['date'] == $dateTime->format('Y-m-d') && $lectureData['start_time'] <= $dateTime->format('H:i:s') && $lectureData['end_time'] >= $dateTime->format('H:i:s')) {
                                                                ?>
                                                                    <div class="col-12 col-lg-6">
                                                                        <div class="d-flex py-3">
                                                                            <button class="table-btn bg-dark-theme text-white px-5"
                                                                                onclick="joinLecture('<?= $lectureData['lecture'] ?>',<?= $lectureData['time_table_id'] ?>)">Join Now</button>
                                                                        </div>
                                                                    </div>
                                                                <?php
                                                                }

                                                                $attachment = Database::search("SELECT * FROM `attachments` WHERE `time_table_time_table_id` = '" . $lectureData['time_table_id'] . "'");
                                                                if ($attachment->num_rows > 0) {
                                                                ?>
                                                                    <div class="col-12">
                                                                        <div class="d-flex">
                                                                            <div class="flex-grow-1">
                                                                                <small class="text-muted d-block">Lecture Content</small>
                                                                                <div class="col-12 px-1 mt-2">
                                                                                    <div class="row">
                                                                                        <?php
                                                                                        while ($attachmentData = $attachment->fetch_assoc()) {
                                                                                            if (!empty($attachmentData["attachment"]) && file_exists(Project::getAssetsPath() . "/uploads/lectures-attachments/" . $attachmentData["attachment"])) {
                                                                                                $ext = strtolower(pathinfo($attachmentData["attachment"], PATHINFO_EXTENSION));
                                                                                        ?>
                                                                                                <div class="col-12 my-3 file-item cursor-pointer" onclick="viewLectureAttachment('<?php echo $attachmentData['attachment'] ?>', '<?php echo $attachmentData['attachment_name'] ?>')">
                                                                                                    <div class="p-3 bl-solid">
                                                                                                        <div class="d-flex">
                                                                                                            <img src="../assets/imgs/icons/<?= $ext ?>.png" alt="Lecture Attachment" class="h-48 w-auto">
                                                                                                            <div class="d-flex flex-column px-4">
                                                                                                                <span class="name"><?php echo $attachmentData["attachment_name"] ?></span>
                                                                                                                <span class="text-muted text-12">Click to view</span>
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
                                                                        </div>
                                                                    </div>
                                                                <?php
                                                                }


                                                                ?>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <?php

                                                    $query = "
                                                               SELECT 
                                                                   `lr`.`lecture_recording_id`,
                                                                   `lr`.`added_at`,
                                                                   `lr`.`recording_title`,
                                                                   `lr`.`recording_cover`,
                                                                   `lr`.`expire_date`,
                                                                   `tt`.`date`,
                                                                   `tt`.`start_time`,
                                                                   `tt`.`end_time`,
                                                                   `u`.`user_fname`,
                                                                   `u`.`user_lname`,
                                                                   `u`.`profile_img`,
                                                                   `t`.`title`,
                                                                   `lt`.`lecture_type`,
                                                                   `tt`.`duration`,
                                                                   `tt`.`time_table_status_time_table_status_id`,
                                                                   `tt`.`lecture_title`,
                                                                   `tt`.`date`,
                                                                   `tt`.`start_time`,
                                                                   `tt`.`end_time`,
                                                                   `tt`.`time_table_id`
                                                                  
                                                                   
                                                               FROM `lecture_recording` AS `lr`
                                                               INNER JOIN `time_table` AS `tt`
                                                                   ON `lr`.`time_table_time_table_id` = `tt`.`time_table_id`
                                                            INNER JOIN `classes` AS `class`
                                                                ON `tt`.`classes_class_id` = `class`.`class_id`
                                                              
                                                               INNER JOIN `users` AS `u`
                                                                   ON `class`.`tutor_id` = `u`.`user_id`
                                                               INNER JOIN `title` AS `t`
                                                                   ON `u`.`title_title_id` = `t`.`title_id`
                                                               INNER JOIN `lecture_type` AS `lt`
                                                                   ON `tt`.`lecture_type_lecture_type_id` = `lt`.`lecture_type_id`
                                                                                                                           
                                                               AND `tt`.`time_table_id` = '" . $lectureData["time_table_id"] . "'
                                                                                                                             
                                                           ";

                                                    $recordings = Database::search($query);
                                                    if ($recordings->num_rows > 0) {
                                                    ?>
                                                        <div class="col-12 mt-4">
                                                            <div class="row g-3">
                                                                <small class="sub-topic">Lecture Recordings</small>
                                                                <?php
                                                                $partsEnabled = $recordings->num_rows > 1 ? true : false;
                                                                for ($i = 0; $i < $recordings->num_rows; $i++) {
                                                                    $recording = $recordings->fetch_assoc();

                                                                    $lectureDate = $recording["date"] . " | " .
                                                                        (new DateTime($recording["start_time"]))->format('h:i A') . " - " .
                                                                        (new DateTime($recording["end_time"]))->format('h:i A');

                                                                    $attendStatus = "Absent (Live Lecture)";
                                                                    $attendStatusId = '2';

                                                                    $isAttendLecture = Database::search("SELECT * FROM `attendance` WHERE `time_table_time_table_id`='" . $recording['time_table_id'] . "' AND `students_student_id`='" . $sessionUserObject["student_id"] . "'");
                                                                    if ($isAttendLecture->num_rows > 0) {
                                                                        $data = $isAttendLecture->fetch_assoc();
                                                                        if ($data['attendance_status_attendance_status_id'] == 2) {
                                                                            $attendStatus = "Present (Live Lecture)";
                                                                            $attendStatusId = '1';
                                                                        } else if ($data['attendance_status_attendance_status_id'] == 4) {
                                                                            $attendStatus = "Recording Watched";
                                                                            $attendStatusId = '3';
                                                                        }
                                                                    }

                                                                ?>
                                                                    <div class="col-12 col-lg-6">
                                                                        <div class="student-details-card position-relative px-4 py-3 component-theme">
                                                                            <div class="student-details-ribbon 
        <?php
                                                                    echo match ($attendStatusId) {
                                                                        '1' => 'ribbon-active',
                                                                        '2' => 'ribbon-deactive',
                                                                        '3' => 'ribbon-watched',
                                                                        default => 'ribbon-default'
                                                                    };
        ?>"> <?= htmlspecialchars($attendStatus ?? 'Unknown') ?>
                                                                            </div>
                                                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                                                <div class="col-12 d-flex flex-column align-items-center p-0 m-0 g-0">
                                                                                    <div class="lecture-thumbnail">
                                                                                        <?php
                                                                                        if (empty($recording['recording_cover']) || !file_exists(Project::getAssetsPath() . '/imgs/recording-thumbnail/' . $recording["recording_cover"])) {
                                                                                        ?>
                                                                                            <img src="../assets/imgs/common/empty-thumbnail.webp" alt="Lecture Thumbnail" class="thumbnail-img">
                                                                                        <?php
                                                                                        } else {
                                                                                        ?>
                                                                                            <img src="<?= Project::getAssetsPath() . '/imgs/recording-thumbnail/' . $recording["recording_cover"] ?>" alt="Lecture Thumbnail" class="thumbnail-img">
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                        <div class="recording-pulse"></div>
                                                                                        <div class="play-icon">
                                                                                            <iconify-icon icon="line-md:play-filled" width="60" height="60" class="dark-theme"></iconify-icon>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-12 d-flex flex-column px-0 mt-3 gap-3 align-content-center justify-content-center">
                                                                                    <h6 class="text-18 text-dark-color fw-semibold m-0"><?= $recording["lecture_title"]; ?></h6>

                                                                                    <div class="d-flex flex-column gap-3 mt-3">
                                                                                        <div class="student-details-item d-flex align-items-center gap-2">
                                                                                            <iconify-icon icon="hugeicons:user-ai" width="20" height="20" class="dark-theme"></iconify-icon>
                                                                                            <span class="student-details-intake text-13 sub-text">Lecturer : <?= $recording['title'] . " " . $recording['user_fname'] . " " . $recording['user_lname'] ?>
                                                                                            </span>
                                                                                        </div>

                                                                                        <div class="student-details-item d-flex align-items-center gap-2">
                                                                                            <iconify-icon icon="hugeicons:date-time" width="20" height="20" class="dark-theme"></iconify-icon>
                                                                                            <span class="student-details-intake text-13 sub-text">
                                                                                                Conducted Date: <?= date('Y-m-d', strtotime($recording['date'])) ?><?= date('h:i A', strtotime($recording['start_time'])) ?>-<?= date('h:i A', strtotime($recording['end_time'])) ?>
                                                                                            </span>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="card-footer d-flex justify-content-end align-items-center p-0">

                                                                                        <button class="table-btn table-btn-dark-theme mt-2"
                                                                                            onclick='window.location.href = "watch-recording?recording-id=<?= $recording["lecture_recording_id"] ?>";'>
                                                                                            View
                                                                                        </button>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php
                                                                }
                                                                ?>

                                                            </div>
                                                        </div>
                                                    <?php
                                                    }

                                                    ?>
                                                </div>

                                                <div class="col-lg-4 p-0">
                                                    <div class="card lecture-card">
                                                        <div class="card-body p-4">
                                                            <h5 class="card-title mb-3">Upcoming Lectures</h5>
                                                            <div class="list-group list-group-flush">
                                                                <?php
                                                                $upcominglectures = Database::search("SELECT * FROM `time_table` 
                                                                    INNER JOIN classes ON `time_table`.`classes_class_id` = `classes`.`class_id`
                                                                    INNER JOIN students_has_classes ON `classes`.`class_id` = `students_has_classes`.`classes_class_id`
                                                                    INNER JOIN subjects ON `classes`.`subjects_subject_id` = `subjects`.`subject_id` 
                                                                    WHERE  CONCAT(`date`, ' ', `start_time`) >= '" . $dateTime->format("Y-m-d H:i:s") . "' ORDER BY `date` ASC, `start_time` ASC LIMIT 3");

                                                                if ($upcominglectures->num_rows > 0) {

                                                                    while ($upcominglecturesData = $upcominglectures->fetch_assoc()) {
                                                                        $upcominglecturesData["start_time"] = date("h:i A", strtotime($upcominglecturesData["start_time"]));
                                                                        $upcominglecturesData["end_time"] = date("h:i A", strtotime($upcominglecturesData["end_time"]));
                                                                        $upcominglecturesData["date"] = date("Y-m-d", strtotime($upcominglecturesData["date"]));

                                                                ?>
                                                                        <!-- Example upcoming lecture - you would replace with dynamic content -->
                                                                        <a href="lecture-details?lecture-id=<?= $upcominglecturesData["time_table_id"] ?>" class="list-group-item list-group-item-action border-0 p-3">
                                                                            <div class="d-flex align-items-center">
                                                                                <div class="time-badge me-3">
                                                                                    <small class="d-block text-center"><?= date('F', strtotime($upcominglecturesData["date"])) ?></small>
                                                                                    <strong class="d-block text-center"><?= date("d", strtotime($upcominglecturesData["date"])) ?></strong>
                                                                                </div>
                                                                                <div>
                                                                                    <small class="d-block text-muted"><?= $upcominglecturesData['class_name'] ?></small>
                                                                                    <small><?= $upcominglecturesData['start_time'] . ' - ' . $upcominglecturesData['end_time'] ?></small>
                                                                                </div>
                                                                            </div>
                                                                        </a>
                                                                    <?php
                                                                    }
                                                                } else {
                                                                    ?>
                                                                    <div class="col-12">
                                                                        <div class="row justify-content-center">
                                                                            <div class="col-6">
                                                                                <img src="./assets/imgs/lecture.png" class="w-100 h-auto" alt="Result Not Found" class="empty-icon" />
                                                                            </div>
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
                                        </div>
                                    </div>
                                    <div class="flex-grow-1"></div>
                                    <footer>
                                        <?php include './includes/footer.php' ?>
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