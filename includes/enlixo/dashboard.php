<?php
/**
 * Dashboard building blocks shared by tutor/, admin/, portal/ (student) and parent/, so the four
 * dashboards look and behave the same (docs/UI-STANDARD.md). Data comes from Enlixo\Dashboard\DashboardRepository.
 *
 *   $repo = enlixo_dash_repo();              // null when the database settings are missing
 *   enlixo_dash_head('Good morning, Nimal', 'Thursday, 12 November 2026', [['Create a Class', 'create-class', 'add', true]]);
 *   enlixo_dash_stats([...]);  enlixo_dash_sessions(...);  enlixo_dash_attendance(...);  enlixo_dash_calendar(...);
 *
 * The calendar, chart hover and notes are wired up by assets/js/enlixo/pages/app-dashboard.js.
 */
require_once __DIR__ . '/icons.php';
require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

use Enlixo\Dashboard\DashboardRepository;
use Enlixo\Dashboard\Stats;
use Enlixo\Legacy\Bridge;

function enlixo_dash_repo(): ?DashboardRepository
{
    $app = Bridge::app();
    return $app ? new DashboardRepository($app->db()) : null;
}

function enlixo_dash_e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** "LKR 12,500" (cents only when there are any). */
function enlixo_dash_money(float $amount): string
{
    return 'LKR ' . number_format($amount, fmod($amount, 1.0) === 0.0 ? 0 : 2);
}

function enlixo_dash_time(string $hm): string
{
    return date('g:i A', strtotime($hm));
}

/** "Good morning, Nimal" for the header card. */
function enlixo_dash_greeting(?string $firstName): string
{
    $name = trim((string) $firstName);
    return Stats::greeting((int) date('G')) . ($name !== '' ? ', ' . $name : '');
}

/** Shown in place of the dashboard when the data can't be read; the page chrome still works. */
function enlixo_dash_error(?Throwable $e = null): void
{
    if ($e) {
        error_log('Dashboard: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
    ?>
    <div class="col-12 component-theme p-5 text-center">
        <div class="ex-empty-ico mb-3"><?= enlixo_icon('danger', 28) ?></div>
        <div class="ex-panel-title mb-1">We couldn't load your dashboard</div>
        <p class="text-14 sub-text mb-4">The rest of the menu still works. Please try again in a moment.</p>
        <a class="custom-btn ex-btn-md px-4 d-inline-flex align-items-center text-decoration-none" href="dashboard">Try again</a>
    </div>
    <?php
}

/**
 * Header card: greeting, a grey line under it, buttons on the right.
 * @param list<array{0:string,1:string,2?:string,3?:bool}> $actions [label, link, icon, filled]
 */
function enlixo_dash_head(string $title, string $subtitle, array $actions = []): void
{
    ?>
    <div class="col-12 px-4 py-3 component-theme mb-4">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <div class="flex-grow-1 min-w-0">
                <h1 class="main-topic lh-lg m-0"><?= enlixo_dash_e($title) ?></h1>
                <p class="text-14 sub-text m-0"><?= enlixo_dash_e($subtitle) ?></p>
            </div>
            <?php if ($actions) { ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($actions as $a) { ?>
                        <a class="<?= !empty($a[3]) ? 'custom-btn' : 'custom-outline-btn' ?> ex-btn-md ex-hd-btn" href="<?= enlixo_dash_e($a[1]) ?>">
                            <?= !empty($a[2]) ? enlixo_icon($a[2], 18) : '' ?><span><?= enlixo_dash_e($a[0]) ?></span>
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>
    <?php
}

/**
 * Row of stat cards.
 * @param list<array{icon:string, value:string, title:string, note?:string, href?:string, tone?:string}> $cards tone: '' | 'warn' | 'good'
 */
function enlixo_dash_stats(array $cards): void
{
    $col = count($cards) === 3 ? 'col-12 col-md-4' : 'col-12 col-md-6 col-xl-3';
    ?>
    <div class="col-12 mb-4">
        <div class="row g-4">
            <?php foreach ($cards as $c) {
                $tag = !empty($c['href']) ? 'a' : 'div'; ?>
                <div class="<?= $col ?>">
                    <<?= $tag ?> class="component-theme px-4 py-3 d-flex align-items-center gap-3 h-100 ex-hd-stat"<?= $tag === 'a' ? ' href="' . enlixo_dash_e($c['href']) . '"' : '' ?>>
                        <span class="ex-stat-ico<?= !empty($c['tone']) ? ' ex-hd-tone-' . enlixo_dash_e($c['tone']) : '' ?>"><?= enlixo_icon($c['icon'], 24) ?></span>
                        <span class="min-w-0 d-block">
                            <span class="ex-stat-num d-block text-truncate"><?= enlixo_dash_stat_value($c['value']) ?></span>
                            <span class="text-14 main-text d-block"><?= enlixo_dash_e($c['title']) ?></span>
                            <?php if (!empty($c['note'])) { ?><span class="text-12 sub-text d-block"><?= enlixo_dash_e($c['note']) ?></span><?php } ?>
                        </span>
                    </<?= $tag ?>>
                </div>
            <?php } ?>
        </div>
    </div>
    <?php
}

/** Money values get a small "LKR" so the number stays the biggest thing in the card. */
function enlixo_dash_stat_value(string $value): string
{
    if (str_starts_with($value, 'LKR ')) {
        return '<span class="ex-hd-unit">LKR</span> ' . enlixo_dash_e(substr($value, 4));
    }
    return enlixo_dash_e($value);
}

/** Card title row with an optional "View all" link. */
function enlixo_dash_card_head(string $title, string $sub = '', ?string $href = null, string $linkText = 'View all'): void
{
    ?>
    <div class="d-flex align-items-start gap-3 mb-3">
        <div class="flex-grow-1 min-w-0">
            <h2 class="ex-panel-title m-0"><?= enlixo_dash_e($title) ?></h2>
            <?php if ($sub !== '') { ?><p class="text-12 sub-text m-0"><?= enlixo_dash_e($sub) ?></p><?php } ?>
        </div>
        <?php if ($href) { ?><a class="ex-hd-link" href="<?= enlixo_dash_e($href) ?>"><?= enlixo_dash_e($linkText) ?></a><?php } ?>
    </div>
    <?php
}

function enlixo_dash_empty(string $text, string $icon = 'check'): void
{
    ?>
    <div class="ex-hd-empty">
        <span class="ex-empty-ico"><?= enlixo_icon($icon, 28) ?></span>
        <p class="text-14 sub-text m-0"><?= enlixo_dash_e($text) ?></p>
    </div>
    <?php
}

/**
 * "Needs your attention": things waiting for this person, each with a link to where it is done.
 * @param list<array{icon:string, text:string, sub?:string, href:string, action:string, tone?:string}> $items
 */
function enlixo_dash_attention(array $items, string $emptyText = 'Nothing is waiting for you. Well done!'): void
{
    ?>
    <div class="component-theme p-4 w-100">
        <?php enlixo_dash_card_head('Needs your attention', $items ? 'Do these first' : '');
        if (!$items) {
            enlixo_dash_empty($emptyText);
        } else { ?>
            <ul class="ex-hd-list">
                <?php foreach ($items as $it) { ?>
                    <li class="ex-hd-row">
                        <span class="ex-hd-ico ex-hd-tone-<?= enlixo_dash_e($it['tone'] ?? 'warn') ?>"><?= enlixo_icon($it['icon'], 20) ?></span>
                        <span class="flex-grow-1 min-w-0">
                            <span class="text-14 main-text d-block"><?= enlixo_dash_e($it['text']) ?></span>
                            <?php if (!empty($it['sub'])) { ?><span class="text-12 sub-text d-block"><?= enlixo_dash_e($it['sub']) ?></span><?php } ?>
                        </span>
                        <a class="custom-outline-btn ex-btn-sm ex-hd-btn" href="<?= enlixo_dash_e($it['href']) ?>"><?= enlixo_dash_e($it['action']) ?></a>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>
    </div>
    <?php
}

/**
 * Today's classes and the next few class days.
 * @param list<array> $sessions rows from DashboardRepository::*Sessions(), oldest first
 * @param array{today:string, link?:callable, action?:string, who?:callable, all?:string, empty?:string, max?:int} $o
 */
function enlixo_dash_sessions(array $sessions, array $o): void
{
    $today = $o['today'];
    $max = $o['max'] ?? 6;
    $todays = array_values(array_filter($sessions, static fn ($s) => $s['date'] === $today));
    $later = array_slice(array_values(array_filter($sessions, static fn ($s) => $s['date'] > $today)), 0, max(2, $max - count($todays)));
    ?>
    <div class="component-theme p-4 w-100">
        <?php enlixo_dash_card_head("Today's classes", count($todays) === 0 ? 'No classes today' : (count($todays) === 1 ? '1 class today' : count($todays) . ' classes today'), $o['all'] ?? 'time-table', 'Time table');
        if (!$todays && !$later) {
            enlixo_dash_empty($o['empty'] ?? 'No classes in the next 7 days.', 'timetable');
        } else {
            if ($todays) {
                echo '<ul class="ex-hd-list">';
                foreach ($todays as $s) {
                    enlixo_dash_session_row($s, $o, true);
                }
                echo '</ul>';
            }
            if ($later) {
                echo '<div class="ex-hd-caption">Coming up</div><ul class="ex-hd-list">';
                foreach ($later as $s) {
                    enlixo_dash_session_row($s, $o, false);
                }
                echo '</ul>';
            }
        } ?>
    </div>
    <?php
}

function enlixo_dash_session_row(array $s, array $o, bool $isToday): void
{
    $ts = strtotime($s['date']);
    $bits = [$s['online'] ? 'Online' : 'Physical', enlixo_dash_time($s['start']) . ' - ' . enlixo_dash_time($s['end'])];
    $who = isset($o['who']) ? (string) $o['who']($s) : '';
    $href = isset($o['link']) ? $o['link']($s) : null;
    ?>
    <li class="ex-hd-row">
        <span class="ex-hd-date<?= $isToday ? ' ex-today' : '' ?>" aria-hidden="true">
            <span class="ex-hd-date-d"><?= $isToday ? enlixo_dash_e(date('g:i', strtotime($s['start']))) : date('j', $ts) ?></span>
            <span class="ex-hd-date-m"><?= $isToday ? date('A', strtotime($s['start'])) : date('M', $ts) ?></span>
        </span>
        <span class="flex-grow-1 min-w-0">
            <span class="text-14 fw-medium ex-hd-title d-block text-truncate"><?= enlixo_dash_e($s['class']) ?></span>
            <span class="text-12 sub-text d-flex align-items-start gap-1">
                <?= enlixo_icon($s['online'] ? 'online' : 'physical', 14) ?>
                <span><?= enlixo_dash_e(($isToday ? '' : date('D', $ts) . ' · ') . implode(' · ', $bits)) ?></span>
            </span>
            <?php if ($who !== '' || $s['title'] !== '') { ?>
                <span class="text-12 sub-text d-block text-truncate"><?= enlixo_dash_e(implode(' · ', array_filter([$who, $s['title']]))) ?></span>
            <?php } ?>
        </span>
        <?php if ($href) { ?>
            <a class="<?= $isToday ? 'custom-btn' : 'custom-outline-btn' ?> ex-btn-sm ex-hd-btn" href="<?= enlixo_dash_e($href) ?>"
               aria-label="<?= enlixo_dash_e(($o['action'] ?? 'View') . ': ' . $s['class']) ?>"><?= enlixo_dash_e($o['action'] ?? 'View') ?></a>
        <?php } ?>
    </li>
    <?php
}

/**
 * Attendance bars: attended (blue) on top of missed (red) for each day or week.
 * @param list<array{date:string, label:string, present:int, absent:int}> $series
 * @param array{title?:string, sub?:string, present?:string, absent?:string, footer?:string, href?:string} $o
 */
function enlixo_dash_attendance(array $series, array $o = []): void
{
    $presentLabel = $o['present'] ?? 'Present';
    $absentLabel = $o['absent'] ?? 'Absent';
    $max = 0;
    foreach ($series as $p) {
        $max = max($max, $p['present'] + $p['absent']);
    }
    $rate = Stats::seriesRate($series);
    ?>
    <div class="component-theme p-4 w-100 d-flex flex-column">
        <?php enlixo_dash_card_head($o['title'] ?? 'Attendance', $o['sub'] ?? 'Last 7 days', $o['href'] ?? null, 'Details');
        if ($max === 0) {
            enlixo_dash_empty('No attendance marked in this period yet.', 'attendance');
        } else { ?>
            <div class="d-flex align-items-end gap-3 mb-3">
                <div><span class="ex-stat-num"><?= (int) $rate ?>%</span> <span class="text-12 sub-text"><?= enlixo_dash_e(strtolower($presentLabel)) ?></span></div>
                <div class="ms-auto d-flex gap-3 text-12 main-text">
                    <span class="d-inline-flex align-items-center gap-1"><i class="ex-hd-chart-key ex-hd-chart-present"></i><?= enlixo_dash_e($presentLabel) ?></span>
                    <span class="d-inline-flex align-items-center gap-1"><i class="ex-hd-chart-key ex-hd-chart-absent"></i><?= enlixo_dash_e($absentLabel) ?></span>
                </div>
            </div>
            <div class="ex-hd-chart" role="img" aria-label="<?= enlixo_dash_e(($o['title'] ?? 'Attendance') . ': ' . $rate . '% ' . strtolower($presentLabel)) ?>">
                <?php foreach ($series as $p) {
                    $total = $p['present'] + $p['absent'];
                    $day = date('j M', strtotime($p['date']));
                    $tip = ($p['label'] === $day ? 'Week of ' . $day : $p['label'] . ' ' . $day) . ': ' . ($total ? $p['present'] . ' ' . strtolower($presentLabel) . ', ' . $p['absent'] . ' ' . strtolower($absentLabel) : 'nothing marked'); ?>
                    <div class="ex-hd-chart-col" tabindex="0" data-tip="<?= enlixo_dash_e($tip) ?>" aria-label="<?= enlixo_dash_e($tip) ?>">
                        <div class="ex-hd-chart-bars">
                            <?php if ($total === 0) { ?>
                                <span class="ex-hd-chart-none"></span>
                            <?php } else { ?>
                                <span class="ex-hd-chart-total"><?= $total ?></span>
                                <?php if ($p['absent']) { ?><span class="ex-hd-chart-bar ex-hd-chart-absent" style="height: <?= round($p['absent'] * 100 / $max, 2) ?>%"></span><?php } ?>
                                <?php if ($p['present']) { ?><span class="ex-hd-chart-bar ex-hd-chart-present" style="height: <?= round($p['present'] * 100 / $max, 2) ?>%"></span><?php } ?>
                            <?php } ?>
                        </div>
                        <span class="ex-hd-chart-x"><?= enlixo_dash_e($p['label']) ?></span>
                    </div>
                <?php } ?>
            </div>
        <?php }
        if (!empty($o['footer'])) { ?>
            <div class="ex-hd-foot mt-3"><?= enlixo_icon('crown', 20) ?><span class="text-12 main-text"><?= enlixo_dash_e($o['footer']) ?></span></div>
        <?php } ?>
    </div>
    <?php
}

/**
 * Simple one-colour bars (new tutors per month, tutors per package).
 * @param list<array{label:string, value:int}> $items
 */
function enlixo_dash_bars(string $title, string $sub, array $items, string $unit, string $emptyText): void
{
    $max = max(array_merge([0], array_column($items, 'value')));
    ?>
    <div class="component-theme p-4 w-100 d-flex flex-column">
        <?php enlixo_dash_card_head($title, $sub);
        if ($max === 0) {
            enlixo_dash_empty($emptyText, 'students');
        } else { ?>
            <div class="ex-hd-chart mt-auto" role="img" aria-label="<?= enlixo_dash_e($title) ?>">
                <?php foreach ($items as $it) {
                    $tip = $it['label'] . ': ' . $it['value'] . ' ' . $unit; ?>
                    <div class="ex-hd-chart-col" tabindex="0" data-tip="<?= enlixo_dash_e($tip) ?>" aria-label="<?= enlixo_dash_e($tip) ?>">
                        <div class="ex-hd-chart-bars">
                            <span class="ex-hd-chart-total"><?= (int) $it['value'] ?></span>
                            <?php if ($it['value'] > 0) { ?><span class="ex-hd-chart-bar ex-hd-chart-present" style="height: <?= round($it['value'] * 100 / $max, 2) ?>%"></span><?php } else { ?><span class="ex-hd-chart-none"></span><?php } ?>
                        </div>
                        <span class="ex-hd-chart-x"><?= enlixo_dash_e($it['label']) ?></span>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
    <?php
}

/**
 * Month calendar with a dot on class days; clicking a day lists its classes underneath.
 * @param list<array> $sessions rows from DashboardRepository::*Sessions() covering $from..$to
 */
function enlixo_dash_calendar(array $sessions, string $today, string $from, string $to): void
{
    $days = [];
    foreach ($sessions as $s) {
        $key = $s['id']; // a parent with two children in one class gets the class day twice; show it once
        $days[$s['date']][$key] = ['c' => $s['class'], 't' => enlixo_dash_time($s['start']) . ' - ' . enlixo_dash_time($s['end']), 'o' => $s['online']];
    }
    $days = array_map('array_values', $days);
    $data = ['today' => $today, 'from' => substr($from, 0, 7), 'to' => substr($to, 0, 7), 'days' => $days ?: new stdClass()];
    ?>
    <div class="component-theme p-4 w-100 ex-hd-cal" data-dash-calendar="<?= enlixo_dash_e(json_encode($data, JSON_UNESCAPED_UNICODE)) ?>">
        <div class="d-flex align-items-center gap-2 mb-3">
            <h2 class="ex-panel-title m-0 flex-grow-1" data-cal-title>Calendar</h2>
            <button type="button" class="ex-hd-cal-nav" data-cal-prev aria-label="Previous month"><?= enlixo_icon('chevron-left', 18) ?></button>
            <button type="button" class="ex-hd-cal-nav" data-cal-next aria-label="Next month"><?= enlixo_icon('chevron-right', 18) ?></button>
        </div>
        <div class="ex-hd-cal-grid ex-hd-cal-dow" aria-hidden="true"><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span><span>Su</span></div>
        <div class="ex-hd-cal-grid" data-cal-grid></div>
        <div class="ex-hd-cal-day" data-cal-day aria-live="polite"></div>
    </div>
    <?php
}

function enlixo_dash_avatar(string $name, string $image, string $folder): string
{
    $file = dirname(__DIR__, 2) . '/assets/imgs/' . $folder . '/' . $image;
    if ($image !== '' && basename($image) === $image && is_file($file)) {
        return '<img src="' . Project::getAssetsPath() . '/imgs/' . $folder . '/' . enlixo_dash_e($image) . '" class="ex-hd-avatar" alt="">';
    }
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
    return '<span class="ex-hd-avatar" aria-hidden="true">' . enlixo_dash_e($initials) . '</span>';
}

/** Status pill. $kind: 'good' | 'wait' | 'bad' | '' */
function enlixo_dash_status(string $label, string $kind): string
{
    return '<span class="ex-hd-pill' . ($kind !== '' ? ' ex-hd-tone-' . $kind : '') . '">' . enlixo_dash_e($label) . '</span>';
}

function enlixo_dash_payment_kind(int $statusId): string
{
    return match ($statusId) {
        3, 6, 8 => 'good',
        4, 7 => 'bad',
        default => 'wait',
    };
}

/**
 * Fee rows as a table.
 * @param list<array> $rows from DashboardRepository::*Payments()
 * @param array{title:string, sub?:string, all?:string, link?:callable, action?:string, empty:string, student?:bool, date?:string, primary?:bool} $o
 *        date: 'paid' (default) or 'due'
 */
function enlixo_dash_payments(array $rows, array $o): void
{
    $showStudent = $o['student'] ?? true;
    $due = ($o['date'] ?? 'paid') === 'due';
    $today = date('Y-m-d');
    ?>
    <div class="component-theme p-4 w-100">
        <?php enlixo_dash_card_head($o['title'], $o['sub'] ?? '', $o['all'] ?? null);
        if (!$rows) {
            enlixo_dash_empty($o['empty'], 'payments');
        } else { ?>
            <div class="table-responsive">
                <table class="table ex-v3-table align-middle m-0">
                    <thead>
                        <tr>
                            <th scope="col"><?= $showStudent ? 'Student' : 'Class' ?></th>
                            <th scope="col" class="text-end">Amount</th>
                            <th scope="col"><?= $due ? 'Due date' : 'Date' ?></th>
                            <th scope="col">Status</th>
                            <?php if (isset($o['link'])) { ?><th scope="col"><span class="visually-hidden">Open</span></th><?php } ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r) {
                            $late = $due && $r['due_date'] !== null && $r['due_date'] < $today;
                            $date = $due ? $r['due_date'] : $r['date_paid']; ?>
                            <tr>
                                <?php if ($showStudent) { // the class sits under the name, so the table stays narrow enough for half-width cards ?>
                                    <td><span class="d-flex align-items-center gap-2"><?= enlixo_dash_avatar($r['student'], $r['image'], 'students') ?>
                                            <span class="min-w-0"><span class="text-14 main-text d-block"><?= enlixo_dash_e($r['student']) ?></span><span class="text-12 sub-text d-block"><?= enlixo_dash_e($r['class']) ?></span></span></span></td>
                                <?php } else { ?>
                                    <td><span class="text-14 main-text"><?= enlixo_dash_e($r['class']) ?></span></td>
                                <?php } ?>
                                <td class="text-end text-nowrap"><span class="text-14 main-text"><?= enlixo_dash_e(enlixo_dash_money($r['amount'])) ?></span></td>
                                <td class="text-nowrap"><span class="text-14 main-text"><?= $date ? date('j M Y', strtotime($date)) : '-' ?></span></td>
                                <td><?= $late ? enlixo_dash_status('Overdue', 'bad') : enlixo_dash_status($r['status'], enlixo_dash_payment_kind($r['status_id'])) ?></td>
                                <?php if (isset($o['link'])) { ?>
                                    <td class="text-end"><a class="<?= !empty($o['primary']) ? 'custom-btn' : 'custom-outline-btn' ?> ex-btn-sm ex-hd-btn" href="<?= enlixo_dash_e($o['link']($r)) ?>"
                                            aria-label="<?= enlixo_dash_e(($o['action'] ?? 'View') . ': ' . $r['class']) ?>"><?= enlixo_dash_e($o['action'] ?? 'View') ?></a></td>
                                <?php } ?>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
    <?php
}

/** Grey blocks shown until the page has loaded (toggleSkeleton() swaps #skeleton-list and #real-list). */
function enlixo_dash_skeleton(): void
{
    ?>
    <div class="col-12 p-4 w-100 m-0 placeholder-glow" id="skeleton-list" aria-hidden="true">
        <div class="component-theme px-4 py-3 mb-4"><span class="placeholder col-4 d-block mb-2"></span><span class="placeholder placeholder-xs col-3"></span></div>
        <div class="row g-4 mb-4">
            <?php for ($i = 0; $i < 4; $i++) { ?>
                <div class="col-12 col-md-6 col-xl-3"><div class="component-theme px-4 py-4"><span class="placeholder col-5 d-block mb-2"></span><span class="placeholder placeholder-xs col-8"></span></div></div>
            <?php } ?>
        </div>
        <div class="row g-4">
            <?php foreach ([5, 4, 3] as $w) { ?>
                <div class="col-12 col-xl-<?= $w ?>"><div class="component-theme p-4 ex-hd-skel"><span class="placeholder col-6 d-block mb-3"></span><span class="placeholder col-12 d-block mb-2"></span><span class="placeholder col-10 d-block mb-2"></span><span class="placeholder col-11 d-block"></span></div></div>
            <?php } ?>
        </div>
    </div>
    <?php
}

/** Stylesheets and scripts every dashboard needs, in the same order as the other new pages. */
function enlixo_dash_styles(): void
{
    $a = Project::getAssetsPath();
    $v = Project::getVersion();
    echo '<link rel="stylesheet" href="' . $a . '/css/style.css?v=' . $v . '">' . "\n";
    echo '<link rel="stylesheet" href="' . $a . '/css/skeleton.css?v=' . $v . '">' . "\n";
    echo '<link rel="stylesheet" href="' . $a . '/plugins/bootstrap/css/bootstrap.min.css?v=' . $v . '">' . "\n";
    echo '<link rel="stylesheet" href="' . $a . '/css/enlixo-ui.css?v=' . $v . '">' . "\n";
}

/** Small 16:9 class picture, or a grey box with an icon when the class has none. */
function enlixo_dash_cover(string $cover): string
{
    $file = dirname(__DIR__, 2) . '/assets/imgs/classes-covers/' . $cover;
    if ($cover !== '' && basename($cover) === $cover && is_file($file)) {
        return '<img class="ex-thumb-sm" src="' . Project::getAssetsPath() . '/imgs/classes-covers/' . enlixo_dash_e($cover) . '" alt="">';
    }
    return '<span class="ex-cover-empty ex-thumb-sm ex-hd-cover" aria-hidden="true">' . enlixo_icon('classes', 20) . '</span>';
}
