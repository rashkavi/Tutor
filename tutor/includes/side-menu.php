<?php
require_once "../includes/nav-item.php";
require_once __DIR__ . "/../../includes/enlixo/icons.php";

/**
 * Tutor side menu, grouped like the ENLIXO V3 portal:
 *   Dashboard / Academic ▾ (Classes, Students ▾, Time Table, Attendance) / Finance ▾ / Communication ▾ / Settings ▾
 * Pages still call load_side_menu::load("Students") etc.; the group holding that item opens and the
 * current page's link is highlighted.
 */
class load_side_menu
{
    /** [label, icon, link, i18n key, matches load() names, children] */
    private static function menu(): array
    {
        return [
            ['Dashboard', 'dashboard', 'dashboard', 'dashboard', ['Dashboard']],
            ['Academic', 'academic', null, '', [], [
                ['Classes', null, null, 'classes', ['Classes', 'Pre-Recordings', 'Tutes'], [
                    ['All Classes', null, 'classes', '', []],
                    ['Create a Class', null, 'create-class', '', []],
                ]],
                ['Students', null, null, 'students', ['Students'], [
                    ['All Students', null, 'students', '', []],
                    ['Add New Student', null, 'add-student-to-class', '', []],
                    ['Add Registered Student', null, 'add-registered-student-to-class', '', []],
                    ['Bulk Upload', null, 'upload-student-bulk', '', []],
                ]],
                ['Time Table', null, 'time-table', 'time-table', ['Time Table']],
                ['Attendance', null, 'attendance', 'attendance', ['Attendance']],
            ]],
            ['Finance', 'finance', null, '', [], [
                ['Payments', null, 'payments', 'payments', ['Payments']],
                ['Billing', null, 'billing', 'billing', ['Billing']],
            ]],
            ['Communication', 'communication', null, '', [], [
                ['Message Center', null, 'message-center', 'message-center', ['Message Center']],
            ]],
            ['Settings', 'settings', null, '', [], [
                ['Class Form', null, 'class-form-settings', '', ['Class Form']],
                ['Plans & Billing', null, 'pricing', '', ['Pricing']],
                ['Integrations', null, 'intergrations', 'intergrations', ['Intergrations']],
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
        if (($item[2] ?? null) === $page || in_array($selected, $item[4] ?? [], true)) {
            return true;
        }
        foreach ($item[5] ?? [] as $child) {
            if (self::isActive($child, $selected, $page)) {
                return true;
            }
        }
        return false;
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
                echo '<li><details class="ex-subgroup"' . ($active ? ' open' : '') . '><summary class="ex-subnav-link' . ($active ? ' ex-in' : '') . '">' . self::label($child) . '<span class="ex-chev" aria-hidden="true"></span></summary>';
                self::renderChildren($child[5], $selected, $page, $depth + 1);
                echo '</details></li>';
            } else {
                // the exact page wins; otherwise the item named by load()
                $current = $child[2] === $page || ($active && !self::anySiblingIsPage($children, $page));
                echo '<li><a class="ex-subnav-link' . ($current ? ' ex-current' : '') . '" href="' . self::e($child[2]) . '"' . ($current ? ' aria-current="page"' : '') . '>' . self::label($child) . '</a></li>';
            }
        }
        echo '</ul>';
    }

    private static function anySiblingIsPage(array $children, string $page): bool
    {
        foreach ($children as $c) {
            if (($c[2] ?? null) === $page) {
                return true;
            }
        }
        return false;
    }

    /** Package card data for the bottom of the menu, or null when the new services aren't set up. */
    private static function plan(int $tutorId): ?array
    {
        try {
            require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
            $app = \Enlixo\Legacy\Bridge::app();
            if ($app === null) {
                return null;
            }
            $ent = $app->entitlements()->for($tutorId);
            $name = \Enlixo\Catalog\FeatureCatalog::PLANS[$ent->planKey]['name'] ?? strtoupper($ent->planKey);
            return ['key' => $ent->planKey, 'name' => $name, 'count' => $ent->studentCount, 'limit' => $ent->studentLimit];
        } catch (\Throwable $e) {
            error_log('Side menu plan card: ' . $e->getMessage());
            return null;
        }
    }

    private static function groupHead(array $item, bool $active, bool $isLink): string
    {
        return enlixo_icon($item[1] . ($active ? '-active' : ''), 20, 'ex-nav-tile') . self::label($item)
            . ($isLink ? '' : enlixo_icon('chevron-down', 16, 'ex-chev'));
    }

    public static function load($selectedItem)
    {
        $sessionUser = SessionUser::getCurrentUserObject('Tutor');
        $page = basename((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '.php');
        $selected = (string) $selectedItem;
        $plan = self::plan((int) ($sessionUser['user_id'] ?? 0));
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
                <div class="ex-nav-caption">Menu</div>
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

            <?php if ($plan) {
                $pct = $plan['limit'] ? min(100, (int) round($plan['count'] / max(1, $plan['limit']) * 100)) : 0; ?>
                <a class="ex-plan-card ex-plan-<?= self::e($plan['key']) ?>" href="pricing">
                    <span class="d-flex align-items-center gap-2">
                        <?= enlixo_icon('crown', 18, 'ex-plan-crown') ?>
                        <span class="ex-plan-name"><?= self::e($plan['name']) ?> plan</span>
                        <?php if ($plan['key'] !== 'pro') { ?><span class="ex-plan-up">Upgrade</span><?php } ?>
                    </span>
                    <?php if ($plan['limit']) { ?>
                        <span class="ex-plan-meter" role="progressbar" aria-label="Students used" aria-valuenow="<?= (int) $plan['count'] ?>" aria-valuemin="0" aria-valuemax="<?= (int) $plan['limit'] ?>"><span style="width: <?= $pct ?>%"></span></span>
                        <span class="ex-plan-use"><?= (int) $plan['count'] ?> of <?= (int) $plan['limit'] ?> students</span>
                    <?php } ?>
                </a>
            <?php } ?>

            <div class="ex-user-card">
                <?php if (!empty($sessionUser["user_image"]) && file_exists(Project::getAssetsPath() . '/imgs/users/' . $sessionUser["user_image"])) { ?>
                    <img src="<?= Project::getAssetsPath() ?>/imgs/users/<?= self::e($sessionUser["user_image"]) ?>" class="ex-user-avatar" alt="">
                <?php } else { ?>
                    <span class="ex-user-avatar" aria-hidden="true"><?= self::e($initials) ?></span>
                <?php } ?>
                <a href="my-profile" class="ex-user-meta">
                    <span class="ex-user-name"><?= self::e($name ?: 'Tutor') ?></span>
                    <span class="ex-user-role">Tutor · My profile</span>
                </a>
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
