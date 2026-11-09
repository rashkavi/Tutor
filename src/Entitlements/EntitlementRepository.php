<?php
declare(strict_types=1);

namespace Enlixo\Entitlements;

interface EntitlementRepository
{
    /** Plan key ('start' | 'grow' | 'pro') of the tutor's current package. */
    public function activePlanKey(int $tutorId): string;

    /** @return list<string> feature keys included in the plan */
    public function planFeatureKeys(string $planKey): array;

    public function planStudentLimit(string $planKey): ?int;

    /** @return list<string> feature keys unlocked by active add-ons */
    public function activeAddonFeatureKeys(int $tutorId): array;

    public function activeStudentCount(int $tutorId): int;
}
