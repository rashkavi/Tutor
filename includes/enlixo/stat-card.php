<?php
require_once __DIR__ . '/icons.php';

/**
 * The V3 stat card for PHP pages: the same markup as assets/js/enlixo/components/StatCard.js
 * (V3 .stats-mini-card: icon tile + title + optional ⋯ menu of links, big number, grey period text). Needs assets/css/enlixo-ui.css.
 *   <?php enlixo_stat_card('payments', 'Total Income', 'LKR 12,500.00', 'Accepted payments', [['Open payments', 'payments']]); ?>
 */
function enlixo_stat_card(string $icon, string $label, string $value, string $caption = '', array $menu = []): void
{
    $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    ?>
    <div class="stats-mini-card h-100">
        <div class="stats-mini-top">
            <div class="stats-mini-left">
                <div class="stats-mini-icon dark-theme"><?= enlixo_icon($icon, 16) ?></div>
                <span class="stats-mini-title"><?= $e($label) ?></span>
            </div>
            <?php if ($menu) { ?>
                <div class="dropdown">
                    <button type="button" class="stats-mini-menu" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= $e($label) ?>: more"><?= enlixo_icon('dots', 18) ?></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach ($menu as [$text, $href]) { ?>
                            <li><a class="dropdown-item" href="<?= $e($href) ?>"><?= $e($text) ?></a></li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } ?>
        </div>
        <div class="stats-mini-body">
            <div class="stats-mini-content">
                <div class="stats-mini-value<?= mb_strlen($value) > 7 ? ' is-long' : '' ?>"><?= $e($value) ?></div>
                <div class="stats-mini-meta"><span class="stats-mini-period"><?= $e($caption) ?></span></div>
            </div>
        </div>
    </div>
    <?php
}
