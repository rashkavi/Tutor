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

if (!function_exists('enlixo_lock_badge')) {
    /**
     * Lock badge for a legacy PHP page, with the same hover card as FeatureGate.badge() (plan, what it does, Upgrade).
     * Empty string when the tutor has the feature. Needs assets/css/enlixo-ui.css on the page.
     */
    function enlixo_lock_badge(int $tutorId, string $featureKey): string
    {
        if (enlixo_tutor_has($tutorId, $featureKey)) {
            return '';
        }
        $f = \Enlixo\Catalog\FeatureCatalog::features()[$featureKey] ?? null;
        $planKey = $f ? (array_values(array_intersect(['free', 'start', 'grow', 'pro'], $f['plans']))[0] ?? 'grow') : 'grow';
        $plan = \Enlixo\Catalog\FeatureCatalog::PLANS[$planKey]['name'] ?? 'STARTER';
        $e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $lock = '<svg viewBox="0 0 24 24" width="11" height="11" fill="currentColor" aria-hidden="true"><path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5Zm-3 8V7a3 3 0 0 1 6 0v3H9Z"/></svg>';
        $url = 'upgrade?feature=' . rawurlencode($featureKey);
        return '<span class="ex-lock-wrap"><span class="ex-lock-badge" tabindex="0" aria-label="' . $e(($f['name'] ?? 'This feature') . ': available on ' . $plan) . '">' . $lock . $e($plan) . '</span>'
            . '<span class="ex-lock-tip" role="tooltip"><span class="ex-lock-tip-plan">Available on ' . $e($plan) . '</span>'
            . '<span class="ex-lock-tip-title d-block">' . $e($f['name'] ?? 'Premium feature') . '</span>'
            . '<span class="ex-lock-tip-text d-block">' . $e($f['description'] ?? '') . '</span>'
            . '<a class="custom-btn ex-lock-tip-btn d-inline-flex align-items-center justify-content-center text-decoration-none" href="' . $e($url) . '">Upgrade to ' . $e($plan) . '</a></span></span>';
    }
}
