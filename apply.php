<?php
// Public student application form: apply?t=<link token>. No login.
// Drawn by assets/js/enlixo/pages/app-apply.js from GET /api/v1/public/apply/{token}; sent with POST to the same address.
// The form is the tutor's student form (Settings > Student Form). Applications land in the tutor's Requested Students.
require_once "./includes/Project.php";
$token = preg_replace('/[^A-Za-z0-9]/', '', (string) ($_GET['t'] ?? ''));
header('X-Frame-Options: DENY');
?>
<!DOCTYPE html>
<html lang="en" data-custom-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Student Application | <?= htmlspecialchars(Project::getBaseName(), ENT_QUOTES, 'UTF-8') ?></title>
    <meta property="og:title" content="Student Application | <?= htmlspecialchars(Project::getBaseName(), ENT_QUOTES, 'UTF-8') ?>" />
    <meta property="og:description" content="Apply online to join the class. It takes about 5 minutes." />
    <link rel="manifest" href="./assets/imgs/favicon/site.webmanifest" />
    <link rel="stylesheet" href="assets/css/style.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="assets/css/enlixo-ui.css?v=<?= Project::getVersion() ?>">
    <style>body { background: var(--body-color, #F7F7F7); }</style>
</head>

<body>
    <div class="notifications"></div>
    <main class="ex-apply" id="apply-root" data-token="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="text-center">
            <img src="assets/imgs/common/enlixo-dark.webp" alt="<?= htmlspecialchars(Project::getBaseName(), ENT_QUOTES, 'UTF-8') ?>" style="height: 44px; width: auto;">
        </div>
        <div id="apply-body" class="mt-4"></div>
        <p class="text-center text-12 sub-text mt-4 mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars(Project::getBaseName(), ENT_QUOTES, 'UTF-8') ?></p>
    </main>
    <script src="assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
    <script type="module" src="assets/js/enlixo/pages/app-apply.js?v=<?= Project::getVersion() ?>"></script>
</body>

</html>
