<?php
require_once __DIR__ . '/icons.php';

/**
 * Student portal: shared parts of the Time Table and Recordings pages (same layout as the ENLIXO V3 student portal).
 *   sl_icons_script([...])          the Solar icons a page script needs (window.SL_ICONS), drawn from this site, no CDN
 *   sl_page_title($title, $crumbs, $intro, $actionsHtml)   the V3 portal title card (title, breadcrumb or one line, buttons)
 *   sl_student_class($studentId, $classId)                 the class row when the student is enrolled (active), else null
 *   sl_student_classes($studentId)                         [class_id => class name] of the student's active classes
 * Ids are cast to int before they go into SQL; text goes out through htmlspecialchars().
 */

if (!function_exists('sl_e')) {
    function sl_e($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    function sl_icons_script(array $names): string
    {
        $map = [];
        foreach ($names as $n) {
            $map[$n] = enlixo_icon($n, $n === 'chevron-right' ? 20 : 15, 'sl-ico');
        }
        return '<script>window.SL_ICONS = Object.assign(window.SL_ICONS || {}, ' . json_encode($map, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ');</script>';
    }

    /** $crumbs = [[label, href|null], ...]; with no crumbs the $intro line is shown under the title. */
    function sl_page_title(string $title, array $crumbs = [], string $intro = '', string $actionsHtml = ''): void
    {
        $divider = "url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;)";
        ?>
        <div class="ac-title">
            <div class="ac-title-text">
                <h1><?= sl_e($title) ?></h1>
                <?php if ($crumbs) { ?>
                    <nav style="--bs-breadcrumb-divider: <?= $divider ?>;" aria-label="breadcrumb">
                        <ol class="breadcrumb m-0">
                            <?php foreach ($crumbs as [$label, $href]) {
                                if ($href) { ?>
                                    <li class="breadcrumb-item"><a href="<?= sl_e($href) ?>"><?= sl_e($label) ?></a></li>
                                <?php } else { ?>
                                    <li class="breadcrumb-item active sub-text" aria-current="page"><?= sl_e($label) ?></li>
                            <?php }
                            } ?>
                        </ol>
                    </nav>
                <?php } elseif ($intro !== '') { ?>
                    <p><?= sl_e($intro) ?></p>
                <?php } ?>
            </div>
            <?php if ($actionsHtml !== '') { ?>
                <div class="ac-title-actions"><?= $actionsHtml ?></div>
            <?php } ?>
        </div>
        <?php
    }

    function sl_student_class(int $studentId, int $classId): ?array
    {
        if ($studentId <= 0 || $classId <= 0) {
            return null;
        }
        $res = Database::search("SELECT c.`class_id`, c.`class_name`, c.`class_cover`
            FROM `students_has_classes` shc INNER JOIN `classes` c ON c.`class_id` = shc.`classes_class_id`
            WHERE shc.`students_student_id` = '" . $studentId . "' AND shc.`classes_class_id` = '" . $classId . "' AND shc.`status_status_id` = 1 LIMIT 1");
        return $res && $res->num_rows > 0 ? $res->fetch_assoc() : null;
    }

    function sl_student_classes(int $studentId): array
    {
        $out = [];
        $res = Database::search("SELECT c.`class_id`, c.`class_name`
            FROM `students_has_classes` shc INNER JOIN `classes` c ON c.`class_id` = shc.`classes_class_id`
            WHERE shc.`students_student_id` = '" . $studentId . "' AND shc.`status_status_id` = 1 ORDER BY c.`class_name`");
        while ($res && ($r = $res->fetch_assoc())) {
            $out[(int) $r['class_id']] = (string) $r['class_name'];
        }
        return $out;
    }
}
