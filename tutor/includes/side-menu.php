<?php
require_once "../includes/nav-item.php";

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
            ['Dashboard', 'material-symbols:team-dashboard-outline', 'dashboard', 'dashboard', ['Dashboard']],
            ['Academic', 'ph:graduation-cap', null, '', [], [
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
            ['Finance', 'f7:wallet', null, '', [], [
                ['Payments', null, 'payments', 'payments', ['Payments']],
                ['Billing', null, 'billing', 'billing', ['Billing']],
            ]],
            ['Communication', 'healthicons:communication', null, '', [], [
                ['Message Center', null, 'message-center', 'message-center', ['Message Center']],
            ]],
            ['Settings', 'solar:settings-linear', null, '', [], [
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

    public static function load($selectedItem)
    {
        $sessionUser = SessionUser::getCurrentUserObject('Tutor');
        $page = basename((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '.php');
        $selected = (string) $selectedItem;
?>
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/enlixo-nav.css?v=<?= Project::getVersion() ?>">
        <nav class="d-flex flex-column container-fluid p-0 m-0 vh-100 component-theme ex-sidenav" aria-label="Main menu">
            <div class="row container-fluid overflow-hidden justify-content-center component-theme py-4 p-3 m-0">
                <img src="<?= Project::getAssetsPath() ?>/imgs/<?= Project::getInstituteLogo() ?>" alt="<?= Project::getBaseName() ?> Logo" class="institute-logo w-100 p-0 m-0">
            </div>
            <div class="flex-grow-1 overflow-auto mt-2 ex-sidenav-scroll">
                <?php foreach (self::menu() as $item) {
                    $active = self::isActive($item, $selected, $page);
                    if (empty($item[5])) { ?>
                        <a class="ex-nav-group<?= $active ? ' ex-in' : '' ?>" href="<?= self::e($item[2]) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                            <iconify-icon icon="<?= self::e($item[1]) ?>" width="18" height="18"></iconify-icon><?= self::label($item) ?>
                        </a>
                    <?php } else { ?>
                        <details class="ex-nav-details"<?= $active ? ' open' : '' ?>>
                            <summary class="ex-nav-group<?= $active ? ' ex-in' : '' ?>">
                                <iconify-icon icon="<?= self::e($item[1]) ?>" width="18" height="18"></iconify-icon><?= self::label($item) ?><span class="ex-chev" aria-hidden="true"></span>
                            </summary>
                            <?php self::renderChildren($item[5], $selected, $page, 1); ?>
                        </details>
                <?php }
                } ?>
            </div>

            <button class="d-flex align-items-center border-0 px-3 py-2 m-3" style="background-color: var(--side-menu-user-background);" onclick="signOut();">
                <?php
                if (!empty($sessionUser["user_image"]) && file_exists(Project::getAssetsPath() . '/imgs/users/' . $sessionUser["user_image"])) {
                ?>
                    <img src="<?= Project::getAssetsPath() ?>/imgs/users/<?= self::e($sessionUser["user_image"]) ?>" class="hwr-40" alt="User image">
                <?php
                } else {
                    $firstNameInitial = strtoupper(substr((string) $sessionUser["user_fname"], 0, 1));
                    $lastNameInitial = strtoupper(substr((string) $sessionUser["user_lname"], 0, 1));
                ?>
                    <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= self::e($firstNameInitial . $lastNameInitial) ?></div>
                <?php
                }
                ?>
                <div class="d-flex flex-column align-items-start mx-2 flex-grow-1">
                    <span class="text-16 main-text fw-medium">Tutor</span>
                    <span class=" text-12 sub-text fw-medium">Log Out</span>
                </div>
                <iconify-icon icon="ic:sharp-logout" width="20" height="20" class="text-dark-red"></iconify-icon>
            </button>
        </nav>
<?php
    }
}
