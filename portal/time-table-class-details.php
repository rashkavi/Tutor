<?php
// Class day details (student), laid out like the ENLIXO V3 student portal "Lecture Details" page: a summary card
// (status, type, class, tutor, date tile, time, countdown, Join Now / Watch Recording / Add to Calendar), then
// Class Day Details, Tutor and Class Notes on the left, My Attendance, the other classes that day and what is next in
// this class on the right. Opened from the time table, the dashboard, class details and the recording page.
// Join Now goes through api/joinLecture.php (the window is checked there; the page never shows the raw link).
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
require_once "../includes/enlixo/student-recordings.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Student');
$studentId = (int) $sessionUserObject['student_id'];
$id = (int) ($_GET['time-table-id'] ?? 0);

$res = Database::search("SELECT tt.`time_table_id`, tt.`date`, tt.`start_time`, tt.`end_time`, tt.`lecture`, tt.`lecture_title`,
        tt.`lecture_type_lecture_type_id` AS lt, tt.`time_table_status_time_table_status_id` AS st,
        c.`class_id`, c.`class_name`, c.`location`, c.`delivery_mode`, s.`subject`, y.`year`, e.`exam_type`, m.`medium`,
        u.`user_fname`, u.`user_lname`, u.`profile_img`, ti.`title`
    FROM `time_table` tt
    INNER JOIN `classes` c ON c.`class_id` = tt.`classes_class_id`
    INNER JOIN `students_has_classes` shc ON shc.`classes_class_id` = c.`class_id` AND shc.`students_student_id` = '" . $studentId . "' AND shc.`status_status_id` = 1
    LEFT JOIN `subjects` s ON s.`subject_id` = c.`subjects_subject_id`
    LEFT JOIN `year` y ON y.`year_id` = c.`year_year_id`
    LEFT JOIN `exam_type` e ON e.`exam_type_id` = c.`exam_type_exam_type_id`
    LEFT JOIN `medium` m ON m.`medium_id` = c.`medium_medium_id`
    LEFT JOIN `users` u ON u.`user_id` = c.`tutor_id`
    LEFT JOIN `title` ti ON ti.`title_id` = u.`title_title_id`
    WHERE tt.`time_table_id` = '" . $id . "' LIMIT 1");
$l = $res ? $res->fetch_assoc() : null;
if (!$l) {
    header("Location: ./time-table");
    exit;
}

$tz = new DateTimeZone('Asia/Colombo');
$now = new DateTime('now', $tz);
$start = new DateTime($l['date'] . ' ' . $l['start_time'], $tz);
$end = new DateTime($l['date'] . ' ' . $l['end_time'], $tz);
$joinFrom = (clone $start)->modify('-1 hour');
$statusId = (int) $l['st'];
$typeId = (int) $l['lt'];
$classId = (int) $l['class_id'];
$isActive = $statusId === 1;
$isLive = $isActive && $now >= $start && $now <= $end;
$isOver = $now > $end;

// status: Upcoming / Live now / Completed (held) / Cancelled / Postponed / Study leave
if ($statusId === 2) {
    [$statusLabel, $statusKey] = ['Cancelled', 'cancelled'];
} elseif ($statusId === 3) {
    [$statusLabel, $statusKey] = ['Postponed', 'postponed'];
} elseif ($statusId === 4) {
    [$statusLabel, $statusKey] = ['Study leave', 'postponed'];
} elseif ($isLive) {
    [$statusLabel, $statusKey] = ['Live now', 'live'];
} elseif ($isOver) {
    [$statusLabel, $statusKey] = ['Completed', 'completed'];
} else {
    [$statusLabel, $statusKey] = ['Upcoming', 'upcoming'];
}
[$typeLabel, $typeIcon] = [1 => ['In the hall', 'physical'], 2 => ['Online', 'online'], 3 => ['Recording', 'recordings']][$typeId] ?? ['Class', 'classes'];
$minutes = max(0, (int) round(($end->getTimestamp() - $start->getTimestamp()) / 60));
$duration = ($minutes >= 60 ? intdiv($minutes, 60) . ' hr ' : '') . ($minutes % 60 ? ($minutes % 60) . ' min' : '');
$duration = trim($duration) ?: '-';
$timeText = $start->format('g:i A') . ' - ' . $end->format('g:i A');
$tutor = trim(($l['title'] ? $l['title'] . ' ' : '') . $l['user_fname'] . ' ' . $l['user_lname']);
$initials = strtoupper(substr((string) $l['user_fname'], 0, 1) . substr((string) $l['user_lname'], 0, 1));

$whenText = '';
if ($isActive && !$isOver) {
    if ($isLive) {
        $left = (int) ceil(($end->getTimestamp() - $now->getTimestamp()) / 60);
        $whenText = 'Ends in ' . ($left >= 60 ? intdiv($left, 60) . ' hr ' : '') . ($left % 60) . ' min';
    } else {
        $days = (int) (new DateTime($now->format('Y-m-d'), $tz))->diff(new DateTime($start->format('Y-m-d'), $tz))->format('%a');
        $mins = (int) ceil(($start->getTimestamp() - $now->getTimestamp()) / 60);
        $whenText = $days === 0 ? 'Starts in ' . ($mins >= 60 ? intdiv($mins, 60) . ' hr ' : '') . ($mins % 60) . ' min' : ($days === 1 ? 'Tomorrow' : 'In ' . $days . ' days');
    }
}

// my attendance
$attRes = Database::search("SELECT MAX(`attendance_status_attendance_status_id`) s FROM `attendance` WHERE `time_table_time_table_id` = '" . $id . "' AND `students_student_id` = '" . $studentId . "'");
$att = (int) (($attRes ? $attRes->fetch_assoc() : null)['s'] ?? 0);
if (!$isActive) {
    $attendance = ['No attendance', 'This class day did not go ahead.', 'x', 'is-muted'];
} elseif ($att === 2) {
    $attendance = ['Present', 'You attended this class.', 'user-check', 'is-good'];
} elseif ($att === 4) {
    $attendance = ['Watched', 'You watched the recording of this class.', 'play', 'is-good'];
} elseif ($att === 1) {
    $attendance = ['Joined online', 'You joined online. Your tutor confirms it as Present.', 'hourglass', 'is-warn'];
} elseif ($att === 3) {
    $attendance = ['Absent', 'You were marked absent. Watch the recording to catch up.', 'user-cross', 'is-bad'];
} elseif ($now < $start) {
    $attendance = ['Not started', 'Attendance is marked once the class starts.', 'clock', 'is-muted'];
} elseif ($isLive) {
    $attendance = ['In progress', $typeId === 2 ? 'Join the class to be marked as joined.' : 'Your tutor marks attendance in the hall.', 'clock', 'is-warn'];
} else {
    $attendance = ['Not marked', 'No attendance was recorded for you yet.', 'question', 'is-muted'];
}

// the recording of this day (unless it has expired), class notes, the other classes that day, what is next in this class
$recording = null;
foreach (sr_lecture_recordings($studentId, $classId) as $r) {
    if ($r['time_table_id'] === $id && $r['lock']['state'] !== 'expired') {
        $recording = $r;
        break;
    }
}
$files = [];
$fr = Database::search("SELECT `attachment`, `attachment_name`, `date_added` FROM `attachments` WHERE `time_table_time_table_id` = '" . $id . "' ORDER BY `attachments_order`, `attachment_id`");
while ($fr && ($f = $fr->fetch_assoc())) {
    if (!empty($f['attachment']) && file_exists(Project::getAssetsPath() . '/uploads/lectures-attachments/' . basename($f['attachment']))) {
        $files[] = $f;
    }
}
$listSql = fn (string $where, int $limit) => Database::search("SELECT tt.`time_table_id`, tt.`date`, tt.`start_time`, tt.`end_time`, tt.`lecture_type_lecture_type_id` lt,
        tt.`time_table_status_time_table_status_id` st, c.`class_name`
    FROM `time_table` tt INNER JOIN `classes` c ON c.`class_id` = tt.`classes_class_id`
    INNER JOIN `students_has_classes` shc ON shc.`classes_class_id` = c.`class_id` AND shc.`students_student_id` = '" . $studentId . "' AND shc.`status_status_id` = 1
    WHERE tt.`time_table_id` <> '" . $id . "' AND $where ORDER BY tt.`date`, tt.`start_time` LIMIT " . $limit);
$rows = fn ($rs) => $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
$sameDay = $rows($listSql("tt.`date` = '" . $start->format('Y-m-d') . "'", 6));
$next = $rows($listSql("c.`class_id` = '" . $classId . "' AND tt.`time_table_status_time_table_status_id` = 1 AND CONCAT(tt.`date`, ' ', tt.`end_time`) >= '" . $now->format('Y-m-d H:i:s') . "'", 3));

$fileKind = function (string $file): string {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    return ['pdf' => 'PDF', 'doc' => 'Word', 'docx' => 'Word', 'ppt' => 'Slides', 'pptx' => 'Slides', 'xls' => 'Sheet', 'xlsx' => 'Sheet', 'csv' => 'Sheet',
        'png' => 'Image', 'jpg' => 'Image', 'jpeg' => 'Image', 'webp' => 'Image', 'zip' => 'Archive'][$ext] ?? (strtoupper($ext) ?: 'File');
};
$miniStatus = fn (array $r) => [2 => 'Cancelled', 3 => 'Postponed', 4 => 'Study leave'][(int) $r['st']] ?? '';
$joinMsg = trim((string) ($_GET['join'] ?? ''));
$e = 'sl_e';

$lf = ['title' => ($l['lecture_title'] ?: $l['class_name']) . ' - Class Day', 'menu' => 'Time Table', 'js' => []];
include __DIR__ . '/includes/learning-frame-top.php';
sl_page_title('Class Day Details', [['Time Table', './time-table'], [$l['class_name'], './class-details?class-id=' . $classId], [$start->format('j M Y'), null]]);
?>
                            <?php if ($joinMsg !== '') { ?>
                                <div class="ld-note is-info" role="status"><?= enlixo_icon('info', 18) ?><span><?= $e($joinMsg) ?></span></div>
                            <?php } ?>
                            <section class="ld-hero status-<?= $e($statusKey) ?>">
                                <div class="ld-hero-main">
                                    <div class="ld-pills">
                                        <span class="ld-pill ld-status"><?php if ($isLive) { ?><span class="ld-pulse"></span><?php } ?><?= $e($statusLabel) ?></span>
                                        <span class="ld-pill"><?= enlixo_icon($typeIcon, 14) ?><?= $e($typeLabel) ?></span>
                                        <?php if ($l['exam_type'] || $l['year']) { ?><span class="ld-pill"><?= enlixo_icon('academic', 14) ?><?= $e(trim($l['year'] . ' ' . $l['exam_type'])) ?></span><?php } ?>
                                    </div>
                                    <h1 class="ld-title"><?= $e($l['class_name']) ?></h1>
                                    <?php if (trim((string) $l['lecture_title']) !== '') { ?><p class="ld-subtitle"><?= $e($l['lecture_title']) ?></p><?php } ?>
                                    <div class="ld-inline"><?= enlixo_icon('teacher', 18) ?><span><?= $e($tutor) ?></span></div>
                                </div>
                                <div class="ld-when">
                                    <div class="ld-date">
                                        <span class="ld-date-month"><?= $start->format('M') ?></span>
                                        <span class="ld-date-day"><?= $start->format('j') ?></span>
                                        <span class="ld-date-dow"><?= $start->format('D') ?></span>
                                    </div>
                                    <div class="ld-when-body">
                                        <span class="ld-time"><?= $e($timeText) ?></span>
                                        <span class="ld-duration"><?= $e($duration) ?> · <?= $start->format('l, j F Y') ?></span>
                                        <?php if ($whenText !== '') { ?><span class="ld-countdown"><?= $e($whenText) ?></span><?php } ?>
                                    </div>
                                </div>
                                <?php ob_start();
                                if ($isActive && $typeId === 2 && !$isOver) {
                                    if ($now >= $joinFrom) { ?>
                                        <a class="custom-btn ld-btn" href="./api/joinLecture?id=<?= $id ?>" target="_blank" rel="noopener"><?= enlixo_icon('online', 18) ?>Join Now</a>
                                    <?php } else { ?>
                                        <button type="button" class="custom-btn ld-btn" disabled><?= enlixo_icon('online', 18) ?>Join opens at <?= $joinFrom->format('g:i A') ?><?= $joinFrom->format('Y-m-d') !== $now->format('Y-m-d') ? ' on ' . $joinFrom->format('j M') : '' ?></button>
                                    <?php }
                                } elseif ($isActive && $typeId === 1 && !$isOver) { ?>
                                    <span class="ld-hint"><?= enlixo_icon('location', 16) ?><?= $e($l['location'] ? 'In the hall: ' . $l['location'] : 'This class is in the hall.') ?></span>
                                <?php }
                                if ($recording && $recording['lock']['watchable']) { ?>
                                    <a class="custom-outline-btn ld-btn" href="./watch-recording?recording-id=<?= (int) $recording['id'] ?>"><?= enlixo_icon('play', 18) ?>Watch Recording</a>
                                <?php } elseif ($recording) { ?>
                                    <span class="ld-hint"><?= enlixo_icon('lock', 16) ?>Recording opens <?= $e($recording['lock']['unlock_text']) ?></span>
                                <?php }
                                if ($isActive && !$isOver) { ?>
                                    <button type="button" class="custom-outline-btn ld-btn" id="ld-add-cal"><?= enlixo_icon('calendar-add', 18) ?>Add to Calendar</button>
                                <?php }
                                $actions = trim(ob_get_clean());
                                if ($actions !== '') { ?>
                                    <div class="ld-actions"><?= $actions ?></div>
                                <?php } ?>
                            </section>

                            <div class="ld-grid">
                                <div class="ac-stack">
                                    <div class="ac-card">
                                        <div class="ac-card-head"><h2 class="ac-card-title">Class Day Details</h2></div>
                                        <div class="ld-facts">
                                            <?php foreach ([
                                                ['calendar', 'Date', $start->format('l, j F Y')],
                                                ['clock', 'Time', $timeText],
                                                ['hourglass', 'Duration', $duration],
                                                [$typeIcon, 'How it runs', $typeLabel],
                                                ['subject', 'Subject', trim($l['subject'] . ($l['medium'] ? ' (' . $l['medium'] . ')' : ''))],
                                                ['location', 'Hall / location', $typeId === 2 ? 'Online' : ((string) $l['location'] ?: 'Ask your tutor')],
                                            ] as [$icon, $label, $value]) { ?>
                                                <div class="ld-fact">
                                                    <span class="ld-fact-icon"><?= enlixo_icon($icon, 20) ?></span>
                                                    <div><span class="ld-fact-label"><?= $e($label) ?></span><span class="ld-fact-value"><?= $e($value) ?></span></div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <div class="ac-card">
                                        <div class="ac-card-head"><h2 class="ac-card-title">Tutor</h2></div>
                                        <div class="ld-person">
                                            <span class="ld-avatar"><span><?= $e($initials) ?></span><?php if (!empty($l['profile_img'])) { ?><img src="<?= $e(Project::getAssetsPath() . '/imgs/users/' . $l['profile_img']) ?>" alt="" onerror="this.remove()"><?php } ?></span>
                                            <div><span class="ld-person-name"><?= $e($tutor) ?></span><span class="ld-person-role"><?= $e($l['subject'] ?: $l['class_name']) ?></span></div>
                                        </div>
                                    </div>

                                    <div class="ac-card">
                                        <div class="ac-card-head"><h2 class="ac-card-title">Class Notes</h2><?php if ($files) { ?><span class="sub-text text-12"><?= count($files) ?> <?= count($files) === 1 ? 'file' : 'files' ?></span><?php } ?></div>
                                        <?php if (!$files) { ?>
                                            <div class="empty-state"><div class="empty-state-icon"><?= enlixo_icon('inbox', 28) ?></div><div class="empty-state-title">No class notes yet</div><div class="empty-state-text">Notes and files your tutor shares for this class day appear here.</div></div>
                                        <?php } else { ?>
                                            <div class="sr-files">
                                                <?php foreach ($files as $f) { ?>
                                                    <a class="sr-file" href="<?= $e(Project::getAssetsPath() . '/uploads/lectures-attachments/' . basename($f['attachment'])) ?>" target="_blank" rel="noopener">
                                                        <span class="sl-ico"><?= enlixo_icon('content', 20) ?></span>
                                                        <span class="sr-file-name"><?= $e($f['attachment_name'] ?: $f['attachment']) ?></span>
                                                        <span class="sr-file-meta"><?= $e($fileKind($f['attachment'])) ?><?= !empty($f['date_added']) ? ' · Added ' . date('j M Y', strtotime($f['date_added'])) : '' ?></span>
                                                    </a>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>

                                <div class="ac-stack">
                                    <div class="ac-card">
                                        <div class="ac-card-head"><h2 class="ac-card-title">My Attendance</h2></div>
                                        <div class="ld-attendance <?= $attendance[3] ?>">
                                            <span class="ld-attendance-icon"><?= enlixo_icon($attendance[2], 24) ?></span>
                                            <div><span class="ld-attendance-label"><?= $e($attendance[0]) ?></span><span class="ld-attendance-text"><?= $e($attendance[1]) ?></span></div>
                                        </div>
                                        <a class="ld-link-row" href="./class-attendance?class-id=<?= $classId ?>">See my attendance in this class<?= enlixo_icon('chevron-right', 16) ?></a>
                                    </div>

                                    <div class="ac-card">
                                        <div class="ac-card-head"><h2 class="ac-card-title">Also on <?= $start->format('D, j M') ?></h2></div>
                                        <?php if (!$sameDay) { ?>
                                            <div class="empty-state"><div class="empty-state-icon"><?= enlixo_icon('inbox', 28) ?></div><div class="empty-state-title">No other classes</div><div class="empty-state-text">This is your only class that day.</div></div>
                                        <?php } else { ?>
                                            <div class="ld-mini-list">
                                                <?php foreach ($sameDay as $r) { $note = $miniStatus($r); ?>
                                                    <a class="ld-mini" href="./time-table-class-details?time-table-id=<?= (int) $r['time_table_id'] ?>">
                                                        <span class="ld-mini-time"><?= date('g:i A', strtotime($r['start_time'])) ?></span>
                                                        <span class="ld-mini-body"><span class="ld-mini-title"><?= $e($r['class_name']) ?></span><span class="ld-mini-sub"><?= $e($note !== '' ? $note : ((int) $r['lt'] === 2 ? 'Online' : 'In the hall')) ?></span></span>
                                                    </a>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
                                    </div>

                                    <div class="ac-card">
                                        <div class="ac-card-head"><h2 class="ac-card-title">Next in this class</h2><a class="sr-link" href="./class-time-table?class-id=<?= $classId ?>">Time table</a></div>
                                        <?php if (!$next) { ?>
                                            <div class="empty-state"><div class="empty-state-icon"><?= enlixo_icon('inbox', 28) ?></div><div class="empty-state-title">Nothing scheduled yet</div><div class="empty-state-text">Your tutor's next class days appear here once they are on the time table.</div></div>
                                        <?php } else { ?>
                                            <div class="ld-mini-list">
                                                <?php foreach ($next as $r) { $d = strtotime($r['date']); ?>
                                                    <a class="ld-mini" href="./time-table-class-details?time-table-id=<?= (int) $r['time_table_id'] ?>">
                                                        <span class="ld-mini-date"><span><?= date('M', $d) ?></span><strong><?= date('j', $d) ?></strong></span>
                                                        <span class="ld-mini-body"><span class="ld-mini-title"><?= date('l', $d) ?></span><span class="ld-mini-sub"><?= date('g:i A', strtotime($r['start_time'])) ?> - <?= date('g:i A', strtotime($r['end_time'])) ?> · <?= (int) $r['lt'] === 2 ? 'Online' : 'In the hall' ?></span></span>
                                                    </a>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                            <script>
                                // Add to Calendar: an .ics file with this class day (works with Google, Apple and Outlook calendars)
                                document.getElementById('ld-add-cal')?.addEventListener('click', () => {
                                    const ev = <?= json_encode([
                                        'title' => $l['class_name'], 'start' => $start->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
                                        'end' => $end->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'), 'where' => $typeId === 2 ? 'Online' : (string) $l['location'],
                                        'uid' => 'class-day-' . $id . '@enlixo',
                                    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                                    const esc = (s) => String(s || '').replace(/([,;\\])/g, '\\$1').replace(/\n/g, '\\n');
                                    const ics = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//ENLIXO//Class Day//EN', 'BEGIN:VEVENT', 'UID:' + ev.uid,
                                        'DTSTAMP:' + new Date().toISOString().replace(/[-:]/g, '').split('.')[0] + 'Z', 'DTSTART:' + ev.start, 'DTEND:' + ev.end,
                                        'SUMMARY:' + esc(ev.title), 'LOCATION:' + esc(ev.where), 'END:VEVENT', 'END:VCALENDAR'].join('\r\n');
                                    const a = document.createElement('a');
                                    a.href = URL.createObjectURL(new Blob([ics], { type: 'text/calendar' }));
                                    a.download = 'class-day.ics';
                                    a.click();
                                    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
                                });
                            </script>
<?php include __DIR__ . '/includes/learning-frame-bottom.php'; ?>
