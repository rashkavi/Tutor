<?php
// Tutor → Time Table → one lecture (class day): everything about it on one page, with the actions.
// What: class, date and time, how it is taught, status, title, the online link (Zoom passcode), attachments,
// recordings and the attendance of the class. Actions: Mark Attendance, Attendance list, Add Recording, Edit Lecture,
// Start / Join the online class, the class page. ?time-table-id=<id>. Only the tutor's own lectures open.
$id = (int) ($_GET['time-table-id'] ?? $_GET['lecture-id'] ?? 0); // older links use lecture-id
$tp = [
    'title' => 'Lecture Details',
    'menu' => 'Time Table',
    'crumbs' => [['Academic', null], ['Time Table', 'time-table'], ['Lecture Details', null]],
    'guide' => [
        'intro' => 'Everything about one class day: when and how it is taught, the files you attached, its recordings and who attended.',
        'points' => [
            'Mark Attendance opens the scanner / list for this class day; Attendance shows who came.',
            'Add Recording puts the video of this class day for the students who missed it.',
            'Edit Lecture changes the date, time, title, link or attachments.',
        ],
    ],
    'actions' => 'ld-actions',
    'root' => 'ld-root',
    'js' => 'app-tutor-lecture-details.js',
    'css' => ['lecture-details.css'],
];
include 'includes/frame-top.php';
require_once __DIR__ . '/../includes/enlixo/icons.php';

$tutorId = (int) ($sessionUserObject['user_id'] ?? 0);
$r = Database::search(
    "SELECT t.*, c.class_id, c.class_name, c.class_cover, s.subject, lt.lecture_type, tts.time_table_status
       FROM time_table t
       JOIN classes c ON c.class_id = t.classes_class_id
       LEFT JOIN subjects s ON s.subject_id = c.subjects_subject_id
       LEFT JOIN lecture_type lt ON lt.lecture_type_id = t.lecture_type_lecture_type_id
       LEFT JOIN time_table_status tts ON tts.time_table_status_id = t.time_table_status_time_table_status_id
      WHERE t.time_table_id = '" . $id . "' AND c.tutor_id = '" . $tutorId . "'"
);
$l = $r && $r->num_rows ? $r->fetch_assoc() : null;
$e = $tpE;
if (!$l) { ?>
    <div class="col-12 component-theme p-5">
        <div class="empty-state">
            <div class="empty-state-icon"><?= enlixo_icon('calendar', 28, '') ?></div>
            <div class="empty-state-title">Lecture not found</div>
            <div class="empty-state-text">It may have been removed, or it belongs to another tutor. <a href="time-table">Back to the Time Table</a></div>
        </div>
    </div>
<?php
    include 'includes/frame-bottom.php';
    exit;
}
$classId = (int) $l['class_id'];
$tz = new DateTimeZone('Asia/Colombo');
$start = new DateTime($l['date'] . ' ' . $l['start_time'], $tz);
$end = new DateTime($l['date'] . ' ' . $l['end_time'], $tz);
$now = new DateTime('now', $tz);
$active = (int) $l['time_table_status_time_table_status_id'] === 1;
$state = !$active ? ['is-bad', (string) ($l['time_table_status'] ?? 'Changed')]
    : ($now < $start ? ['is-blue', 'Upcoming'] : ($now <= $end ? ['is-live', 'Ongoing'] : ['is-good', 'Completed']));
$mins = (int) round(($end->getTimestamp() - $start->getTimestamp()) / 60);
$length = ($mins >= 60 ? intdiv($mins, 60) . ' h ' : '') . ($mins % 60 ? ($mins % 60) . ' min' : '');
$typeId = (int) $l['lecture_type_lecture_type_id'];
$online = $typeId === 2;

// online link: the lecture's own link or the Zoom meeting made for it
$zoom = Database::search("SELECT join_url, passcode, status FROM zoom_meetings WHERE time_table_time_table_id = '" . $id . "' ORDER BY zoom_meeting_row_id DESC LIMIT 1");
$zoom = $zoom && $zoom->num_rows ? $zoom->fetch_assoc() : null;
$link = trim((string) ($zoom['join_url'] ?? '')) ?: trim((string) $l['lecture']);
$linkOk = preg_match('#^https?://#i', $link) === 1;

$files = [];
$q = Database::search("SELECT attachment, attachment_name, date_added FROM attachments WHERE time_table_time_table_id = '" . $id . "' ORDER BY attachments_order, attachment_id");
while ($q && ($f = $q->fetch_assoc())) {
    $files[] = $f;
}
$recs = [];
$q = Database::search("SELECT lecture_recording_id, recording_title, duration, added_at, expire_date, unlock_at FROM lecture_recording WHERE time_table_time_table_id = '" . $id . "' ORDER BY lecture_recording_id");
while ($q && ($x = $q->fetch_assoc())) {
    $recs[] = $x;
}
// attendance of the active students of the class
$att = Database::search(
    "SELECT COUNT(*) total,
            SUM(a.st IN (1, 2)) present, SUM(a.st = 3) absent, SUM(a.st = 4) watched
       FROM students_has_classes h
       LEFT JOIN (SELECT students_student_id sid, MAX(attendance_status_attendance_status_id) st FROM attendance WHERE time_table_time_table_id = '" . $id . "' GROUP BY students_student_id) a ON a.sid = h.students_student_id
      WHERE h.classes_class_id = '" . $classId . "' AND h.status_status_id = 1"
)->fetch_assoc();
$total = (int) $att['total'];
$present = (int) $att['present'];
$absent = (int) $att['absent'];
$watched = (int) $att['watched'];
$unmarked = max(0, $total - $present - $absent - $watched);
$pct = $total ? (int) round($present / $total * 100) : 0;
$fileUrl = static fn (string $f): string => Project::getAssetsPath() . '/uploads/lectures-attachments/' . rawurlencode($f);
$ext = static fn (string $f): string => strtolower(pathinfo($f, PATHINFO_EXTENSION));
$q2 = '&class-id=' . $classId;
?>
<div class="col-12" id="ld-page">
    <!-- header buttons (moved into the page header by the page script) -->
    <div class="d-none" id="ld-actions-src">
        <div class="dropdown">
            <button class="custom-outline-btn ex-btn-lg px-4 d-inline-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">Actions <?= enlixo_icon('chevron-down', 18, '') ?></button>
            <ul class="dropdown-menu dropdown-menu-end border-0 shadow br-5 ld-menu">
                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="mark-attendance?session=<?= $id ?>"><?= enlixo_icon('qr', 20, 'sub-text-color me-3') ?>Mark Attendance</a></li>
                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="time-table-attendance?time-table-id=<?= $id . $q2 ?>"><?= enlixo_icon('checklist', 20, 'sub-text-color me-3') ?>Attendance List</a></li>
                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="add-recording?time-table-id=<?= $id . $q2 ?>"><?= enlixo_icon('recordings', 20, 'sub-text-color me-3') ?>Add Recording</a></li>
                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="edit-lecture?time-table-id=<?= $id . $q2 ?>"><?= enlixo_icon('edit', 20, 'sub-text-color me-3') ?>Edit Lecture</a></li>
                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="class-details?class-id=<?= $classId ?>"><?= enlixo_icon('classes', 20, 'sub-text-color me-3') ?>Class Page</a></li>
            </ul>
        </div>
        <?php if ($online && $linkOk && $active && $now <= $end) { ?>
            <a class="custom-btn ex-btn-lg px-5 text-decoration-none d-inline-flex align-items-center gap-2" href="<?= $e($link) ?>" target="_blank" rel="noopener"><?= enlixo_icon('online', 20, '') ?>Start Class</a>
        <?php } elseif ($active && $now >= $start) { ?>
            <a class="custom-btn ex-btn-lg px-5 text-decoration-none" href="mark-attendance?session=<?= $id ?>">Mark Attendance</a>
        <?php } else { ?>
            <a class="custom-btn ex-btn-lg px-5 text-decoration-none" href="edit-lecture?time-table-id=<?= $id . $q2 ?>">Edit Lecture</a>
        <?php } ?>
    </div>

    <!-- hero -->
    <div class="ld-hero component-theme mb-4">
        <div class="ld-date"><strong><?= $start->format('d') ?></strong><span><?= $start->format('M') ?></span><small><?= $start->format('D') ?></small></div>
        <div class="ld-hero-main">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="ld-chip <?= $state[0] ?>"><?= $e($state[1]) ?></span>
                <span class="ld-chip"><?= enlixo_icon($online ? 'online' : 'physical', 14) ?><?= $e($l['lecture_type'] ?? ($online ? 'Online' : 'Physical')) ?></span>
            </div>
            <h2 class="ld-title"><?= $e($l['lecture_title'] ?: $l['class_name']) ?></h2>
            <div class="ld-sub"><?= $e($l['class_name']) ?><?= $l['subject'] ? ' · ' . $e($l['subject']) : '' ?></div>
            <div class="ld-when"><?= enlixo_icon('clock', 18, '') ?><?= $e($start->format('l, j F Y') . ' · ' . $start->format('g:i A') . ' – ' . $end->format('g:i A')) ?><?= $length ? ' · ' . $e(trim($length)) : '' ?></div>
        </div>
    </div>

    <!-- quick actions -->
    <div class="ld-actions mb-4">
        <a class="ld-act" href="mark-attendance?session=<?= $id ?>"><span class="ld-act-ico"><?= enlixo_icon('qr', 22, '') ?></span><span><strong>Mark Attendance</strong><small>Scan QR / barcode or tick names</small></span></a>
        <a class="ld-act" href="time-table-attendance?time-table-id=<?= $id . $q2 ?>"><span class="ld-act-ico"><?= enlixo_icon('checklist', 22, '') ?></span><span><strong>Attendance List</strong><small><?= $present ?> present of <?= $total ?></small></span></a>
        <a class="ld-act" href="add-recording?time-table-id=<?= $id . $q2 ?>"><span class="ld-act-ico"><?= enlixo_icon('recordings', 22, '') ?></span><span><strong><?= $recs ? 'Add Another Recording' : 'Add Recording' ?></strong><small><?= count($recs) ? count($recs) . ' recording' . (count($recs) > 1 ? 's' : '') : 'For students who missed it' ?></small></span></a>
        <a class="ld-act" href="edit-lecture?time-table-id=<?= $id . $q2 ?>"><span class="ld-act-ico"><?= enlixo_icon('edit', 22, '') ?></span><span><strong>Edit Lecture</strong><small>Date, time, title, link, files</small></span></a>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-7 d-flex flex-column gap-4">
            <div class="component-theme p-4">
                <div class="sub-topic mb-3">Details</div>
                <div class="ld-facts">
                    <div><span>Class</span><strong><a href="class-details?class-id=<?= $classId ?>"><?= $e($l['class_name']) ?></a></strong></div>
                    <div><span>Subject</span><strong><?= $e($l['subject'] ?: '—') ?></strong></div>
                    <div><span>Date</span><strong><?= $e($start->format('D, j M Y')) ?></strong></div>
                    <div><span>Time</span><strong><?= $e($start->format('g:i A') . ' – ' . $end->format('g:i A')) ?></strong></div>
                    <div><span>How it is taught</span><strong><?= $e($l['lecture_type'] ?? '—') ?></strong></div>
                    <div><span>Status</span><strong><?= $e($state[1]) ?></strong></div>
                    <?php if ($online) { ?>
                        <div class="is-wide"><span>Online class link</span>
                            <?php if ($linkOk) { ?>
                                <strong class="ld-link"><a href="<?= $e($link) ?>" target="_blank" rel="noopener"><?= $e($link) ?></a>
                                    <button type="button" class="ld-copy" data-copy="<?= $e($link) ?>" title="Copy the link"><?= enlixo_icon('copy', 16, '') ?></button></strong>
                            <?php } else { ?><strong class="sub-text">No link yet. Add it with Edit Lecture.</strong><?php } ?>
                        </div>
                        <?php if (!empty($zoom['passcode'])) { ?><div><span>Zoom passcode</span><strong><?= $e($zoom['passcode']) ?> <button type="button" class="ld-copy" data-copy="<?= $e($zoom['passcode']) ?>" title="Copy the passcode"><?= enlixo_icon('copy', 16, '') ?></button></strong></div><?php } ?>
                    <?php } ?>
                </div>
            </div>

            <div class="component-theme p-4">
                <div class="d-flex justify-content-between align-items-center mb-3"><div class="sub-topic">Attachments <span class="tab-count"><?= count($files) ?></span></div>
                    <a class="custom-outline-btn table-btn px-3 text-decoration-none" href="edit-lecture?time-table-id=<?= $id . $q2 ?>">Add / Remove</a></div>
                <?php if (!$files) { ?>
                    <div class="empty-state"><div class="empty-state-icon"><?= enlixo_icon('inbox', 28, '') ?></div><div class="empty-state-title">No attachments</div><div class="empty-state-text">Notes or tutes you attach with Edit Lecture are listed here for the students.</div></div>
                <?php } else { ?>
                    <div class="ld-files">
                        <?php foreach ($files as $f) {
                            $x = $ext((string) $f['attachment']);
                            $icon = in_array($x, ['jpg', 'jpeg', 'png', 'webp'], true) ? 'image' : 'content'; ?>
                            <a class="ld-file" href="<?= $e($fileUrl((string) $f['attachment'])) ?>" target="_blank" rel="noopener">
                                <span class="ld-file-ico"><?= enlixo_icon($icon, 24) ?><em><?= $e(strtoupper($x ?: 'file')) ?></em></span>
                                <span class="min-w-0"><strong><?= $e($f['attachment_name'] ?: $f['attachment']) ?></strong><small><?= $e(strtoupper($x ?: 'file')) ?><?= $f['date_added'] ? ' · added ' . $e(date('j M Y', strtotime((string) $f['date_added']))) : '' ?></small></span>
                                <?= enlixo_icon('download', 20, 'sub-text-color') ?>
                            </a>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="col-12 col-xl-5 d-flex flex-column gap-4">
            <div class="component-theme p-4">
                <div class="d-flex justify-content-between align-items-center mb-3"><div class="sub-topic">Attendance</div><a class="custom-outline-btn table-btn px-3 text-decoration-none" href="time-table-attendance?time-table-id=<?= $id . $q2 ?>">View list</a></div>
                <div class="ld-att">
                    <div class="ld-ring" style="--pct: <?= $pct ?>"><span><strong><?= $pct ?>%</strong><small>present</small></span></div>
                    <ul>
                        <li><i class="is-good"></i>Present<strong><?= $present ?></strong></li>
                        <li><i class="is-bad"></i>Absent<strong><?= $absent ?></strong></li>
                        <li><i class="is-purple"></i>Watched recording<strong><?= $watched ?></strong></li>
                        <li><i></i>Not marked<strong><?= $unmarked ?></strong></li>
                    </ul>
                </div>
                <div class="text-12 sub-text mt-2"><?= $total ?> active students in this class.</div>
            </div>

            <div class="component-theme p-4">
                <div class="d-flex justify-content-between align-items-center mb-3"><div class="sub-topic">Recordings <span class="tab-count"><?= count($recs) ?></span></div>
                    <a class="custom-btn table-btn px-3 text-decoration-none" href="add-recording?time-table-id=<?= $id . $q2 ?>">Add Recording</a></div>
                <?php if (!$recs) { ?>
                    <div class="empty-state"><div class="empty-state-icon"><?= enlixo_icon('inbox', 28, '') ?></div><div class="empty-state-title">No recording yet</div><div class="empty-state-text">Add the video of this class day so students who missed it can watch.</div></div>
                <?php } else { ?>
                    <ul class="ld-recs">
                        <?php foreach ($recs as $x) {
                            $lock = $x['unlock_at'] && strtotime((string) $x['unlock_at']) > time();
                            $gone = $x['expire_date'] && strtotime((string) $x['expire_date']) < time(); ?>
                            <li>
                                <span class="ld-rec-ico"><?= enlixo_icon('play', 22, '') ?></span>
                                <span class="min-w-0"><strong><?= $e($x['recording_title'] ?: 'Recording') ?></strong>
                                    <small><?= (int) $x['duration'] ? (int) $x['duration'] . ' min · ' : '' ?><?= $gone ? 'Expired ' . $e(date('j M', strtotime((string) $x['expire_date']))) : ($lock ? 'Opens ' . $e(date('j M, g:i A', strtotime((string) $x['unlock_at']))) : 'Open to students') ?></small></span>
                                <span class="d-flex gap-2">
                                    <a class="custom-outline-btn table-btn px-3 text-decoration-none" href="watch-recording?recording-id=<?= (int) $x['lecture_recording_id'] ?>">Watch</a>
                                    <a class="custom-outline-btn table-btn px-3 text-decoration-none" href="edit-recording?recording-id=<?= (int) $x['lecture_recording_id'] ?>">Edit</a>
                                </span>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/frame-bottom.php';
