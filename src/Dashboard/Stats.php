<?php
declare(strict_types=1);

namespace Enlixo\Dashboard;

/** Small date and number helpers the dashboards share. No database, so they are easy to test. */
final class Stats
{
    /** Whole percent, 0 when there is nothing to divide by. */
    public static function rate(int $part, int $total): int
    {
        return $total > 0 ? (int) round($part * 100 / $total) : 0;
    }

    /** Monday and Sunday of the week that holds $day. @return array{string, string} */
    public static function week(string $day): array
    {
        $d = new \DateTimeImmutable($day);
        $monday = $d->modify('-' . ((int) $d->format('N') - 1) . ' days');
        return [$monday->format('Y-m-d'), $monday->modify('+6 days')->format('Y-m-d')];
    }

    /**
     * One entry per day, oldest first, ending on $lastDay. Days with no data get zeros.
     * @param array<string, array{present:int, absent:int}> $byDay
     * @return list<array{date:string, label:string, present:int, absent:int}>
     */
    public static function daySeries(array $byDay, string $lastDay, int $days): array
    {
        $out = [];
        $end = new \DateTimeImmutable($lastDay);
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = $end->modify("-$i days");
            $key = $d->format('Y-m-d');
            $out[] = [
                'date' => $key,
                'label' => $d->format('D'),
                'present' => (int) ($byDay[$key]['present'] ?? 0),
                'absent' => (int) ($byDay[$key]['absent'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * One entry per week (Monday to Sunday), oldest first, ending with the week that holds $lastDay.
     * @param array<string, array{present:int, absent:int}> $byDay
     * @return list<array{date:string, label:string, present:int, absent:int}>
     */
    public static function weekSeries(array $byDay, string $lastDay, int $weeks): array
    {
        $out = [];
        $monday = new \DateTimeImmutable(self::week($lastDay)[0]);
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $start = $monday->modify('-' . ($i * 7) . ' days');
            $present = $absent = 0;
            for ($d = 0; $d < 7; $d++) {
                $key = $start->modify("+$d days")->format('Y-m-d');
                $present += (int) ($byDay[$key]['present'] ?? 0);
                $absent += (int) ($byDay[$key]['absent'] ?? 0);
            }
            $out[] = ['date' => $start->format('Y-m-d'), 'label' => $start->format('j M'), 'present' => $present, 'absent' => $absent];
        }
        return $out;
    }

    /**
     * One entry per month, oldest first, ending with the month of $lastDay.
     * @param array<string, int> $byMonth keyed 'YYYY-MM'
     * @return list<array{month:string, label:string, value:int}>
     */
    public static function monthSeries(array $byMonth, string $lastDay, int $months): array
    {
        $out = [];
        $first = new \DateTimeImmutable(substr($lastDay, 0, 8) . '01');
        for ($i = $months - 1; $i >= 0; $i--) {
            $m = $first->modify("-$i months");
            $out[] = ['month' => $m->format('Y-m'), 'label' => $m->format('M'), 'value' => (int) ($byMonth[$m->format('Y-m')] ?? 0)];
        }
        return $out;
    }

    /** Present share of all marks in a series, or null when nothing was marked. */
    public static function seriesRate(array $series): ?int
    {
        $present = array_sum(array_column($series, 'present'));
        $total = $present + array_sum(array_column($series, 'absent'));
        return $total > 0 ? self::rate((int) $present, (int) $total) : null;
    }

    /** "12% more than last month" style note, or null when last month had nothing. */
    public static function change(float $now, float $before): ?string
    {
        if ($before <= 0) {
            return null;
        }
        $pct = (int) round(($now - $before) * 100 / $before);
        if ($pct === 0) {
            return 'Same as last month';
        }
        return abs($pct) . '% ' . ($pct > 0 ? 'more' : 'less') . ' than last month';
    }

    public static function greeting(int $hour): string
    {
        return $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    }
}
