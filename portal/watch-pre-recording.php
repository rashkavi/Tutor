<?php
// Watch a pre-recorded class (student), laid out like the ENLIXO V3 student portal pre-recorded lecture view: the player,
// the class and progress under it, "About this lesson", its files and more lessons of the class.
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
$kind = 'pre';

$recording = Database::search("SELECT * FROM `pre_recordings`
    INNER JOIN `classes` ON `pre_recordings`.`classes_class_id` = `classes`.`class_id`
    WHERE `pre_recordings`.`pre_recording_id` = '" . $recording_id . "'
    AND `classes`.`class_id` IN (SELECT `classes_class_id` FROM `students_has_classes` WHERE `students_student_id` = '" . $studentId . "' AND `status_status_id` = 1)");
$recordingData = $recording ? $recording->fetch_assoc() : null;
$all = $recordingData ? sr_pre_recordings($studentId, (int) $recordingData['class_id']) : [];
$rec = null;
foreach ($all as $r) {
    if ($r['id'] === $recording_id) {
        $rec = $r;
    }
}
if ($recordingData === null || $rec === null) {
    header("Location: ./pre-recorded-classes");
    exit;
}
$more = array_slice(array_values(array_filter($all, fn ($r) => $r['id'] !== $recording_id)), 0, 4);

$files = [];
try {
    $att = Database::search("SELECT * FROM `pre_recordings_attachments` WHERE `pre_recordings_pre_recording_id` = '" . $recording_id . "'");
    while ($att && ($a = $att->fetch_assoc())) {
        $files[] = [(string) ($a['attachment'] ?? ''), (string) ($a['attachment_name'] ?? '')];
    }
} catch (Throwable $e) {
    $files = [];
}

$lock = $rec['lock'];
$statusText = ['completed' => ['Completed', 'is-good'], 'in_progress' => [$rec['progress'] . '% watched', 'is-warn'], 'new' => ['Not started', '']][$rec['status']];

$lf = ['title' => $rec['title'], 'menu' => 'Pre Recorded Classes', 'js' => ['js/watch.js'], 'modules' => [Project::getAssetsPath() . '/js/enlixo/pages/app-watch-recording.js']];
include __DIR__ . '/includes/learning-frame-top.php';
sl_page_title($rec['title'], [
    ['Pre Recorded Classes', './pre-recorded-classes'],
    [$rec['class'], './pre-recorded-classes?class=' . $rec['class_id']],
    ['Watch', null],
], '', '<a class="custom-outline-btn" href="./class-details?class-id=' . $rec['class_id'] . '">' . enlixo_icon('classes', 18) . 'Class Details</a>');
?>
                            <div class="ac-grid-2">
                                <div class="ac-stack">
<?php include __DIR__ . '/includes/watch-player.php'; ?>
                                    <div class="ac-card">
                                        <div class="sr-tags mb-2">
                                            <span class="ac-badge <?= $lock['watchable'] ? 'is-good' : 'is-purple' ?>"><?= $lock['watchable'] ? 'Available' : 'Opens later' ?></span>
                                            <span class="ac-badge <?= $statusText[1] ?>"><?= sl_e($statusText[0]) ?></span>
                                            <span class="sr-kind"><?= sl_e($rec['type']) ?> lesson</span>
                                        </div>
                                        <h2 class="sr-view-title"><?= sl_e($rec['class']) ?></h2>
                                        <div class="sr-meta">
                                            <?php if ($rec['duration'] !== '') { ?><span><?= enlixo_icon('hourglass', 15, 'sl-ico') ?><?= sl_e($rec['duration']) ?></span><?php } ?>
                                            <?php if ($rec['tutor'] !== '') { ?><span><?= enlixo_icon('teacher', 15, 'sl-ico') ?><?= sl_e($rec['tutor']) ?></span><?php } ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="ac-stack">
                                    <div class="ac-card">
                                        <div class="ac-card-head"><h2 class="ac-card-title">Your progress</h2></div>
                                        <div class="d-flex align-items-baseline gap-2 mb-2">
                                            <strong class="main-text" style="font-size: 30px; line-height: 1;"><?= (int) $rec['progress'] ?>%</strong>
                                            <span class="sub-text text-14"><?= $rec['status'] === 'completed' ? 'completed' : 'watched' ?></span>
                                        </div>
                                        <div class="sr-bar"><span class="<?= $rec['status'] === 'completed' ? 'is-good' : '' ?>" style="width: <?= (int) $rec['progress'] ?>%"></span></div>
                                        <span class="d-block sub-text text-12 mt-2"><?= $rec['status'] === 'completed' ? 'Well done, you finished this lesson.' : 'Your progress is saved while you watch. Watch ' . SR_COMPLETE_AT . '% to complete it.' ?></span>
                                    </div>
                                    <div class="ac-card">
                                        <div class="ac-card-head"><h2 class="ac-card-title">About this lesson</h2></div>
                                        <?php sr_facts([
                                            ['check', 'Availability', $lock['watchable'] ? 'Open now' : 'Opens ' . $lock['unlock_text']],
                                            ['hourglass', 'Length', $rec['duration']],
                                            ['recordings', 'Plays on', $rec['type']],
                                            ['history', 'Last watched', $rec['last_watched']],
                                            ['upload', 'Added', $rec['added_at'] ? date('j M Y', strtotime($rec['added_at'])) : ''],
                                        ]); ?>
                                    </div>
                                    <?php sr_files($files, 'recordings-attachments'); ?>
                                    <?php sr_more($more, 'pre', './pre-recorded-classes?class=' . $rec['class_id']); ?>
                                </div>
                            </div>
<?php include __DIR__ . '/includes/learning-frame-bottom.php';
