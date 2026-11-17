<?php
/**
 * Plan limits for the older tutor/api/*.php endpoints (the new API uses EntitlementService::requireWithin()).
 *   if ($msg = enlixo_plan_block($tutorId, 'classes')) { $status->error = $msg; echo json_encode($status); exit; }
 * Returns the message to show when the tutor's plan does not allow one more, or null. When the new code is not
 * configured (Bridge off) nothing is blocked.
 */
if (!function_exists('enlixo_plan_block')) {
    function enlixo_plan_block(int $tutorId, string $limitKey, ?string $featureKey = null): ?string
    {
        require_once dirname(__DIR__, 2) . '/src/Legacy/Bridge.php';
        $app = \Enlixo\Legacy\Bridge::app();
        if ($app === null || $tutorId <= 0) {
            return null;
        }
        try {
            if ($featureKey !== null) {
                $app->entitlements()->require($tutorId, $featureKey);
            }
            $app->entitlements()->requireWithin($tutorId, $limitKey);
            return null;
        } catch (\Enlixo\Core\HttpException $e) {
            return $e->getMessage() . ' Open My Subscription to upgrade.';
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Echo the JSON error of a legacy endpoint and stop, when the plan blocks it. */
    function enlixo_plan_guard(object $status, int $tutorId, string $limitKey, ?string $featureKey = null): void
    {
        if ($msg = enlixo_plan_block($tutorId, $limitKey, $featureKey)) {
            $status->error = $msg;
            echo json_encode($status);
            exit;
        }
    }

    /** Does this tutor's plan have the feature? true when the new code is not configured (Bridge off). */
    function enlixo_tutor_has(int $tutorId, string $featureKey): bool
    {
        static $cache = [];
        $key = $tutorId . ':' . $featureKey;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        require_once dirname(__DIR__, 2) . '/src/Legacy/Bridge.php';
        $app = \Enlixo\Legacy\Bridge::app();
        if ($app === null || $tutorId <= 0) {
            return $cache[$key] = true;
        }
        try {
            return $cache[$key] = $app->entitlements()->has($tutorId, $featureKey);
        } catch (\Throwable $e) {
            return $cache[$key] = true;
        }
    }

    /** Older tutor pages of a feature the plan does not have: open the Upgrade page for it instead. */
    function enlixo_plan_page(int $tutorId, string $featureKey): void
    {
        if (!enlixo_tutor_has($tutorId, $featureKey)) {
            header('Location: ./upgrade?feature=' . rawurlencode($featureKey));
            exit;
        }
    }

    /** Older tutor/api endpoints of a feature the plan does not have: the JSON error and stop. */
    function enlixo_feature_guard(object $status, int $tutorId, string $featureKey): void
    {
        if (!enlixo_tutor_has($tutorId, $featureKey)) {
            $name = \Enlixo\Catalog\FeatureCatalog::features()[$featureKey]['name'] ?? 'This feature';
            $status->error = $name . ' are not in your plan (GROW and CUSTOM have them). Open My Subscription to upgrade.';
            echo json_encode($status);
            exit;
        }
    }
}
