<?php
require_once "../includes/nav-item.php";
require_once __DIR__ . "/../../includes/enlixo/icons.php";

// Staff members (Settings -> Staff & Roles, includes/enlixo/staff-access.php) work in their tutor's account: their
// access is checked again on every page (switched off = signed out) and pages their role does not cover send them
// to the dashboard. The tutor's subscription pages are never theirs.
$enlixoStaff = isset($sessionUserObject['staff_user_id']) && (int) $sessionUserObject['staff_user_id'] > 0;
if ($enlixoStaff) {
    require_once __DIR__ . "/../../includes/enlixo/staff-access.php";
    $enlixoPage = basename((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '.php');
    $enlixoEmp = enlixo_staff_employer((int) $sessionUserObject['staff_user_id']);
    if ($enlixoEmp === null || $enlixoEmp['tutor_id'] !== (int) $sessionUserObject['user_id']) {
        echo '<script>location.replace("api/signOut.php");</script></body></html>';
        exit;
    }
    if (!enlixo_staff_page_allowed($sessionUserObject, $enlixoPage)) {
        echo '<script>location.replace("dashboard?no-access=' . rawurlencode($enlixoPage) . '");</script></body></html>';
        exit;
    }
}

// Admin side billing (migration 007): while the subscription is suspended or cancelled the tutor can only open
// Renew (tutor/renew.php: what happened and how to pay), the invoice page, My Subscription and My Profile; every
// other page sends them to Renew. Their students and parents cannot sign in meanwhile (includes/TutorAccess.php).
// The API refuses the same calls (api/v1/index.php).
if (isset($sessionUserObject['user_id']) && class_exists('SessionUser') && SessionUser::ifUserIsTutor() && !$enlixoStaff
    && !in_array(basename((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '.php'), ['subscription', 'renew', 'invoice', 'my-profile', 'payment-pending', 'subscription-payment-details'], true)) {
    try {
        $enlixoSub = Database::search("SELECT `status` FROM `users_has_payment_plans` WHERE `users_user_id` = '" . (int) $sessionUserObject['user_id'] . "' ORDER BY `users_has_payment_plan_id` DESC LIMIT 1");
        $enlixoSub = $enlixoSub ? $enlixoSub->fetch_assoc() : null;
    } catch (\Throwable $e) {
        $enlixoSub = null; // before migration 007
    }
    if ($enlixoSub && in_array($enlixoSub['status'] ?? '', ['suspended', 'cancelled'], true)) {
        echo '<script>location.replace("renew");</script></body></html>';
        exit;
    }
    // Payment due / overdue and the slip is waiting for ENLIXO to check it: the tutor waits on "Payment Being Checked"
    // (tutor/payment-pending.php: the payment, the steps, ENLIXO's contacts) until it is approved or rejected.
    if ($enlixoSub && in_array($enlixoSub['status'] ?? '', ['due', 'overdue'], true)) {
        try {
            $enlixoWait = Database::search("SELECT 1 FROM `users_has_payment_plans_payments` p JOIN `platform_invoices` i ON i.`platform_invoice_id` = p.`platform_invoice_id`
                                             WHERE i.`users_user_id` = '" . (int) $sessionUserObject['user_id'] . "' AND i.`status` IN ('open', 'part') AND p.`payment_status_payment_status_id` = 2 LIMIT 1");
        } catch (\Throwable $e) {
            $enlixoWait = null;
        }
        if ($enlixoWait && $enlixoWait->num_rows > 0) {
            echo '<script>location.replace("payment-pending");</script></body></html>';
            exit;
        }
    }
}

/**
 * Tutor side menu, grouped like the ENLIXO V3 portal:
 *   Dashboard / Academic ▾ (Classes, Students ▾, Time Table, Attendance) / Finance ▾ / Communication ▾ / Settings ▾
 * Pages still call load_side_menu::load("Students") etc.; the group holding that item opens and the
 * current page's link is highlighted.
 */
class load_side_menu
{
    private static bool $hideUpgrade = false;

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
                    ['Registered Students', null, 'students', '', []],
                    ['Requested Students', null, 'requested-students', '', ['Requested Students']],
                    ['Add New Student', null, 'add-student', '', ['Add New Student']],
                    ['Add Registered Student', null, 'add-registered-student-to-class', '', []],
                    ['Upload Students Bulk', null, 'upload-student-bulk', '', ['Upload Students Bulk']],
                ]],
                ['Time Table', null, 'time-table', 'time-table', ['Time Table']],
                ['Class Recordings', null, 'recordings', '', ['Class Recordings']],
                ['Attendance', null, null, 'attendance', ['Attendance'], [
                    ['Attendance Dashboard', null, 'attendance-dashboard', '', ['Attendance Dashboard']],
                    ['Mark Attendance', null, 'mark-attendance', '', ['Mark Attendance']],
                ]],
            ]],
            ['Finance', 'finance', null, '', [], [
                ['Student Fees', null, 'fees', '', ['Student Fees', 'Payments']],
                ['My Subscription', null, 'subscription', '', ['My Subscription', 'Billing']],
                ['Upgrade Plan', null, 'upgrade', '', ['Upgrade']],
            ]],
            ['Communication', 'communication', null, '', [], [
                ['Message Center', null, 'message-center', 'message-center', ['Message Center']],
            ]],
            ['Settings', 'settings', null, '', [], [
                ['Class Form', null, 'class-form-settings', '', ['Class Form']],
                ['Student Form', null, 'student-form-settings', '', ['Student Form']],
                ['Fees & Receipts', null, 'fee-settings', '', ['Fee Settings']],
                ['Attendance', null, 'attendance-settings', '', ['Attendance Settings']],
                ['Recording Types', null, 'recording-types', '', ['Recording Types']],
                ['Plans & Billing', null, 'subscription?tab=plans', '', ['Pricing']],
                ['WhatsApp', null, 'whatsapp-settings', '', ['WhatsApp', 'WhatsApp Settings']],
                ['Staff & Roles', null, 'staff', '', ['Staff', 'Staff & Roles']],
                ['Integrations', null, 'intergrations', 'intergrations', ['Intergrations']],
            ]],
        ];
    }

    /** @var array|null the staff session (null for the tutor) */
    private static ?array $staff = null;

    /** Can the signed-in person open this item (or one of its children)? Always true for the tutor. */
    private static function visible(array $item): bool
    {
        if (self::$staff === null) {
            return true;
        }
        if (!empty($item[5])) {
            foreach ($item[5] as $c) {
                if (self::visible($c)) {
                    return true;
                }
            }
            return false;
        }
        $page = (string) strtok((string) ($item[2] ?? ''), '?');
        return $page === '' || enlixo_staff_page_allowed(self::$staff, $page);
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
            if (self::$hideUpgrade && ($child[2] ?? '') === 'upgrade') {
                continue; // GROW / CUSTOM: no "Upgrade Plan" item
            }
            if (!self::visible($child)) {
                continue; // a staff member only sees the pages of their role
            }
            $active = self::isActive($child, $selected, $page);
            if (!empty($child[5])) {
                echo '<li><details class="ex-subgroup"' . ($active ? ' open' : '') . '><summary class="ex-subnav-link' . ($active ? ' ex-in' : '') . '">' . self::label($child) . enlixo_icon('chevron-down', 16, 'ex-chev') . '</summary>';
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
            $sub = $app->db()->one('SELECT status, trial_ends_at, period_end, grace_until FROM users_has_payment_plans WHERE users_user_id = :t ORDER BY users_has_payment_plan_id DESC LIMIT 1', ['t' => $tutorId]) ?? [];
            $open = $app->db()->one("SELECT platform_invoice_id id, total - paid AS amount, due_on FROM platform_invoices WHERE users_user_id = :t AND status IN ('open', 'part') ORDER BY due_on LIMIT 1", ['t' => $tutorId]);
            $today = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d');
            $left = static fn (?string $d) => $d ? (int) round((strtotime($d) - strtotime($today)) / 86400) : null;
            // the limit closest to full (80 % or more) and the next plan, for the upgrade nudges (docs/PLANS.md)
            $plans = \Enlixo\Catalog\FeatureCatalog::PLANS;
            $hot = null;
            foreach ($ent->toArray()['limits'] as $l) {
                if ($l['percent'] !== null && $l['percent'] >= 80 && ($hot === null || $l['percent'] > $hot['percent'])) {
                    $hot = $l + ['next_name' => $l['next'] ? ($plans[$l['next']]['name'] ?? strtoupper($l['next'])) : null,
                        'next_limit' => $l['next'] ? \Enlixo\Catalog\FeatureCatalog::limitFor($l['key'], $l['next']) : null];
                }
            }
            if ($ent->studentLimit && $ent->studentCount >= 0.9 * $ent->studentLimit && ($hot === null || $hot['percent'] < 100)) {
                $nextKey = \Enlixo\Catalog\FeatureCatalog::nextPlan($ent->paidPlanKey());
                $hot = ['key' => 'students', 'name' => 'Students', 'unit' => 'students', 'period' => 'total', 'used' => $ent->studentCount, 'limit' => $ent->studentLimit,
                    'percent' => min(100, (int) round($ent->studentCount / $ent->studentLimit * 100)), 'next' => $nextKey,
                    'next_name' => $nextKey ? ($plans[$nextKey]['name'] ?? null) : null, 'next_limit' => $nextKey ? ($plans[$nextKey]['student_limit'] ?? null) : null];
            }
            $paid = $ent->paidPlanKey();
            // upgrade nudges only for FREE, STARTER and anyone on a trial; GROW / CUSTOM are never pushed
            $push = in_array($paid, ['free', 'start'], true) || ($sub['status'] ?? '') === 'trial';
            if (!$push) {
                $hot = null;
            }
            return ['key' => $ent->planKey, 'push' => $push, 'name' => $name, 'count' => $ent->studentCount, 'limit' => $ent->studentLimit,
                'paid_key' => $paid, 'paid_name' => $plans[$paid]['name'] ?? strtoupper($paid), 'preview_left' => $left($ent->previewUntil), 'hot' => $hot,
                'status' => (string) ($sub['status'] ?? ''), 'trial_left' => $left($sub['trial_ends_at'] ?? null), 'trial_ends' => $sub['trial_ends_at'] ?? null,
                'grace_left' => $left($sub['grace_until'] ?? null), 'invoice' => $open ? ['id' => (int) $open['id'], 'amount' => (float) $open['amount'], 'due' => $open['due_on'], 'left' => $left($open['due_on'])] : null];
        } catch (\Throwable $e) {
            error_log('Side menu plan card: ' . $e->getMessage());
            return null;
        }
    }

    private static function groupHead(array $item, bool $active, bool $isLink): string
    {
        return enlixo_icon($item[1], 20, 'ex-nav-tile') . self::label($item)
            . ($isLink ? '' : enlixo_icon('chevron-down', 16, 'ex-chev'));
    }

    public static function load($selectedItem)
    {
        $sessionUser = SessionUser::getCurrentUserObject('Tutor');
        $page = basename((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '.php');
        $selected = (string) $selectedItem;
        self::$staff = !empty($sessionUser['staff_user_id']) ? $sessionUser : null;
        $plan = self::$staff === null ? self::plan((int) ($sessionUser['user_id'] ?? 0)) : null; // staff never see the tutor's plan / billing
        self::$hideUpgrade = $plan !== null && !$plan['push'];
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
                    if (!self::visible($item)) {
                        continue;
                    }
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
                <a class="ex-plan-card ex-plan-<?= self::e($plan['paid_key']) ?>" href="<?= $plan['push'] ? ($plan['status'] === 'trial' ? 'subscription?tab=plans' : 'upgrade') : 'subscription' ?>">
                    <span class="d-flex align-items-center gap-2">
                        <?= enlixo_icon('crown', 18, 'ex-plan-crown') ?>
                        <span class="ex-plan-name"><?= self::e($plan['paid_name']) ?> plan</span>
                        <?php if ($plan['push']) { ?><span class="ex-plan-up"><?= $plan['status'] === 'trial' ? 'Choose' : 'Upgrade' ?></span><?php } ?>
                    </span>
                    <?php if ($plan['preview_left'] !== null) { ?>
                        <span class="ex-plan-trial is-preview"><?= enlixo_icon('magic', 14) ?>Trying <?= self::e($plan['name']) ?> · <?= $plan['preview_left'] > 0 ? (int) $plan['preview_left'] . ($plan['preview_left'] === 1 ? ' day' : ' days') . ' left' : 'last day' ?></span>
                    <?php } elseif ($plan['status'] === 'trial' && $plan['trial_left'] !== null) { ?>
                        <span class="ex-plan-trial"><?= enlixo_icon('hourglass', 14) ?>Free trial · <?= $plan['trial_left'] > 0 ? (int) $plan['trial_left'] . ($plan['trial_left'] === 1 ? ' day' : ' days') . ' left' : 'ends today' ?></span>
                    <?php } elseif (in_array($plan['status'], ['due', 'overdue'], true) && $plan['invoice']) { ?>
                        <span class="ex-plan-trial is-due"><?= enlixo_icon('warning', 14) ?><?= $plan['status'] === 'overdue' ? 'Overdue' : 'Payment due' ?> · LKR <?= number_format($plan['invoice']['amount']) ?></span>
                    <?php } ?>
                    <?php if ($plan['limit']) { ?>
                        <span class="ex-plan-meter" role="progressbar" aria-label="Students used" aria-valuenow="<?= (int) $plan['count'] ?>" aria-valuemin="0" aria-valuemax="<?= (int) $plan['limit'] ?>"><span style="width: <?= $pct ?>%"></span></span>
                        <span class="ex-plan-use"><?= (int) $plan['count'] ?> of <?= (int) $plan['limit'] ?> students</span>
                    <?php } ?>
                </a>
            <?php } ?>

            <?php
            // banner at the top of every tutor page (phones too): trial ending soon, payment due / overdue
            $banner = null;
            if ($plan && $plan['status'] === 'trial' && $plan['trial_left'] !== null && $plan['trial_left'] <= 5) {
                $banner = ['warn', 'Your free trial ' . ($plan['trial_left'] > 0 ? 'ends in ' . $plan['trial_left'] . ($plan['trial_left'] === 1 ? ' day' : ' days') : 'ends today') . '.', 'Choose your plan and pay so your students keep their access.', $plan['invoice'] ? 'invoice?invoice-id=' . $plan['invoice']['id'] . '#pay' : 'subscription?tab=plans', $plan['invoice'] ? 'Pay Now' : 'Choose Plan'];
            } elseif ($plan && $plan['status'] === 'overdue' && $plan['invoice']) {
                $banner = ['bad', 'Your ENLIXO payment of LKR ' . number_format($plan['invoice']['amount']) . ' is overdue.', $plan['grace_left'] !== null && $plan['grace_left'] >= 0 ? 'Pay within ' . $plan['grace_left'] . ($plan['grace_left'] === 1 ? ' day' : ' days') . ', or your account and your students\' access are paused.' : 'Pay now so your account and your students\' access stay on.', 'invoice?invoice-id=' . $plan['invoice']['id'] . '#pay', 'Pay Now'];
            } elseif ($plan && $plan['status'] === 'due' && $plan['invoice'] && $plan['invoice']['left'] !== null && $plan['invoice']['left'] <= 3) {
                $banner = ['warn', 'Your ENLIXO invoice of LKR ' . number_format($plan['invoice']['amount']) . ' is due ' . ($plan['invoice']['left'] > 0 ? 'in ' . $plan['invoice']['left'] . ($plan['invoice']['left'] === 1 ? ' day' : ' days') : 'today') . '.', 'Pay by bank transfer and upload the slip.', 'invoice?invoice-id=' . $plan['invoice']['id'] . '#pay', 'Pay Now'];
            } elseif ($plan && $plan['preview_left'] !== null) {
                $banner = ['up', 'You are trying ' . $plan['name'] . ' free · ' . ($plan['preview_left'] > 0 ? $plan['preview_left'] . ($plan['preview_left'] === 1 ? ' day' : ' days') . ' left.' : 'last day today.'), 'Upgrade to keep these features. Everything you set up stays.', 'upgrade?plan=' . $plan['key'], 'Upgrade to ' . $plan['name']];
            } elseif ($plan && $plan['hot'] && $plan['hot']['next_name']) {
                $h = $plan['hot'];
                $full = $h['used'] >= $h['limit'];
                $banner = ['up', $h['used'] > $h['limit']
                        ? sprintf('You have %d %s; %s allows %d.', $h['used'], $h['unit'], $plan['paid_name'], $h['limit'])
                        : sprintf('You have used %d of your %d %s%s.', $h['used'], $h['limit'], $h['unit'], $h['period'] === 'month' ? ' this month' : ''),
                    sprintf('%s%s gives you %s.', $full ? 'To add more, upgrade. ' : '', $h['next_name'], $h['next_limit'] === null ? 'unlimited ' . $h['unit'] : $h['next_limit'] . ' ' . $h['unit'] . ($h['period'] === 'month' ? ' a month' : '')),
                    'upgrade?limit=' . rawurlencode($h['key']), 'See ' . $h['next_name']];
            }
            if ($banner) { ?>
                <template id="ex-bill-banner"><div class="ex-bill-banner is-<?= self::e($banner[0]) ?>" role="status">
                    <?= enlixo_icon($banner[0] === 'bad' ? 'warning' : ($banner[0] === 'up' ? 'crown' : 'hourglass'), 20, 'ex-bill-ico') ?>
                    <span class="ex-bill-text"><strong><?= self::e($banner[1]) ?></strong> <?= self::e($banner[2]) ?></span>
                    <a class="custom-btn ex-bill-btn" href="<?= self::e($banner[3]) ?>"><?= self::e($banner[4]) ?></a>
                </div></template>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        // the menu is printed twice (desktop + phone): one banner only, in the first content row
                        const t = document.getElementById('ex-bill-banner');
                        const at = ['#real-list > .row', '#real-list > .ac-page', '#real-list', '.contentBodyArea'].map((q) => document.querySelector(q)).find(Boolean);
                        if (t && at && !document.querySelector('.ex-bill-banner:not(.is-hit)') && !/(\/subscription|\/invoice|\/renew|\/upgrade)/.test(location.pathname)) at.prepend(t.content.cloneNode(true));
                    });
                </script>
            <?php } ?>
            <script>
                // A plan limit or a locked feature hit while working (ApiClient fires enlixo:upgrade): an upgrade card at the
                // top of the page, never a pop-up. "See plan" opens the Upgrade page for that limit / feature.
                if (!window.__exUpgradeCard) window.addEventListener('enlixo:upgrade', (ev) => {
                    const d = ev.detail || {};
                    const at = ['#real-list > .row', '#real-list > .ac-page', '#real-list', '.contentBodyArea'].map((q) => document.querySelector(q)).find(Boolean);
                    if (!at) return;
                    document.querySelector('.ex-bill-banner.is-hit')?.remove();
                    const names = { free: 'FREE', start: 'STARTER', grow: 'GROW', pro: 'CUSTOM' };
                    const plan = names[d.required_plan] || 'a bigger plan';
                    const href = 'upgrade?' + (d.limit ? 'limit=' + encodeURIComponent(d.limit) : 'feature=' + encodeURIComponent(d.feature || ''));
                    const box = document.createElement('div');
                    box.className = 'ex-bill-banner is-up is-hit';
                    box.setAttribute('role', 'alert');
                    const txt = document.createElement('span');
                    txt.className = 'ex-bill-text';
                    const b = document.createElement('strong');
                    b.textContent = d.code === 'limit_reached' ? 'You reached a limit of your plan.' : 'This is part of ' + plan + '.';
                    txt.append(b, ' ' + (d.message || ''));
                    const a = document.createElement('a');
                    a.className = 'custom-btn ex-bill-btn';
                    a.href = href;
                    a.textContent = d.required_plan ? 'See ' + plan : 'See plans';
                    box.append(txt, a);
                    at.prepend(box);
                    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
                window.__exUpgradeCard = true;
            </script>

            <div class="ex-user-card">
                <?php if (!empty($sessionUser["user_image"]) && file_exists(Project::getAssetsPath() . '/imgs/users/' . $sessionUser["user_image"])) { ?>
                    <img src="<?= Project::getAssetsPath() ?>/imgs/users/<?= self::e($sessionUser["user_image"]) ?>" class="ex-user-avatar" alt="">
                <?php } else { ?>
                    <span class="ex-user-avatar" aria-hidden="true"><?= self::e($initials) ?></span>
                <?php } ?>
                <?php if (self::$staff !== null) { ?>
                    <span class="ex-user-meta">
                        <span class="ex-user-name"><?= self::e($name ?: 'Staff') ?></span>
                        <span class="ex-user-role">Staff · <?= self::e($sessionUser['employer_name'] ?? '') ?></span>
                    </span>
                <?php } else { ?>
                <a href="my-profile" class="ex-user-meta">
                    <span class="ex-user-name"><?= self::e($name ?: 'Tutor') ?></span>
                    <span class="ex-user-role">Tutor · My profile</span>
                </a>
                <?php } ?>
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
