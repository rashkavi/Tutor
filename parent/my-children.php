<?php
// My Children (parent): one card per child in the student portal design (learning frame, theme colours, light + dark):
// photo or initials, status, classes, this month's attendance with a bar, fees to pay, the next class, and the ways in
// (Progress, Classes, Fees). Numbers come from ps_children() / ps_progress() (includes/enlixo/parent-scope.php), the same
// figures as Child Progress, so the two pages never disagree.
require_once '../includes/UserClass.php';
require_once '../includes/connection.php';
require_once '../includes/Project.php';
require_once '../includes/enlixo/parent-scope.php';
if (!SessionUser::ifUserIsParent()) {
    header('Location: ./index');
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Parent');
$parentId = (int) $sessionUserObject['user_id'];
$children = ps_children($parentId);

// status, WhatsApp and registration date of each child (login_details)
$extra = [];
if ($children) {
    $ids = implode(',', array_map('intval', array_keys($children)));
    $q = Database::search("SELECT s.student_id, ld.status_status_id st, ld.whatsapp, ld.date_registered
        FROM students s JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id WHERE s.student_id IN ($ids)");
    while ($q && ($r = $q->fetch_assoc())) {
        $extra[(int) $r['student_id']] = $r;
    }
}
$e = 'sl_e';
$imgDir = Project::getAssetsPath() . '/imgs/students/';

$lf = [
    'title' => 'My Children', 'menu' => 'My Children',
    'side' => __DIR__ . '/includes/side-menu.php', 'header' => dirname(__DIR__) . '/includes/header.php',
    'css' => [Project::getAssetsPath() . '/css/parent-progress.css'],
];
include dirname(__DIR__) . '/portal/includes/learning-frame-top.php';
sl_page_title('My Children', [], 'Each child at a glance: classes, attendance this month, fees to pay and the next class.');
?>
                            <?php if (!$children) { ?>
                                <div class="ac-card">
                                    <div class="empty-state"><div class="empty-state-icon"><?= enlixo_icon('inbox', 28) ?></div><div class="empty-state-title">No children linked yet</div><div class="empty-state-text">Your child's tutor links you when they add your child. Ask the tutor if a child is missing.</div></div>
                                </div>
                            <?php } else { ?>
                                <div class="mc-grid">
                                    <?php foreach ($children as $id => $c) {
                                        $x = $extra[$id] ?? [];
                                        $pg = ps_progress($id);
                                        $rate = $pg['month']['rate'];
                                        $active = (int) ($x['st'] ?? 1) === 1;
                                        $initials = strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($c['name'])), 0, 2))));
                                        $next = $pg['upcoming'][0] ?? null;
                                        $tone = $rate === null ? '' : ($rate >= 80 ? 'is-good' : ($rate >= 60 ? 'is-warn' : 'is-bad'));
                                        ?>
                                        <article class="mc-card">
                                            <div class="mc-cover">
                                                <span class="ac-badge <?= $active ? 'is-good' : 'is-bad' ?>"><?= $active ? 'Active' : 'Inactive' ?></span>
                                            </div>
                                            <div class="mc-head">
                                                <span class="mc-avatar"><span><?= $e($initials) ?></span><?php if ($c['img'] !== '') { ?><img src="<?= $e($imgDir . basename($c['img'])) ?>" alt="" onerror="this.remove()"><?php } ?></span>
                                                <div class="min-w-0">
                                                    <h2 class="mc-name"><?= $e($c['name']) ?></h2>
                                                    <span class="mc-sub"><?= $c['classes'] ?> <?= $c['classes'] === 1 ? 'class' : 'classes' ?><?= !empty($x['date_registered']) ? ' · joined ' . date('M Y', strtotime($x['date_registered'])) : '' ?></span>
                                                </div>
                                            </div>

                                            <div class="mc-stats">
                                                <div class="mc-stat">
                                                    <span class="mc-stat-label"><?= enlixo_icon('attendance', 15) ?>Attendance (30 days)</span>
                                                    <strong class="mc-stat-value <?= $tone ?>"><?= $rate === null ? '-' : $rate . '%' ?></strong>
                                                    <span class="mc-bar"><span class="<?= $tone ?>" style="width: <?= (int) ($rate ?? 0) ?>%"></span></span>
                                                    <span class="mc-stat-sub"><?= $pg['month']['present'] ?> present · <?= $pg['month']['absent'] ?> absent<?= $pg['month']['watched'] ? ' · ' . $pg['month']['watched'] . ' watched' : '' ?></span>
                                                </div>
                                                <div class="mc-stat">
                                                    <span class="mc-stat-label"><?= enlixo_icon('wallet', 15) ?>Fees to pay</span>
                                                    <strong class="mc-stat-value <?= $pg['late'] > 0 ? 'is-bad' : ($pg['to_pay'] > 0 ? '' : 'is-good') ?>"><?= $pg['to_pay'] > 0 ? $e(sf_money($pg['to_pay'])) : 'All paid' ?></strong>
                                                    <span class="mc-stat-sub"><?= $pg['late'] > 0 ? $e(sf_money($pg['late'])) . ' overdue' : ($pg['to_pay'] > 0 ? 'Nothing overdue' : 'Nothing waiting') ?></span>
                                                </div>
                                            </div>

                                            <div class="mc-next">
                                                <?= enlixo_icon('calendar', 18) ?>
                                                <?php if ($next) { $d = strtotime($next['date']); ?>
                                                    <span class="min-w-0"><span class="mc-next-label">Next class</span><span class="mc-next-text"><?= $e($next['class_name']) ?> · <?= $next['date'] === date('Y-m-d') ? 'Today' : date('D, j M', $d) ?>, <?= date('g:i A', strtotime($next['start_time'])) ?></span></span>
                                                <?php } else { ?>
                                                    <span class="min-w-0"><span class="mc-next-label">Next class</span><span class="mc-next-text">Nothing in the next 7 days</span></span>
                                                <?php } ?>
                                            </div>

                                            <div class="mc-actions">
                                                <a class="custom-btn mc-btn" href="./child-progress?child-id=<?= $id ?>"><?= enlixo_icon('growth', 18) ?>View Progress</a>
                                                <a class="custom-outline-btn mc-btn" href="./child-classes?child-id=<?= $id ?>"><?= enlixo_icon('classes', 18) ?>Classes</a>
                                                <a class="custom-outline-btn mc-btn" href="./payments?child=<?= $id ?>"><?= enlixo_icon('wallet', 18) ?>Fees</a>
                                            </div>
                                        </article>
                                    <?php } ?>
                                </div>
                            <?php } ?>
<?php include dirname(__DIR__) . '/portal/includes/learning-frame-bottom.php'; ?>
