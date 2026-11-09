<?php
declare(strict_types=1);

namespace Enlixo\Entitlements;

use Enlixo\Catalog\FeatureCatalog;
use Enlixo\Core\HttpException;

/** HTTP 402: the frontend shows the upgrade modal when it sees error code `feature_locked`. */
final class FeatureLockedException extends HttpException
{
    public function __construct(string $featureKey)
    {
        $feature = FeatureCatalog::features()[$featureKey] ?? ['name' => $featureKey];
        $plan = FeatureCatalog::lowestPlanFor($featureKey);
        parent::__construct(402, 'feature_locked', sprintf('%s is not included in your package.', $feature['name']), [
            'feature' => $featureKey,
            'required_plan' => $plan,
            'addon' => $feature['addon'] ?? null,
        ]);
    }
}
