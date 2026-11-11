<?php
// Class Recordings (student): every recording of the student's classes, laid out like the ENLIXO V3 student portal
// "Lecture Recordings" page. ?class=<id> shows one class (class-recordings?class-id= forwards here).
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
$items = sr_lecture_recordings($studentId, $classId);

$open = array_filter($items, fn ($r) => $r['lock']['watchable']);
$todo = array_filter($items, fn ($r) => sr_lecture_state($r)[0] === 'todo');
$missed = array_filter($todo, fn ($r) => $r['watch'] === 'absent');
$watched = array_filter($items, fn ($r) => in_array($r['watch'], ['watched', 'attended'], true));
$locked = array_values(array_filter($items, fn ($r) => $r['lock']['state'] === 'locked'));
usort($locked, fn ($a, $b) => strcmp($a['lock']['unlock_iso'], $b['lock']['unlock_iso']));
$expiring = array_filter($todo, fn ($r) => $r['lock']['days_left'] !== null && $r['lock']['days_left'] <= 3);
$watchedPct = $items ? (int) round(count($watched) / count($items) * 100) : 0;
$className = $classId ? $classes[$classId] : '';

$lf = ['title' => 'Class Recordings', 'menu' => 'Class Recordings', 'js' => ['js/recordings.js']];
include __DIR__ . '/includes/learning-frame-top.php';

$preLink = '<a class="custom-outline-btn" href="./pre-recorded-classes' . ($classId ? '?class=' . $classId : '') . '">' . enlixo_icon('pre-recorded', 18) . 'Pre Recorded Classes</a>';
if ($classId) {
    sl_page_title('Class Recordings', [['Class Recordings', './recordings'], [$className, './class-details?class-id=' . $classId]], '', $preLink);
} else {
    sl_page_title('Class Recordings', [], 'Watch the recordings of your classes, catch up on the days you missed and revise before exams.', $preLink);
}
?>
                            <div class="ac-tiles">
                                <?php
                                enlixo_stat_card('recordings', 'Available', (string) count($open), count($items) . (count($items) === 1 ? ' class day recorded' : ' class days recorded'));
                                enlixo_stat_card('play', 'To Watch', (string) count($todo), $missed ? count($missed) . ' from classes you missed' : ($expiring ? count($expiring) . ' closing within 3 days' : 'You are all caught up'));
                                enlixo_stat_card('check', 'Watched', (string) count($watched), $watchedPct . '% of your class recordings');
                                enlixo_stat_card('lock', 'Opens Later', (string) count($locked), $locked ? 'Next opens ' . $locked[0]['lock']['unlock_text'] : 'Nothing waiting to open');
                                ?>
                            </div>
                            <?php sr_grid($items, 'lecture', [
                                'all' => ['All', 'No class recordings yet', 'Recordings of your classes appear here once your tutor adds them.', 'recordings'],
                                'todo' => ['To watch', 'Nothing left to watch', 'You have watched or attended every recorded class.', 'check'],
                                'watched' => ['Watched', 'Nothing watched yet', 'Recordings you watch, and classes you attended, appear here.', 'play'],
                                'locked' => ['Opens later & expired', 'Nothing locked', 'Recordings that open later or have closed appear here.', 'lock'],
                            ], $classes, $classId); ?>
<?php include __DIR__ . '/includes/learning-frame-bottom.php';
