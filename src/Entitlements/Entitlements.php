<?php
declare(strict_types=1);

namespace Enlixo\Entitlements;

use Enlixo\Catalog\FeatureCatalog;

/** What one tutor's package (plan + add-ons) allows. Immutable. */
final class Entitlements
{
    /**
     * @param list<string> $featureKeys
     * @param array<string, ?int> $limits limit key => allowed (null = unlimited), FeatureCatalog::limits()
     * @param array<string, int> $usage limit key => used
     */
    public function __construct(
        public readonly string $planKey,
        private readonly array $featureKeys,
        public readonly ?int $studentLimit,
        public readonly int $studentCount,
        private readonly array $limits = [],
        private readonly array $usage = [],
        public readonly ?string $basePlanKey = null,
        public readonly ?string $previewUntil = null,
        public readonly bool $previewUsed = false,
    ) {
    }

    public function limit(string $limitKey): ?int
    {
        return $this->limits[$limitKey] ?? null;
    }

    public function used(string $limitKey): int
    {
        return (int) ($this->usage[$limitKey] ?? 0);
    }

    /** Can $adding more be added without going over the plan limit? */
    public function within(string $limitKey, int $adding = 1): bool
    {
        $limit = $this->limit($limitKey);
        return $limit === null || $this->used($limitKey) + $adding <= $limit;
    }

    /** The plan the tutor pays for (a running preview is not their plan). */
    public function paidPlanKey(): string
    {
        return $this->basePlanKey ?? $this->planKey;
    }

    public function has(string $featureKey): bool
    {
        return in_array($featureKey, $this->featureKeys, true);
    }

    /** @return list<string> */
    public function featureKeys(): array
    {
        return $this->featureKeys;
    }

    public function canAddStudents(int $howMany = 1): bool
    {
        return $this->studentLimit === null || $this->studentCount + $howMany <= $this->studentLimit;
    }

    /** Full catalogue with locked/unlocked state: what the UI needs to draw lock badges. */
    public function toArray(): array
    {
        $features = [];
        foreach (FeatureCatalog::features() as $key => $f) {
            $unlocked = $this->has($key);
            $features[] = [
                'key' => $key,
                'name' => $f['name'],
                'category' => $f['category'],
                'description' => $f['description'],
                'unlocked' => $unlocked,
                'required_plan' => $unlocked ? null : FeatureCatalog::lowestPlanFor($key),
                'addon' => $f['addon'] ?? null,
                'plans' => $f['plans'],
            ];
        }
        $limits = [];
        foreach (FeatureCatalog::limits() as $key => $l) {
            $limit = $this->limit($key);
            $used = $this->used($key);
            $limits[] = [
                'key' => $key, 'name' => $l['name'], 'unit' => $l['unit'], 'period' => $l['period'], 'help' => $l['help'],
                'limit' => $limit, 'used' => $used,
                'percent' => $limit === null || $limit === 0 ? null : min(100, (int) round($used / $limit * 100)),
                'next' => $limit === null ? null : FeatureCatalog::lowestPlanForLimit($key, $limit + 1),
                'plans' => $l['plans'], // the limit on every plan, for the upgrade page
            ];
        }
        $paid = $this->paidPlanKey();
        $next = FeatureCatalog::nextPlan($paid);
        $all = FeatureCatalog::features();
        return [
            'plan' => ['key' => $this->planKey] + (FeatureCatalog::PLANS[$this->planKey] ?? []),
            'paid_plan' => ['key' => $paid] + (FeatureCatalog::PLANS[$paid] ?? []),
            'preview' => $this->previewUntil === null ? null : ['plan' => $this->planKey, 'until' => $this->previewUntil],
            'preview_used' => $this->previewUsed,
            'next_plan' => $next === null ? null : ['key' => $next] + FeatureCatalog::PLANS[$next] + [
                // what the next plan adds over the plan the tutor pays for
                'adds' => array_values(array_map(static fn ($k) => $all[$k]['name'], array_filter(
                    FeatureCatalog::featuresForPlan($next),
                    static fn ($k) => !in_array($paid, $all[$k]['plans'], true),
                ))),
            ],
            'plans' => FeatureCatalog::PLANS,
            'students' => ['count' => $this->studentCount, 'limit' => $this->studentLimit],
            'limits' => $limits,
            'features' => $features,
        ];
    }
}
