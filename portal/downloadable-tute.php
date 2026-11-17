<?php
// Student → a tute (portal/downloadable-tute.php?class-id=&tute-id=): what the tute is, for which class and month,
// the files to open or download, and for a tute sent by post where the parcel is now (tute_deliveries,
// Enlixo\Tutes\TuteRepository::forStudent). Same page frame as Time Table and Recordings.
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/UserPermissionsClass.php";
require_once "../includes/Project.php";
if (!SessionUser::ifUserIsStudent()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Student');
require_once __DIR__ . '/../includes/enlixo/student-learning.php';
$sid = (int) $sessionUserObject['student_id'];
$classId = (int) ($_GET['class-id'] ?? 0);
$tuteId = (int) ($_GET['tute-id'] ?? 0);

$tute = Database::search("SELECT ct.*, c.class_name, tt.tute_type FROM class_tutes ct
    JOIN classes c ON c.class_id = ct.classes_class_id
    LEFT JOIN tute_types tt ON tt.tute_type_id = ct.tute_types_tute_type_id
    WHERE ct.class_tute_id = '" . $tuteId . "' AND ct.classes_class_id = '" . $classId . "'
      AND ct.classes_class_id IN (SELECT classes_class_id FROM students_has_classes WHERE students_student_id = '" . $sid . "' AND status_status_id = 1)")->fetch_assoc();
if (!$tute) {
    header("Location: ./tutes");
    exit;
}
$posted = (int) $tute['tute_types_tute_type_id'] !== 1;

$files = [];
$res = Database::search("SELECT attachment, attachment_name FROM class_tutes_has_attachments WHERE class_tutes_class_tute_id = '" . $tuteId . "'");
while ($res && ($a = $res->fetch_assoc())) {
    $path = Project::getAssetsPath() . '/uploads/tutes-attachments/' . $a['attachment'];
    if ($a['attachment'] !== '' && file_exists($path)) {
        $bytes = (int) filesize($path);
        $files[] = ['url' => $path, 'name' => (string) ($a['attachment_name'] ?: $a['attachment']), 'ext' => strtoupper(pathinfo($a['attachment'], PATHINFO_EXTENSION) ?: pathinfo((string) $a['attachment_name'], PATHINFO_EXTENSION)),
            'size' => $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB'];
    }
}

$parcel = null;
if ($posted) {
    try {
        require_once __DIR__ . '/../src/bootstrap.php';
        $app = \Enlixo\Legacy\Bridge::app();
        if ($app) {
            foreach ((new \Enlixo\Tutes\TuteRepository($app->db()))->forStudent($sid) as $p) {
                if ($p['tute_id'] === $tuteId) {
                    $parcel = $p;
                    break;
                }
            }
        }
    } catch (\Throwable $e) {
        error_log('downloadable-tute parcel: ' . $e->getMessage());
    }
}
$month = !empty($tute['payment_month']) ? date('F Y', strtotime($tute['payment_month'] . (strlen((string) $tute['payment_month']) === 7 ? '-01' : ''))) : null;

$lf = ['title' => $tute['tute_title'], 'menu' => 'Tutes'];
include __DIR__ . '/includes/learning-frame-top.php';
sl_page_title((string) $tute['tute_title'], [
    ['Tutes', './tutes'],
    [(string) $tute['class_name'], './class-tutes?class-id=' . $classId],
    ['Tute', null],
], '', '<a class="custom-outline-btn" href="./class-tutes?class-id=' . $classId . '">' . enlixo_icon('tutes', 18) . 'All Class Tutes</a>');
?>
                            <div class="ac-grid-2">
                                <div class="ac-stack">
                                    <div class="ac-card">
                                        <div class="ac-card-head">
                                            <h2 class="ac-card-title">Files</h2>
                                            <span class="ac-badge"><?= count($files) ?> <?= count($files) === 1 ? 'file' : 'files' ?></span>
                                        </div>
                                        <?php if ($files) { ?>
                                            <div class="tu-files">
                                                <?php foreach ($files as $f) { ?>
                                                    <a class="tu-file" href="<?= sl_e($f['url']) ?>" target="_blank" rel="noopener" <?= $f['ext'] === 'PDF' ? '' : 'download="' . sl_e($f['name']) . '"' ?>>
                                                        <span class="tu-file-ico"><?= sl_e($f['ext'] ?: 'FILE') ?></span>
                                                        <span class="tu-file-text"><strong><?= sl_e($f['name']) ?></strong><small><?= sl_e(trim($f['ext'] . ' · ' . $f['size'], ' ·')) ?></small></span>
                                                        <span class="tu-file-act"><?= enlixo_icon($f['ext'] === 'PDF' ? 'view' : 'download', 18) ?><?= $f['ext'] === 'PDF' ? 'Open' : 'Download' ?></span>
                                                    </a>
                                                <?php } ?>
                                            </div>
                                        <?php } else { ?>
                                            <div class="empty-state">
                                                <div class="empty-state-icon"><?= enlixo_icon('inbox', 28) ?></div>
                                                <div class="empty-state-title"><?= $posted ? 'This tute comes by post' : 'No files yet' ?></div>
                                                <div class="empty-state-text"><?= $posted ? 'Your tutor sends the printed tute to your home. See where it is on the right.' : 'Your tutor has not added the files of this tute yet.' ?></div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                    <?php if (trim((string) $tute['tute_description']) !== '') { ?>
                                        <div class="ac-card">
                                            <h2 class="ac-card-title mb-2">About this tute</h2>
                                            <p class="text-14 main-text m-0" style="white-space: pre-line"><?= sl_e($tute['tute_description']) ?></p>
                                        </div>
                                    <?php } ?>
                                </div>

                                <div class="ac-stack">
                                    <div class="ac-card">
                                        <h2 class="ac-card-title mb-3">Details</h2>
                                        <div class="tu-facts">
                                            <div><span>Class</span><strong><?= sl_e($tute['class_name']) ?></strong></div>
                                            <div><span>How you get it</span><strong><?= $posted ? 'By post to your home' : 'Download in the app' ?></strong></div>
                                            <?php if ($month) { ?><div><span>For the month</span><strong><?= sl_e($month) ?></strong></div><?php } ?>
                                            <div><span>Added on</span><strong><?= sl_e(date('j M Y', strtotime((string) $tute['date_added']))) ?></strong></div>
                                        </div>
                                    </div>
                                    <?php if ($posted) { ?>
                                        <div class="ac-card">
                                            <div class="ac-card-head">
                                                <h2 class="ac-card-title">Your parcel</h2>
                                                <?php if ($parcel) { ?><span class="ac-badge <?= $parcel['status'] === 'delivered' ? 'is-good' : ($parcel['status'] === 'returned' ? 'is-bad' : 'is-warn') ?>"><?= sl_e($parcel['step']) ?></span><?php } ?>
                                            </div>
                                            <?php if ($parcel) {
                                                $order = ['pending' => 0, 'packed' => 1, 'dispatched' => 2, 'delivered' => 3];
                                                $at = $order[$parcel['status']] ?? 0; ?>
                                                <ol class="tu-steps">
                                                    <?php foreach (['To pack', 'Packed', 'Sent', 'Delivered'] as $i => $label) { ?>
                                                        <li class="<?= $i < $at ? 'is-done' : ($i === $at ? 'is-now' : '') ?>"><span></span><?= $label ?></li>
                                                    <?php } ?>
                                                </ol>
                                                <div class="tu-facts mt-3">
                                                    <?php if ($parcel['courier_name']) { ?><div><span>Courier</span><strong><?= sl_e($parcel['courier_name']) ?></strong></div><?php } ?>
                                                    <?php if ($parcel['tracking_no']) { ?><div><span>Tracking number</span><strong><?= sl_e($parcel['tracking_no']) ?></strong></div><?php } ?>
                                                    <?php if ($parcel['dispatched_on']) { ?><div><span>Sent on</span><strong><?= sl_e(date('j M Y', strtotime($parcel['dispatched_on']))) ?></strong></div><?php } ?>
                                                    <?php if ($parcel['delivered_on']) { ?><div><span>Delivered on</span><strong><?= sl_e(date('j M Y', strtotime($parcel['delivered_on']))) ?></strong></div><?php } ?>
                                                    <?php if ($parcel['address']) { ?><div><span>Sent to</span><strong><?= sl_e($parcel['address']) ?></strong></div><?php } ?>
                                                </div>
                                                <?php if ($parcel['tracking_url'] && $parcel['tracking_no']) { ?>
                                                    <a class="custom-btn w-100 mt-3 d-inline-flex align-items-center justify-content-center gap-2 text-decoration-none" href="<?= sl_e($parcel['tracking_url']) ?>" target="_blank" rel="noopener"><?= enlixo_icon('link', 18) ?>Track the parcel</a>
                                                <?php } ?>
                                            <?php } else { ?>
                                                <div class="empty-state">
                                                    <div class="empty-state-icon"><?= enlixo_icon('inbox', 28) ?></div>
                                                    <div class="empty-state-title">Not on the parcel list yet</div>
                                                    <div class="empty-state-text">Your tutor adds you when the parcels are packed. Check that your address is right in My Profile.</div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
<?php include __DIR__ . '/includes/learning-frame-bottom.php';
