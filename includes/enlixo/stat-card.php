<?php
require_once __DIR__ . '/icons.php';

/**
 * The V3 stat card for PHP pages: the same markup as assets/js/enlixo/components/StatCard.js
 * (icon tile + label + optional ⋯ menu of links, big number, grey caption). Needs assets/css/enlixo-ui.css.
 *   <?php enlixo_stat_card('payments', 'Total Income', 'LKR 12,500.00', 'Accepted payments', [['Open payments', 'payments']]); ?>
 */
function enlixo_stat_card(string $icon, string $label, string $value, string $caption = '', array $menu = []): void
{
    $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    ?>
    <div class="ex-stat">
        <div class="ex-stat-head">
            <span class="ex-stat-tile"><?= enlixo_icon($icon, 18) ?></span>
            <span class="ex-stat-label"><?= $e($label) ?></span>
            <?php if ($menu) { ?>
                <div class="dropdown">
                    <button type="button" class="ex-stat-menu" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= $e($label) ?>: more"><?= enlixo_icon('dots', 18) ?></button>
                    <ul class="dropdown-menu dropdown-menu-end ex-menu border-0 shadow">
                        <?php foreach ($menu as [$text, $href]) { ?>
                            <li><a class="dropdown-item ex-menu-item d-flex" href="<?= $e($href) ?>"><?= $e($text) ?></a></li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } ?>
        </div>
        <div class="ex-stat-body">
            <div class="ex-stat-main">
                <div class="ex-stat-value"><?= $e($value) ?></div>
                <div class="ex-stat-cap"><?= $e($caption) ?></div>
            </div>
        </div>
    </div>
    <?php
}
