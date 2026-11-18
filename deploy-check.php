<?php
// Deploy check (ENLIXO admin only): compares the files on this server with deploy-manifest.json (made from git by
// bin/make-deploy-manifest.php before an upload). Lists files that are missing, cut off (smaller than they should be)
// or a different version, so a broken upload is found in seconds instead of by a page that looks wrong.
// The page uses only inline styles, so it works even when the CSS files themselves are the broken ones.
require_once __DIR__ . '/includes/UserClass.php';
if (!SessionUser::ifUserIsAdmin()) {
    header('Location: ./admin/');
    exit;
}
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$manifest = is_file(__DIR__ . '/deploy-manifest.json') ? json_decode((string) file_get_contents(__DIR__ . '/deploy-manifest.json'), true) : null;
$rows = ['missing' => [], 'cut' => [], 'changed' => []];
$ok = 0;
if (is_array($manifest)) {
    foreach ($manifest['files'] as $path => [$size, $sha]) {
        $full = __DIR__ . '/' . $path;
        if (!is_file($full)) {
            $rows['missing'][] = [$path, $size, null];
            continue;
        }
        $have = filesize($full);
        if ($have < $size) {
            $rows['cut'][] = [$path, $size, $have];
        } elseif (sha1_file($full) !== $sha) {
            $rows['changed'][] = [$path, $size, $have];
        } else {
            $ok++;
        }
    }
}
$bad = count($rows['missing']) + count($rows['cut']) + count($rows['changed']);
$kb = static fn ($b) => $b === null ? '-' : number_format($b / 1024, 1) . ' KB';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Deploy Check - ENLIXO Tutor</title>
    <style>
        body { margin: 0; font: 14px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; background: #f6f8fc; color: #0f172a; }
        .w { max-width: 1100px; margin: 0 auto; padding: 24px 16px 60px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .sub { color: #5b6475; margin: 0 0 20px; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .card { background: #fff; padding: 16px; border: 1px solid #e6e9f2; }
        .card b { display: block; font-size: 26px; }
        .good b { color: #16a34a; } .bad b { color: #dc2626; } .warn b { color: #d97706; }
        .note { background: #fff; border-left: 4px solid #0061d2; padding: 12px 16px; margin-bottom: 20px; }
        .note.ok { border-color: #16a34a; } .note.bad { border-color: #dc2626; }
        h2 { font-size: 16px; margin: 24px 0 8px; }
        table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e6e9f2; }
        th, td { text-align: left; padding: 8px 12px; border-bottom: 1px solid #eef1f7; font-size: 13px; }
        th { background: #f1f4fa; font-weight: 600; }
        td.p { font-family: ui-monospace, Menlo, Consolas, monospace; word-break: break-all; }
        code { background: #eef2f9; padding: 1px 6px; }
    </style>
</head>
<body>
<div class="w">
    <h1>Deploy Check</h1>
    <p class="sub">Compares the files on this server with the release they should be<?= is_array($manifest) ? ' (commit <code>' . $e($manifest['commit']) . '</code>, made ' . $e(substr((string) $manifest['generated'], 0, 16)) . ' UTC)' : '' ?>.</p>

    <?php if (!is_array($manifest)) { ?>
        <div class="note bad"><b>deploy-manifest.json is missing.</b> Run <code>php bin/make-deploy-manifest.php</code> on your computer and upload <code>deploy-manifest.json</code> with the other files.</div>
    <?php } else { ?>
        <div class="cards">
            <div class="card good"><span>Files OK</span><b><?= $ok ?></b></div>
            <div class="card bad"><span>Missing</span><b><?= count($rows['missing']) ?></b></div>
            <div class="card bad"><span>Cut off (upload stopped)</span><b><?= count($rows['cut']) ?></b></div>
            <div class="card warn"><span>Different version</span><b><?= count($rows['changed']) ?></b></div>
        </div>
        <?php if ($bad === 0) { ?>
            <div class="note ok"><b>Every file is uploaded completely.</b> If a page still looks old, clear the host cache and press Ctrl + F5.</div>
        <?php } else { ?>
            <div class="note bad"><b>Upload these files again</b> (FTP in binary mode, wait until every file is done), then open this page again.
                "Different version" can also mean the file was changed on the server by hand.</div>
            <?php foreach (['missing' => 'Missing on the server', 'cut' => 'Cut off: smaller than they should be', 'changed' => 'Different version'] as $k => $title) {
                if (!$rows[$k]) {
                    continue;
                } ?>
                <h2><?= $e($title) ?> (<?= count($rows[$k]) ?>)</h2>
                <table>
                    <tr><th>File</th><th>Should be</th><th>On the server</th></tr>
                    <?php foreach ($rows[$k] as [$path, $size, $have]) { ?>
                        <tr><td class="p"><?= $e($path) ?></td><td><?= $kb($size) ?></td><td><?= $kb($have) ?></td></tr>
                    <?php } ?>
                </table>
            <?php }
        }
    } ?>
</div>
</body>
</html>
