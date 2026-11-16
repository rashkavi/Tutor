<?php
// Child Progress (parent): how one child is doing, on one phone screen at a time. What parents look for first
// (research: weekly/at-a-glance progress, attendance alerts, fees due): what needs attention, attendance this month,
// missed classes and whether the recording was watched, the next classes, fees. Data: includes/enlixo/parent-scope.php.
require_once '../includes/UserClass.php';
require_once "../includes/connection.php";
require_once "../includes/Project.php";
require_once "../includes/enlixo/parent-scope.php";
require_once "../includes/enlixo/stat-card.php";
require_once __DIR__ . "/includes/child-switch.php";
if (!SessionUser::ifUserIsParent()) {
    header("Location: ./index");
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Parent');
$parentId = (int) $sessionUserObject['user_id'];
$children = ps_children($parentId);
$childId = (int) ($_GET['child-id'] ?? 0);
if (!isset($children[$childId])) {
    $childId = (int) (array_key_first($children) ?? 0);
}
$child = $children[$childId] ?? null;
$pg = $child ? ps_progress($childId) : null;
$e = 'sl_e';

$lf = [
    'title' => 'Child Progress', 'menu' => 'Child Progress',
    'side' => __DIR__ . '/includes/side-menu.php', 'header' => dirname(__DIR__) . '/includes/header.php',
    'css' => [Project::getAssetsPath() . '/css/student-fees.css', Project::getAssetsPath() . '/css/parent-progress.css'],
];
include dirname(__DIR__) . '/portal/includes/learning-frame-top.php';

sl_page_title('Child Progress', [], "How your child is doing: attendance, missed classes, the next classes and fees.", $child ? '<a class="custom-btn" href="./payments?child=' . $childId . '">' . enlixo_icon('wallet', 18) . 'Fees</a>' : '');

if (!$child) { ?>
    <div class="ac-card">
        <div class="empty-state">
            <div class="empty-state-icon"><?= enlixo_icon('students', 28) ?></div>
            <div class="empty-state-title">No child linked yet</div>
            <div class="empty-state-text">Ask the tutor to add your WhatsApp number to your child as the parent.</div>
        </div>
    </div>
<?php } else {
    $m = $pg['month'];
    $all = $pg['all'];
    $tone = $m['rate'] === null ? 'muted' : ($m['rate'] >= 80 ? 'good' : ($m['rate'] >= 60 ? 'warn' : 'bad')); ?>
    <?php parent_child_switch($children, $childId, './child-progress?child-id='); ?>

    <div class="ac-card pp-hero">
        <div class="pp-hero-who">
            <span class="pp-avatar is-big"><?= $e(strtoupper(mb_substr($child['first_name'], 0, 1))) ?></span>
            <div class="min-w-0">
                <h2 class="pp-name"><?= $e($child['name']) ?></h2>
                <span class="text-13 sub-text"><?= (int) $child['classes'] ?> <?= $child['classes'] === 1 ? 'class' : 'classes' ?> · <?= (int) $all['held'] ?> class days so far</span>
            </div>
        </div>
        <div class="pp-meter is-<?= $tone ?>" role="img" aria-label="Attendance in the last 30 days">
            <strong><?= $m['rate'] === null ? '—' : $m['rate'] . '%' ?></strong>
            <span>attendance, last 30 days</span>
        </div>
    </div>

    <div class="ac-card pp-attention">
        <h2 class="ac-card-title">Needs your attention</h2>
        <?php if (!$pg['attention']) { ?>
            <p class="pp-allgood"><?= enlixo_icon('check', 18) ?>All good. Nothing needs your attention right now.</p>
        <?php } else { ?>
            <ul class="pp-alerts">
                <?php foreach ($pg['attention'] as [$t, $icon, $title, $sub, $href, $action]) { ?>
                    <li class="is-<?= $t ?>">
                        <span class="pp-alert-ico"><?= enlixo_icon($icon, 18) ?></span>
                        <span class="min-w-0"><strong><?= $e($title) ?></strong><small><?= $e($sub) ?></small></span>
                        <?php if ($href) { ?><a class="custom-btn table-btn" href="<?= $e($href) ?>"><?= $e($action) ?></a><?php } ?>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>
    </div>

    <div class="ac-tiles pp-tiles">
        <?php
        enlixo_stat_card('check', 'Present', (string) $m['present'], 'last 30 days · ' . $all['present'] . ' in total');
        enlixo_stat_card('close', 'Absent', (string) $m['absent'], 'last 30 days · ' . $all['absent'] . ' in total');
        enlixo_stat_card('recordings', 'Caught up', (string) ($all['watched'] + count(array_filter($pg['days'], fn ($d) => $d['state'] === 'absent' && $d['recording_watched']))), count($pg['missed']) ? count($pg['missed']) . ' missed classes still to watch' : 'Every missed class with a recording');
        enlixo_stat_card('wallet', 'Fees to pay', sf_money($pg['to_pay']), $pg['late'] > 0 ? sf_money($pg['late']) . ' overdue' : 'Nothing overdue');
        ?>
    </div>

    <div class="ac-grid-2">
        <div class="ac-stack">
            <div class="ac-card">
                <h2 class="ac-card-title">Recent class days</h2>
                <?php if (!$pg['days']) { ?>
                    <div class="empty-state"><div class="empty-state-icon"><?= enlixo_icon('calendar', 28) ?></div><div class="empty-state-title">No class days yet</div><div class="empty-state-text">Class days appear here once the first class is held.</div></div>
                <?php } else { ?>
                    <ul class="pp-days">
                        <?php foreach ($pg['days'] as $d) {
                            [$label, $cls] = match ($d['state']) {
                                'present' => ['Present', 'is-good'],
                                'watched' => ['Watched recording', 'is-purple'],
                                'absent' => [$d['recording_watched'] ? 'Absent · watched recording' : 'Absent', $d['recording_watched'] ? 'is-warn' : 'is-bad'],
                                default => ['Not marked yet', 'is-muted'],
                            }; ?>
                            <li>
                                <span class="pp-day-date"><strong><?= date('d', strtotime($d['date'])) ?></strong><small><?= date('M', strtotime($d['date'])) ?></small></span>
                                <span class="min-w-0">
                                    <strong><?= $e($d['class']) ?></strong>
                                    <small><?= $e(date('D', strtotime($d['date'])) . ' · ' . date('g:i A', strtotime($d['start']))) ?><?= $d['title'] !== 'Class day' ? ' · ' . $e($d['title']) : '' ?></small>
                                </span>
                                <span class="ac-badge <?= $cls ?>"><?= $e($label) ?></span>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>
            <div class="ac-card">
                <h2 class="ac-card-title">Attendance by class</h2>
                <?php if (!$pg['classes']) { ?>
                    <p class="text-14 sub-text m-0">Nothing yet.</p>
                <?php } else {
                    foreach ($pg['classes'] as $c) {
                        $r = $c['rate']; ?>
                        <div class="pp-class">
                            <div class="d-flex justify-content-between gap-2"><strong class="min-w-0"><?= $e($c['name']) ?></strong><span class="text-13 sub-text text-nowrap"><?= $r === null ? 'Not marked' : $r . '%' ?></span></div>
                            <div class="pp-bar"><span style="width: <?= (int) $r ?>%" class="<?= $r === null ? '' : ($r >= 80 ? 'is-good' : ($r >= 60 ? 'is-warn' : 'is-bad')) ?>"></span></div>
                            <small class="sub-text"><?= (int) $c['present'] ?> present · <?= (int) $c['absent'] ?> absent<?= $c['watched'] ? ' · ' . (int) $c['watched'] . ' watched' : '' ?> · <?= (int) $c['held'] ?> held</small>
                        </div>
                <?php }
                } ?>
            </div>
        </div>
        <div class="ac-stack">
            <div class="ac-card">
                <h2 class="ac-card-title">Next 7 days</h2>
                <?php if (!$pg['upcoming']) { ?>
                    <p class="text-14 sub-text m-0">No classes in the next 7 days.</p>
                <?php } else { ?>
                    <ul class="pp-days">
                        <?php foreach ($pg['upcoming'] as $u) { ?>
                            <li>
                                <span class="pp-day-date is-next"><strong><?= date('d', strtotime($u['date'])) ?></strong><small><?= date('M', strtotime($u['date'])) ?></small></span>
                                <span class="min-w-0"><strong><?= $e($u['class_name']) ?></strong><small><?= $e(date('D', strtotime($u['date'])) . ' · ' . date('g:i A', strtotime((string) $u['start_time'])) . ' – ' . date('g:i A', strtotime((string) $u['end_time']))) ?></small></span>
                                <span class="ac-badge"><?= (int) $u['type'] === 2 ? 'Online' : 'Physical' ?></span>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>
            <?php if ($pg['missed']) { ?>
                <div class="ac-card">
                    <h2 class="ac-card-title">Missed classes to catch up</h2>
                    <p class="text-13 sub-text">The recording of these days is ready but has not been watched yet.</p>
                    <ul class="pp-days">
                        <?php foreach (array_slice($pg['missed'], 0, 5) as $d) { ?>
                            <li>
                                <span class="pp-day-date is-bad"><strong><?= date('d', strtotime($d['date'])) ?></strong><small><?= date('M', strtotime($d['date'])) ?></small></span>
                                <span class="min-w-0"><strong><?= $e($d['class']) ?></strong><small><?= $e($d['title']) ?></small></span>
                                <a class="custom-outline-btn table-btn" href="watch-recording?recording-id=<?= (int) $d['recording'] ?>">Recording</a>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } ?>
        </div>
    </div>
<?php }
include dirname(__DIR__) . '/portal/includes/learning-frame-bottom.php';
