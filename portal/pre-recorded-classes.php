<?php
// Pre Recorded Classes (student): ready-made lessons of the student's classes, laid out like the ENLIXO V3 student
// portal "Pre Recordings" page. ?class=<id> shows one class (class-pre-recordings?class-id= forwards here).
// Only the student's own active classes are listed (the old page listed every pre-recording of every tutor).
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
require_once "../includes/enlixo/student-recordings.php";
require_once "../includes/enlixo/stat-card.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Student');
$studentId = (int) $sessionUserObject['student_id'];

$classes = sl_student_classes($studentId);
$wanted = (int) ($_GET['class'] ?? 0);
$classId = isset($classes[$wanted]) ? $wanted : 0;
$items = sr_pre_recordings($studentId, $classId);

$open = array_filter($items, fn ($r) => $r['lock']['watchable']);
$progress = array_filter($items, fn ($r) => $r['status'] === 'in_progress' && $r['lock']['watchable']);
$done = array_filter($items, fn ($r) => $r['status'] === 'completed');
$soon = array_values(array_filter($items, fn ($r) => $r['lock']['state'] === 'locked'));
usort($soon, fn ($a, $b) => strcmp($a['lock']['unlock_iso'], $b['lock']['unlock_iso']));
$donePct = $items ? (int) round(count($done) / count($items) * 100) : 0;
$className = $classId ? $classes[$classId] : '';

$lf = ['title' => 'Pre Recorded Classes', 'menu' => 'Pre Recorded Classes', 'js' => ['js/recordings.js']];
include __DIR__ . '/includes/learning-frame-top.php';

$recLink = '<a class="custom-outline-btn" href="./recordings' . ($classId ? '?class=' . $classId : '') . '">' . enlixo_icon('recordings', 18) . 'Class Recordings</a>';
if ($classId) {
    sl_page_title('Pre Recorded Classes', [['Pre Recorded Classes', './pre-recorded-classes'], [$className, './class-details?class-id=' . $classId]], '', $recLink);
} else {
    sl_page_title('Pre Recorded Classes', [], 'Lessons your tutors recorded for you to learn at your own pace. Your progress is saved as you watch.', $recLink);
}
?>
                            <div class="ac-tiles">
                                <?php
                                enlixo_stat_card('pre-recorded', 'Available', (string) count($open), count($items) . (count($items) === 1 ? ' lesson' : ' lessons') . ' in your classes');
                                enlixo_stat_card('hourglass', 'In Progress', (string) count($progress), $progress ? 'Pick up where you left off' : 'Nothing half watched');
                                enlixo_stat_card('check', 'Completed', (string) count($done), $donePct . '% of the lessons');
                                enlixo_stat_card('lock', 'Opens Later', (string) count($soon), $soon ? 'Next opens ' . $soon[0]['lock']['unlock_text'] : 'Nothing waiting to open');
                                ?>
                            </div>
                            <?php sr_grid($items, 'pre', [
                                'all' => ['All', 'No pre recorded classes yet', 'Lessons your tutors record for you appear here.', 'pre-recorded'],
                                'new' => ['Not started', 'Nothing new to start', 'You have opened every lesson that is available.', 'check'],
                                'progress' => ['In progress', 'Nothing in progress', 'Lessons you started but did not finish appear here.', 'hourglass'],
                                'completed' => ['Completed', 'Nothing completed yet', 'Lessons you watch to the end appear here.', 'play'],
                                'soon' => ['Opens later', 'Nothing waiting to open', 'Lessons that open on a later date appear here.', 'lock'],
                            ], $classes, $classId); ?>
<?php include __DIR__ . '/includes/learning-frame-bottom.php';
