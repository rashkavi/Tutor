<?php
/**
 * Shared frame of the new admin pages (like V3's finance-frame-top.php). Set $ap, then include at TOP LEVEL:
 *   $ap = ['title' => 'Tutors', 'menu' => 'Tutors', 'crumbs' => [['Tutors', null]], 'guide' => [...], 'actions' => 'tutors-actions', 'root' => 'tutors-page'];
 *   include 'includes/frame-top.php';   ...page body (optional)...   include 'includes/frame-bottom.php';
 * 'header' => false leaves out the header card (the dashboard draws a greeting card instead).
 * The page script ($ap['js'], an ES module in assets/js/enlixo/pages/) draws everything inside #$ap['root'].
 */
require_once __DIR__ . '/../../includes/UserClass.php';
require_once __DIR__ . '/../../includes/Project.php';
require_once __DIR__ . '/../../includes/enlixo/page-header.php';

if (!SessionUser::ifUserIsAdmin()) {
    header('Location: ./index');
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject('Admin');
$sessionUserRole = 'Admin';
$apE = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $apE($ap['title']) ?> - Admin | <?= Project::getBaseName() ?></title>

    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/enlixo-ui.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/enlixo-admin.css?v=<?= Project::getVersion() ?>">
    <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
</head>

<body class="overflow-hidden">
    <div class="notifications"></div>
    <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
        <div id="appContainer" class="row p-0">
            <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                <?php
                include __DIR__ . '/side-menu.php';
                load_side_menu::load($ap['menu']);
                ?>
            </div>
            <div id="app-wrapper" class="app-wrapper p-0 m-0">
                <div class="row p-0 m-0 g-0">
                    <div class="col-12 p-0 m-0 g-0">
                        <?php
                        include __DIR__ . '/header.php';
                        selectItem::setSelected($ap['menu']);
                        ?>
                    </div>
                    <div class="container p-4 m-0 d-none" id="skeleton-list"></div>
                    <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100" id="real-list">
                        <div class="row p-4 m-0 g-0">
                            <?php if ($ap['header'] ?? true) { // the dashboard has a greeting card instead
                                enlixo_form_header($ap['title'], $ap['crumbs'], $ap['guide'] ?? [], $ap['actions'] ?? 'page-actions');
                            } ?>
                            <div class="col-12 p-0" id="<?= $apE($ap['root'] ?? 'admin-page') ?>" <?= $ap['attrs'] ?? '' ?>></div>
