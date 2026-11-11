<?php
// Watch a class recording (student), laid out like the ENLIXO V3 student portal recording view: the player with its
// class and status under it, "About this recording", the class notes of that day and more recordings of the class.
// The player rules are unchanged: watch-time limit (recording-guard.php), Recording Types player, old links, Bunny.
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
require_once "../includes/enlixo/recording-player.php";
require_once "../includes/enlixo/student-recordings.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Student');
$studentId = (int) $sessionUserObject['student_id'];
$recording_id = (int) ($_GET['recording-id'] ?? 0);
$kind = 'lecture';

$recording = Database::search("SELECT * FROM `lecture_recording`
    INNER JOIN `time_table` ON `lecture_recording`.`time_table_time_table_id` = `time_table`.`time_table_id`
    INNER JOIN `classes` ON `time_table`.`classes_class_id` = `classes`.`class_id`
    WHERE `lecture_recording`.`lecture_recording_id` = '" . $recording_id . "'
    AND `classes`.`class_id` IN (SELECT `classes_class_id` FROM `students_has_classes` WHERE `students_student_id` = '" . $studentId . "' AND `status_status_id` = 1)");
$recordingData = $recording ? $recording->fetch_assoc() : null;
$all = $recordingData ? sr_lecture_recordings($studentId, (int) $recordingData['class_id']) : [];
$rec = null;
foreach ($all as $r) {
    if ($r['id'] === $recording_id) {
        $rec = $r;
    }
}
if ($recordingData === null || $rec === null) {
    header("Location: ./recordings");
    exit;
}
$more = array_slice(array_values(array_filter($all, fn ($r) => $r['id'] !== $recording_id)), 0, 4);

$files = [];
$att = Database::search("SELECT `attachment`, `attachment_name` FROM `attachments` WHERE `time_table_time_table_id` = '" . (int) $recordingData['time_table_id'] . "'");
while ($att && ($a = $att->fetch_assoc())) {
    $files[] = [(string) $a['attachment'], (string) $a['attachment_name']];
}

[, $badge, $badgeClass] = sr_lecture_state($rec);
$watchText = ['watched' => ['Watched', 'is-good'], 'attended' => ['Attended the class', 'is-good'], 'absent' => ['Missed the class', 'is-warn'], 'none' => ['Not watched yet', '']][$rec['watch']];
$lock = $rec['lock'];
$available = $lock['state'] === 'open'
    ? ($lock['expire_text'] !== '' ? 'Until ' . $lock['expire_text'] . ($lock['days_left'] === 0 ? ' (last day)' : ' (' . $lock['days_left'] . ($lock['days_left'] === 1 ? ' day' : ' days') . ' left)') : 'No end date')
    : ($lock['state'] === 'locked' ? 'Opens ' . $lock['unlock_text'] : 'Closed on ' . $lock['expire_text']);

$lf = ['title' => $rec['title'], 'menu' => 'Class Recordings', 'js' => ['js/watch.js'], 'modules' => [Project::getAssetsPath() . '/js/enlixo/pages/app-watch-recording.js']];
include __DIR__ . '/includes/learning-frame-top.php';
sl_page_title($rec['title'], [
    ['Class Recordings', './recordings'],
    [$rec['class'], './recordings?class=' . $rec['class_id']],
    [$rec['date_text'], null],
], '', '<a class="custom-outline-btn" href="./time-table-class-details?time-table-id=' . $rec['time_table_id'] . '">' . enlixo_icon('calendar', 18) . 'Class Day Details</a>');
?>
                            <div class="ac-grid-2">
                                <div class="ac-stack">
<?php include __DIR__ . '/includes/watch-player.php'; ?>
                                    <div class="ac-card">
                                        <div class="sr-tags mb-2">
                                            <span class="ac-badge <?= $lock['watchable'] ? 'is-good' : ($lock['state'] === 'expired' ? 'is-bad' : 'is-purple') ?>"><?= $lock['watchable'] ? 'Available' : ($lock['state'] === 'expired' ? 'Expired' : 'Opens later') ?></span>
                                            <span class="ac-badge <?= $watchText[1] ?>"><?= sl_e($watchText[0]) ?></span>
                                            <span class="sr-kind"><?= sl_e($rec['type']) ?> recording</span>
                                        </div>
                                        <h2 class="sr-view-title"><?= sl_e($rec['class']) ?></h2>
                                        <div class="sr-meta">
                                            <span><?= enlixo_icon('calendar', 15, 'sl-ico') ?><?= sl_e($rec['date_text']) ?></span>
                                            <?php if ($rec['time_text'] !== '') { ?><span><?= enlixo_icon('clock', 15, 'sl-ico') ?><?= sl_e($rec['time_text']) ?></span><?php } ?>
                                            <?php if ($rec['tutor'] !== '') { ?><span><?= enlixo_icon('teacher', 15, 'sl-ico') ?><?= sl_e($rec['tutor']) ?></span><?php } ?>
                                        </div>
                                        <?php if (trim(strip_tags($rec['description'])) !== '') { ?><p class="sr-description"><?= nl2br(sl_e(trim(strip_tags($rec['description'])))) ?></p><?php } ?>
                                    </div>
                                </div>

                                <div class="ac-stack">
                                    <div class="ac-card">
                                        <div class="ac-card-head"><h2 class="ac-card-title">About this recording</h2></div>
                                        <?php sr_facts([
                                            ['check', 'Availability', $available],
                                            ['play', 'Your status', $watchText[0]],
                                            ['hourglass', 'Length', $rec['duration']],
                                            ['recordings', 'Plays on', $rec['type']],
                                            ['upload', 'Added', $rec['added_at'] ? date('j M Y', strtotime($rec['added_at'])) : ''],
                                        ]); ?>
                                    </div>
                                    <?php sr_files($files, 'lectures-attachments'); ?>
                                    <?php sr_more($more, 'lecture', './recordings?class=' . $rec['class_id']); ?>
                                </div>
                            </div>
<?php
include __DIR__ . '/includes/learning-frame-bottom.php';
