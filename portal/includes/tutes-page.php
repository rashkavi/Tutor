<?php
/**
 * Student Tutes (portal/tutes.php: every class, portal/class-tutes.php?class-id=: one class), in the same design as
 * Class Recordings: header card, stat tiles, then the card list with tabs, search and the class filter.
 * Needs $sessionUserObject and $tpClass (null or the class row of sl_student_class()).
 */
require_once __DIR__ . '/../../includes/enlixo/student-tutes.php';
require_once __DIR__ . '/../../includes/enlixo/stat-card.php';
$tpStudent = (int) $sessionUserObject['student_id'];
$tpClassId = $tpClass ? (int) $tpClass['class_id'] : (int) ($_GET['class'] ?? 0);
$tpClasses = sl_student_classes($tpStudent);
if (!$tpClass && !isset($tpClasses[$tpClassId])) {
    $tpClassId = 0;
}
$tpItems = st_tutes($tpStudent, $tpClassId);
$tpDownload = array_filter($tpItems, static fn ($t) => !$t['posted']);
$tpPosted = array_filter($tpItems, static fn ($t) => $t['posted']);
$tpWay = array_filter($tpPosted, static fn ($t) => in_array($t['parcel'], ['To pack', 'Packed', 'Sent'], true));
$tpClosed = array_filter($tpItems, static fn ($t) => !$t['open']);

$tpTitle = $tpClass ? $tpClass['class_name'] . ' Tutes' : 'Tutes';
$lf = ['title' => $tpTitle, 'menu' => $tpClass ? 'Classes' : 'Tutes', 'js' => ['js/recordings.js']];
include __DIR__ . '/learning-frame-top.php';
$tpParcels = '<a class="custom-outline-btn" href="./couriered-tute-details">' . enlixo_icon('box', 18) . 'My Tute Parcels</a>';
if ($tpClass) {
    sl_page_title('Class Tutes', [['Classes', './classes'], [$tpClass['class_name'], './class-details?class-id=' . $tpClassId], ['Tutes', null]], '', $tpParcels);
} elseif ($tpClassId) {
    sl_page_title('Tutes', [['Tutes', './tutes'], [$tpClasses[$tpClassId], null]], '', $tpParcels);
} else {
    sl_page_title('Tutes', [], 'Notes, papers and tute books from your tutors: open them in the app or follow the ones posted to your home.', $tpParcels);
}
?>
                            <div class="ac-tiles">
                                <?php
                                enlixo_stat_card('tutes', 'All Tutes', (string) count($tpItems), $tpItems ? 'Latest added ' . $tpItems[0]['date'] : 'Nothing shared yet');
                                enlixo_stat_card('download', 'To Download', (string) count($tpDownload), 'Open them in the app');
                                enlixo_stat_card('box', 'By Post', (string) count($tpPosted), $tpWay ? count($tpWay) . ' on the way to you' : 'Nothing on the way');
                                enlixo_stat_card('lock', 'Waiting for Payment', (string) count($tpClosed), $tpClosed ? 'Pay the month\'s fee to open them' : 'Every tute is open to you');
                                ?>
                            </div>
                            <?php st_grid($tpItems, $tpClasses, $tpClass ? $tpClassId : 0); ?>
<?php include __DIR__ . '/learning-frame-bottom.php';
