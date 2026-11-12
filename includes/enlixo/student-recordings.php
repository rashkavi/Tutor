<?php
require_once __DIR__ . '/student-learning.php';

/**
 * Student portal recordings, laid out like the ENLIXO V3 student portal (lecture-recordings / pre-recordings and the
 * recording view): stat tiles, tabs with counts, search + class filter, 16:9 video cards, and the watch page panels.
 *
 *   sr_lecture_recordings($studentId, $classId)   recordings of the class days of the student's active classes
 *   sr_pre_recordings($studentId, $classId)       pre-recorded classes of the student's active classes
 *   sr_grid($items, 'lecture' | 'pre', $tabs, $classes, $classId)   toolbar + cards (portal/js/recordings.js filters them)
 *   sr_facts(), sr_files(), sr_more()             the side panels of the watch pages
 *
 * Only the student's own active classes are read (students_has_classes.status_status_id = 1). Ids are cast to int
 * before they go into SQL; every text goes out through htmlspecialchars().
 * Lock rules: "Opens on" = unlock_at (later = locked), "Available until" = expire_date (passed = expired).
 * Watched (class recordings): attendance Watched (4) or a watch session; Present / Joined = attended; Absent = missed.
 * Progress (pre-recorded): watched seconds of recording_watch_sessions against the length; 90 % or more = completed.
 */

if (!function_exists('sr_lecture_recordings')) {
    define('SR_COMPLETE_AT', 90);

    function sr_minutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $h > 0 ? ($m > 0 ? "{$h}h {$m}m" : "{$h}h") : "{$m}m";
    }

    function sr_thumb(?string $cover, string $folder, ?string $classCover): string
    {
        $assets = Project::getAssetsPath();
        if (!empty($cover) && file_exists($assets . '/imgs/' . $folder . '/' . basename($cover))) {
            return $assets . '/imgs/' . $folder . '/' . rawurlencode(basename($cover));
        }
        if (!empty($classCover) && file_exists($assets . '/imgs/classes-covers/' . basename($classCover))) {
            return $assets . '/imgs/classes-covers/' . rawurlencode(basename($classCover));
        }
        return '';
    }

    /** locked (opens later) | expired | open, with the texts the cards show */
    function sr_lock(?string $unlockAt, ?string $expireDate): array
    {
        $now = time();
        $unlock = $unlockAt ? strtotime($unlockAt) : false;
        $expire = $expireDate ? strtotime(substr($expireDate, 0, 10) . ' 23:59:59') : false;
        $lock = [
            'state' => 'open', 'watchable' => true,
            'unlock_text' => $unlock ? date('D, j M', $unlock) . ' at ' . date('g:i A', $unlock) : '',
            'unlock_iso' => $unlock ? date('c', $unlock) : '',
            'expire_text' => $expire ? date('j M Y', $expire) : '',
            'days_left' => $expire ? max(0, (int) floor(($expire - $now) / 86400)) : null,
        ];
        if ($unlock !== false && $unlock > $now) {
            $lock['state'] = 'locked';
            $lock['watchable'] = false;
        } elseif ($expire !== false && $expire < $now) {
            $lock['state'] = 'expired';
            $lock['watchable'] = false;
        }
        return $lock;
    }

    /** runs the query, and again without the watch-session part where that table is not there yet */
    function sr_query(string $withWatch, string $withoutWatch)
    {
        try {
            $res = Database::search($withWatch);
        } catch (Throwable $e) {
            $res = false;
        }
        return $res ?: Database::search($withoutWatch);
    }

    function sr_lecture_recordings(int $studentId, int $classId = 0): array
    {
        $where = "shc.`students_student_id` = '" . $studentId . "' AND shc.`status_status_id` = 1"
            . ($classId > 0 ? " AND c.`class_id` = '" . $classId . "'" : '');
        $select = "SELECT lr.`lecture_recording_id`, lr.`recording_title`, lr.`recording_description`, lr.`recording_cover`, lr.`duration`,
                lr.`added_at`, lr.`expire_date`, lr.`unlock_at`, rt.`name` AS type_name,
                tt.`time_table_id`, tt.`date`, tt.`start_time`, tt.`end_time`, tt.`lecture_title`,
                c.`class_id`, c.`class_name`, c.`class_cover`, u.`user_fname`, u.`user_lname`,
                (SELECT MAX(a.`attendance_status_attendance_status_id`) FROM `attendance` a
                  WHERE a.`time_table_time_table_id` = tt.`time_table_id` AND a.`students_student_id` = '" . $studentId . "') AS att";
        $from = " FROM `lecture_recording` lr
            INNER JOIN `time_table` tt ON tt.`time_table_id` = lr.`time_table_time_table_id`
            INNER JOIN `classes` c ON c.`class_id` = tt.`classes_class_id`
            INNER JOIN `students_has_classes` shc ON shc.`classes_class_id` = c.`class_id`
            LEFT JOIN `users` u ON u.`user_id` = c.`tutor_id`
            LEFT JOIN `tutor_recording_types` rt ON rt.`recording_type_id` = lr.`tutor_recording_types_id`
            WHERE $where ORDER BY tt.`date` DESC, tt.`start_time` DESC, lr.`lecture_recording_id` DESC";
        $watch = ", (SELECT COUNT(*) FROM `recording_watch_sessions` ws WHERE ws.`recording_kind` = 'lecture'
                  AND ws.`recording_id` = lr.`lecture_recording_id` AND ws.`students_student_id` = '" . $studentId . "') AS sessions";
        $res = sr_query($select . $watch . $from, $select . ', 0 AS sessions' . $from);

        $out = [];
        while ($res && ($r = $res->fetch_assoc())) {
            $att = (int) $r['att'];
            $watch = (int) $r['sessions'] > 0 || $att === 4 ? 'watched' : (in_array($att, [1, 2], true) ? 'attended' : ($att === 3 ? 'absent' : 'none'));
            $day = strtotime((string) $r['date']);
            $out[] = [
                'id' => (int) $r['lecture_recording_id'],
                'title' => (string) ($r['recording_title'] ?: ($r['lecture_title'] ?: 'Class recording')),
                'description' => (string) ($r['recording_description'] ?? ''),
                'class_id' => (int) $r['class_id'],
                'class' => (string) $r['class_name'],
                'tutor' => trim(($r['user_fname'] ?? '') . ' ' . ($r['user_lname'] ?? '')),
                'type' => (string) ($r['type_name'] ?: 'Video'),
                'time_table_id' => (int) $r['time_table_id'],
                'date_text' => $day ? date('D, j M Y', $day) : '',
                'time_text' => $r['start_time'] ? date('g:i A', strtotime((string) $r['start_time'])) . ' - ' . date('g:i A', strtotime((string) $r['end_time'])) : '',
                'duration' => sr_minutes((int) $r['duration']),
                'added_at' => (string) $r['added_at'],
                'thumb' => sr_thumb($r['recording_cover'] ?? null, 'recording-thumbnail', $r['class_cover'] ?? null),
                'watch' => $watch,
                'lock' => sr_lock($r['unlock_at'] ?? null, $r['expire_date'] ?? null),
            ];
        }
        return $out;
    }

    function sr_pre_recordings(int $studentId, int $classId = 0): array
    {
        $where = "shc.`students_student_id` = '" . $studentId . "' AND shc.`status_status_id` = 1"
            . ($classId > 0 ? " AND c.`class_id` = '" . $classId . "'" : '');
        $select = "SELECT pr.`pre_recording_id`, pr.`title`, pr.`recording_cover`, pr.`duration`, pr.`added_at`, pr.`unlock_at`,
                rt.`name` AS type_name, c.`class_id`, c.`class_name`, c.`class_cover`, u.`user_fname`, u.`user_lname`";
        $from = " FROM `pre_recordings` pr
            INNER JOIN `classes` c ON c.`class_id` = pr.`classes_class_id`
            INNER JOIN `students_has_classes` shc ON shc.`classes_class_id` = c.`class_id`
            LEFT JOIN `users` u ON u.`user_id` = c.`tutor_id`
            LEFT JOIN `tutor_recording_types` rt ON rt.`recording_type_id` = pr.`tutor_recording_types_id`
            WHERE $where ORDER BY pr.`added_at` DESC, pr.`pre_recording_id` DESC";
        $watch = ", (SELECT COALESCE(SUM(ws.`watched_seconds`), 0) FROM `recording_watch_sessions` ws WHERE ws.`recording_kind` = 'pre'
                  AND ws.`recording_id` = pr.`pre_recording_id` AND ws.`students_student_id` = '" . $studentId . "') AS seconds,
                (SELECT MAX(ws.`last_heartbeat_at`) FROM `recording_watch_sessions` ws WHERE ws.`recording_kind` = 'pre'
                  AND ws.`recording_id` = pr.`pre_recording_id` AND ws.`students_student_id` = '" . $studentId . "') AS last_watched";
        $res = sr_query($select . $watch . $from, $select . ', 0 AS seconds, NULL AS last_watched' . $from);

        $out = [];
        while ($res && ($r = $res->fetch_assoc())) {
            $minutes = (int) $r['duration'];
            $seconds = (int) $r['seconds'];
            $progress = $minutes > 0 ? min(100, (int) floor($seconds / ($minutes * 60) * 100)) : ($seconds > 0 ? 100 : 0);
            $status = $progress >= SR_COMPLETE_AT ? 'completed' : ($seconds > 0 ? 'in_progress' : 'new');
            $out[] = [
                'id' => (int) $r['pre_recording_id'],
                'title' => (string) ($r['title'] ?: 'Pre-recorded class'),
                'class_id' => (int) $r['class_id'],
                'class' => (string) $r['class_name'],
                'tutor' => trim(($r['user_fname'] ?? '') . ' ' . ($r['user_lname'] ?? '')),
                'type' => (string) ($r['type_name'] ?: 'Video'),
                'duration' => sr_minutes($minutes),
                'added_at' => (string) $r['added_at'],
                'thumb' => sr_thumb($r['recording_cover'] ?? null, 'pre-recording-thumbnail', $r['class_cover'] ?? null),
                'progress' => $status === 'completed' ? 100 : $progress,
                'status' => $status,
                'last_watched' => $r['last_watched'] ? date('j M', strtotime((string) $r['last_watched'])) : '',
                'lock' => sr_lock($r['unlock_at'] ?? null, null),
            ];
        }
        return $out;
    }

    /** [tab, badge, badge class, footer text] of a class recording */
    function sr_lecture_state(array $r): array
    {
        $lock = $r['lock'];
        if ($lock['state'] === 'locked') return ['locked', 'Opens later', 'is-purple', 'Opens ' . $lock['unlock_text']];
        if ($lock['state'] === 'expired') return ['locked', 'Expired', 'is-bad', 'Expired on ' . $lock['expire_text']];
        if ($r['watch'] === 'watched') return ['watched', 'Watched', 'is-good', 'You watched this recording'];
        if ($r['watch'] === 'attended') return ['watched', 'Attended', 'is-good', 'You were at this class'];
        $left = $lock['days_left'];
        $text = $lock['expire_text'] !== '' ? ($left === 0 ? 'Last day to watch' : 'Available until ' . $lock['expire_text']) : 'Available to watch';
        return ['todo', $r['watch'] === 'absent' ? 'Missed class' : 'Not watched', $r['watch'] === 'absent' ? 'is-warn' : '', $text];
    }

    /** [tab, badge, badge class, footer text] of a pre-recorded class */
    function sr_pre_state(array $r): array
    {
        $lock = $r['lock'];
        if ($lock['state'] === 'locked') return ['soon', 'Opens later', 'is-purple', 'Opens ' . $lock['unlock_text']];
        if ($r['status'] === 'completed') return ['completed', 'Completed', 'is-good', 'You finished this class'];
        if ($r['status'] === 'in_progress') return ['progress', $r['progress'] . '% watched', 'is-warn', $r['last_watched'] !== '' ? 'Last watched ' . $r['last_watched'] : 'Keep watching'];
        return ['new', 'Not started', '', 'Available to watch'];
    }

    function sr_href(array $r, string $kind): string
    {
        return ($kind === 'pre' ? 'watch-pre-recording?recording-id=' : 'watch-recording?recording-id=') . $r['id'];
    }

    function sr_card(array $r, string $kind): void
    {
        $isPre = $kind === 'pre';
        [$tab, $badge, $badgeClass, $foot] = $isPre ? sr_pre_state($r) : sr_lecture_state($r);
        $open = $r['lock']['watchable'];
        $href = sr_href($r, $kind);
        $search = mb_strtolower($r['title'] . ' ' . $r['class'] . ' ' . $r['tutor'] . ' ' . $r['type'] . ' ' . ($r['date_text'] ?? ''));
        $icon = $open ? 'play' : ($r['lock']['state'] === 'locked' ? 'lock' : 'history');
        ?>
        <article class="sr-card<?= $open ? '' : ' is-closed' ?>" data-tab="<?= $tab ?>" data-search="<?= sl_e($search) ?>">
            <a class="sr-thumb" href="<?= sl_e($href) ?>" aria-label="<?= sl_e(($open ? 'Watch ' : 'Open ') . $r['title']) ?>">
                <span class="sr-thumb-empty"><?= enlixo_icon('recordings', 34) ?></span>
                <?php if ($r['thumb'] !== '') { ?><img src="<?= sl_e($r['thumb']) ?>" alt="" loading="lazy" onerror="this.remove()"><?php } ?>
                <span class="sr-play"><?= enlixo_icon($icon, 22) ?></span>
                <?php if ($r['duration'] !== '') { ?><span class="sr-duration"><?= sl_e($r['duration']) ?></span><?php } ?>
                <?php if ($isPre && $r['progress'] > 0) { ?>
                    <span class="sr-progress"><span class="<?= $r['status'] === 'completed' ? 'is-good' : '' ?>" style="width: <?= (int) $r['progress'] ?>%"></span></span>
                <?php } ?>
            </a>
            <div class="sr-body">
                <div class="sr-tags">
                    <span class="ac-badge <?= $badgeClass ?>"><?= sl_e($badge) ?></span>
                    <span class="sr-kind"><?= sl_e($r['type']) ?></span>
                </div>
                <h3 class="sr-title"><a href="<?= sl_e($href) ?>"><?= sl_e($r['title']) ?></a></h3>
                <div class="sr-meta">
                    <span><?= enlixo_icon('classes', 15, 'sl-ico') ?><?= sl_e($r['class']) ?></span>
                    <?php if (!$isPre && $r['date_text'] !== '') { ?><span><?= enlixo_icon('calendar', 15, 'sl-ico') ?><?= sl_e($r['date_text']) ?></span><?php } ?>
                    <?php if ($r['tutor'] !== '') { ?><span><?= enlixo_icon('teacher', 15, 'sl-ico') ?><?= sl_e($r['tutor']) ?></span><?php } ?>
                </div>
                <div class="sr-foot">
                    <span class="sr-foot-text"><?= sl_e($foot) ?></span>
                    <?php if ($open) { ?>
                        <a class="custom-btn" href="<?= sl_e($href) ?>"><?= enlixo_icon('play', 17) ?><?= $isPre && $r['status'] === 'in_progress' ? 'Continue' : 'Watch' ?></a>
                    <?php } else { ?>
                        <a class="custom-outline-btn" href="<?= sl_e($href) ?>"><?= enlixo_icon('info', 17) ?>Details</a>
                    <?php } ?>
                </div>
            </div>
        </article>
        <?php
    }

    function sr_empty(string $title, string $text, string $icon = 'inbox'): void
    {
        ?>
        <div class="empty-state">
            <span class="empty-state-icon"><?= enlixo_icon($icon, 28) ?></span>
            <span class="empty-state-title"><?= sl_e($title) ?></span>
            <span class="empty-state-text"><?= sl_e($text) ?></span>
        </div>
        <?php
    }

    /** $tabs: key => [label, empty title, empty text, icon]; the first key is "all". $classes: [id => name] for the filter. */
    function sr_grid(array $items, string $kind, array $tabs, array $classes, int $classId): void
    {
        $counts = array_fill_keys(array_keys($tabs), 0);
        $counts['all'] = count($items);
        foreach ($items as $r) {
            $t = ($kind === 'pre' ? sr_pre_state($r) : sr_lecture_state($r))[0];
            $counts[$t] = ($counts[$t] ?? 0) + 1;
        }
        ?>
        <div class="ac-card sr-list" data-kind="<?= $kind ?>">
            <div class="sr-toolbar">
                <div class="ac-tabs" role="tablist">
                    <?php foreach ($tabs as $key => $t) { ?>
                        <button type="button" class="ac-tab<?= $key === 'all' ? ' is-active' : '' ?>" data-tab="<?= $key ?>" role="tab">
                            <?= sl_e($t[0]) ?><span class="ac-tab-count"><?= (int) ($counts[$key] ?? 0) ?></span>
                        </button>
                    <?php } ?>
                </div>
                <form class="sr-filters" method="get" onsubmit="return false">
                    <div class="sr-search">
                        <label class="visually-hidden" for="sr-search">Search recordings</label>
                        <?= enlixo_icon('search', 18, 'sl-ico') ?>
                        <input type="search" id="sr-search" class="form-control custom-input h-48" placeholder="Search recordings" data-limit="text" maxlength="100" autocomplete="off">
                    </div>
                    <?php if (count($classes) > 1 || $classId > 0) { ?>
                        <label class="visually-hidden" for="sr-class">Class</label>
                        <select class="form-select custom-select h-48" id="sr-class" name="class" onchange="this.form.submit()">
                            <option value="">All classes</option>
                            <?php foreach ($classes as $id => $name) { ?>
                                <option value="<?= (int) $id ?>" <?= (int) $id === $classId ? 'selected' : '' ?>><?= sl_e($name) ?></option>
                            <?php } ?>
                        </select>
                    <?php } ?>
                </form>
            </div>

            <div class="sr-grid">
                <?php foreach ($items as $r) sr_card($r, $kind); ?>
            </div>
            <?php foreach ($tabs as $key => $t) { ?>
                <div class="sr-empty<?= ($key === 'all' && !$items) ? '' : ' d-none' ?>" data-empty="<?= $key ?>"><?php sr_empty($t[1], $t[2], $t[3]); ?></div>
            <?php } ?>
            <div class="sr-empty d-none" data-empty="search"><?php sr_empty('No recordings match your search', 'Try another word or clear the search box.', 'search'); ?></div>
        </div>
        <?php
    }

    /** "About this recording" rows: [icon, label, value] (empty values are left out) */
    function sr_facts(array $facts): void
    {
        echo '<ul class="sr-facts">';
        foreach ($facts as [$icon, $label, $value]) {
            if ((string) $value === '') {
                continue;
            }
            echo '<li><span class="sr-fact-icon">' . enlixo_icon($icon, 16) . '</span><span class="sr-fact-label">' . sl_e($label)
                . '</span><strong>' . sl_e($value) . '</strong></li>';
        }
        echo '</ul>';
    }

    /** class notes: [[file, name], ...] stored in assets/uploads/$folder/ (PDF opens in a new tab, other files download) */
    function sr_files(array $files, string $folder): void
    {
        $base = Project::getAssetsPath() . '/uploads/' . $folder . '/';
        $files = array_values(array_filter($files, fn ($f) => $f[0] !== '' && file_exists($base . basename($f[0]))));
        if (!$files) {
            return;
        }
        ?>
        <div class="ac-card">
            <div class="ac-card-head"><h2 class="ac-card-title">Class Notes</h2><span class="sub-text text-12"><?= count($files) ?> <?= count($files) === 1 ? 'file' : 'files' ?></span></div>
            <div class="sr-files">
                <?php foreach ($files as [$file, $name]) {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $label = $name !== '' ? $name : $file; ?>
                    <a class="sr-file" href="<?= sl_e($base . rawurlencode(basename($file))) ?>" <?= $ext === 'pdf' ? 'target="_blank" rel="noopener"' : 'download="' . sl_e($label) . '"' ?>>
                        <?= enlixo_icon($ext === 'pdf' ? 'tutes' : 'download', 18, 'sl-ico') ?>
                        <span class="sr-file-name"><?= sl_e($label) ?></span>
                        <span class="sr-file-meta"><?= sl_e(strtoupper($ext)) ?> file · <?= $ext === 'pdf' ? 'Opens in a new tab' : 'Downloads to your device' ?></span>
                    </a>
                <?php } ?>
            </div>
        </div>
        <?php
    }

    /** "More from this class": up to 4 other recordings */
    function sr_more(array $items, string $kind, string $listUrl): void
    {
        if (!$items) {
            return;
        }
        ?>
        <div class="ac-card">
            <div class="ac-card-head"><h2 class="ac-card-title">More from this class</h2><a class="sr-link" href="<?= sl_e($listUrl) ?>">See all</a></div>
            <div class="sr-mini-list">
                <?php foreach ($items as $r) {
                    [, $badge] = $kind === 'pre' ? sr_pre_state($r) : sr_lecture_state($r); ?>
                    <a class="sr-mini<?= $r['lock']['watchable'] ? '' : ' is-closed' ?>" href="<?= sl_e(sr_href($r, $kind)) ?>">
                        <span class="sr-mini-thumb">
                            <?php if ($r['thumb'] !== '') { ?><img src="<?= sl_e($r['thumb']) ?>" alt="" loading="lazy" onerror="this.remove()"><?php } ?>
                            <?= enlixo_icon($r['lock']['watchable'] ? 'play' : 'lock', 16, 'sl-ico') ?>
                        </span>
                        <span class="sr-mini-body">
                            <strong><?= sl_e($r['title']) ?></strong>
                            <span><?= sl_e($kind === 'pre' ? $r['duration'] : $r['date_text']) ?> · <?= sl_e($badge) ?></span>
                        </span>
                    </a>
                <?php } ?>
            </div>
        </div>
        <?php
    }
}
