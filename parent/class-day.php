<?php
// Class day (parent): one class day from the calendar (a page, no pop-up): class, date and time, how it is taught,
// whether each of the parent's children in that class was present, and the recording when there is one.
// ?time-table-id=<id>. Only class days of the parent's own children open (includes/enlixo/parent-scope.php).
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
require_once "../includes/enlixo/parent-scope.php";
require_once "../includes/enlixo/stat-card.php";
if (!SessionUser::ifUserIsParent()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Parent');
$parentId = (int) $sessionUserObject['user_id'];
$id = (int) ($_GET['time-table-id'] ?? 0);
$res = Database::search(
    "SELECT t.time_table_id, t.date, t.start_time, t.end_time, t.lecture_title, t.lecture_type_lecture_type_id type, tts.time_table_status, t.time_table_status_time_table_status_id status_id,
            c.class_id, c.class_name, u.user_fname, u.user_lname,
            (SELECT r.lecture_recording_id FROM lecture_recording r WHERE r.time_table_time_table_id = t.time_table_id LIMIT 1) recording
       FROM time_table t
       JOIN classes c ON c.class_id = t.classes_class_id
       LEFT JOIN users u ON u.user_id = c.tutor_id
       LEFT JOIN time_table_status tts ON tts.time_table_status_id = t.time_table_status_time_table_status_id
      WHERE t.time_table_id = '" . $id . "'"
);
$day = $res && $res->num_rows ? $res->fetch_assoc() : null;
$kids = $day ? ps_class_children($parentId, (int) $day['class_id']) : [];
if (!$day || !$kids) {
    header("Location: ./time-table");
    exit;
}
$children = ps_children($parentId);
$e = 'sl_e';
$start = strtotime($day['date'] . ' ' . $day['start_time']);
$end = strtotime($day['date'] . ' ' . $day['end_time']);
$over = $end < time();
$type = match ((int) $day['type']) { 1 => 'Physical class', 2 => 'Online class', 3 => 'Recording', default => 'Class' };

$lf = [
    'title' => 'Class Day', 'menu' => 'Time Table',
    'side' => __DIR__ . '/includes/side-menu.php', 'header' => dirname(__DIR__) . '/includes/header.php',
    'css' => [Project::getAssetsPath() . '/css/parent-progress.css'],
];
include dirname(__DIR__) . '/portal/includes/learning-frame-top.php';
sl_page_title($day['class_name'], [['Time Table', './time-table'], [date('D, j M Y', $start), '']], '', '<a class="custom-outline-btn" href="./time-table">' . enlixo_icon('calendar', 18) . 'Time Table</a>');
?>
<div class="ac-card pp-hero">
    <div class="pp-hero-who">
        <span class="pp-day-date is-next" style="width:56px;height:60px"><strong><?= date('d', $start) ?></strong><small><?= date('M', $start) ?></small></span>
        <div class="min-w-0">
            <h2 class="pp-name"><?= $e($day['lecture_title'] ?: $day['class_name']) ?></h2>
            <span class="text-13 sub-text"><?= $e(date('l', $start) . ' · ' . date('g:i A', $start) . ' – ' . date('g:i A', $end) . ' · ' . $type) ?></span>
        </div>
    </div>
    <span class="ac-badge <?= (int) $day['status_id'] === 1 ? ($over ? 'is-good' : '') : 'is-bad' ?>"><?= $e((int) $day['status_id'] === 1 ? ($over ? 'Held' : 'Coming up') : ($day['time_table_status'] ?? 'Changed')) ?></span>
</div>
<div class="ac-grid-2">
    <div class="ac-card">
        <h2 class="ac-card-title">Attendance</h2>
        <ul class="pp-days">
            <?php foreach ($kids as $sid) {
                $a = (int) (Database::search("SELECT MAX(attendance_status_attendance_status_id) a FROM attendance WHERE time_table_time_table_id = '" . $id . "' AND students_student_id = '" . $sid . "'")->fetch_assoc()['a'] ?? 0);
                [$label, $cls] = !$over ? ['Not held yet', 'is-muted'] : match ($a) { 1, 2 => ['Present', 'is-good'], 3 => ['Absent', 'is-bad'], 4 => ['Watched recording', 'is-purple'], default => ['Not marked yet', 'is-muted'] }; ?>
                <li>
                    <span class="pp-avatar"><?= $e(strtoupper(mb_substr($children[$sid]['first_name'] ?? '?', 0, 1))) ?></span>
                    <span class="min-w-0"><strong><?= $e($children[$sid]['name'] ?? 'Child') ?></strong><small><a href="./child-progress?child-id=<?= $sid ?>">See progress</a></small></span>
                    <span class="ac-badge <?= $cls ?>"><?= $e($label) ?></span>
                </li>
            <?php } ?>
        </ul>
    </div>
    <div class="ac-card">
        <h2 class="ac-card-title">Details</h2>
        <ul class="pp-days">
            <li><span class="min-w-0"><strong>Class</strong><small><?= $e($day['class_name']) ?></small></span></li>
            <li><span class="min-w-0"><strong>Tutor</strong><small><?= $e(trim(($day['user_fname'] ?? '') . ' ' . ($day['user_lname'] ?? '')) ?: '—') ?></small></span></li>
            <li><span class="min-w-0"><strong>How it is taught</strong><small><?= $e($type) ?></small></span></li>
            <li><span class="min-w-0"><strong>Recording</strong><small><?= $day['recording'] ? 'Ready to watch' : 'No recording for this day' ?></small></span>
                <?php if ($day['recording']) { ?><a class="custom-btn table-btn" href="./watch-recording?recording-id=<?= (int) $day['recording'] ?>">Watch</a><?php } ?></li>
        </ul>
    </div>
</div>
<?php include dirname(__DIR__) . '/portal/includes/learning-frame-bottom.php';
