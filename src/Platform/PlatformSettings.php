<?php
declare(strict_types=1);

namespace Enlixo\Platform;

use Enlixo\Core\Database;
use Enlixo\Core\HttpException;

/** Trial & Billing Rules and the bank accounts tutors pay into (platform_settings, JSON per key). */
final class PlatformSettings
{
    /** Defaults until Rashmi confirms (docs/ADMIN-PLAN.md section 8): the old 7-day trial, on PRO, 30 students, 7 grace days, no VAT. */
    public const RULES = [
        'trial_days' => 7, 'trial_plan' => 'grow', 'trial_student_cap' => 30, 'invoice_days_before' => 7, 'grace_days' => 7,
        'tax_on' => false, 'tax_rate' => 18, 'invoice_prefix' => 'EN-INV',
    ];

    private ?array $rules = null;

    public function __construct(private readonly Database $db)
    {
    }

    private function read(string $key): mixed
    {
        $v = $this->db->value('SELECT setting_value FROM platform_settings WHERE setting_key = :k', ['k' => $key]);
        return $v === null ? null : json_decode((string) $v, true);
    }

    private function write(string $key, mixed $value, int $by): void
    {
        $this->db->execute(
            'INSERT INTO platform_settings (setting_key, setting_value, updated_by, updated_at) VALUES (:k, :v, :b, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()',
            ['k' => $key, 'v' => json_encode($value, JSON_UNESCAPED_UNICODE), 'b' => $by],
        );
    }

    public function rules(): array
    {
        return $this->rules ??= array_merge(self::RULES, array_intersect_key((array) ($this->read('rules') ?? []), self::RULES));
    }

    /** @return list<array{id:int, bank:string, branch:string, name:string, number:string, on:bool}> */
    public function banks(): array
    {
        return array_values((array) ($this->read('banks') ?? []));
    }

    /** Validates and saves the rules. @throws HttpException with field messages */
    public function saveRules(array $in, array $planKeys, int $by): array
    {
        $old = $this->rules();
        $e = [];
        $int = static function (string $k, int $min, int $max, string $msg) use ($in, &$e): int {
            $v = filter_var($in[$k] ?? null, FILTER_VALIDATE_INT);
            if ($v === false || $v < $min || $v > $max) {
                $e[$k] = $msg;
                return 0;
            }
            return $v;
        };
        $new = [
            'trial_days' => $int('trial_days', 1, 60, 'Enter 1 to 60 days, for example 7.'),
            'trial_plan' => (string) ($in['trial_plan'] ?? ''),
            'trial_student_cap' => $int('trial_student_cap', 1, 5000, 'Enter the most students, for example 30.'),
            'invoice_days_before' => $int('invoice_days_before', 0, 30, 'Enter 0 to 30 days, for example 7.'),
            'grace_days' => $int('grace_days', 0, 60, 'Enter 0 to 60 days, for example 7.'),
            'tax_on' => filter_var($in['tax_on'] ?? false, FILTER_VALIDATE_BOOL),
            'tax_rate' => $int('tax_rate', 0, 50, 'Enter a rate from 0 to 50, for example 18.'),
            'invoice_prefix' => strtoupper(trim((string) ($in['invoice_prefix'] ?? ''))),
        ];
        if (!in_array($new['trial_plan'], $planKeys, true)) {
            $e['trial_plan'] = 'Choose a plan.';
        }
        if (!preg_match('/^[A-Z][A-Z0-9-]{1,11}$/', $new['invoice_prefix'])) {
            $e['invoice_prefix'] = 'Use 2 to 12 capital letters, numbers or dashes, for example EN-INV.';
        }
        if ($e) {
            throw new HttpException(422, 'validation_failed', 'Please check the highlighted rules.', $e);
        }
        $this->write('rules', $new, $by);
        $this->rules = $new;
        return [$old, $new];
    }

    public function saveBanks(array $banks, int $by): void
    {
        $this->write('banks', array_values($banks), $by);
    }
}
