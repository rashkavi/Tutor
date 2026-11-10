<?php
/**
 * Older signature, kept for callers: draws the same V3 header card as enlixo_form_header().
 * The Action menu is filled by the page script (so locked items can show their premium badge):
 *     <?php enlixo_page_header('Students', 'Effortlessly manage ...', 'students', 'students-desc', 'Info text', 'students-actions'); ?>
 */
function enlixo_page_header(string $title, string $subtitle, string $i18nTitle = '', string $i18nSub = '', string $info = '', string $actionsId = 'page-actions'): void
{
    // Same card as every other page (V3): the description goes into the (i) quick guide, the breadcrumb under the title.
    enlixo_form_header($title, [[$title, null]], ['intro' => $subtitle] + ($info !== '' ? ['points' => [$info]] : []), $actionsId);
}

/**
 * ENLIXO V3 form page header: title + (i) Quick guide, breadcrumb under it, gear + primary button on the right.
 *     enlixo_form_header('Create a Class', [['Classes', 'classes'], ['Create a Class', null]],
 *         ['intro' => '...', 'points' => ['...', '...']], 'class-actions');
 * The buttons are added by the page script into #$actionsId.
 */
function enlixo_form_header(string $title, array $crumbs, array $guide = [], string $actionsId = 'page-actions'): void
{
    $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $divider = "url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;)";
    ?>
    <div class="col-12 px-4 py-3 component-theme mb-4">
        <div class="row p-0 m-0 g-0 align-items-center">
            <div class="col-12 col-md-7">
                <div class="d-flex align-items-center gap-2">
                    <span class="main-topic lh-lg"><?= $e($title) ?></span>
                    <?php if ($guide) { ?>
                        <button type="button" class="ex-info-btn" data-quick-guide="<?= $e(json_encode(['title' => $title] + $guide, JSON_UNESCAPED_UNICODE)) ?>" aria-label="Quick guide: <?= $e($title) ?>">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 15h-2v-6h2v6Zm0-8h-2V7h2v2Z"/></svg>
                        </button>
                    <?php } ?>
                </div>
                <nav style="--bs-breadcrumb-divider: <?= $divider ?>;" aria-label="breadcrumb">
                    <ol class="breadcrumb m-0 text-14">
                        <?php foreach ($crumbs as [$label, $href]) {
                            if ($href) { ?>
                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="<?= $e($href) ?>"><?= $e($label) ?></a></li>
                            <?php } else { ?>
                                <li class="breadcrumb-item active sub-text" aria-current="page"><?= $e($label) ?></li>
                        <?php }
                        } ?>
                    </ol>
                </nav>
            </div>
            <!-- V3: buttons sit in a col-lg-4 row, each in an equal col-6 cell (students.php / users.php); the gear goes left of the main button -->
            <div class="col-12 col-md-5 mt-3 mt-md-0 d-flex justify-content-md-end align-items-center gap-3 ex-header-actions" id="<?= $e($actionsId) ?>"></div>
        </div>
    </div>
    <?php
}
