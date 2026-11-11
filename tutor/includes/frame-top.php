<?php
/**
 * Shared frame of the new tutor pages (same idea as admin/includes/frame-top.php and V3's finance-frame-top.php).
 * Set $tp, then include at TOP LEVEL of the page:
 *   $tp = ['title' => 'Student Fees', 'menu' => 'Student Fees', 'crumbs' => [['Finance', null], ['Student Fees', null]],
 *          'guide' => [...], 'actions' => 'fees-actions', 'root' => 'fees-page', 'js' => 'app-tutor-fees.js',
 *          'css' => ['enlixo-fees.css'], 'scripts' => [...]];
 *   include 'includes/frame-top.php';   include 'includes/frame-bottom.php';
 * The page script ($tp['js'], an ES module in assets/js/enlixo/pages/) draws everything inside #$tp['root'].
 */
require_once __DIR__ . '/../../includes/UserClass.php';
require_once __DIR__ . '/../../includes/UserPermissionsClass.php';
require_once __DIR__ . '/../../includes/connection.php';
require_once __DIR__ . '/../../includes/Project.php';
require_once __DIR__ . '/security-helpers.php';
require_once __DIR__ . '/../../includes/enlixo/page-header.php';

if (!SessionUser::hasValidSession()) {
    header('Location: ./index');
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Tutor');
$sessionUserRole = SessionUser::getCurrentUserRole('Tutor');
UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
$tpE = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $tpE($tp['title']) ?> - Tutor Dashboard | <?= Project::getBaseName() ?></title>

    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/enlixo-ui.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/enlixo-admin.css?v=<?= Project::getVersion() ?>">
    <?php foreach ($tp['css'] ?? [] as $css) { ?>
        <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/<?= $tpE($css) ?>?v=<?= Project::getVersion() ?>">
    <?php } ?>
    <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
</head>

<body class="overflow-hidden">
    <div class="notifications"></div>
    <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
        <div id="appContainer" class="row p-0">
            <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                <?php
                include __DIR__ . '/side-menu.php';
                load_side_menu::load($tp['menu']);
                ?>
            </div>
            <div id="app-wrapper" class="app-wrapper p-0 m-0">
                <div class="row p-0 m-0 g-0">
                    <div class="col-12 p-0 m-0 g-0">
                        <?php
                        include __DIR__ . '/header.php';
                        selectItem::setSelected($tp['menu']);
                        ?>
                    </div>
                    <div class="container p-4 m-0 d-none" id="skeleton-list"></div>
                    <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100" id="real-list">
                        <div class="row p-4 m-0 g-0">
                            <?php enlixo_form_header($tp['title'], $tp['crumbs'], $tp['guide'] ?? [], $tp['actions'] ?? 'page-actions'); ?>
                            <div class="col-12 p-0" id="<?= $tpE($tp['root'] ?? 'tutor-page') ?>" <?= $tp['attrs'] ?? '' ?>></div>
