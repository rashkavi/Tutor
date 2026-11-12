<?php
declare(strict_types=1);

namespace Enlixo\Platform;

use Enlixo\Core\Database;
use Enlixo\Core\HttpException;

/** Plans & Prices and Add-ons as the admin pages show them (payment_plans, addons, features). */
final class PlatformCatalog
{
    private ?array $plans = null;

    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string,mixed>> raw payment_plans rows with a plan_key, cheapest first */
    public function planRows(): array
    {
        return $this->plans ??= $this->db->all(
            'SELECT payment_plan_id, payment_plan, plan_key, per_month_amount, annual_discount, student_limit, extra_pack_price, tagline, sort_order
               FROM payment_plans WHERE plan_key IS NOT NULL ORDER BY sort_order, payment_plan_id',
        );
    }

    public function planByKey(string $key): ?array
    {
        foreach ($this->planRows() as $p) {
            if ($p['plan_key'] === $key) {
                return $p;
            }
        }
        return null;
    }

    public function planById(int $id): ?array
    {
        foreach ($this->planRows() as $p) {
            if ((int) $p['payment_plan_id'] === $id) {
                return $p;
            }
        }
        return null;
    }

    /** @return list<string> */
    public function planKeys(): array
    {
        return array_column($this->planRows(), 'plan_key');
    }

    /** Plans for the pages: price, limit, pack price, and the features that are new on each plan. */
    public function plans(array $tutorCounts = []): array
    {
        $byPlan = [];
        foreach ($this->db->all(
            'SELECT pp.plan_key, f.name FROM payment_plan_features ppf
               JOIN features f ON f.feature_id = ppf.features_feature_id
               JOIN payment_plans pp ON pp.payment_plan_id = ppf.payment_plans_payment_plan_id
              ORDER BY f.sort_order',
        ) as $r) {
            $byPlan[$r['plan_key']][] = $r['name'];
        }
        $out = [];
        $prev = null;
        foreach ($this->planRows() as $p) {
            $mine = $byPlan[$p['plan_key']] ?? [];
            $features = $prev === null ? array_slice($mine, 0, 4)
                : ['Everything in ' . strtoupper((string) $prev['payment_plan']), ...array_slice(array_values(array_diff($mine, $byPlan[$prev['plan_key']] ?? [])), 0, 3)];
            $out[] = [
                'key' => $p['plan_key'], 'id' => (int) $p['payment_plan_id'], 'name' => strtoupper((string) $p['payment_plan']),
                'monthly' => (float) $p['per_month_amount'], 'yearly_discount' => (float) $p['annual_discount'],
                'limit' => $p['student_limit'] === null ? null : (int) $p['student_limit'], 'pack_size' => Lifecycle::PACK_SIZE,
                'pack_price' => $p['extra_pack_price'] === null ? null : (float) $p['extra_pack_price'],
                'tagline' => (string) ($p['tagline'] ?? ''), 'features' => $features, 'tutors' => (int) ($tutorCounts[$p['plan_key']] ?? 0),
            ];
            $prev = $p;
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    public function addons(): array
    {
        return array_map(static fn (array $a) => [
            'key' => $a['addon_key'], 'id' => (int) $a['addon_id'], 'name' => $a['name'], 'model' => $a['charge_model'],
            'price' => $a['price'] === null ? null : (float) $a['price'], 'note' => (string) ($a['pricing_note'] ?? ''), 'on' => (bool) $a['is_offered'],
        ], $this->db->all('SELECT addon_id, addon_key, name, pricing_note, price, charge_model, is_offered FROM addons ORDER BY addon_id'));
    }

    /** Validates and saves one plan. @return array{0:array,1:array} before / after */
    public function savePlan(string $key, array $in): array
    {
        $p = $this->planByKey($key) ?? throw HttpException::notFound('Plan not found.');
        $e = [];
        $money = static function (string $k, bool $required) use ($in, &$e): ?float {
            $v = trim((string) ($in[$k] ?? ''));
            if ($v === '') {
                if ($required) {
                    $e[$k] = 'Enter an amount, for example 5000.';
                }
                return null;
            }
            if (!is_numeric($v) || (float) $v < 0 || (float) $v > 10000000) {
                $e[$k] = 'Enter an amount in LKR, numbers only, for example 5000.';
                return null;
            }
            return round((float) $v, 2);
        };
        $monthly = $money('monthly', true);
        $discount = $money('yearly_discount', false) ?? 0.0;
        $pack = $money('pack_price', false);
        $limit = filter_var($in['limit'] ?? null, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 100000) {
            $e['limit'] = 'Enter the most students, for example 150.';
        }
        if ($monthly !== null && $discount >= $monthly) {
            $e['yearly_discount'] = 'The yearly discount must be less than the monthly price.';
        }
        $tagline = trim((string) ($in['tagline'] ?? ''));
        if (mb_strlen($tagline) > 150) {
            $e['tagline'] = 'Up to 150 characters.';
        }
        if ($e) {
            throw new HttpException(422, 'validation_failed', 'Please check the highlighted fields.', $e);
        }
        $this->db->execute(
            'UPDATE payment_plans SET per_month_amount = :m, annual_discount = :d, student_limit = :l, extra_pack_price = :p, tagline = :t WHERE payment_plan_id = :id',
            ['m' => $monthly, 'd' => $discount, 'l' => $limit, 'p' => $pack, 't' => $tagline === '' ? null : $tagline, 'id' => $p['payment_plan_id']],
        );
        $this->plans = null;
        return [$p, $this->planByKey($key)];
    }

    /** @return array{0:array,1:array} before / after */
    public function saveAddon(string $key, array $in): array
    {
        $a = $this->db->one('SELECT addon_id, name, price, charge_model, pricing_note, is_offered FROM addons WHERE addon_key = :k', ['k' => $key]) ?? throw HttpException::notFound('Add-on not found.');
        $models = ['Monthly', 'Monthly + per payment', 'Pay as you go', 'Per conversation', 'One-time'];
        $e = [];
        $price = trim((string) ($in['price'] ?? ''));
        if ($price !== '' && (!is_numeric($price) || (float) $price < 0)) {
            $e['price'] = 'Enter an amount in LKR, numbers only, for example 1500.';
        }
        $model = array_key_exists('model', $in) ? (string) $in['model'] : $a['charge_model'];
        if (!in_array($model, $models, true)) {
            $e['model'] = 'Choose how it is charged.';
        }
        $note = array_key_exists('note', $in) ? trim((string) $in['note']) : (string) $a['pricing_note'];
        if ($note === '' || mb_strlen($note) > 150) {
            $e['note'] = 'Say in one line how the price works, up to 150 characters.';
        }
        if ($e) {
            throw new HttpException(422, 'validation_failed', 'Please check the highlighted fields.', $e);
        }
        $on = array_key_exists('on', $in) ? (filter_var($in['on'], FILTER_VALIDATE_BOOL) ? 1 : 0) : (int) $a['is_offered'];
        $this->db->execute(
            'UPDATE addons SET price = :p, charge_model = :m, pricing_note = :n, is_offered = :o WHERE addon_id = :id',
            ['p' => array_key_exists('price', $in) ? ($price === '' ? null : round((float) $price, 2)) : $a['price'], 'm' => $model, 'n' => $note, 'o' => $on, 'id' => $a['addon_id']],
        );
        return [$a, $this->db->one('SELECT addon_id, name, price, charge_model, pricing_note, is_offered FROM addons WHERE addon_id = :id', ['id' => $a['addon_id']])];
    }
}
