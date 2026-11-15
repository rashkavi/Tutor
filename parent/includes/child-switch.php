<?php
/** Child chips (parent pages): one link per child, the current one marked. $base = page address with ? or & ready. */
function parent_child_switch(array $children, int $current, string $base): void
{
    if (count($children) < 2) {
        return;
    } ?>
    <div class="pp-children" role="tablist" aria-label="Choose a child">
        <?php foreach ($children as $c) { ?>
            <a class="pp-child <?= $c['id'] === $current ? 'is-active' : '' ?>" href="<?= sl_e($base . $c['id']) ?>" role="tab" aria-selected="<?= $c['id'] === $current ? 'true' : 'false' ?>">
                <span class="pp-avatar"><?= sl_e(strtoupper(mb_substr($c['first_name'], 0, 1))) ?></span><?= sl_e($c['name']) ?>
            </a>
        <?php } ?>
    </div>
<?php
}
