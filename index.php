<?php
// ENLIXO Tutor public landing page. Plans and prices come from Admin > Plans & Prices (payment_plans);
// the feature table is the pricing sheet (src/Catalog/FeatureCatalog.php). Works without the database too.
// Styles: assets/landing/landing.css. Motion: assets/landing/landing.js (respects "reduce motion").
require_once __DIR__ . '/includes/Project.php';

$info = null;
try {
    $config = require __DIR__ . '/src/bootstrap.php';
    $info = (new Enlixo\App($config))->tutorRequests()->signupInfo();
} catch (Throwable $e) {
    error_log('Landing page: ' . $e->getMessage());
}
if ($info === null) {
    require_once __DIR__ . '/src/bootstrap.php';
    $info = ['plans' => [], 'comparison' => Enlixo\Platform\TutorRequests::comparison(), 'addons' => [], 'trial' => ['days' => 7, 'plan' => 'PRO', 'students' => 30]];
    foreach (Enlixo\Catalog\FeatureCatalog::PLANS as $k => $p) {
        $info['plans'][] = ['key' => $k, 'name' => $p['name'], 'monthly' => ['start' => 3000, 'grow' => 6000, 'pro' => 10000][$k], 'yearly_discount' => 0, 'limit' => $p['student_limit'],
            'pack_price' => $k === 'pro' ? null : 1000, 'pack_size' => 50, 'tagline' => '', 'everything_in' => null, 'new_features' => [], 'feature_count' => count(Enlixo\Catalog\FeatureCatalog::featuresForPlan($k))];
    }
    array_unshift($info['plans'], Enlixo\Platform\TutorRequests::freePlan());
    foreach (Enlixo\Catalog\FeatureCatalog::features() as $f) {
        if (isset($f['addon'])) {
            $info['addons'][] = ['key' => $f['addon']['key'], 'name' => $f['addon']['name'], 'note' => $f['addon']['pricing']];
        }
    }
}
$e = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$lkr = static fn (float $v): string => number_format($v, 0);
$v = Project::getVersion();
$name = Project::getBaseName();
$trial = $info['trial'];
$signup = 'tutor-registration-application.php';
$planTone = ['free' => 'free', 'start' => 'green', 'grow' => 'blue', 'pro' => 'violet'];
$freeLimit = Enlixo\Platform\TutorRequests::FREE_LIMIT;
$host = preg_replace('#^https?://#', '', rtrim(Project::getBaseUrl(), '/'));
$calcPlans = array_map(static fn ($p) => ['key' => $p['key'], 'name' => $p['name'], 'monthly' => (float) $p['monthly'], 'limit' => $p['limit'], 'pack_price' => $p['pack_price'], 'pack_size' => $p['pack_size']], $info['plans']);
$addonIcon = ['enviiz_gateway' => 'card', 'enviiz_zoom' => 'video', 'enviiz_streaming' => 'shield', 'enviiz_sms' => 'sms', 'enviiz_whatsapp' => 'chat', 'physical_id' => 'id'];

// Small inline icons (stroke, currentColor)
function lp_icon(string $n, int $s = 22): string
{
    $p = [
        'qr' => '<path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4z"/><path d="M14 14h2v2h-2zM18 14h2M14 18h2M18 18h2v2M16 16h2v2"/>',
        'card' => '<rect x="3" y="5" width="18" height="14" rx="1"/><path d="M3 10h18M7 15h4"/>',
        'video' => '<rect x="3" y="6" width="13" height="12" rx="1"/><path d="m16 10 5-3v10l-5-3"/>',
        'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'sms' => '<path d="M4 5h16v11H8l-4 4z"/><path d="M8 10h8"/>',
        'chat' => '<path d="M20 12a8 8 0 0 1-11.6 7.1L4 20l1-4.2A8 8 0 1 1 20 12z"/><path d="M9 11h.01M12 11h.01M15 11h.01"/>',
        'id' => '<rect x="3" y="5" width="18" height="14" rx="1"/><circle cx="9" cy="11" r="2"/><path d="M6 16c.6-1.4 1.7-2 3-2s2.4.6 3 2M15 10h3M15 13h3"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="1"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5.5 6.5-5.5s5.7 2 6.5 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5c1.8.7 3 2.6 3.5 5.5"/>',
        'chart' => '<path d="M4 20V4M4 20h16"/><path d="M8 16v-5M12 16V8M16 16v-3M20 16V6"/>',
        'book' => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5M8 7h7"/>',
        'truck' => '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
        'bell' => '<path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'lock' => '<rect x="5" y="10" width="14" height="10" rx="1"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'check' => '<path d="m5 12 4.5 4.5L19 7"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'play' => '<path d="M7 4.5v15l12-7.5z"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/>',
        'sparkle' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
        'phone' => '<rect x="7" y="2.5" width="10" height="19" rx="1.5"/><path d="M11 18.5h2"/>',
        'student' => '<path d="m2 9 10-5 10 5-10 5z"/><path d="M6 11v5c1.8 1.6 3.8 2.4 6 2.4s4.2-.8 6-2.4v-5M22 9v6"/>',
        'parent' => '<circle cx="8" cy="7" r="3"/><circle cx="17" cy="10" r="2.3"/><path d="M2.5 20c.6-3.6 2.6-5.6 5.5-5.6s4.9 2 5.5 5.6M14 20c.3-2.6 1.4-4 3-4s2.7 1.4 3 4"/>',
        'tutor' => '<rect x="3" y="4" width="18" height="12" rx="1"/><path d="M8 20h8M12 16v4M7 9l3 2-3 2M12 13h4"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'chev' => '<path d="m6 9 6 6 6-6"/>',
    ][$n] ?? '';
    return '<svg class="lp-i" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $e($name) ?> | Classes, fees, attendance and recordings in one place</title>
    <meta name="description" content="ENLIXO Tutor is the all-in-one app for Sri Lankan tutors: students, class fees, QR attendance, Zoom classes, secure recordings, tutes and parents, in Sinhala, Tamil and English.">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= $e($name) ?> | Run your classes like a pro">
    <meta property="og:description" content="Students, fees, QR attendance, recordings, tutes and parents in one app. Start with a <?= (int) $trial['days'] ?>-day free trial.">
    <meta property="og:url" content="<?= $e(Project::getBaseUrl()) ?>">
    <meta property="og:image" content="assets/imgs/favican/android-chrome-512x512.png">
    <meta name="theme-color" content="#050b1f">
    <link rel="icon" href="favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Sinhala:wght@600&family=Noto+Sans+Tamil:wght@600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/landing/landing.css?v=<?= $v ?>">
</head>

<body class="lp">
    <a class="lp-skip" href="#main">Skip to the content</a>

    <!-- ============ NAV ============ -->
    <header class="lp-nav" id="lp-nav">
        <a href="#free" class="lp-announce" id="lp-announce">
            <span class="lp-announce-in"><span class="lp-announce-tag">FREE</span> Teaching <?= $freeLimit ?> students or fewer? Use ENLIXO Tutor completely free. <b>See how <?= lp_icon('arrow', 14) ?></b></span>
        </a>
        <div class="lp-wrap lp-nav-in">
            <a href="./" class="lp-logo" aria-label="<?= $e($name) ?> home">
                <img src="assets/imgs/common/enlixo-light.webp" alt="<?= $e($name) ?>" class="lp-logo-light">
                <img src="assets/imgs/common/enlixo-dark.webp" alt="" class="lp-logo-dark" aria-hidden="true">
            </a>
            <nav class="lp-links" aria-label="Main">
                <a href="#free">Free plan</a>
                <a href="#features">Features</a>
                <a href="#logins">Logins</a>
                <a href="#languages">Languages</a>
                <a href="#pricing">Pricing</a>
                <a href="#faq">FAQ</a>
            </nav>
            <div class="lp-nav-cta">
                <div class="lp-signin">
                    <button type="button" class="lp-btn lp-btn-ghost" aria-haspopup="true" aria-expanded="false" id="lp-signin-btn">Sign in <?= lp_icon('chev', 16) ?></button>
                    <div class="lp-signin-menu" role="menu" aria-labelledby="lp-signin-btn">
                        <a role="menuitem" href="tutor/"><span class="lp-dot lp-blue"><?= lp_icon('tutor', 18) ?></span><span><b>Tutor</b><small>Manage your classes</small></span></a>
                        <a role="menuitem" href="portal/"><span class="lp-dot lp-green"><?= lp_icon('student', 18) ?></span><span><b>Student</b><small>Classes, tutes and recordings</small></span></a>
                        <a role="menuitem" href="parent/"><span class="lp-dot lp-violet"><?= lp_icon('parent', 18) ?></span><span><b>Parent</b><small>Follow your child</small></span></a>
                    </div>
                </div>
                <a href="<?= $signup ?>" class="lp-btn lp-btn-primary lp-hide-sm">Start free</a>
                <button type="button" class="lp-burger" id="lp-burger" aria-label="Open the menu" aria-expanded="false"><?= lp_icon('menu', 24) ?></button>
            </div>
        </div>
        <div class="lp-mobile" id="lp-mobile" hidden>
            <a href="#free">Free plan</a><a href="#features">Features</a><a href="#logins">Logins</a><a href="#languages">Languages</a><a href="#pricing">Pricing</a><a href="#faq">FAQ</a>
            <div class="lp-mobile-signin"><a href="tutor/">Tutor sign in</a><a href="portal/">Student sign in</a><a href="parent/">Parent sign in</a></div>
            <a href="<?= $signup ?>" class="lp-btn lp-btn-primary w-100">Start free</a>
        </div>
    </header>

    <main id="main">
        <!-- ============ HERO ============ -->
        <section class="lp-hero" id="top">
            <canvas class="lp-stars" id="lp-stars" aria-hidden="true"></canvas>
            <div class="lp-aurora" aria-hidden="true"><i></i><i></i><i></i></div>
            <div class="lp-grid-bg" aria-hidden="true"></div>
            <div class="lp-spot" aria-hidden="true"></div>

            <div class="lp-wrap lp-hero-in">
                <div class="lp-hero-copy">
                    <a href="#free" class="lp-pill" data-reveal>
                        <span class="lp-pill-tag">Free</span> Up to <?= $freeLimit ?> students? It costs you nothing <?= lp_icon('arrow', 16) ?>
                    </a>
                    <h1 class="lp-h1" data-reveal>
                        <span class="lp-h1-a">Run your classes</span>
                        <span class="lp-rotator" aria-live="polite"><span class="lp-rot-word" id="lp-rot">like a pro.</span></span>
                    </h1>
                    <p class="lp-lead" data-reveal>Students, class fees, QR attendance, Zoom classes, secure recordings, tutes and parents. One app, built for Sri Lankan tutors, in
                        <span class="lp-lang">සිංහල</span>, <span class="lp-lang">தமிழ்</span> and English.</p>
                    <div class="lp-hero-cta" data-reveal>
                        <a href="<?= $signup ?>" class="lp-btn lp-btn-primary lp-btn-lg lp-shine">Start free today <?= lp_icon('arrow', 18) ?></a>
                        <a href="#logins" class="lp-btn lp-btn-glass lp-btn-lg"><?= lp_icon('play', 16) ?> Student &amp; parent logins</a>
                    </div>
                    <ul class="lp-hero-facts" data-reveal>
                        <li><?= lp_icon('check', 16) ?> Free up to <?= $freeLimit ?> students</li>
                        <li><?= lp_icon('check', 16) ?> Own logins for students &amp; parents</li>
                        <li><?= lp_icon('check', 16) ?> Our team sets you up</li>
                    </ul>
                </div>

                <!-- live dashboard mock-up (drawn in HTML, animated by landing.js) -->
                <div class="lp-stage" id="lp-stage" aria-hidden="true">
                    <div class="lp-mock" id="lp-mock">
                        <div class="lp-mock-bar"><i></i><i></i><i></i><span>tutor / dashboard</span></div>
                        <div class="lp-mock-body">
                            <div class="lp-mock-side">
                                <b></b><span class="on"></span><span></span><span></span><span></span><span></span>
                            </div>
                            <div class="lp-mock-main">
                                <div class="lp-mock-hello"><div><b>Good evening, Nimal</b><small>2 classes today · 4 slips to approve</small></div><span class="lp-mock-btn">Mark Attendance</span></div>
                                <div class="lp-mock-stats">
                                    <div class="lp-ms lp-ms-blue"><small>Active students</small><b data-count="248">0</b><em><i style="--h:40%"></i><i style="--h:55%"></i><i style="--h:48%"></i><i style="--h:70%"></i><i style="--h:92%"></i></em></div>
                                    <div class="lp-ms lp-ms-green"><small>Fees this month</small><b><span>LKR </span><span data-count="486000" data-format="money">0</span></b><em><i style="--h:30%"></i><i style="--h:50%"></i><i style="--h:65%"></i><i style="--h:60%"></i><i style="--h:85%"></i></em></div>
                                    <div class="lp-ms lp-ms-violet"><small>Attendance</small><b><span data-count="91">0</span>%</b><em><i style="--h:80%"></i><i style="--h:70%"></i><i style="--h:88%"></i><i style="--h:84%"></i><i style="--h:91%"></i></em></div>
                                </div>
                                <div class="lp-mock-row">
                                    <div class="lp-mock-chart">
                                        <small>Fee collection</small>
                                        <div class="lp-bars"><i style="--h:42%"></i><i style="--h:58%"></i><i style="--h:50%"></i><i style="--h:66%"></i><i style="--h:62%"></i><i style="--h:78%"></i><i style="--h:74%"></i><i style="--h:90%"></i></div>
                                        <svg class="lp-line" viewBox="0 0 200 60" preserveAspectRatio="none"><path d="M0 50 C25 44 35 30 55 34 S90 20 110 24 150 10 200 6" /></svg>
                                    </div>
                                    <div class="lp-mock-donut">
                                        <svg viewBox="0 0 42 42"><circle cx="21" cy="21" r="15.9" class="bg"/><circle cx="21" cy="21" r="15.9" class="a"/><circle cx="21" cy="21" r="15.9" class="b"/></svg>
                                        <small>Paid 78%</small>
                                    </div>
                                </div>
                                <div class="lp-mock-list">
                                    <div><span class="lp-av">KS</span><span>Kavindi Silva</span><em class="ok">Paid</em></div>
                                    <div><span class="lp-av">NP</span><span>Nimal Perera</span><em class="wait">Slip uploaded</em></div>
                                    <div><span class="lp-av">AF</span><span>Ayesha Fernando</span><em class="ok">Present</em></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- floating notifications -->
                    <div class="lp-toast lp-toast-1"><span class="lp-dot lp-green"><?= lp_icon('check', 16) ?></span><span><b>Payment approved</b><small>Kavindi · LKR 3,000 · November</small></span></div>
                    <div class="lp-toast lp-toast-2"><span class="lp-dot lp-blue"><?= lp_icon('qr', 16) ?></span><span><b>QR scan · Present</b><small>2026 A/L Physics · 3:31 PM</small></span></div>
                    <div class="lp-toast lp-toast-3"><span class="lp-dot lp-violet"><?= lp_icon('lock', 16) ?></span><span><b>Recording unlocked</b><small>Open for 7 days · 2 views left</small></span></div>
                    <div class="lp-phone">
                        <div class="lp-phone-notch"></div>
                        <div class="lp-phone-screen">
                            <small>Student ID</small>
                            <div class="lp-qr"><?php for ($i = 0; $i < 49; $i++) { echo '<i' . ((($i * 7 + $i % 5 * 3) % 11) < 6 ? ' class="on"' : '') . '></i>'; } ?><span class="lp-scan"></span></div>
                            <b>Kavindi Silva</b>
                            <small>ST-000248</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lp-marquee" aria-label="Classes that use it">
                <div class="lp-marquee-track">
                    <?php $tags = ['A/L Physics', 'O/L Maths', 'Grade 5 Scholarship', 'A/L Chemistry', 'English Literature', 'ICT', 'Combined Maths', 'Biology', 'Accounting', 'Economics', 'සිංහල', 'தமிழ்', 'History', 'Spoken English'];
                    for ($k = 0; $k < 2; $k++) { foreach ($tags as $t) { echo '<span' . ($k ? ' aria-hidden="true"' : '') . '>' . $e($t) . '</span>'; } } ?>
                </div>
            </div>
        </section>

        <!-- ============ FREE FOR SMALL CLASSES ============ -->
        <section class="lp-sec lp-free" id="free">
            <div class="lp-free-glow" aria-hidden="true"></div>
            <div class="lp-wrap lp-free-in">
                <div class="lp-free-copy">
                    <span class="lp-eyebrow lp-eyebrow-green" data-reveal>Free for small classes</span>
                    <h2 class="lp-h2" data-reveal>Up to <?= $freeLimit ?> students?<br><span class="lp-grad-green">It's completely free.</span></h2>
                    <p class="lp-sub" data-reveal>Just starting out, or teaching a small group? Use ENLIXO Tutor with all the START features and pay nothing, for as long as you teach <?= $freeLimit ?> students or fewer.</p>
                    <ul class="lp-free-list" data-reveal>
                        <li><span class="lp-tick"><?= lp_icon('check', 14) ?></span>Students, classes and timetable</li>
                        <li><span class="lp-tick"><?= lp_icon('check', 14) ?></span>Fees with bank slips and approval</li>
                        <li><span class="lp-tick"><?= lp_icon('check', 14) ?></span>QR and manual attendance</li>
                        <li><span class="lp-tick"><?= lp_icon('check', 14) ?></span>Zoom classes and recordings</li>
                        <li><span class="lp-tick"><?= lp_icon('check', 14) ?></span>Tutes and study material</li>
                        <li><span class="lp-tick"><?= lp_icon('check', 14) ?></span>Student and parent logins</li>
                    </ul>
                    <div class="lp-hero-cta" data-reveal>
                        <a href="<?= $signup ?>?plan=free" class="lp-btn lp-btn-green lp-btn-lg lp-shine">Start free, no card <?= lp_icon('arrow', 18) ?></a>
                        <a href="#pricing" class="lp-btn lp-btn-soft lp-btn-lg">Compare plans</a>
                    </div>
                </div>
                <div class="lp-calc" data-reveal id="lp-calc" data-plans="<?= $e(json_encode($calcPlans)) ?>" data-free="<?= $freeLimit ?>" data-signup="<?= $signup ?>">
                    <div class="lp-calc-zero" aria-hidden="true"><small>LKR</small>0</div>
                    <label for="lp-calc-range" class="lp-calc-q">How many students do you teach?</label>
                    <div class="lp-calc-n"><output id="lp-calc-n" for="lp-calc-range">35</output><span>students</span></div>
                    <input type="range" id="lp-calc-range" min="5" max="400" step="5" value="35" aria-describedby="lp-calc-res">
                    <div class="lp-calc-scale" aria-hidden="true"><span>5</span><span class="m" style="--at:<?= round(($freeLimit - 5) / 395 * 100, 2) ?>%"><?= $freeLimit ?> · free</span><span>400</span></div>
                    <div class="lp-calc-res" id="lp-calc-res" aria-live="polite">
                        <div><small>Your plan</small><b id="lp-calc-plan">FREE</b></div>
                        <div class="r"><small>You pay</small><b id="lp-calc-price">LKR 0</b><em id="lp-calc-per">a month</em></div>
                    </div>
                    <p class="lp-calc-note" id="lp-calc-note">Free while you teach up to <?= $freeLimit ?> students.</p>
                    <a href="<?= $signup ?>?plan=free" class="lp-btn lp-btn-white w-100" id="lp-calc-cta">Start with FREE <?= lp_icon('arrow', 16) ?></a>
                </div>
            </div>
        </section>

        <!-- ============ CHAOS → ONE APP ============ -->
        <section class="lp-sec lp-chaos" id="why">
            <div class="lp-wrap">
                <div class="lp-head" data-reveal>
                    <span class="lp-eyebrow">Why tutors switch</span>
                    <h2 class="lp-h2">Five tools, one notebook and a lot of WhatsApp.<br><span class="lp-grad">Or one app.</span></h2>
                </div>
                <div class="lp-chaos-stage" id="lp-chaos">
                    <div class="lp-chip c1"><?= lp_icon('chat', 18) ?> 14 WhatsApp groups</div>
                    <div class="lp-chip c2"><?= lp_icon('chart', 18) ?> Fee Excel sheet</div>
                    <div class="lp-chip c3"><?= lp_icon('card', 18) ?> Bank slip photos</div>
                    <div class="lp-chip c4"><?= lp_icon('book', 18) ?> Attendance book</div>
                    <div class="lp-chip c5"><?= lp_icon('video', 18) ?> Shared Drive links</div>
                    <div class="lp-chip c6"><?= lp_icon('truck', 18) ?> Tute post list</div>
                    <svg class="lp-chaos-lines" viewBox="0 0 900 380" preserveAspectRatio="none" aria-hidden="true">
                        <defs><linearGradient id="lp-lg" x1="0" x2="1"><stop offset="0" stop-color="#2f7bff"/><stop offset="1" stop-color="#7c5cff"/></linearGradient></defs>
                        <path d="M215 52 C330 52 330 190 450 190"/><path d="M215 182 C330 182 340 190 450 190"/><path d="M215 312 C330 312 330 190 450 190"/>
                        <path d="M621 52 C540 52 560 190 450 190"/><path d="M621 182 C560 182 560 190 450 190"/><path d="M621 312 C540 312 560 190 450 190"/>
                    </svg>
                    <div class="lp-chaos-core"><img src="assets/imgs/common/enlixo-dark.webp" alt="<?= $e($name) ?>"><small>Everything in one place</small></div>
                </div>
            </div>
        </section>

        <!-- ============ FEATURES ============ -->
        <section class="lp-sec" id="features">
            <div class="lp-wrap">
                <div class="lp-head" data-reveal>
                    <span class="lp-eyebrow">Features</span>
                    <h2 class="lp-h2">Everything a class needs, <span class="lp-grad">nothing it doesn't.</span></h2>
                    <p class="lp-sub">From the first registration to the last exam, every part of running a class works together.</p>
                </div>
                <div class="lp-bento">
                    <article class="lp-tile lp-t-wide" data-reveal data-tilt>
                        <div class="lp-tile-txt">
                            <span class="lp-dot lp-blue"><?= lp_icon('qr') ?></span>
                            <h3>QR attendance in a second</h3>
                            <p>Every student gets a digital ID with a QR code. Scan at the door; the parent knows they arrived. Absent students who watched the recording are counted too.</p>
                        </div>
                        <div class="lp-scan-demo" aria-hidden="true">
                            <div class="lp-scan-card"><div class="lp-qr lp-qr-sm"><?php for ($i = 0; $i < 49; $i++) { echo '<i' . ((($i * 5 + $i % 3 * 4) % 9) < 5 ? ' class="on"' : '') . '></i>'; } ?></div><span class="lp-scan"></span></div>
                            <div class="lp-scan-log"><span>Present · 3:30 PM</span><span>Present · 3:31 PM</span><span>Present · 3:31 PM</span><span class="new">Present · 3:32 PM</span></div>
                        </div>
                    </article>
                    <article class="lp-tile" data-reveal data-tilt>
                        <span class="lp-dot lp-green"><?= lp_icon('card') ?></span>
                        <h3>Fees that collect themselves</h3>
                        <p>Monthly fee per class, bank slip uploads, one-click approval, card payments and penalties. See who owes what at a glance.</p>
                        <div class="lp-meter" aria-hidden="true"><i style="--w:78%"></i><span>78% paid this month</span></div>
                    </article>
                    <article class="lp-tile" data-reveal data-tilt>
                        <span class="lp-dot lp-violet"><?= lp_icon('lock') ?></span>
                        <h3>Recordings that stay yours</h3>
                        <p>Share Zoom recordings only with students who paid. Set how long and how many times they can watch, with secure streaming.</p>
                        <div class="lp-timer" aria-hidden="true"><b data-count="7">0</b><small>days left to watch</small></div>
                    </article>
                    <article class="lp-tile" data-reveal data-tilt>
                        <span class="lp-dot lp-orange"><?= lp_icon('calendar') ?></span>
                        <h3>Timetable and Zoom</h3>
                        <p>Weekly physical and online classes, extra classes and exam countdowns. Zoom meetings are made for you.</p>
                    </article>
                    <article class="lp-tile" data-reveal data-tilt>
                        <span class="lp-dot lp-teal"><?= lp_icon('truck') ?></span>
                        <h3>Tutes, posted or online</h3>
                        <p>Give tutes to paid students only, and track every parcel from packed to delivered.</p>
                        <div class="lp-track" aria-hidden="true"><i class="on"></i><i class="on"></i><i class="on"></i><i></i><span></span></div>
                    </article>
                    <article class="lp-tile" data-reveal data-tilt>
                        <span class="lp-dot lp-pink"><?= lp_icon('bell') ?></span>
                        <h3>WhatsApp, SMS and e-mail</h3>
                        <p>Send class notices and fee reminders to students and parents from one place.</p>
                    </article>
                    <article class="lp-tile lp-t-wide lp-t-dark" data-reveal data-tilt>
                        <div class="lp-tile-txt">
                            <span class="lp-dot lp-blue"><?= lp_icon('chart') ?></span>
                            <h3>Know your classes by the numbers</h3>
                            <p>Attendance by week, fee collection, students who joined and left, and which classes need you. Export any report.</p>
                        </div>
                        <div class="lp-mini-chart" aria-hidden="true">
                            <?php foreach ([38, 52, 46, 64, 58, 72, 69, 84, 80, 94] as $i => $hgt) { echo '<i style="--h:' . $hgt . '%;--d:' . ($i * 60) . 'ms"></i>'; } ?>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- ============ SEPARATE LOGINS ============ -->
        <section class="lp-sec lp-logins" id="logins">
            <div class="lp-wrap">
                <div class="lp-head" data-reveal>
                    <span class="lp-eyebrow">Three logins, one system</span>
                    <h2 class="lp-h2">Students and parents get <span class="lp-grad">their own login.</span></h2>
                    <p class="lp-sub">Students sign in to the Student Portal, parents to the Parent Portal, and you run everything from your dashboard. Each one sees only what is theirs, on any phone, with no app to install.</p>
                </div>
                <div class="lp-portals">
                    <article class="lp-portal lp-portal-student" data-reveal>
                        <div class="lp-portal-dev" aria-hidden="true">
                            <div class="lp-dev-phone">
                                <div class="lp-phone-notch"></div>
                                <div class="lp-dev-scr">
                                    <div class="lp-dev-top"><b>Hi Kavindi</b><span class="lp-av lp-av-g">KS</span></div>
                                    <div class="lp-dev-card lp-dev-live"><small>Next class · in 25 min</small><b>A/L Physics</b><span class="lp-dev-btn">Join Zoom</span></div>
                                    <div class="lp-dev-card"><small>Recording · 6 days left</small><b>Lesson 12 · Waves</b><div class="lp-dev-prog"><i style="--w:62%"></i></div></div>
                                    <div class="lp-dev-row"><div class="lp-dev-card"><small>November fee</small><b class="ok">Paid</b></div><div class="lp-dev-card lp-dev-qr"><span class="lp-qr lp-qr-xs"><?php for ($i = 0; $i < 25; $i++) { echo '<i' . ((($i * 3 + $i % 4) % 5) < 3 ? ' class="on"' : '') . '></i>'; } ?></span><small>My ID</small></div></div>
                                </div>
                            </div>
                        </div>
                        <div class="lp-portal-body">
                            <span class="lp-portal-tag lp-green"><?= lp_icon('student', 16) ?> Student Portal</span>
                            <h3>Everything for class, in their pocket</h3>
                            <ul>
                                <li><?= lp_icon('check', 15) ?> Join Zoom classes in one tap</li>
                                <li><?= lp_icon('check', 15) ?> Watch the recordings they paid for</li>
                                <li><?= lp_icon('check', 15) ?> Pay fees or upload a bank slip</li>
                                <li><?= lp_icon('check', 15) ?> Tutes, papers, marks and timetable</li>
                                <li><?= lp_icon('check', 15) ?> Digital student ID with a QR code</li>
                            </ul>
                            <div class="lp-portal-foot"><code><?= $e($host) ?>/portal</code><a href="portal/" class="lp-btn lp-btn-dark">Student sign in <?= lp_icon('arrow', 16) ?></a></div>
                        </div>
                    </article>
                    <article class="lp-portal lp-portal-tutor" data-reveal>
                        <div class="lp-portal-dev" aria-hidden="true">
                            <div class="lp-dev-laptop">
                                <div class="lp-dev-lscr">
                                    <div class="lp-dev-lside"><b></b><span class="on"></span><span></span><span></span><span></span></div>
                                    <div class="lp-dev-lmain">
                                        <div class="lp-dev-stats"><span><small>Students</small><b>248</b></span><span><small>Paid</small><b>78%</b></span><span><small>Present</small><b>91%</b></span></div>
                                        <div class="lp-dev-bars"><?php foreach ([40, 62, 54, 70, 66, 84, 78, 95] as $hh) { echo '<i style="--h:' . $hh . '%"></i>'; } ?></div>
                                        <div class="lp-dev-list"><span><em class="ok"></em>Kavindi paid LKR 3,000</span><span><em class="wait"></em>Nimal uploaded a slip</span></div>
                                    </div>
                                </div>
                                <div class="lp-dev-base"></div>
                            </div>
                        </div>
                        <div class="lp-portal-body">
                            <span class="lp-portal-tag lp-blue"><?= lp_icon('tutor', 16) ?> Tutor Dashboard</span>
                            <h3>You run the whole class</h3>
                            <ul>
                                <li><?= lp_icon('check', 15) ?> Today's classes, fees and attendance</li>
                                <li><?= lp_icon('check', 15) ?> Approve slips and mark attendance</li>
                                <li><?= lp_icon('check', 15) ?> Share recordings, tutes and notices</li>
                                <li><?= lp_icon('check', 15) ?> Give students and parents their logins</li>
                                <li><?= lp_icon('check', 15) ?> Staff logins for your assistants</li>
                            </ul>
                            <div class="lp-portal-foot"><code><?= $e($host) ?>/tutor</code><a href="tutor/" class="lp-btn lp-btn-primary">Tutor sign in <?= lp_icon('arrow', 16) ?></a></div>
                        </div>
                    </article>
                    <article class="lp-portal lp-portal-parent" data-reveal>
                        <div class="lp-portal-dev" aria-hidden="true">
                            <div class="lp-dev-phone">
                                <div class="lp-phone-notch"></div>
                                <div class="lp-dev-scr">
                                    <div class="lp-dev-top"><b>Hi Sunil</b><span class="lp-av lp-av-v">SR</span></div>
                                    <div class="lp-dev-kids"><span class="on">Kavindi</span><span>Nimal</span></div>
                                    <div class="lp-dev-card lp-dev-ring"><svg viewBox="0 0 42 42"><circle cx="21" cy="21" r="15.9" class="bg" /><circle cx="21" cy="21" r="15.9" class="fg" /></svg><div><small>Attendance</small><b>92%</b></div></div>
                                    <div class="lp-dev-card lp-dev-note"><span class="lp-dot lp-violet"><?= lp_icon('bell', 14) ?></span><span><b>Kavindi arrived</b><small>A/L Physics · 3:29 PM</small></span></div>
                                    <div class="lp-dev-card"><small>Fees to pay</small><b>LKR 4,500</b><span class="lp-dev-btn lp-dev-btn-v">Pay now</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="lp-portal-body">
                            <span class="lp-portal-tag lp-violet"><?= lp_icon('parent', 16) ?> Parent Portal</span>
                            <h3>Peace of mind for parents</h3>
                            <ul>
                                <li><?= lp_icon('check', 15) ?> Every child in one login</li>
                                <li><?= lp_icon('check', 15) ?> Know when they arrive at class</li>
                                <li><?= lp_icon('check', 15) ?> Attendance for every class</li>
                                <li><?= lp_icon('check', 15) ?> See and pay fees online</li>
                                <li><?= lp_icon('check', 15) ?> Classes, exams and notices</li>
                            </ul>
                            <div class="lp-portal-foot"><code><?= $e($host) ?>/parent</code><a href="parent/" class="lp-btn lp-btn-dark">Parent sign in <?= lp_icon('arrow', 16) ?></a></div>
                        </div>
                    </article>
                </div>
                <div class="lp-login-facts" data-reveal>
                    <span><?= lp_icon('lock', 18) ?> Own password for each student and parent</span>
                    <span><?= lp_icon('users', 18) ?> One parent login for all their children</span>
                    <span><?= lp_icon('phone', 18) ?> Any phone browser, no app to install</span>
                    <span><?= lp_icon('globe', 18) ?> <span class="lp-lang">සිංහල · தமிழ் · English</span></span>
                </div>
            </div>
        </section>

        <!-- ============ LANGUAGES ============ -->
        <section class="lp-sec lp-lang-sec" id="languages">
            <div class="lp-aurora lp-aurora-soft" aria-hidden="true"><i></i><i></i><i></i></div>
            <div class="lp-wrap lp-lang-in">
                <div class="lp-lang-copy">
                    <span class="lp-eyebrow lp-eyebrow-dark" data-reveal>Your language, your choice</span>
                    <h2 class="lp-h2" data-reveal>
                        <span class="lp-lang-big" lang="si">සිංහල</span>
                        <span class="lp-lang-big" lang="ta">தமிழ்</span>
                        <span class="lp-lang-big">English</span>
                    </h2>
                    <p class="lp-sub" data-reveal>Every tutor, student and parent picks the language they read best, and can change it any time from the top of the page. In the same class, the tutor can work in English while a student reads Sinhala and a parent reads Tamil.</p>
                    <div class="lp-lang-people" data-reveal>
                        <div class="lp-lp-row"><span class="lp-dot lp-blue"><?= lp_icon('tutor', 18) ?></span><span><b>Tutor</b><small>Mr. Perera</small></span><em>English</em></div>
                        <div class="lp-lp-row"><span class="lp-dot lp-green"><?= lp_icon('student', 18) ?></span><span><b>Student</b><small>Kavindi</small></span><em lang="si">සිංහල</em></div>
                        <div class="lp-lp-row"><span class="lp-dot lp-violet"><?= lp_icon('parent', 18) ?></span><span><b>Parent</b><small>Mrs. Shanthi</small></span><em lang="ta">தமிழ்</em></div>
                    </div>
                    <ul class="lp-lang-points" data-reveal>
                        <li><?= lp_icon('check', 16) ?> Menus, buttons and messages, on every page</li>
                        <li><?= lp_icon('check', 16) ?> Each person's choice is kept for them</li>
                        <li><?= lp_icon('check', 16) ?> Names and class details stay as you typed them</li>
                    </ul>
                </div>

                <!-- live demo: landing.js switches the words between the three languages -->
                <div class="lp-lang-demo" data-reveal id="lp-lang-demo">
                    <div class="lp-ld-switch" role="tablist" aria-label="Try a language">
                        <button type="button" role="tab" data-lang="si" aria-selected="true" class="on" lang="si">සිංහල</button>
                        <button type="button" role="tab" data-lang="ta" aria-selected="false" lang="ta">தமிழ்</button>
                        <button type="button" role="tab" data-lang="en" aria-selected="false">English</button>
                        <span class="lp-ld-ink" aria-hidden="true"></span>
                    </div>
                    <div class="lp-ld-app" aria-live="polite">
                        <div class="lp-ld-side">
                            <span class="lp-ld-logo"></span>
                            <span class="lp-ld-nav on" data-k="dashboard"></span>
                            <span class="lp-ld-nav" data-k="classes"></span>
                            <span class="lp-ld-nav" data-k="timetable"></span>
                            <span class="lp-ld-nav" data-k="attendance"></span>
                            <span class="lp-ld-nav" data-k="payments"></span>
                            <span class="lp-ld-nav" data-k="recordings"></span>
                        </div>
                        <div class="lp-ld-main">
                            <div class="lp-ld-hello"><b data-k="hello"></b><small data-k="today"></small></div>
                            <div class="lp-ld-cards">
                                <div class="lp-ld-card"><small data-k="attendance"></small><b>92%</b><span class="lp-ld-chip ok" data-k="present"></span></div>
                                <div class="lp-ld-card"><small data-k="students"></small><b>48</b><span class="lp-ld-chip" data-k="classes3"></span></div>
                            </div>
                            <div class="lp-ld-card lp-ld-next"><div><small data-k="next"></small><b>A/L Physics · 3:30 PM</b></div><span class="lp-ld-btn" data-k="join"></span></div>
                            <div class="lp-ld-card lp-ld-fee"><div><small data-k="fees"></small><b>LKR 1,500</b></div><span class="lp-ld-btn lp-ld-btn-g" data-k="paynow"></span></div>
                            <div class="lp-ld-list">
                                <span><i class="ok"></i><b data-k="tutes"></b><em data-k="new"></em></span>
                                <span><i class="v"></i><b data-k="recordings"></b><em data-k="watch"></em></span>
                            </div>
                        </div>
                    </div>
                    <p class="lp-ld-cap">Try it: tap a language above.</p>
                </div>
            </div>
        </section>

        <!-- ============ HOW IT WORKS ============ -->
        <section class="lp-sec" id="how">
            <div class="lp-wrap">
                <div class="lp-head" data-reveal>
                    <span class="lp-eyebrow">How it works</span>
                    <h2 class="lp-h2">Teaching on ENLIXO in <span class="lp-grad">three steps.</span></h2>
                </div>
                <ol class="lp-steps" id="lp-steps">
                    <li data-reveal><span class="lp-step-n">1</span><h3>Send your request</h3><p>Tell us about your classes and pick a plan. It takes about two minutes.</p></li>
                    <li data-reveal><span class="lp-step-n">2</span><h3>We set you up</h3><p>Our team calls you, makes your account and helps you add your classes and students.</p></li>
                    <li data-reveal><span class="lp-step-n">3</span><h3>Teach, we handle the rest</h3><p>Start with <?= (int) $trial['days'] ?> free days on <?= $e($trial['plan']) ?>. Fees, attendance and recordings run by themselves.</p></li>
                    <span class="lp-steps-line" aria-hidden="true"><i></i></span>
                </ol>
            </div>
        </section>

        <!-- ============ PRICING ============ -->
        <section class="lp-sec lp-pricing" id="pricing">
            <div class="lp-wrap">
                <div class="lp-head" data-reveal>
                    <span class="lp-eyebrow">Pricing</span>
                    <h2 class="lp-h2">Simple plans that <span class="lp-grad">grow with your class.</span></h2>
                    <p class="lp-sub">Free up to <?= $freeLimit ?> students. Bigger classes start with a <?= (int) $trial['days'] ?>-day free trial. Add more students any time.</p>
                </div>
                <?php $anyYearly = array_sum(array_column($info['plans'], 'yearly_discount')) > 0; ?>
                <?php if ($anyYearly) { ?>
                    <div class="lp-cycle" role="radiogroup" aria-label="Billing" data-reveal>
                        <button type="button" role="radio" aria-checked="true" data-cycle="monthly" class="on">Monthly</button>
                        <button type="button" role="radio" aria-checked="false" data-cycle="yearly">Yearly <span class="lp-save">Save more</span></button>
                    </div>
                <?php } ?>
                <div class="lp-plans<?= count($info['plans']) > 3 ? ' lp-plans-4' : '' ?>">
                    <?php foreach ($info['plans'] as $p) {
                        $isFree = !empty($p['free']);
                        $tone = $planTone[$p['key']] ?? 'blue';
                        $pop = $p['key'] === 'grow';
                        $yearly = max(0, $p['monthly'] - $p['yearly_discount']); ?>
                        <article class="lp-plan lp-plan-<?= $tone ?><?= $pop ? ' lp-plan-pop' : '' ?>" data-reveal data-tilt>
                            <?php if ($pop) { ?><span class="lp-plan-badge">Most popular</span><?php } ?>
                            <div class="lp-plan-head">
                                <span class="lp-plan-name"><?= $e($p['name']) ?></span>
                                <span class="lp-plan-tag"><?= $e($p['tagline'] ?: ($p['limit'] ? 'Up to ' . $p['limit'] . ' students' : 'Unlimited students')) ?></span>
                            </div>
                            <div class="lp-price">
                                <small>LKR</small>
                                <b data-monthly="<?= (int) $p['monthly'] ?>" data-yearly="<?= (int) $yearly ?>"><?= $lkr($p['monthly']) ?></b>
                                <span>/ month</span>
                            </div>
                            <?php if ($isFree) { $fn = 'Free while you teach up to ' . (int) $p['limit'] . ' students'; ?>
                                <div class="lp-price-note" data-monthly-note="<?= $fn ?>" data-yearly-note="<?= $fn ?>"><?= $fn ?></div>
                            <?php } else { ?>
                                <div class="lp-price-note" data-monthly-note="Billed every month" data-yearly-note="Billed yearly, LKR <?= $lkr($yearly * 12) ?> a year">Billed every month</div>
                            <?php } ?>
                            <a href="<?= $signup ?>?plan=<?= $e($p['key']) ?>" class="lp-btn <?= $pop ? 'lp-btn-primary lp-shine' : ($isFree ? 'lp-btn-green' : 'lp-btn-dark') ?> w-100" data-plan-link="<?= $e($p['key']) ?>"><?= $isFree ? 'Start free' : 'Choose ' . $e($p['name']) ?></a>
                            <ul class="lp-plan-list">
                                <li class="lp-strong"><?= lp_icon('users', 16) ?> <?= $p['limit'] ? 'Up to ' . (int) $p['limit'] . ' students' : 'Unlimited students' ?></li>
                                <li class="lp-strong"><?= lp_icon('plus', 16) ?> <?= $isFree ? 'No card, no invoices' : ($p['pack_price'] ? '+LKR ' . $lkr($p['pack_price']) . ' per ' . (int) $p['pack_size'] . ' more students' : 'Custom price above ' . (int) $p['limit']) ?></li>
                                <?php if ($p['everything_in']) { ?><li class="lp-strong"><?= lp_icon('sparkle', 16) ?> Everything in <?= $e($p['everything_in']) ?>, plus:</li><?php } ?>
                                <?php foreach (array_slice($p['new_features'], 0, $p['everything_in'] ? 7 : 6) as $f) { echo '<li>' . lp_icon('check', 16) . ' ' . $e($f) . '</li>'; } ?>
                            </ul>
                        </article>
                    <?php } ?>
                </div>

                <details class="lp-compare" data-reveal>
                    <summary><span>Compare every feature</span> <?= lp_icon('chev', 18) ?></summary>
                    <div class="lp-compare-scroll">
                        <table>
                            <thead><tr><th scope="col">Feature</th><?php foreach ($info['plans'] as $p) { echo '<th scope="col">' . $e($p['name']) . '</th>'; } ?></tr></thead>
                            <tbody>
                                <?php foreach ($info['comparison'] as $cat) { if ($cat['category'] === 'Team') { continue; } ?>
                                    <tr class="lp-cat"><th colspan="<?= count($info['plans']) + 1 ?>" scope="colgroup"><?= $e($cat['category']) ?></th></tr>
                                    <?php foreach ($cat['rows'] as $row) { ?>
                                        <tr><th scope="row"><?= $e($row['name']) ?></th>
                                            <?php foreach ($info['plans'] as $p) {
                                                if ($row['addon']) { echo '<td><span class="lp-addon-chip">Add-on</span></td>'; }
                                                elseif (!empty($row['plans'][$p['same_as'] ?? $p['key']])) { echo '<td><span class="lp-yes" aria-label="Included">' . lp_icon('check', 18) . '</span></td>'; }
                                                else { echo '<td><span class="lp-no" aria-label="Not included">–</span></td>'; }
                                            } ?>
                                        </tr>
                                <?php } } ?>
                            </tbody>
                        </table>
                    </div>
                </details>

                <div class="lp-addons">
                    <div class="lp-addons-head" data-reveal>
                        <h3>Add-ons for every plan</h3>
                        <p>Use your own Zoom, gateway and WhatsApp, or let ENVIIZ run them for you.</p>
                    </div>
                    <div class="lp-addon-grid">
                        <?php foreach ($info['addons'] as $a) { ?>
                            <div class="lp-addon" data-reveal><span class="lp-dot lp-blue"><?= lp_icon($addonIcon[$a['key']] ?? 'plus', 18) ?></span><span><b><?= $e($a['name']) ?></b><small><?= $e($a['note']) ?></small></span></div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============ FAQ ============ -->
        <section class="lp-sec" id="faq">
            <div class="lp-wrap lp-faq-wrap">
                <div class="lp-head lp-head-left" data-reveal>
                    <span class="lp-eyebrow">FAQ</span>
                    <h2 class="lp-h2">Questions tutors <span class="lp-grad">ask us.</span></h2>
                    <p class="lp-sub">Something else? Send your request and our team will call you.</p>
                </div>
                <div class="lp-faq">
                    <?php $faq = [
                        ['Is it really free up to ' . $freeLimit . ' students?', 'Yes. If you teach ' . $freeLimit . ' students or fewer, you use ENLIXO Tutor with the START features and pay nothing. No card is needed. When your class grows past ' . $freeLimit . ', you pick a paid plan and everything you added stays.'],
                        ['Do students and parents get their own login?', 'Yes. Students sign in to the Student Portal and parents to the Parent Portal, each with their own password. A parent with more than one child sees all of them in one login. You make the logins from your dashboard.'],
                        ['How does the free trial work?', 'You get every ' . $trial['plan'] . ' feature free for ' . $trial['days'] . ' days, for up to ' . $trial['students'] . ' students. No card is needed. Before it ends you pick the plan that suits you.'],
                        ['Do my students need to install an app?', 'No. Students and parents sign in from any phone or computer browser. Their pages are in Sinhala, Tamil or English.'],
                        ['How do students pay their class fees?', 'They upload a bank slip, or pay by card if you connect your own payment gateway or use the ENVIIZ Payment Gateway. You approve slips in one tap.'],
                        ['Can I use my own Zoom account?', 'Yes. Every plan connects your Zoom, and meetings are made for your class times. You can also use an ENVIIZ Zoom account as an add-on.'],
                        ['What happens when I have more students than my plan?', 'Add packs of 50 students to START or GROW any time, or move to a bigger plan. Your data stays as it is.'],
                        ['Is my data safe?', 'Each tutor\'s classes, students and payments are kept apart, recordings can be locked to paid students, and you choose what your staff can see.'],
                    ];
                    foreach ($faq as $i => [$q, $a]) { ?>
                        <details class="lp-q" data-reveal <?= $i === 0 ? 'open' : '' ?>>
                            <summary><?= $e($q) ?><span class="lp-q-ic" aria-hidden="true"><?= lp_icon('plus', 18) ?></span></summary>
                            <p><?= $e($a) ?></p>
                        </details>
                    <?php } ?>
                </div>
            </div>
        </section>

    </main>

    <footer class="lp-foot">
        <div class="lp-foot-glow" aria-hidden="true"></div>
        <div class="lp-wrap">
            <div class="lp-foot-cta" data-reveal>
                <div>
                    <span class="lp-foot-badge"><span class="lp-announce-tag">FREE</span> Up to <?= $freeLimit ?> students</span>
                    <h3>Bring your class online this week.</h3>
                    <p>Send your request in two minutes. Our team calls you and sets everything up.</p>
                </div>
                <div class="lp-foot-cta-btns">
                    <a href="<?= $signup ?>" class="lp-btn lp-btn-primary lp-btn-lg lp-shine">Start free today <?= lp_icon('arrow', 18) ?></a>
                    <a href="#pricing" class="lp-btn lp-btn-glass lp-btn-lg">See pricing</a>
                </div>
            </div>

            <div class="lp-foot-in">
                <div class="lp-foot-brand">
                    <img src="assets/imgs/common/enlixo-light.webp" alt="<?= $e($name) ?>">
                    <p>The all-in-one app for tutors in Sri Lanka. Students, fees, attendance, Zoom classes, recordings, tutes and parents, in one place.</p>
                    <div class="lp-foot-langs" aria-label="Languages">
                        <a href="#languages" lang="si">සිංහල</a><a href="#languages" lang="ta">தமிழ்</a><a href="#languages">English</a>
                    </div>
                    <div class="lp-foot-trust">
                        <span><?= lp_icon('lock', 16) ?> Each tutor's data kept apart</span>
                        <span><?= lp_icon('phone', 16) ?> Works on any phone</span>
                    </div>
                </div>
                <nav class="lp-foot-col" aria-label="Product"><b>Product</b>
                    <a href="#free">Free plan</a><a href="#features">Features</a><a href="#languages">Languages</a><a href="#pricing">Pricing</a><a href="#how">How it works</a><a href="#faq">FAQ</a>
                </nav>
                <nav class="lp-foot-col" aria-label="Sign in"><b>Sign in</b>
                    <a href="tutor/"><span class="lp-fdot lp-fdot-b"></span>Tutor Dashboard</a>
                    <a href="portal/"><span class="lp-fdot lp-fdot-g"></span>Student Portal</a>
                    <a href="parent/"><span class="lp-fdot lp-fdot-v"></span>Parent Portal</a>
                    <a href="admin/"><span class="lp-fdot"></span>ENLIXO team</a>
                </nav>
                <nav class="lp-foot-col" aria-label="Get started"><b>Get started</b>
                    <a href="<?= $signup ?>?plan=free">Start free</a>
                    <a href="<?= $signup ?>?plan=grow"><?= (int) $trial['days'] ?>-day trial for bigger classes</a>
                    <a href="#logins">Student &amp; parent logins</a>
                    <a href="https://enviiz.com" target="_blank" rel="noopener">About ENVIIZ</a>
                </nav>
            </div>

            <div class="lp-foot-word" aria-hidden="true">ENLIXO</div>

            <div class="lp-foot-bottom">
                <span>&copy; <?= date('Y') ?> <?= $e($name) ?>. All rights reserved. Made in Sri Lanka.</span>
                <div class="lp-foot-bottom-r">
                    <a href="https://enviiz.com" target="_blank" rel="noopener" class="lp-powered">Powered by <img src="assets/imgs/common/enviiz-white.webp" alt="ENVIIZ Softwares"></a>
                    <a href="#top" class="lp-totop" aria-label="Back to the top"><?= lp_icon('arrow', 18) ?></a>
                </div>
            </div>
        </div>
    </footer>

    <script src="assets/landing/landing.js?v=<?= $v ?>" defer></script>
</body>

</html>
