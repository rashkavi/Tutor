<?php
// Offer page opened by the QR codes on the landing page: offer.php?code=QR25 → "Congratulations: 25% off STARTER".
// The button takes the tutor to the sign-up with the plan and the code (tutor-registration-application.php?plan=start&promo=QR25);
// the code is saved with the request and the admin's Add Tutor starts with the discounted price. Codes: src/Platform/Promotions.php.
require_once __DIR__ . '/includes/Project.php';
$config = require __DIR__ . '/src/bootstrap.php';

$promo = Enlixo\Platform\Promotions::find((string) ($_GET['code'] ?? ''));
$plan = null;
if ($promo) {
    try {
        $plan = (new Enlixo\App($config))->platformCatalog()->planByKey($promo['plan']);
    } catch (Throwable $e) {
        error_log('Offer page: ' . $e->getMessage());
    }
}
$monthly = (float) ($plan['per_month_amount'] ?? 3000);
$limit = (int) ($plan['student_limit'] ?? 150);
$planName = strtoupper((string) ($plan['payment_plan'] ?? 'Starter'));
$offer = $promo ? Enlixo\Platform\Promotions::price($monthly, $promo) : $monthly;
$e = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$v = Project::getVersion();
$name = Project::getBaseName();
$claim = 'tutor-registration-application.php?plan=' . rawurlencode($promo['plan'] ?? 'start') . '&promo=' . rawurlencode($promo['code'] ?? '');
header('X-Frame-Options: DENY');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= $promo ? 'You found a gift' : 'Offer' ?> | <?= $e($name) ?></title>
    <meta name="theme-color" content="#050b1f">
    <link rel="icon" href="favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Sinhala:wght@400;600;700&family=Noto+Sans+Tamil:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/landing/landing.css?v=<?= $v ?>">
    <script>
        (function () {
            var saved = null;
            try { saved = localStorage.getItem('lang'); } catch (e) { }
            var dev = (navigator.languages || [navigator.language || '']).join(',').toLowerCase();
            window.LP_LANG = saved || (/(^|,)si/.test(dev) ? 'si' : /(^|,)ta/.test(dev) ? 'ta' : 'en');
        }());
    </script>
    <script src="assets/js/i18n-auto.js?v=<?= $v ?>"></script>
</head>

<body class="lp of">
    <div class="of-glow" aria-hidden="true"><i></i><i></i></div>
    <header class="of-top lp-wrap">
        <a href="./"><img src="assets/imgs/common/enlixo-light.webp" alt="<?= $e($name) ?>" height="28"></a>
        <div class="lp-langsw" role="group" aria-label="Language" data-no-i18n>
            <button type="button" data-set-lang="si" lang="si">සිං</button><button type="button" data-set-lang="ta" lang="ta">தமி</button><button type="button" data-set-lang="en">EN</button>
        </div>
    </header>

    <main class="of-main lp-wrap">
        <?php if ($promo) { ?>
            <div class="of-copy">
                <span class="of-kicker"><span class="lp-announce-tag">QR</span> <span>You scanned the ENLIXO QR code</span></span>
                <h1 class="of-h1"><span>Congratulations!</span><br><span class="lp-grad-green">You found a gift.</span></h1>
                <p class="of-lead">Here is <b data-no-i18n><?= (int) $promo['percent'] ?>%</b> <span>off the STARTER plan, just for scanning. Sign up with this code and our team adds it to your account.</span></p>

                <div class="of-price" aria-label="Price with the offer">
                    <div><small>Usual price</small><s data-no-i18n>LKR <?= number_format($monthly) ?></s></div>
                    <span class="of-arrow" aria-hidden="true">→</span>
                    <div><small>With your offer</small><b data-no-i18n>LKR <?= number_format($offer) ?></b><em>/ month</em></div>
                </div>

                <ul class="of-list">
                    <li><span class="lp-tick">✓</span> <span>Up to <?= $limit ?> students</span></li>
                    <li><span class="lp-tick">✓</span> <span>Zoom classes included</span></li>
                    <li><span class="lp-tick">✓</span> <span>Fees, QR attendance, recordings and tutes</span></li>
                    <li><span class="lp-tick">✓</span> <span>Student and parent logins</span></li>
                </ul>

                <div class="lp-hero-cta">
                    <a href="<?= $e($claim) ?>" class="lp-btn lp-btn-primary lp-btn-lg lp-shine"><span>Claim my <?= (int) $promo['percent'] ?>% off</span> →</a>
                    <a href="./#pricing" class="lp-btn lp-btn-glass lp-btn-lg">See all plans</a>
                </div>
                <p class="of-small">The offer is added when our team makes your account. Fewer than 50 students? ENLIXO is free anyway.</p>
            </div>

            <div class="of-ticket-wrap" aria-hidden="true">
                <div class="of-ticket" id="of-ticket">
                    <div class="of-ticket-top">
                        <img src="assets/imgs/common/enlixo-light.webp" alt="" height="20">
                        <span data-no-i18n><?= $e($planName) ?></span>
                    </div>
                    <div class="of-ticket-big" data-no-i18n><b><?= (int) $promo['percent'] ?></b><span>%<small>OFF</small></span></div>
                    <div class="of-ticket-cut"><i></i><i></i></div>
                    <div class="of-ticket-code"><small>Your code</small><b data-no-i18n><?= $e($promo['code']) ?></b></div>
                </div>
            </div>
        <?php } else { ?>
            <div class="of-copy of-none">
                <h1 class="of-h1">This offer is not available</h1>
                <p class="of-lead">The code may be old or typed wrongly. You can still start free with up to 50 students.</p>
                <div class="lp-hero-cta"><a href="tutor-registration-application.php" class="lp-btn lp-btn-primary lp-btn-lg">Start free</a><a href="./" class="lp-btn lp-btn-glass lp-btn-lg">Back to the home page</a></div>
            </div>
        <?php } ?>
    </main>

    <script>
        (function () {
            var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
            var set = function (lang) { document.querySelectorAll('[data-set-lang]').forEach(function (b) { b.classList.toggle('on', b.dataset.setLang === lang); }); };
            set(window.LP_LANG || 'en');
            if ((window.LP_LANG || 'en') !== 'en' && window.EnlixoI18n) window.EnlixoI18n.apply(window.LP_LANG);
            document.querySelectorAll('[data-set-lang]').forEach(function (b) {
                b.addEventListener('click', function () { set(b.dataset.setLang); if (window.EnlixoI18n) window.EnlixoI18n.apply(b.dataset.setLang); try { localStorage.setItem('lang', b.dataset.setLang); } catch (e) { } });
            });
            // the ticket follows the pointer a little
            var t = document.getElementById('of-ticket');
            if (t && !reduce) {
                addEventListener('pointermove', function (e) {
                    var x = e.clientX / innerWidth - 0.5, y = e.clientY / innerHeight - 0.5;
                    t.style.transform = 'rotateY(' + (x * 18) + 'deg) rotateX(' + (-y * 14) + 'deg) rotateZ(-4deg)';
                });
            }
            // confetti once
            if (t && !reduce) {
                var c = document.createElement('canvas'); c.className = 'su-confetti'; c.setAttribute('aria-hidden', 'true'); document.body.appendChild(c);
                var ctx = c.getContext('2d'); c.width = innerWidth; c.height = innerHeight;
                var colors = ['#0958da', '#3b82f6', '#22c55e', '#ffffff', '#93c5fd', '#f59e0b'];
                var bits = []; for (var i = 0; i < 180; i++) bits.push({ x: innerWidth * (0.3 + Math.random() * 0.4), y: innerHeight * 0.35, vx: (Math.random() - 0.5) * 16, vy: -Math.random() * 15 - 5, s: Math.random() * 7 + 4, r: Math.random() * 6, vr: (Math.random() - 0.5) * 0.3, c: colors[i % colors.length] });
                var t0 = performance.now();
                (function frame(now) {
                    ctx.clearRect(0, 0, c.width, c.height);
                    bits.forEach(function (b) { b.vy += 0.35; b.vx *= 0.99; b.x += b.vx; b.y += b.vy; b.r += b.vr; ctx.save(); ctx.translate(b.x, b.y); ctx.rotate(b.r); ctx.fillStyle = b.c; ctx.fillRect(-b.s / 2, -b.s / 4, b.s, b.s / 2); ctx.restore(); });
                    if (now - t0 < 4000) requestAnimationFrame(frame); else c.remove();
                }(t0));
            }
        }());
    </script>
</body>

</html>
