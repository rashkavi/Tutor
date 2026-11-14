<?php
declare(strict_types=1);

namespace Enlixo\Platform;

/**
 * Offer codes a tutor can bring to the sign-up form.
 *
 *   QR10: the QR codes on the landing page (attendance demos) open offer.php?code=QR10:
 *         "Congratulations: 10% off STARTER". The code goes with the sign-up request (requested_users.promo_code,
 *         migration 015); when the admin approves, Add Tutor starts with STARTER at 10% off as a custom price.
 *
 * Add a code here; nothing else changes. Codes are matched without case.
 */
final class Promotions
{
    public const CODES = [
        'QR10' => [
            'plan' => 'start',
            'percent' => 10,
            'title' => '10% off STARTER',
            'source' => 'Landing page QR code',
        ],
    ];

    public static function find(?string $code): ?array
    {
        $code = strtoupper(trim((string) $code));
        return isset(self::CODES[$code]) ? ['code' => $code] + self::CODES[$code] : null;
    }

    /** Monthly price after the offer, rounded to LKR 10. */
    public static function price(float $monthly, array $promo): float
    {
        return round($monthly * (100 - (int) $promo['percent']) / 100 / 10) * 10;
    }
}
