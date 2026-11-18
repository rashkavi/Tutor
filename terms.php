<?php
// Terms and Conditions of ENLIXO Tutor (linked from the sign-up form, the landing page footer and the offer page).
// Plain HTML with its own small styles, so it reads well even when other CSS is missing. Contact details: Project.php.
require_once __DIR__ . '/includes/Project.php';
$name = Project::getBaseName();
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$updated = '18 November 2026';
$phone = '076 967 9778';
$sections = [
    ['About these terms', [
        "These terms are the agreement between you (the tutor, institute or assistant who uses {$name}) and ENVIIZ (Pvt) Ltd, which runs {$name}. By sending a sign-up request or using the system you accept them.",
        'If you use the system for an institute, you confirm that you may accept these terms for it.',
    ]],
    ['Your account', [
        'You give us correct details (name, e-mail and mobile number) and keep them up to date. Your sign-in details are personal: keep your password safe and tell us at once if someone else may know it.',
        'You are responsible for what happens in your account, including what your assistants (staff logins) do.',
        'We make your account after we check your request. We may refuse a request, for example when the details are not correct.',
    ]],
    ['Plans, payments and slips', [
        'FREE is free for up to 50 students with the limits shown on the pricing page. STARTER and GROW are paid monthly or yearly. CUSTOM is a price we agree with you in writing.',
        'You pay by bank transfer or deposit to the ENVIIZ account shown in the system and upload the slip. A plan or renewal starts when we have checked the slip, normally within one working day.',
        'Prices are in Sri Lankan Rupees. Offers and discount codes apply only as described with them and cannot be exchanged for money.',
        'If an invoice is not paid by its due date, the account can move to FREE (when your students fit) or be paused for students and parents until it is paid. Your data is kept.',
        'Payments are not refunded for a period that has started, except where the law requires it or we agree in writing. If you pay by mistake, contact us within 7 days.',
        'You can move to a bigger plan at any time and pay the difference; a smaller plan starts at your next renewal.',
    ]],
    ['Free trials of a bigger plan', [
        'You can ask for one trial of a bigger plan. It starts only after our team approves it, runs for the days shown, and ends by itself. Nothing is deleted when it ends.',
    ]],
    ['Your students\' data', [
        'You own the data you put into the system (students, parents, fees, attendance, tutes and recordings). We process it only to run the service for you.',
        'You confirm that you may collect and use your students\' and parents\' details, and that you tell them how you use them. For students under 18, a parent or guardian should know about it.',
        'We keep the data safe with reasonable measures, do not sell it, and share it only with services needed to run the system (for example e-mail, WhatsApp, Zoom or video hosting you connect), or when the law requires it.',
        'When you close your account you can ask for a copy of your data within 30 days. After that we may delete it, except what we must keep by law (for example invoices).',
    ]],
    ['Recordings, tutes and other content', [
        'You are responsible for the content you upload or link (videos, tutes, papers, messages) and confirm you have the right to share it. Do not upload content that is illegal, offensive or belongs to others without permission.',
        'Watch limits and expiry dates help protect recordings, but no system can stop every copy. We are not responsible for content copied by users.',
    ]],
    ['Messages to students and parents', [
        'E-mail and WhatsApp messages are sent in your name. Send only messages about your classes, follow the rules of WhatsApp and do not send spam. Monthly message limits depend on your plan.',
    ]],
    ['Connected services', [
        'Zoom, WhatsApp, payment gateways and video hosting are run by other companies under their own terms. If one of them is unavailable or changes its rules, some features may not work for a time.',
    ]],
    ['Fair use', [
        'Do not try to break into the system, read data that is not yours, overload it or copy the system itself. We may suspend an account that does this or that is used against the law.',
    ]],
    ['Availability and support', [
        'We work to keep the system available and backed up, but there may be short breaks for maintenance or problems outside our control. We tell you about planned breaks when we can.',
        'Support is by phone, WhatsApp and e-mail on working days.',
    ]],
    ['Our responsibility', [
        'The system is given "as it is". To the extent the law allows, we are not responsible for indirect losses (for example lost income) and our total responsibility is limited to the fees you paid in the last three months.',
    ]],
    ['Closing an account', [
        'You can stop using the system at any time by telling us. We may close an account that breaks these terms, after telling you why when we can.',
    ]],
    ['Changes to these terms', [
        'We may update these terms. The date at the top shows the latest version. For important changes we tell you in the system or by e-mail before they apply.',
    ]],
    ['Law and contact', [
        'These terms follow the laws of Sri Lanka.',
        "Questions: ENVIIZ (Pvt) Ltd, phone / WhatsApp {$phone}.",
    ]],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms and Conditions - <?= $e($name) ?></title>
    <meta name="description" content="Terms and Conditions of <?= $e($name) ?>: accounts, plans and payments, students' data, content and support.">
    <link rel="icon" href="favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font: 16px/1.7 Inter, system-ui, -apple-system, "Segoe UI", sans-serif; color: #0f172a; background: #f6f8fc; }
        a { color: #0061d2; }
        .top { background: #050b1f; color: #fff; }
        .top-in, .wrap { max-width: 880px; margin: 0 auto; padding: 0 20px; }
        .top-in { display: flex; align-items: center; justify-content: space-between; height: 68px; }
        .top img { height: 28px; width: auto; display: block; }
        .top a.btn { color: #fff; text-decoration: none; font-weight: 600; font-size: 14px; padding: 9px 16px; border: 1px solid rgba(255, 255, 255, .25); border-radius: 4px; }
        .hero { background: #050b1f; color: #fff; padding: 40px 0 56px; }
        .hero h1 { margin: 0; font-size: clamp(30px, 5vw, 44px); letter-spacing: -.03em; line-height: 1.1; }
        .hero p { margin: 10px 0 0; color: rgba(255, 255, 255, .7); }
        .card { background: #fff; margin: -28px auto 48px; padding: 32px clamp(20px, 4vw, 44px); border: 1px solid #e6e9f2; border-radius: 4px; }
        .toc { columns: 2; gap: 24px; padding: 0 0 24px; margin: 0 0 8px; border-bottom: 1px solid #e6e9f2; list-style: none; font-size: 14px; }
        .toc li { margin: 0 0 6px; break-inside: avoid; }
        .toc a { text-decoration: none; }
        h2 { font-size: 19px; margin: 30px 0 8px; letter-spacing: -.01em; scroll-margin-top: 16px; }
        h2 span { color: #0061d2; margin-right: 6px; }
        p.t { margin: 0 0 10px; color: #334155; }
        .foot { text-align: center; font-size: 13px; color: #5b6475; padding: 0 0 40px; }
        @media (max-width: 600px) { .toc { columns: 1; } }
    </style>
</head>
<body>
    <header class="top">
        <div class="top-in">
            <a href="./"><img src="assets/imgs/common/enlixo-light.webp" alt="<?= $e($name) ?>"></a>
            <a class="btn" href="tutor-registration-application">Start free</a>
        </div>
    </header>
    <section class="hero">
        <div class="wrap">
            <h1>Terms and Conditions</h1>
            <p>Last updated <?= $e($updated) ?>. Please read them before you sign up.</p>
        </div>
    </section>
    <main class="wrap">
        <article class="card">
            <ol class="toc">
                <?php foreach ($sections as $i => [$title]) { ?><li><a href="#t<?= $i + 1 ?>"><?= $i + 1 ?>. <?= $e($title) ?></a></li><?php } ?>
            </ol>
            <?php foreach ($sections as $i => [$title, $paras]) { ?>
                <h2 id="t<?= $i + 1 ?>"><span><?= $i + 1 ?>.</span><?= $e($title) ?></h2>
                <?php foreach ($paras as $para) { ?><p class="t"><?= $e($para) ?></p><?php } ?>
            <?php } ?>
        </article>
    </main>
    <p class="foot">&copy; <?= date('Y') ?> <?= $e($name) ?> · ENVIIZ (Pvt) Ltd · <a href="./">Home</a></p>
</body>
</html>
