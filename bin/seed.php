<?php
declare(strict_types=1);

// Writes FeatureCatalog + PermissionCatalog into the database. Safe to run again after editing the catalogues.
// Usage: php bin/seed.php

use Enlixo\App;
use Enlixo\Catalog\FeatureCatalog;
use Enlixo\Catalog\PermissionCatalog;

$config = require dirname(__DIR__) . '/src/bootstrap.php';
$db = (new App($config))->db();

$db->transaction(static function () use ($db) {
    $order = 0;
    foreach (FeatureCatalog::features() as $key => $f) {
        $db->execute(
            'INSERT INTO features (feature_key, name, category, description, sort_order) VALUES (:k, :n, :c, :d, :o)
             ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category), description = VALUES(description), sort_order = VALUES(sort_order)',
            ['k' => $key, 'n' => $f['name'], 'c' => $f['category'], 'd' => $f['description'], 'o' => ++$order],
        );
        $featureId = (int) $db->value('SELECT feature_id FROM features WHERE feature_key = :k', ['k' => $key]);

        $db->execute('DELETE FROM payment_plan_features WHERE features_feature_id = :f', ['f' => $featureId]);
        foreach ($f['plans'] as $planKey) {
            $db->execute(
                'INSERT INTO payment_plan_features (payment_plans_payment_plan_id, features_feature_id) SELECT payment_plan_id, :f FROM payment_plans WHERE plan_key = :p',
                ['f' => $featureId, 'p' => $planKey],
            );
        }
        if (isset($f['addon'])) {
            $db->execute(
                'INSERT INTO addons (addon_key, name, pricing_note, features_feature_id) VALUES (:k, :n, :p, :f)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), pricing_note = VALUES(pricing_note), features_feature_id = VALUES(features_feature_id)',
                ['k' => $f['addon']['key'], 'n' => $f['addon']['name'], 'p' => $f['addon']['pricing'], 'f' => $featureId],
            );
        }
    }

    $order = 0;
    foreach (PermissionCatalog::permissions() as $key => $p) {
        $db->execute(
            'INSERT INTO permissions (permission_key, name, module, requires_feature_key, sort_order) VALUES (:k, :n, :m, :f, :o)
             ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), requires_feature_key = VALUES(requires_feature_key), sort_order = VALUES(sort_order)',
            ['k' => $key, 'n' => $p['name'], 'm' => $p['module'], 'f' => $p['feature'] ?? null, 'o' => ++$order],
        );
    }
});

echo "Seeded features, plan features, add-ons and permissions.\n";
