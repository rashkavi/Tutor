<?php
declare(strict_types=1);

use Enlixo\Dashboard\Stats;

test('dashboard: rate is a whole percent and never divides by zero', function () {
    T::eq(0, Stats::rate(3, 0));
    T::eq(75, Stats::rate(3, 4));
    T::eq(67, Stats::rate(2, 3));
});

test('dashboard: week runs Monday to Sunday', function () {
    T::eq(['2026-11-09', '2026-11-15'], Stats::week('2026-11-12')); // a Thursday
    T::eq(['2026-11-09', '2026-11-15'], Stats::week('2026-11-09')); // a Monday
    T::eq(['2026-11-09', '2026-11-15'], Stats::week('2026-11-15')); // a Sunday
});

test('dashboard: day series fills days without marks with zeros, oldest first', function () {
    $s = Stats::daySeries(['2026-11-11' => ['present' => 5, 'absent' => 1]], '2026-11-12', 7);
    T::eq(7, count($s));
    T::eq('2026-11-06', $s[0]['date']);
    T::eq('2026-11-12', $s[6]['date']);
    T::eq('Wed', $s[5]['label']);
    T::eq(5, $s[5]['present']);
    T::eq(0, $s[6]['present'] + $s[6]['absent']);
});

test('dashboard: week series adds up the days of each week', function () {
    $s = Stats::weekSeries([
        '2026-11-09' => ['present' => 1, 'absent' => 0],
        '2026-11-12' => ['present' => 2, 'absent' => 1],
        '2026-11-08' => ['present' => 0, 'absent' => 4], // Sunday of the week before
    ], '2026-11-12', 3);
    T::eq(['2026-10-26', '2026-11-02', '2026-11-09'], array_column($s, 'date'));
    T::eq(['present' => 3, 'absent' => 1], ['present' => $s[2]['present'], 'absent' => $s[2]['absent']]);
    T::eq(4, $s[1]['absent']);
    T::eq(38, Stats::seriesRate($s)); // 3 of 8
    T::eq(null, Stats::seriesRate(Stats::weekSeries([], '2026-11-12', 3)));
});

test('dashboard: month series crosses the year end', function () {
    $s = Stats::monthSeries(['2025-12' => 2, '2026-02' => 1], '2026-02-10', 4);
    T::eq(['2025-11', '2025-12', '2026-01', '2026-02'], array_column($s, 'month'));
    T::eq([0, 2, 0, 1], array_column($s, 'value'));
    // the 31st must not skip a month (31 January − 1 month is not "31 December" in every library)
    T::eq(['2026-01', '2026-02', '2026-03'], array_column(Stats::monthSeries([], '2026-03-31', 3), 'month'));
});

test('dashboard: change note compares with last month', function () {
    T::eq(null, Stats::change(500.0, 0.0));
    T::eq('50% more than last month', Stats::change(1500.0, 1000.0));
    T::eq('70% less than last month', Stats::change(300.0, 1000.0));
    T::eq('Same as last month', Stats::change(1000.0, 1000.0));
});

test('dashboard: greeting follows the time of day', function () {
    T::eq('Good morning', Stats::greeting(6));
    T::eq('Good afternoon', Stats::greeting(12));
    T::eq('Good evening', Stats::greeting(19));
});
