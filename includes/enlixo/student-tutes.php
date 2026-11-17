<?php
/**
 * Student Tutes lists (portal/tutes.php, portal/class-tutes.php?class-id=): the same card list as Class Recordings
 * (sr-list / sr-card, tabs + search + class filter, portal/js/recordings.js). A tute of a month opens once that
 * month's fee is paid (same rule as TuteRepository::whoGets: status 3 / 8, free classes always open).
 *   st_tutes($studentId, $classId)  the tutes of the student's active classes
 *   st_grid($items, $classes, $classId)
 */
require_once __DIR__ . '/student-learning.php';
require_once __DIR__ . '/student-recordings.php';

if (!function_exists('st_tutes')) {
    function st_tutes(int $studentId, int $classId = 0): array
    {
        $res = Database::search("SELECT ct.class_tute_id, ct.tute_title, ct.tute_description, ct.payment_month, ct.tute_types_tute_type_id, ct.date_added,
                c.class_id, c.class_name, c.class_cover, c.is_free, shc.students_has_class_id,
                (SELECT COUNT(*) FROM class_tutes_has_attachments a WHERE a.class_tutes_class_tute_id = ct.class_tute_id) files,
                (SELECT d.status FROM tute_deliveries d WHERE d.class_tutes_class_tute_id = ct.class_tute_id AND d.students_student_id = '" . $studentId . "' ORDER BY d.tute_delivery_id DESC LIMIT 1) parcel
            FROM class_tutes ct
            JOIN classes c ON c.class_id = ct.classes_class_id
            JOIN students_has_classes shc ON shc.classes_class_id = c.class_id AND shc.students_student_id = '" . $studentId . "' AND shc.status_status_id = 1"
            . ($classId > 0 ? " WHERE c.class_id = '" . $classId . "'" : '') . "
            ORDER BY ct.date_added DESC, ct.class_tute_id DESC");
        $paid = [];
        $p = Database::search("SELECT p.students_has_class_students_has_class_id enr, DATE_FORMAT(p.date_added, '%Y-%m') m
            FROM student_has_payments p JOIN students_has_classes shc ON shc.students_has_class_id = p.students_has_class_students_has_class_id
            WHERE shc.students_student_id = '" . $studentId . "' AND p.payment_status_payment_status_id IN (3, 8)");
        while ($p && ($row = $p->fetch_assoc())) {
            $paid[$row['enr'] . ':' . $row['m']] = true;
        }
        $steps = ['pending' => 'To pack', 'packed' => 'Packed', 'dispatched' => 'Sent', 'delivered' => 'Delivered', 'returned' => 'Returned'];
        $out = [];
        while ($res && ($r = $res->fetch_assoc())) {
            $month = $r['payment_month'] ? substr((string) $r['payment_month'], 0, 7) : null;
            $open = $month === null || (int) $r['is_free'] === 1 || isset($paid[$r['students_has_class_id'] . ':' . $month]);
            $posted = (int) $r['tute_types_tute_type_id'] !== 1;
            $out[] = [
                'id' => (int) $r['class_tute_id'], 'class_id' => (int) $r['class_id'], 'class' => (string) $r['class_name'],
                'title' => (string) ($r['tute_title'] ?: 'Tute'), 'description' => trim((string) $r['tute_description']),
                'posted' => $posted, 'files' => (int) $r['files'], 'open' => $open,
                'month' => $month ? date('F Y', strtotime($month . '-01')) : '',
                'date' => date('j M Y', strtotime((string) $r['date_added'])),
                'parcel' => $r['parcel'] ? ($steps[$r['parcel']] ?? $r['parcel']) : null,
                'thumb' => sr_thumb(null, 'tutes', $r['class_cover'] ?? null),
            ];
        }
        return $out;
    }

    function st_card(array $t): void
    {
        $href = 'downloadable-tute?class-id=' . $t['class_id'] . '&tute-id=' . $t['id'];
        $tab = $t['posted'] ? 'post' : 'download';
        if (!$t['open']) {
            [$badge, $badgeClass, $foot] = ['Pay to open', 'is-warn', 'Opens when the ' . $t['month'] . ' fee is paid'];
        } elseif ($t['posted']) {
            [$badge, $badgeClass, $foot] = ['By post', 'is-purple', $t['parcel'] ? 'Parcel: ' . $t['parcel'] : 'Sent to your home address'];
        } else {
            [$badge, $badgeClass, $foot] = ['Download', 'is-good', $t['files'] . ($t['files'] === 1 ? ' file' : ' files') . ' to open'];
        }
        $search = mb_strtolower($t['title'] . ' ' . $t['class'] . ' ' . $t['month'] . ' ' . $t['description']);
        ?>
        <article class="sr-card<?= $t['open'] ? '' : ' is-closed' ?>" data-tab="<?= $tab ?>" data-search="<?= sl_e($search) ?>">
            <a class="sr-thumb st-thumb" href="<?= sl_e($href) ?>" aria-label="Open <?= sl_e($t['title']) ?>">
                <span class="sr-thumb-empty"><?= enlixo_icon('tutes', 34) ?></span>
                <?php if ($t['thumb'] !== '') { ?><img src="<?= sl_e($t['thumb']) ?>" alt="" loading="lazy" onerror="this.remove()"><?php } ?>
                <span class="sr-play"><?= enlixo_icon($t['open'] ? ($t['posted'] ? 'box' : 'view') : 'lock', 22) ?></span>
                <?php if ($t['files']) { ?><span class="sr-duration"><?= (int) $t['files'] ?> <?= $t['files'] === 1 ? 'file' : 'files' ?></span><?php } ?>
            </a>
            <div class="sr-body">
                <div class="sr-tags">
                    <span class="ac-badge <?= $badgeClass ?>"><?= sl_e($badge) ?></span>
                    <span class="sr-kind"><?= sl_e($t['month'] !== '' ? $t['month'] : 'Tute') ?></span>
                </div>
                <h3 class="sr-title"><a href="<?= sl_e($href) ?>"><?= sl_e($t['title']) ?></a></h3>
                <div class="sr-meta">
                    <span><?= enlixo_icon('classes', 15, 'sl-ico') ?><?= sl_e($t['class']) ?></span>
                    <span><?= enlixo_icon('calendar', 15, 'sl-ico') ?>Added <?= sl_e($t['date']) ?></span>
                </div>
                <div class="sr-foot">
                    <span class="sr-foot-text"><?= sl_e($foot) ?></span>
                    <a class="<?= $t['open'] ? 'custom-btn' : 'custom-outline-btn' ?>" href="<?= sl_e($href) ?>"><?= enlixo_icon($t['open'] ? 'view' : 'info', 17) ?><?= $t['open'] ? 'Open Tute' : 'Details' ?></a>
                </div>
            </div>
        </article>
        <?php
    }

    /** $classes: [id => name] for the class filter */
    function st_grid(array $items, array $classes, int $classId): void
    {
        $tabs = [
            'all' => ['All', 'No tutes yet', 'Tutes your tutor shares appear here.', 'tutes'],
            'download' => ['Download', 'No tutes to download', 'Tutes you can open in the app appear here.', 'download'],
            'post' => ['By post', 'No tutes by post', 'Printed tutes your tutor posts to your home appear here.', 'box'],
        ];
        $counts = ['all' => count($items), 'download' => 0, 'post' => 0];
        foreach ($items as $t) {
            $counts[$t['posted'] ? 'post' : 'download']++;
        }
        ?>
        <div class="ac-card sr-list" data-kind="tutes">
            <div class="sr-toolbar">
                <div class="ac-tabs" role="tablist">
                    <?php foreach ($tabs as $key => $tb) { ?>
                        <button type="button" class="ac-tab<?= $key === 'all' ? ' is-active' : '' ?>" data-tab="<?= $key ?>" role="tab"><?= sl_e($tb[0]) ?><span class="ac-tab-count"><?= (int) $counts[$key] ?></span></button>
                    <?php } ?>
                </div>
                <form class="sr-filters" method="get" onsubmit="return false">
                    <div class="sr-search">
                        <label class="visually-hidden" for="sr-search">Search tutes</label>
                        <?= enlixo_icon('search', 18, 'sl-ico') ?>
                        <input type="search" id="sr-search" class="form-control custom-input h-48" placeholder="Search tutes" data-limit="text" maxlength="100" autocomplete="off">
                    </div>
                    <?php if (count($classes) > 1 && $classId === 0) { ?>
                        <label class="visually-hidden" for="sr-class">Class</label>
                        <select class="form-select custom-select h-48" id="sr-class" name="class" onchange="this.form.submit()">
                            <option value="">All classes</option>
                            <?php foreach ($classes as $id => $name) { ?>
                                <option value="<?= (int) $id ?>"><?= sl_e($name) ?></option>
                            <?php } ?>
                        </select>
                    <?php } ?>
                </form>
            </div>
            <div class="sr-grid">
                <?php foreach ($items as $t) st_card($t); ?>
            </div>
            <?php foreach ($tabs as $key => $tb) { ?>
                <div class="sr-empty<?= ($key === 'all' && !$items) ? '' : ' d-none' ?>" data-empty="<?= $key ?>"><?php sr_empty($tb[1], $tb[2], $tb[3]); ?></div>
            <?php } ?>
            <div class="sr-empty d-none" data-empty="search"><?php sr_empty('No tutes match your search', 'Try another word or clear the search box.', 'search'); ?></div>
        </div>
        <?php
    }
}
