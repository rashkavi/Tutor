<?php
declare(strict_types=1);

namespace Enlixo\Platform;

/**
 * Offer codes a tutor can bring to the sign-up form.
 *
 *   QR25: the QR codes on the landing page (attendance demos) open offer.php?code=QR25:
 *         "Congratulations: 25% off STARTER". The code goes with the sign-up request (requested_users.promo_code,
 *         migration 015); when the admin approves, Add Tutor starts with STARTER at 25% off as a custom price.
 *
 * Add a code here; nothing else changes. Codes are matched without case.
 */
final class Promotions
{
    public const CODES = [
        'QR25' => [
            'plan' => 'start',
            'percent' => 25,
            'title' => '25% off STARTER',
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
