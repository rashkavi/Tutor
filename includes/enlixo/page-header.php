<?php
/**
 * ENLIXO V3 page header card: title + (i) info + subtitle on the left, the blue "Action" menu on the right.
 * The Action menu is filled by the page script (so locked items can show their premium badge):
 *     <?php enlixo_page_header('Students', 'Effortlessly manage ...', 'students', 'students-desc', 'Info text', 'students-actions'); ?>
 */
function enlixo_page_header(string $title, string $subtitle, string $i18nTitle = '', string $i18nSub = '', string $info = '', string $actionsId = 'page-actions'): void
{
    $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    ?>
    <div class="ex-root ex-page-header component-theme mb-4">
        <div class="ex-grow">
            <h1 class="ex-page-title"><span<?= $i18nTitle ? ' data-i18n="' . $e($i18nTitle) . '"' : '' ?>><?= $e($title) ?></span>
                <?php if ($info !== '') { ?>
                    <span class="ex-info" tabindex="0" role="img" aria-label="<?= $e($info) ?>" title="<?= $e($info) ?>"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 15h-2v-6h2v6Zm0-8h-2V7h2v2Z"/></svg></span>
                <?php } ?>
            </h1>
            <p class="ex-page-sub"<?= $i18nSub ? ' data-i18n="' . $e($i18nSub) . '"' : '' ?>><?= $e($subtitle) ?></p>
        </div>
        <div id="<?= $e($actionsId) ?>" class="d-flex gap-2"></div>
    </div>
    <?php
}
