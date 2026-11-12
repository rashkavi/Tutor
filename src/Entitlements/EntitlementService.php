<?php
declare(strict_types=1);

namespace Enlixo\Entitlements;

final class EntitlementService
{
    /** @var array<int, Entitlements> per-request cache */
    private array $cache = [];

    public function __construct(private readonly EntitlementRepository $repo)
    {
    }

    public function for(int $tutorId): Entitlements
    {
        if (!isset($this->cache[$tutorId])) {
            $plan = $this->repo->activePlanKey($tutorId);
            $features = array_values(array_unique([
                ...$this->repo->planFeatureKeys($plan),
                ...$this->repo->activeAddonFeatureKeys($tutorId),
            ]));
            $this->cache[$tutorId] = new Entitlements(
                $plan,
                $features,
                $this->repo->tutorStudentLimit($tutorId, $plan),
                $this->repo->activeStudentCount($tutorId),
            );
        }
        return $this->cache[$tutorId];
    }

    public function has(int $tutorId, string $featureKey): bool
    {
        return $this->for($tutorId)->has($featureKey);
    }

    /** Guard for controllers/services. @throws FeatureLockedException */
    public function require(int $tutorId, string $featureKey): void
    {
        if (!$this->has($tutorId, $featureKey)) {
            throw new FeatureLockedException($featureKey);
        }
    }
}
