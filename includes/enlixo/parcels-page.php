<?php
/**
 * Tute parcels (portal/couriered-tute-details.php for the student, parent/couriered-tute-details.php for the parent):
 * stat tiles and one card per parcel (tute, class, steps To pack -> Packed -> Sent -> Delivered, courier, tracking).
 * Rows come from Enlixo\Tutes\TuteRepository::forStudent() (the parent adds 'child' to each row).
 *   pc_render($parcels, ['tute' => fn ($p) => url|null, 'child' => true|false]);
 */
require_once __DIR__ . '/student-learning.php';
require_once __DIR__ . '/stat-card.php';

if (!function_exists('pc_render')) {
    function pc_render(array $parcels, array $opts = []): void
    {
        $way = array_filter($parcels, static fn ($p) => in_array($p['status'], ['pending', 'packed', 'dispatched'], true));
        $sent = array_filter($parcels, static fn ($p) => $p['status'] === 'dispatched');
        $done = array_filter($parcels, static fn ($p) => $p['status'] === 'delivered');
        $back = array_filter($parcels, static fn ($p) => $p['status'] === 'returned');
        $order = ['pending' => 0, 'packed' => 1, 'dispatched' => 2, 'delivered' => 3];
        $date = static fn (?string $d) => $d ? date('j M Y', strtotime($d)) : '';
        ?>
                            <div class="ac-tiles">
                                <?php
                                enlixo_stat_card('box', 'All Parcels', (string) count($parcels), $parcels ? 'Printed tutes posted to your home' : 'Nothing posted yet');
                                enlixo_stat_card('hourglass', 'On the Way', (string) count($way), $sent ? count($sent) . ' with the courier now' : 'Being packed by the tutor');
                                enlixo_stat_card('check', 'Delivered', (string) count($done), $done ? 'Last on ' . $date(max(array_column($done, 'delivered_on')) ?: null) : 'None delivered yet');
                                enlixo_stat_card('warning', 'Returned', (string) count($back), $back ? 'Check the address with the tutor' : 'No parcel came back');
                                ?>
                            </div>

                            <div class="ac-card">
                                <?php if (!$parcels) { ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><?= enlixo_icon('inbox', 28) ?></div>
                                        <div class="empty-state-title">No posted tutes yet</div>
                                        <div class="empty-state-text">When a tutor posts a printed tute, you can follow it here from packing to your door.</div>
                                    </div>
                                <?php } else { ?>
                                    <div class="pc-grid">
                                        <?php foreach ($parcels as $p) {
                                            $at = $order[$p['status']] ?? -1;
                                            $tone = $p['status'] === 'delivered' ? 'is-good' : ($p['status'] === 'returned' ? 'is-bad' : ($p['status'] === 'dispatched' ? 'is-purple' : 'is-warn'));
                                            $href = isset($opts['tute']) ? ($opts['tute'])($p) : null; ?>
                                            <article class="pc-card">
                                                <div class="pc-head">
                                                    <span class="pc-icon"><?= enlixo_icon('box', 22) ?></span>
                                                    <div class="pc-title">
                                                        <strong><?= $href ? '<a href="' . sl_e($href) . '">' . sl_e($p['tute']) . '</a>' : sl_e($p['tute']) ?></strong>
                                                        <small><?= sl_e($p['class']) ?><?= !empty($opts['child']) && !empty($p['child']) ? ' · for ' . sl_e($p['child']) : '' ?></small>
                                                    </div>
                                                    <span class="ac-badge <?= $tone ?>"><?= sl_e($p['step']) ?></span>
                                                </div>
                                                <?php if ($p['status'] === 'returned') { ?>
                                                    <div class="pc-note is-bad"><?= enlixo_icon('warning', 16) ?>The parcel came back to the tutor. Ask them to send it again and check your address.</div>
                                                <?php } else { ?>
                                                    <ol class="tu-steps">
                                                        <?php foreach (['To pack', 'Packed', 'Sent', 'Delivered'] as $i => $label) { ?>
                                                            <li class="<?= $i < $at || ($i === $at && $at === 3) ? 'is-done' : ($i === $at ? 'is-now' : '') ?>"><span></span><?= $label ?></li>
                                                        <?php } ?>
                                                    </ol>
                                                <?php } ?>
                                                <div class="tu-facts">
                                                    <?php if ($p['courier_name']) { ?><div><span>Courier</span><strong><?= sl_e($p['courier_name']) ?></strong></div><?php } ?>
                                                    <?php if ($p['tracking_no']) { ?><div><span>Tracking number</span><strong><?= sl_e($p['tracking_no']) ?></strong></div><?php } ?>
                                                    <?php if ($p['dispatched_on']) { ?><div><span>Sent on</span><strong><?= sl_e($date($p['dispatched_on'])) ?></strong></div><?php } elseif ($p['expected_on']) { ?><div><span>Tutor posts it on</span><strong><?= sl_e($date($p['expected_on'])) ?></strong></div><?php } ?>
                                                    <?php if ($p['delivered_on']) { ?><div><span>Delivered on</span><strong><?= sl_e($date($p['delivered_on'])) ?></strong></div><?php } ?>
                                                    <?php if ($p['address']) { ?><div><span>Sent to</span><strong><?= sl_e($p['address']) ?></strong></div><?php } ?>
                                                </div>
                                                <?php if ($p['note']) { ?><div class="pc-note"><?= enlixo_icon('info', 16) ?><?= sl_e($p['note']) ?></div><?php } ?>
                                                <?php if ($p['tracking_url'] && $p['tracking_no']) { ?>
                                                    <a class="custom-btn w-100 mt-3 d-inline-flex align-items-center justify-content-center gap-2 text-decoration-none" href="<?= sl_e($p['tracking_url']) ?>" target="_blank" rel="noopener"><?= enlixo_icon('link', 18) ?>Track the parcel</a>
                                                <?php } ?>
                                            </article>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
        <?php
    }
}
