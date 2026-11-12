<?php
/**
 * Page frame of the student Time Table and Recordings pages (head, side menu, header, skeleton, .ac-page start).
 * Set $lf before including it at the top level of the page (the header reads $sessionUserObject):
 *   $lf = ['title' => 'Class Recordings', 'menu' => 'Class Recordings', 'js' => ['js/recordings.js'], 'icons' => ['play']];
 *   include __DIR__ . '/includes/learning-frame-top.php';   ...page body...   include __DIR__ . '/includes/learning-frame-bottom.php';
 */
require_once __DIR__ . '/../../includes/enlixo/student-learning.php';
$lf += ['title' => '', 'menu' => '', 'js' => [], 'icons' => []];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= sl_e($lf['title']) ?> - Student Portal | <?= sl_e(Project::getBaseName()) ?></title>
    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/style.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/skeleton.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/enlixo-ui.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="<?= Project::getAssetsPath() ?>/css/portal-learning.css?v=<?= Project::getVersion() ?>">
    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
    <script src="<?= Project::getAssetsPath() ?>/js/init.js?v=<?= Project::getVersion() ?>"></script>
</head>

<body class="overflow-hidden">
    <div class="notifications"></div>
    <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
        <div id="appContainer" class="row p-0">
            <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                <?php
                include __DIR__ . "/side-menu.php";
                load_side_menu::load($lf['menu']);
                ?>
            </div>
            <div id="app-wrapper" class="app-wrapper p-0 m-0">
                <div class="row p-0 m-0 g-0">
                    <div class="col-12 p-0 m-0 g-0">
                        <?php
                        include __DIR__ . "/header.php";
                        selectItem::setSelected($lf['menu']);
                        ?>
                    </div>
                    <div class="container p-4 m-0" id="skeleton-list">
                        <?php include __DIR__ . '/../../includes/components/skeleton-header.php'; ?>
                    </div>
                    <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                        <div class="ac-page">
