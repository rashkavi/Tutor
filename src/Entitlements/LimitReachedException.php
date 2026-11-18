<?php
declare(strict_types=1);

namespace Enlixo\Entitlements;

use Enlixo\Catalog\FeatureCatalog;
use Enlixo\Core\HttpException;

/** HTTP 402 `limit_reached`: the plan limit (FREE: 3 classes, 3 tutes a month ...) is used up. The page shows the upgrade page. */
final class LimitReachedException extends HttpException
{
    public function __construct(string $limitKey, string $planKey, int $limit, int $used)
    {
        $l = FeatureCatalog::limits()[$limitKey] ?? ['name' => $limitKey, 'unit' => 'items', 'period' => 'total'];
        $next = FeatureCatalog::lowestPlanForLimit($limitKey, $limit + 1);
        $plan = FeatureCatalog::PLANS[$planKey]['name'] ?? strtoupper($planKey);
        $nextName = $next === null ? null : (FeatureCatalog::PLANS[$next]['name'] ?? strtoupper($next));
        $nextLimit = $next === null ? null : FeatureCatalog::limitFor($limitKey, $next);
        $message = sprintf(
            'You have used %d of the %d %s%s on %s.%s',
            $used, $limit, $l['unit'], $l['period'] === 'month' ? ' this month' : '', $plan,
            $nextName === null ? '' : sprintf(' %s gives you %s.', $nextName, $nextLimit === null ? 'unlimited ' . $l['unit'] : $nextLimit . ' ' . $l['unit'] . ($l['period'] === 'month' ? ' a month' : '')),
        );
        parent::__construct(402, 'limit_reached', $message, [
            'limit' => $limitKey, 'used' => $used, 'max' => $limit, 'plan' => $planKey, 'required_plan' => $next,
        ]);
    }
}
