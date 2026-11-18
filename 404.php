<?php
// Page not found. Apache sends every missing address here (.htaccess ErrorDocument 404); bin/dev-router.php does the
// same locally. Inline styles only, so it looks right even when a CSS file is missing. Links are absolute (/...) because
// the missing address can be in any folder.
http_response_code(404);
$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
// a link from inside one of the apps goes back to that app's start page
$area = preg_match('~^/(tutor|portal|parent|admin)(/|$)~', $path, $m) ? $m[1] : '';
$home = ['tutor' => ['/tutor/dashboard', 'Tutor dashboard'], 'portal' => ['/portal/dashboard', 'Student portal'],
    'parent' => ['/parent/dashboard', 'Parent portal'], 'admin' => ['/admin/dashboard', 'Admin dashboard']][$area] ?? ['/', 'ENLIXO Tutor home'];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Page not found - ENLIXO Tutor</title>
    <link rel="icon" href="/favicon.ico">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px;
            font: 15px/1.6 Inter, system-ui, -apple-system, "Segoe UI", "Noto Sans Sinhala", "Noto Sans Tamil", sans-serif;
            color: #fff; background: radial-gradient(1200px 600px at 80% -10%, #0b3a8a 0%, transparent 60%), radial-gradient(900px 500px at -10% 110%, #0b5d3b 0%, transparent 55%), #050b1f; }
        .box { width: 100%; max-width: 560px; text-align: center; }
        .logo { height: 34px; width: auto; margin-bottom: 40px; }
        .code { font-size: clamp(88px, 22vw, 150px); font-weight: 800; line-height: 1; letter-spacing: -.05em;
            background: linear-gradient(90deg, #2f7bff, #22d3ee); -webkit-background-clip: text; background-clip: text; color: transparent; }
        h1 { font-size: 24px; margin: 12px 0 8px; }
        p { margin: 0 0 6px; color: rgba(255, 255, 255, .75); }
        .path { display: inline-block; max-width: 100%; margin: 10px 0 28px; padding: 4px 10px; background: rgba(255, 255, 255, .08); color: rgba(255, 255, 255, .8);
            font: 13px ui-monospace, Menlo, Consolas, monospace; word-break: break-all; }
        .btns { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; }
        a.btn { display: inline-flex; align-items: center; justify-content: center; height: 46px; padding: 0 22px; text-decoration: none; font-weight: 600; border-radius: 4px; }
        a.main { background: #0061d2; color: #fff; } a.main:hover { background: #0b51b3; }
        a.alt { border: 1px solid rgba(255, 255, 255, .25); color: #fff; } a.alt:hover { background: rgba(255, 255, 255, .08); }
        .si { margin-top: 28px; font-size: 13px; color: rgba(255, 255, 255, .55); }
    </style>
</head>
<body>
    <main class="box">
        <img src="/assets/imgs/common/enlixo-light.webp" alt="ENLIXO Tutor" class="logo" onerror="this.remove()">
        <div class="code">404</div>
        <h1>This page is not here</h1>
        <p>The link may be old or typed wrong, or the page was moved.</p>
        <span class="path"><?= $e($path) ?></span>
        <div class="btns">
            <a class="btn main" href="<?= $e($home[0]) ?>"><?= $e('Go to ' . $home[1]) ?></a>
            <a class="btn alt" href="javascript:history.back()">Go back</a>
        </div>
        <p class="si">මෙම පිටුව සොයාගත නොහැක · இந்தப் பக்கம் கிடைக்கவில்லை</p>
    </main>
</body>
</html>
