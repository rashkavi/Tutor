<?php
declare(strict_types=1);

namespace Enlixo\Entitlements;

use Enlixo\Catalog\FeatureCatalog;

/** What one tutor's package (plan + add-ons) allows. Immutable. */
final class Entitlements
{
    /** @param list<string> $featureKeys */
    public function __construct(
        public readonly string $planKey,
        private readonly array $featureKeys,
        public readonly ?int $studentLimit,
        public readonly int $studentCount,
    ) {
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
        return [
            'plan' => ['key' => $this->planKey] + (FeatureCatalog::PLANS[$this->planKey] ?? []),
            'plans' => FeatureCatalog::PLANS,
            'students' => ['count' => $this->studentCount, 'limit' => $this->studentLimit],
            'features' => $features,
        ];
    }
}
