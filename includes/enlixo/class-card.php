<?php
/**
 * The premium class card (same markup and look as the "Cards" view of tutor/classes, built in app-tutor-classes.js):
 * cover (or a drawn cover when the class has no picture), "2027 • A/L" pill, subject, name, medium, a second fact,
 * the blue "View Class" button and a ⋯ menu.
 *
 *   enlixo_class_card($row, 'class-details?class-id=12', '20 Students', [['Time Table', 'class-time-table?class-id=12', 'timetable'], ...]);
 *
 * $row needs class_name, class_cover, subject, year, exam_type, medium.
 */
require_once __DIR__ . '/icons.php';

function enlixo_class_tone(string $exam): string
{
    $e = strtolower($exam);
    return str_contains($e, 'a/l') || str_contains($e, 'advanced') ? 'pink'
        : (str_contains($e, 'o/l') || str_contains($e, 'ordinary') ? 'orange'
        : (str_contains($e, 'scholar') ? 'purple' : 'blue'));
}

/** @param list<array{0:string,1:string,2:string}> $menu [label, link, icon] */
function enlixo_class_card(array $c, string $href, string $fact, array $menu = [], string $factIcon = 'user'): void
{
    $e = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $tone = enlixo_class_tone((string) $c['exam_type']);
    $cover = (string) ($c['class_cover'] ?? '');
    $hasCover = $cover !== '' && basename($cover) === $cover && is_file(dirname(__DIR__, 2) . '/assets/imgs/classes-covers/' . $cover);
    $subject = (string) $c['subject'];
    $menuId = 'pc-' . substr(md5($href . $c['class_name']), 0, 8);
    ?>
    <article class="ex-pcard h-100">
        <div class="ex-pcard-cover">
            <a href="<?= $e($href) ?>" tabindex="-1" aria-hidden="true">
                <?php if ($hasCover) { ?>
                    <img src="<?= Project::getAssetsPath() ?>/imgs/classes-covers/<?= $e($cover) ?>" alt="" loading="lazy">
                <?php } else { ?>
                    <span class="ex-pcard-art tone-<?= $tone ?>"><span class="ex-pcard-art-letter"><?= $e(mb_strtoupper(mb_substr($subject !== '' ? $subject : (string) $c['class_name'], 0, 1))) ?></span><span class="ex-pcard-art-name"><?= $e($subject) ?></span></span>
                <?php } ?>
            </a>
            <span class="ex-pcard-pill tone-<?= $tone ?>"><?= $e($c['year']) ?> • <?= $e($c['exam_type']) ?></span>
        </div>
        <div class="ex-pcard-body">
            <span class="ex-pcard-subject"><?= $e($subject) ?></span>
            <a href="<?= $e($href) ?>" class="ex-pcard-title"><?= $e($c['class_name']) ?></a>
            <div class="ex-pcard-meta">
                <span><?= enlixo_icon('language', 18) ?><?= $e($c['medium']) ?></span>
                <span><?= enlixo_icon($factIcon, 18) ?><span class="text-truncate"><?= $e($fact) ?></span></span>
            </div>
            <div class="ex-pcard-foot">
                <a href="<?= $e($href) ?>" class="ex-pcard-view"><?= enlixo_icon('view', 18) ?>View Class</a>
                <?php if ($menu) { ?>
                    <div class="ex-pcard-tools dropdown">
                        <button type="button" class="ex-pcard-round" id="<?= $menuId ?>" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More for <?= $e($c['class_name']) ?>"><?= enlixo_icon('kebab', 20) ?></button>
                        <ul class="dropdown-menu dropdown-menu-end ex-pcard-menu" aria-labelledby="<?= $menuId ?>">
                            <?php foreach ($menu as [$label, $link, $icon]) { ?>
                                <li><a class="dropdown-item" href="<?= $e($link) ?>"><?= enlixo_icon($icon, 18) ?><span><?= $e($label) ?></span></a></li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>
            </div>
        </div>
    </article>
    <?php
}
