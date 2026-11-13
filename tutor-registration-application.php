<?php
// Online tutor sign-up: 4 steps (about you, your classes, plan, review). Drawn by assets/landing/signup.js from
// GET /api/v1/public/tutor-signup and sent with POST to the same address. The request waits in
// Admin > Tutors > Requested Tutors, where the admin approves it into a tutor account (Add Tutor) or rejects it.
require_once __DIR__ . '/includes/Project.php';
$e = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$v = Project::getVersion();
$name = Project::getBaseName();
header('X-Frame-Options: DENY');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Start your free trial | <?= $e($name) ?></title>
    <meta name="description" content="Send your request to join ENLIXO Tutor. Pick a plan, and our team sets up your account and your free trial.">
    <meta property="og:title" content="Start your free trial | <?= $e($name) ?>">
    <meta property="og:description" content="Students, fees, QR attendance, recordings, tutes and parents in one app for tutors.">
    <meta property="og:url" content="<?= $e(Project::getBaseUrl()) ?>/tutor-registration-application.php">
    <meta name="theme-color" content="#050b1f">
    <link rel="icon" href="favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Sinhala:wght@400;600;700&family=Noto+Sans+Tamil:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/landing/landing.css?v=<?= $v ?>">
    <script>
        // Same language as the landing page: the saved choice, else the device language.
        (function () {
            var saved = null;
            try { saved = localStorage.getItem('lang'); } catch (e) { }
            var dev = (navigator.languages || [navigator.language || '']).join(',').toLowerCase();
            window.LP_LANG = saved || (/(^|,)si/.test(dev) ? 'si' : /(^|,)ta/.test(dev) ? 'ta' : 'en');
        }());
    </script>
    <script src="assets/js/i18n-auto.js?v=<?= $v ?>"></script>
</head>

<body class="lp">
    <div class="su">
        <aside class="su-aside">
            <img class="su-aside-photo" src="assets/landing/img/portal-3.webp" alt="" aria-hidden="true">
            <a href="./" class="su-aside-logo"><img src="assets/imgs/common/enlixo-light.webp" alt="<?= $e($name) ?>"></a>
            <div class="su-aside-body">
                <span class="lp-pill"><span class="lp-pill-tag">Free</span> <span id="su-trial-pill">Free up to 50 students, no card needed</span></span>
                <h1>Teach more.<br><span class="lp-grad">Manage less.</span></h1>
                <p>Tell us about your classes and pick a plan. Our team calls you, sets up your account and helps you bring your students in.</p>
                <ul class="su-perks">
                    <li><span class="lp-dot"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4.5 4.5L19 7" /></svg></span> Fees, attendance, recordings and tutes in one place</li>
                    <li><span class="lp-dot"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4.5 4.5L19 7" /></svg></span> Students and parents in Sinhala, Tamil or English</li>
                    <li><span class="lp-dot"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4.5 4.5L19 7" /></svg></span> We help you move your students in</li>
                    <li><span class="lp-dot"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4.5 4.5L19 7" /></svg></span> Change your plan any time</li>
                </ul>
                <div class="su-quote"><b>What happens next?</b> We call or WhatsApp you within one working day, make your account and send your sign-in details by e-mail.</div>
            </div>
            <div class="su-aside-foot"><span>&copy; <?= date('Y') ?> <?= $e($name) ?></span><a href="https://enviiz.com" target="_blank" rel="noopener">Powered by ENVIIZ</a></div>
        </aside>

        <main class="su-main" id="su-root">
            <div class="su-top">
                <a href="./" class="su-top-logo"><img src="assets/imgs/common/enlixo-dark.webp" alt="<?= $e($name) ?>"></a>
                <div class="su-top-r">
                    <div class="lp-langsw su-langsw" role="group" aria-label="Language" data-no-i18n>
                        <button type="button" data-set-lang="si" lang="si">සිංහල</button><button type="button" data-set-lang="ta" lang="ta">தமிழ்</button><button type="button" data-set-lang="en">English</button>
                    </div>
                    <span><span>Already a tutor?</span> <a href="tutor/">Sign in</a></span>
                </div>
            </div>
            <div id="su-app">
                <div class="su-loading" aria-busy="true">
                    <div class="su-sk" style="height:8px;margin-bottom:36px"></div>
                    <div class="su-sk" style="height:30px;width:50%;margin-bottom:14px"></div>
                    <div class="su-sk" style="height:16px;width:70%;margin-bottom:30px"></div>
                    <div class="su-sk" style="height:48px;margin-bottom:16px"></div>
                    <div class="su-sk" style="height:48px;margin-bottom:16px"></div>
                    <div class="su-sk" style="height:48px"></div>
                </div>
            </div>
        </main>
    </div>
    <script type="module" src="assets/landing/signup.js?v=<?= $v ?>"></script>
</body>

</html>
