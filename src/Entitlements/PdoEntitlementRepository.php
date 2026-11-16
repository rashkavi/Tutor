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

    /** The live subscription row (migration 007), or null on an older database. */
    private function subscription(int $tutorId): ?array
    {
        $sql = static fn (string $extra) => 'SELECT uhp.status, uhp.extra_packs, pp.plan_key' . $extra . ' FROM users_has_payment_plans uhp
                   JOIN payment_plans pp ON pp.payment_plan_id = uhp.payment_plans_payment_plan_id
                  WHERE uhp.users_user_id = :tutor ORDER BY uhp.users_has_payment_plan_id DESC LIMIT 1';
        try {
            // migration 017: a trial on a chosen plan
            return $this->db->one($sql(', uhp.trial_plan_key, uhp.trial_student_cap'), ['tutor' => $tutorId]);
        } catch (\PDOException) {
            try {
                return $this->db->one($sql(''), ['tutor' => $tutorId]);
            } catch (\PDOException) {
                return null;
            }
        }
    }

    /** Trial & Billing Rules (Admin -> Plans & Billing), defaults when not saved yet. */
    private function trialRules(): array
    {
        try {
            $saved = json_decode((string) $this->db->value("SELECT setting_value FROM platform_settings WHERE setting_key = 'rules'"), true);
        } catch (\PDOException) {
            $saved = null;
        }
        return ['plan' => (string) ($saved['trial_plan'] ?? 'grow'), 'cap' => (int) ($saved['trial_student_cap'] ?? 30)];
    }

    public function activePlanKey(int $tutorId): string
    {
        // Admin side billing (migration 007): a free trial gets the trial plan's features, everyone else their plan.
        $sub = $this->subscription($tutorId);
        if ($sub !== null && $sub['status'] !== null) {
            return $sub['status'] === 'trial' ? ((string) ($sub['trial_plan_key'] ?? '') ?: $this->trialRules()['plan']) : (string) $sub['plan_key'];
        }
        // Before migration 007: prefer the plan with an accepted payment covering today; otherwise the latest assigned plan.
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

    public function tutorStudentLimit(int $tutorId, string $planKey): ?int
    {
        $sub = $this->subscription($tutorId);
        if ($sub !== null && $sub['status'] === 'trial') {
            return isset($sub['trial_student_cap']) && $sub['trial_student_cap'] !== null ? (int) $sub['trial_student_cap'] : $this->trialRules()['cap'];
        }
        $limit = $this->planStudentLimit($planKey);
        return $limit === null || $sub === null ? $limit : $limit + (int) $sub['extra_packs'] * 50;
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

    public function usage(int $tutorId): array
    {
        $month = date('Y-m-01 00:00:00');
        $count = function (string $sql, array $args): int {
            try {
                return (int) $this->db->value($sql, $args);
            } catch (\PDOException) {
                return 0; // table not there yet (older database): nothing counted
            }
        };
        return [
            'classes' => $count('SELECT COUNT(*) FROM classes WHERE tutor_id = :t AND status_status_id = :a', ['t' => $tutorId, 'a' => self::STATUS_ACTIVE]),
            'tutes.month' => $count(
                'SELECT COUNT(*) FROM class_tutes ct JOIN classes c ON c.class_id = ct.classes_class_id WHERE c.tutor_id = :t AND ct.date_added >= :m',
                ['t' => $tutorId, 'm' => $month],
            ),
            'recordings.month' => $count(
                'SELECT COUNT(*) FROM lecture_recording r JOIN time_table tt ON tt.time_table_id = r.time_table_time_table_id
                   JOIN classes c ON c.class_id = tt.classes_class_id WHERE c.tutor_id = :t AND r.added_at >= :m',
                ['t' => $tutorId, 'm' => $month],
            ),
            // one per person reached: sent, or waiting to be sent from a message made this month
            'messages.month' => $count(
                "SELECT COUNT(*) FROM tutor_message_recipients r JOIN tutor_messages m ON m.message_id = r.message_id
                  WHERE m.tutor_id = :t AND r.status IN ('sent', 'pending') AND m.created_at >= :m",
                ['t' => $tutorId, 'm' => $month],
            ),
        ];
    }

    public function preview(int $tutorId): ?array
    {
        try {
            $row = $this->db->one(
                'SELECT preview_plan_key, preview_until FROM users_has_payment_plans WHERE users_user_id = :t ORDER BY users_has_payment_plan_id DESC LIMIT 1',
                ['t' => $tutorId],
            );
        } catch (\PDOException) {
            return null; // before migration 018
        }
        if (!$row || !$row['preview_plan_key'] || !$row['preview_until'] || $row['preview_until'] < date('Y-m-d')) {
            return null;
        }
        return ['plan' => (string) $row['preview_plan_key'], 'until' => (string) $row['preview_until']];
    }

    public function previewUsed(int $tutorId): bool
    {
        try {
            return (bool) $this->db->value(
                'SELECT preview_used_at IS NOT NULL FROM users_has_payment_plans WHERE users_user_id = :t ORDER BY users_has_payment_plan_id DESC LIMIT 1',
                ['t' => $tutorId],
            );
        } catch (\PDOException) {
            return false;
        }
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
