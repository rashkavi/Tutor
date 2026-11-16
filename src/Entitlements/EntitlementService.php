<?php
declare(strict_types=1);

namespace Enlixo\Entitlements;

use Enlixo\Catalog\FeatureCatalog;

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
            $base = $this->repo->activePlanKey($tutorId);
            // "Try the next plan for 7 days": the preview plan's features and limits while it runs
            $preview = $this->repo->preview($tutorId);
            $plan = $preview['plan'] ?? $base;
            $features = array_values(array_unique([
                ...$this->repo->planFeatureKeys($plan),
                ...$this->repo->activeAddonFeatureKeys($tutorId),
            ]));
            $limits = [];
            foreach (array_keys(FeatureCatalog::limits()) as $k) {
                $limits[$k] = FeatureCatalog::limitFor($k, $plan);
            }
            $this->cache[$tutorId] = new Entitlements(
                $plan,
                $features,
                $preview === null ? $this->repo->tutorStudentLimit($tutorId, $plan) : max((int) $this->repo->tutorStudentLimit($tutorId, $base), (int) $this->repo->planStudentLimit($plan)),
                $this->repo->activeStudentCount($tutorId),
                $limits,
                $this->repo->usage($tutorId),
                $base,
                $preview === null ? null : (string) $preview['until'],
                $this->repo->previewUsed($tutorId),
            );
        }
        return $this->cache[$tutorId];
    }

    public function has(int $tutorId, string $featureKey): bool
    {
        return $this->for($tutorId)->has($featureKey);
    }

    /**
     * Guard for a plan limit: adding $adding more must stay within it. @throws LimitReachedException
     * e.g. requireWithin($tutor, 'tutes.month') before a tute is saved.
     */
    public function requireWithin(int $tutorId, string $limitKey, int $adding = 1): void
    {
        $e = $this->for($tutorId);
        if (!$e->within($limitKey, $adding)) {
            throw new LimitReachedException($limitKey, $e->planKey, (int) $e->limit($limitKey), $e->used($limitKey));
        }
    }

    /** Forget the cached package (after a plan change in the same request). */
    public function forget(int $tutorId): void
    {
        unset($this->cache[$tutorId]);
    }

    /** Guard for controllers/services. @throws FeatureLockedException */
    public function require(int $tutorId, string $featureKey): void
    {
        if (!$this->has($tutorId, $featureKey)) {
            throw new FeatureLockedException($featureKey);
        }
    }
}
