<?php
declare(strict_types=1);

namespace Enlixo\Entitlements;

use Enlixo\Core\Database;

final class PdoEntitlementRepository implements EntitlementRepository
{
    private const PAYMENT_ACCEPTED = 3;
    private const STATUS_ACTIVE = 1;

    public function __construct(private readonly Database $db)
    {
    }

    public function activePlanKey(int $tutorId): string
    {
        // Prefer the plan with an accepted payment covering today; otherwise the latest assigned plan.
        $key = $this->db->value(
            'SELECT pp.plan_key
               FROM users_has_payment_plans uhp
               JOIN payment_plans pp ON pp.payment_plan_id = uhp.payment_plans_payment_plan_id
               JOIN users_has_payment_plans_payments pay ON pay.users_has_payment_plans_users_has_payment_plan_id = uhp.users_has_payment_plan_id
              WHERE uhp.users_user_id = :tutor AND pay.payment_status_payment_status_id = :accepted
                AND NOW() BETWEEN pay.`from` AND pay.`to`
              ORDER BY pp.sort_order DESC LIMIT 1',
            ['tutor' => $tutorId, 'accepted' => self::PAYMENT_ACCEPTED],
        ) ?? $this->db->value(
            'SELECT pp.plan_key FROM users_has_payment_plans uhp
               JOIN payment_plans pp ON pp.payment_plan_id = uhp.payment_plans_payment_plan_id
              WHERE uhp.users_user_id = :tutor ORDER BY uhp.date_added DESC LIMIT 1',
            ['tutor' => $tutorId],
        );
        return (string) ($key ?? 'start');
    }

    public function planFeatureKeys(string $planKey): array
    {
        return array_column($this->db->all(
            'SELECT f.feature_key FROM payment_plan_features ppf
               JOIN features f ON f.feature_id = ppf.features_feature_id
               JOIN payment_plans pp ON pp.payment_plan_id = ppf.payment_plans_payment_plan_id
              WHERE pp.plan_key = :plan',
            ['plan' => $planKey],
        ), 'feature_key');
    }

    public function planStudentLimit(string $planKey): ?int
    {
        $limit = $this->db->value('SELECT student_limit FROM payment_plans WHERE plan_key = :plan', ['plan' => $planKey]);
        return $limit === null ? null : (int) $limit;
    }

    public function activeAddonFeatureKeys(int $tutorId): array
    {
        return array_column($this->db->all(
            'SELECT f.feature_key FROM tutor_addons ta
               JOIN addons a ON a.addon_id = ta.addons_addon_id
               JOIN features f ON f.feature_id = a.features_feature_id
              WHERE ta.tutor_id = :tutor AND ta.starts_at <= NOW() AND (ta.ends_at IS NULL OR ta.ends_at > NOW())',
            ['tutor' => $tutorId],
        ), 'feature_key');
    }

    public function activeStudentCount(int $tutorId): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(DISTINCT shc.students_student_id) FROM students_has_classes shc
               JOIN classes c ON c.class_id = shc.classes_class_id
              WHERE c.tutor_id = :tutor AND shc.status_status_id = :active',
            ['tutor' => $tutorId, 'active' => self::STATUS_ACTIVE],
        );
    }
}
