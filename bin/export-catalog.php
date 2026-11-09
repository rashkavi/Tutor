<?php
declare(strict_types=1);

// Writes the catalogues to public/assets/mock/catalog.json so the demo pages use the same data as the backend.
use Enlixo\Catalog\FeatureCatalog;
use Enlixo\Catalog\PermissionCatalog;

require dirname(__DIR__) . '/src/bootstrap.php';

$out = ['plans' => FeatureCatalog::PLANS, 'features' => [], 'permissions' => []];
foreach (FeatureCatalog::features() as $key => $f) {
    $out['features'][] = ['key' => $key] + $f;
}
foreach (PermissionCatalog::permissions() as $key => $p) {
    $out['permissions'][] = ['key' => $key, 'name' => $p['name'], 'module' => $p['module'], 'feature' => $p['feature'] ?? null];
}
file_put_contents(dirname(__DIR__) . '/public/assets/mock/catalog.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
echo "Wrote public/assets/mock/catalog.json\n";
