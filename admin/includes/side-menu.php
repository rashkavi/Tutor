<?php
require_once "../includes/nav-item.php";
require_once __DIR__ . "/../../includes/enlixo/icons.php";

/**
 * Admin (platform owner) side menu, same look and code as the Tutor menu (enlixo-nav.css), see docs/ADMIN-PLAN.md:
 *   Dashboard / Tutors ▾ / Finance ▾ / Plans & Billing ▾ / Content ▾
 * Pages call load_side_menu::load("Tutors") etc.; the group holding that item opens and the
 * current page's link is highlighted (plans?tab=... links match the open tab).
 */
class load_side_menu
{
    /** [label, icon, link, i18n key, matches load() names, children] */
    private static function menu(): array
    {
        return [
            ['Dashboard', 'dashboard', 'dashboard', '', ['Dashboard']],
            ['Tutors', 'tutors', null, '', [], [
                ['All Tutors', null, 'tutors', '', ['Tutors', 'Users', 'Tutor Details']],
                ['Requested Tutors', null, 'requested-users', '', ['Requested Tutors']],
                ['Add Tutor', null, 'add-tutor', '', ['Add Tutor']],
            ]],
            ['Finance', 'finance', null, '', [], [
                ['Invoices', null, 'invoices', '', ['Invoices']],
                ['Payments', null, 'tutor-payments', '', ['Payments']],
            ]],
            ['Plans & Billing', 'plans', null, '', [], [
                ['Plans & Prices', null, 'plans?tab=plans', '', ['Plans']],
                ['Add-ons', null, 'plans?tab=addons', '', []],
                ['Trial & Billing Rules', null, 'plans?tab=rules', '', []],
                ['Bank Accounts', null, 'plans?tab=banks', '', []],
            ]],
            ['Content', 'content', null, '', [], [
                ['News', null, 'news', '', ['News']],
                ['Classes', null, 'classes', '', ['Classes']],
            ]],
        ];
    }

    private static function e(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }

    /** Does this item (or a child) belong to the current page? */
    private static function isActive(array $item, string $selected, string $page): bool
    {
        if (strtok((string) ($item[2] ?? ''), '?') === $page && self::tabMatches($item[2]) || in_array($selected, $item[4] ?? [], true)) {
            return true;
        }
        foreach ($item[5] ?? [] as $child) {
            if (self::isActive($child, $selected, $page)) {
                return true;
            }
        }
        return false;
    }

    /** plans?tab=rules is current only on that tab (plans without a tab = the first one) */
    private static function tabMatches(?string $href): bool
    {
        $q = parse_url((string) $href, PHP_URL_QUERY);
        if (!$q) {
            return true;
        }
        parse_str($q, $want);
        return ($_GET['tab'] ?? 'plans') === ($want['tab'] ?? '');
    }

    private static function label(array $item): string
    {
        return '<span class="ex-nav-label"' . ($item[3] ? ' data-i18n="' . self::e($item[3]) . '"' : '') . '>' . self::e($item[0]) . '</span>';
    }

    private static function renderChildren(array $children, string $selected, string $page, int $depth): void
    {
        echo '<ul class="ex-subnav ex-depth-' . $depth . '">';
        foreach ($children as $child) {
            $active = self::isActive($child, $selected, $page);
            if (!empty($child[5])) {
                echo '<li><details class="ex-subgroup"' . ($active ? ' open' : '') . '><summary class="ex-subnav-link' . ($active ? ' ex-in' : '') . '">' . self::label($child) . enlixo_icon('chevron-down', 16, 'ex-chev') . '</summary>';
                self::renderChildren($child[5], $selected, $page, $depth + 1);
                echo '</details></li>';
            } else {
                // the exact page wins; otherwise the item named by load()
                $current = (strtok((string) $child[2], '?') === $page && self::tabMatches($child[2])) || ($active && !self::anySiblingIsPage($children, $page));
                echo '<li><a class="ex-subnav-link' . ($current ? ' ex-current' : '') . '" href="' . self::e($child[2]) . '"' . ($current ? ' aria-current="page"' : '') . '>' . self::label($child) . '</a></li>';
            }
        }
        echo '</ul>';
    }

    private static function anySiblingIsPage(array $children, string $page): bool
    {
        foreach ($children as $c) {
            if (strtok((string) ($c[2] ?? ''), '?') === $page) {
                return true;
            }
        }
        return false;
    }

    private static function groupHead(array $item, bool $active, bool $isLink): string
    {
        return enlixo_icon($item[1], 20, 'ex-nav-tile') . self::label($item)
            . ($isLink ? '' : enlixo_icon('chevron-down', 16, 'ex-chev'));
    }

    public static function load($selectedItem)
    {
        $sessionUser = SessionUser::getCurrentUserObject('Admin');
        $page = basename((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '.php');
        $selected = (string) $selectedItem;
        $name = trim(($sessionUser['user_fname'] ?? '') . ' ' . ($sessionUser['user_lname'] ?? ''));
        $initials = strtoupper(substr((string) ($sessionUser['user_fname'] ?? ''), 0, 1) . substr((string) ($sessionUser['user_lname'] ?? ''), 0, 1));
?>
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/enlixo-nav.css?v=<?= Project::getVersion() ?>">
        <nav class="d-flex flex-column p-0 m-0 vh-100 component-theme ex-sidenav" aria-label="Main menu">
            <div class="ex-sidenav-brand">
                <img src="<?= Project::getAssetsPath() ?>/imgs/<?= Project::getInstituteLogo() ?>" alt="<?= Project::getBaseName() ?> Logo" class="institute-logo">
            </div>

            <div class="ex-sidenav-find">
                <?= enlixo_icon('search', 16, 'ex-find-ico') ?>
                <input type="search" class="ex-find-input" placeholder="Find a page" aria-label="Find a page in the menu" autocomplete="off">
                <kbd class="ex-find-kbd" aria-hidden="true">/</kbd>
            </div>

            <div class="flex-grow-1 overflow-auto ex-sidenav-scroll">
                <?php foreach (self::menu() as $item) {
                    $active = self::isActive($item, $selected, $page);
                    if (empty($item[5])) { ?>
                        <a class="ex-nav-group<?= $active ? ' ex-in' : '' ?>" href="<?= self::e($item[2]) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= self::groupHead($item, $active, true) ?></a>
                    <?php } else { ?>
                        <details class="ex-nav-details"<?= $active ? ' open' : '' ?>>
                            <summary class="ex-nav-group<?= $active ? ' ex-in' : '' ?>"><?= self::groupHead($item, $active, false) ?></summary>
                            <?php self::renderChildren($item[5], $selected, $page, 1); ?>
                        </details>
                <?php }
                } ?>
                <p class="ex-find-empty" hidden>No page matches.</p>
            </div>

            <div class="ex-user-card">
                <?php if (!empty($sessionUser["user_image"]) && file_exists(Project::getAssetsPath() . '/imgs/users/' . $sessionUser["user_image"])) { ?>
                    <img src="<?= Project::getAssetsPath() ?>/imgs/users/<?= self::e($sessionUser["user_image"]) ?>" class="ex-user-avatar" alt="">
                <?php } else { ?>
                    <span class="ex-user-avatar" aria-hidden="true"><?= self::e($initials) ?></span>
                <?php } ?>
                <span class="ex-user-meta">
                    <span class="ex-user-name"><?= self::e($name ?: 'Admin') ?></span>
                    <span class="ex-user-role">ENLIXO Admin</span>
                </span>
                <button type="button" class="ex-user-out" onclick="signOut();" aria-label="Log out" title="Log out"><?= enlixo_icon('logout', 20) ?></button>
            </div>
        </nav>
        <script>
            (() => {
                const nav = document.currentScript.previousElementSibling;
                const input = nav.querySelector('.ex-find-input');
                const empty = nav.querySelector('.ex-find-empty');
                const links = [...nav.querySelectorAll('.ex-sidenav-scroll a')];
                const filter = () => {
                    const q = input.value.trim().toLowerCase();
                    nav.classList.toggle('ex-finding', q !== '');
                    let shown = 0;
                    links.forEach((a) => { const hit = !q || a.textContent.toLowerCase().includes(q); a.closest('li, .ex-nav-group')?.toggleAttribute('hidden', !hit); if (hit) shown += 1; });
                    nav.querySelectorAll('details').forEach((d) => {
                        const any = [...d.querySelectorAll('a')].some((a) => !a.closest('[hidden]'));
                        d.toggleAttribute('hidden', !!q && !any);
                        if (q) { d.dataset.wasOpen ??= d.open ? '1' : ''; d.open = any; } else if (d.dataset.wasOpen !== undefined) { d.open = d.dataset.wasOpen === '1'; delete d.dataset.wasOpen; }
                    });
                    empty.hidden = !q || shown > 0;
                };
                // V3 accordion: opening a group closes the other groups at the same level, so one long group never pushes the rest out of view
                nav.addEventListener('toggle', (e) => {
                    const d = e.target;
                    if (!(d instanceof HTMLDetailsElement) || !d.open || nav.classList.contains('ex-finding')) return;
                    const level = d.closest('li') ? d.closest('ul') : d.parentElement;
                    level.querySelectorAll(':scope > details[open], :scope > li > details[open]').forEach((o) => { if (o !== d) o.open = false; });
                    d.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }, true);
                input.addEventListener('input', filter);
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') { const first = links.find((a) => !a.closest('[hidden]')); if (first) location.href = first.href; }
                    if (e.key === 'Escape') { input.value = ''; filter(); input.blur(); }
                });
                document.addEventListener('keydown', (e) => {
                    if (e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName ?? '') && !document.activeElement?.isContentEditable) { e.preventDefault(); input.focus(); }
                });
            })();
        </script>
<?php
    }
}
