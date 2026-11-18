<?php
declare(strict_types=1);
// Writes deploy-manifest.json: every file the live server needs (from git), with its size and SHA-1.
// After uploading, open /deploy-check (signed in as ENLIXO admin): it lists files that are missing, cut off or old.
// Run before every upload:   php bin/make-deploy-manifest.php
$root = dirname(__DIR__);
chdir($root);
exec('git ls-files -z', $out, $code);
if ($code !== 0) {
    fwrite(STDERR, "git ls-files failed\n");
    exit(1);
}
$skip = '~^(tests/|docs/|bin/|database/demo/|\.github/|md/|node_modules/)|(^|/)(CLAUDE|README)\.md$|^deploy-manifest\.json$~';
$files = [];
foreach (array_filter(explode("\0", implode("\0", $out))) as $path) {
    if (preg_match($skip, $path) || !is_file($path)) {
        continue;
    }
    $files[$path] = [filesize($path), sha1_file($path)];
}
ksort($files);
$commit = trim((string) shell_exec('git rev-parse --short HEAD'));
file_put_contents($root . '/deploy-manifest.json', json_encode(['generated' => gmdate('c'), 'commit' => $commit, 'files' => $files], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");
echo count($files) . " files written to deploy-manifest.json (commit $commit)\n";
