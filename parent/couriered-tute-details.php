<?php
// Parent → Tute parcels (parent/couriered-tute-details.php): every tute posted to the parent's children, how far it has
// got, the courier and tracking number, with the child's name. Read from tute_deliveries (TuteRepository::forStudent),
// only for the parent's own children (includes/enlixo/parent-scope.php). ?child=<id> shows one child.
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once '../includes/connection.php';
require_once '../includes/Project.php';

require_once '../includes/enlixo/parent-scope.php';

if (!SessionUser::ifUserIsParent()) {
    header("Location: ./index");
} else {
    require_once '../includes/enlixo/dashboard.php';
    $sessionUserObject = SessionUser::getCurrentUserObject('Parent');
    $parcels = null;
    $dashError = null;
    $children = ps_children((int) ($sessionUserObject['user_id'] ?? 0));
    $only = (int) ($_GET['child'] ?? 0);
    try {
        $app = \Enlixo\Legacy\Bridge::app();
        if ($app) {
            $repo = new \Enlixo\Tutes\TuteRepository($app->db());
            $parcels = [];
            foreach ($children as $cid => $c) {
                if ($only && $only !== $cid) {
                    continue;
                }
                foreach ($repo->forStudent($cid) as $p) {
                    $parcels[] = $p + ['child' => $c['name']];
                }
            }
        }
    } catch (Throwable $e) {
        $dashError = $e;
    }
    $steps = ['pending' => 'To pack', 'packed' => 'Packed', 'dispatched' => 'Sent', 'delivered' => 'Delivered'];
    $order = array_keys($steps);
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Tute Parcels - <?= Project::getPortalBasePath() ?></title>

        <?php enlixo_dash_styles(); ?>

        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/plugins/jquery/jquery-3.7.1.min.js"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
    </head>

    <body class="overflow-hidden">
        <div class="notifications"></div>
        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
            <div id="appContainer" class="row p-0">
                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                    <?php
                    include "includes/side-menu.php";
                    load_side_menu::load("couriered-tute-details");
                    ?>
                </div>
                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                    <div class="col-12 p-0 m-0 g-0">
                        <?php
                        include "includes/header.php";
                        selectItem::setSelected("couriered-tute-details");
                        ?>
                    </div>

                    <?php enlixo_dash_skeleton(); ?>

                    <div class="col-12 d-flex flex-column contentBodyArea vh-100" id="real-list">
                        <div class="row p-4 m-0 g-0">
                            <?php
                            if ($parcels === null) {
                                enlixo_dash_error($dashError);
                            } else {
                                enlixo_dash_head('Tute parcels', count($children) > 1 ? 'Printed tutes posted to your children' : 'Printed tutes posted to your child', [['My Children', 'my-children', 'users']], '', 'location');
                                if (count($children) > 1) { ?>
                                    <div class="col-12 mb-4 d-flex flex-wrap gap-2" role="group" aria-label="Choose a child">
                                        <a class="<?= $only ? 'custom-outline-btn' : 'custom-btn' ?> ex-btn-sm px-3 text-decoration-none d-inline-flex align-items-center" href="couriered-tute-details">All children</a>
                                        <?php foreach ($children as $cid => $c) { ?>
                                            <a class="<?= $only === $cid ? 'custom-btn' : 'custom-outline-btn' ?> ex-btn-sm px-3 text-decoration-none d-inline-flex align-items-center" href="couriered-tute-details?child=<?= $cid ?>"><?= enlixo_dash_e($c['first_name']) ?></a>
                                        <?php } ?>
                                    </div>
                                <?php }
                                if (!$parcels) { ?>
                                    <div class="col-12 component-theme p-4"><?php enlixo_dash_empty('No posted tutes yet. When a tutor posts a tute to your child, you can follow it here.', 'location'); ?></div>
                                <?php } else { ?>
                                    <div class="col-12">
                                        <div class="row g-4">
                                            <?php foreach ($parcels as $p) {
                                                $at = array_search($p['status'], $order, true);
                                                $returned = $p['status'] === 'returned'; ?>
                                                <div class="col-12 col-xl-6">
                                                    <article class="component-theme p-4 h-100 ex-parcel">
                                                        <div class="d-flex align-items-start gap-3 mb-3">
                                                            <span class="ex-dl-fact-ico"><?= enlixo_icon('tutes', 20) ?></span>
                                                            <div class="flex-grow-1 min-w-0">
                                                                <div class="ex-panel-title text-truncate"><?= enlixo_dash_e($p['tute']) ?></div>
                                                                <div class="text-12 sub-text"><?= enlixo_dash_e($p['child']) ?> · <?= enlixo_dash_e($p['class']) ?></div>
                                                            </div>
                                                            <?= enlixo_dash_status($returned ? 'Returned' : $p['step'], $returned ? 'bad' : ($p['status'] === 'delivered' ? 'good' : 'wait')) ?>
                                                        </div>
                                                        <?php if (!$returned) { ?>
                                                            <ol class="ex-parcel-steps" aria-label="Parcel progress">
                                                                <?php foreach ($steps as $k => $label) {
                                                                    $i = array_search($k, $order, true); ?>
                                                                    <li class="<?= $i < $at ? 'done' : ($i === $at ? 'now' : '') ?>"<?= $i === $at ? ' aria-current="step"' : '' ?>><span></span><?= enlixo_dash_e($label) ?></li>
                                                                <?php } ?>
                                                            </ol>
                                                        <?php } else { ?>
                                                            <div class="ex-tf-note mb-3"><?= enlixo_icon('danger', 18) ?><span>This parcel came back to your tutor. Check your child's address and contact the tutor.</span></div>
                                                        <?php } ?>
                                                        <div class="row g-3 mt-1">
                                                            <div class="col-12 col-md-6">
                                                                <div class="text-12 sub-text">Courier</div>
                                                                <div class="text-14 main-text fw-medium"><?= enlixo_dash_e($p['courier_name'] ?: 'Not chosen yet') ?></div>
                                                            </div>
                                                            <div class="col-12 col-md-6">
                                                                <div class="text-12 sub-text"><?= $p['delivered_on'] ? 'Delivered on' : ($p['dispatched_on'] ? 'Sent on' : 'Posting on') ?></div>
                                                                <div class="text-14 main-text fw-medium"><?php $d = $p['delivered_on'] ?: ($p['dispatched_on'] ?: $p['expected_on']); echo $d ? date('j F Y', strtotime($d)) : 'Not set yet'; ?></div>
                                                            </div>
                                                            <?php if ($p['tracking_no'] !== '') { ?>
                                                                <div class="col-12">
                                                                    <div class="text-12 sub-text mb-1">Tracking number</div>
                                                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                                                        <span class="ex-parcel-track" data-no-i18n><?= enlixo_dash_e($p['tracking_no']) ?></span>
                                                                        <button type="button" class="custom-outline-btn ex-btn-sm px-3 d-inline-flex align-items-center gap-1" data-copy="<?= enlixo_dash_e($p['tracking_no']) ?>"><?= enlixo_icon('copy', 16) ?><span>Copy</span></button>
                                                                        <?php if ($p['tracking_url']) { ?><a class="custom-btn ex-btn-sm px-3 d-inline-flex align-items-center text-decoration-none" href="<?= enlixo_dash_e($p['tracking_url']) ?>" target="_blank" rel="noopener">Track parcel</a><?php } ?>
                                                                    </div>
                                                                </div>
                                                            <?php } ?>
                                                            <div class="col-12">
                                                                <div class="text-12 sub-text">Going to</div>
                                                                <div class="text-14 main-text" data-no-i18n><?= enlixo_dash_e($p['address'] ?: 'No address yet. Ask the tutor to add your child\'s address so the tute can be posted.') ?></div>
                                                            </div>
                                                            <?php if ($p['note'] !== '') { ?>
                                                                <div class="col-12"><div class="ex-tf-note"><?= enlixo_icon('info', 18) ?><span><?= enlixo_dash_e($p['note']) ?></span></div></div>
                                                            <?php } ?>
                                                        </div>
                                                    </article>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                            <?php }
                            } ?>
                        </div>
                        <div class="flex-grow-1"></div>
                        <footer>
                            <?php include '../includes/footer.php' ?>
                        </footer>
                    </div>
                </div>
            </div>
        </div>

        <script src="<?= Project::getAssetsPath() ?>/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="<?= Project::getAssetsPath() ?>/js/functions.js?v=<?= Project::getVersion() ?>"></script>
        <script src="js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script>
            // Copy a tracking number with one tap
            document.addEventListener("click", (e) => {
                const b = e.target.closest("[data-copy]");
                if (!b) return;
                navigator.clipboard?.writeText(b.dataset.copy).then(() => { b.querySelector("span").textContent = "Copied"; setTimeout(() => { b.querySelector("span").textContent = "Copy"; }, 1500); });
            });
        </script>
    </body>

    </html>
<?php
}
?>
