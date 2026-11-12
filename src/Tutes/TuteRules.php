<?php
declare(strict_types=1);

namespace Enlixo\Tutes;

/**
 * Rules for tutes, with no database, so they are easy to test (tests/TuteRulesTest.php).
 *
 * A tute is either downloadable (PDF / Word files) or posted to the student's home by courier.
 * Who gets it: every active student of the class, or only the students whose fee for one month is paid.
 * A posted tute makes one delivery per student: pending → packed → dispatched → delivered (or returned).
 */
final class TuteRules
{
    public const TYPE_DOWNLOAD = 1; // tute_types: 1 Downloadable (Online)
    public const TYPE_COURIER = 2;  //            2 Courier Delivery

    /** Paid fee statuses: 3 approved (slip or cash), 8 paid by card. */
    public const PAID = [3, 8];

    /** Couriers tutors in Sri Lanka use most. "Other" lets the tutor type one. */
    public const COURIERS = [
        'Sri Lanka Post (Speed Post)',
        'Sri Lanka Post (Registered Post)',
        'Domex',
        'Prompt Xpress',
        'Koombiyo',
        'Pronto',
        'Certis Lanka',
        'Trans Express',
        'Hand delivery',
    ];

    /** Delivery steps, in order, with the words people see. */
    public const STATUSES = [
        'pending' => 'To pack',
        'packed' => 'Packed',
        'dispatched' => 'Sent',
        'delivered' => 'Delivered',
        'returned' => 'Returned',
    ];

    public const MAX_FILES = 20;
    public const MAX_FILE_BYTES = 10 * 1024 * 1024;
    public const FILE_TYPES = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

    /**
     * Checks the Add Tute form. Returns [clean values, errors by field]; errors empty = good.
     * @param array $in   title, description, type ('download'|'courier'), who ('all'|'paid'), payment_month ('YYYY-MM'),
     *                    items (list of titles, courier), courier_name, dispatch_on ('YYYY-MM-DD'), student_note
     * @param list<array{name:string,size:int,error:int,mime:string}> $files downloadable files (mime read from the file itself)
     * @return array{0: array, 1: array<string,string>}
     */
    public static function validateTute(array $in, array $files, string $today): array
    {
        $e = [];
        $title = self::text($in['title'] ?? '');
        $description = self::text($in['description'] ?? '');
        $type = ($in['type'] ?? '') === 'courier' ? self::TYPE_COURIER : (($in['type'] ?? '') === 'download' ? self::TYPE_DOWNLOAD : 0);
        $who = ($in['who'] ?? 'all') === 'paid' ? 'paid' : 'all';
        $month = self::text($in['payment_month'] ?? '');

        if ($title === '') {
            $e['title'] = 'Enter a name for the tute.';
        } elseif (mb_strlen($title) > 100) {
            $e['title'] = 'Keep the name under 100 characters.';
        }
        if (mb_strlen($description) > 200) {
            $e['description'] = 'Keep the description under 200 characters.';
        }
        if ($type === 0) {
            $e['type'] = 'Choose how students get it: download or courier.';
        }
        if ($who === 'paid') {
            if (!self::isMonth($month)) {
                $e['payment_month'] = 'Choose the month whose fee must be paid.';
            }
        } else {
            $month = '';
        }

        $items = [];
        $courier = null;
        $dispatch = null;
        $note = self::text($in['student_note'] ?? '');
        if ($type === self::TYPE_DOWNLOAD) {
            if (!$files) {
                $e['files'] = 'Add at least one PDF or Word file.';
            } elseif (count($files) > self::MAX_FILES) {
                $e['files'] = 'Add up to ' . self::MAX_FILES . ' files at a time.';
            } else {
                foreach ($files as $f) {
                    $name = (string) ($f['name'] ?? '');
                    if (($f['error'] ?? 0) !== 0) {
                        $e['files'] = "\"$name\" did not upload. Please try again.";
                    } elseif (!isset(self::FILE_TYPES[$f['mime'] ?? ''])) {
                        $e['files'] = "\"$name\" is not a PDF or Word file.";
                    } elseif (($f['size'] ?? 0) > self::MAX_FILE_BYTES) {
                        $e['files'] = "\"$name\" is bigger than 10 MB.";
                    } elseif (mb_strlen($name) > 100) {
                        $e['files'] = "Shorten the name of \"" . mb_substr($name, 0, 30) . "...\" to under 100 characters.";
                    }
                    if (isset($e['files'])) {
                        break;
                    }
                }
            }
        }
        if ($type === self::TYPE_COURIER) {
            foreach ((array) ($in['items'] ?? []) as $item) {
                $item = self::text($item);
                if ($item !== '') {
                    $items[] = $item;
                }
            }
            if (!$items) {
                $e['items'] = 'Add at least one item in the parcel, e.g. "Unit 5 tute book".';
            } elseif (count($items) > 30) {
                $e['items'] = 'Add up to 30 items.';
            } else {
                foreach ($items as $item) {
                    if (mb_strlen($item) > 100) {
                        $e['items'] = 'Keep each item under 100 characters.';
                        break;
                    }
                }
            }
            $courier = self::text($in['courier_name'] ?? '');
            if (mb_strlen($courier) > 60) {
                $e['courier_name'] = 'Keep the courier name under 60 characters.';
            }
            $courier = $courier === '' ? null : $courier;
            $dispatch = self::text($in['dispatch_on'] ?? '');
            if ($dispatch !== '' && !self::isDate($dispatch)) {
                $e['dispatch_on'] = 'Choose a valid date.';
            } elseif ($dispatch !== '' && $dispatch < date('Y-m-d', strtotime($today . ' -60 days'))) {
                $e['dispatch_on'] = 'Choose a date from the last two months or later.';
            }
            $dispatch = $dispatch === '' ? null : $dispatch;
        }
        if (mb_strlen($note) > 255) {
            $e['student_note'] = 'Keep the note under 255 characters.';
        }

        return [[
            'title' => $title,
            'description' => $description === '' ? null : $description,
            'type' => $type,
            'payment_month' => $month === '' ? null : $month,
            'items' => $items,
            'courier_name' => $courier,
            'dispatch_on' => $dispatch,
            'student_note' => $note === '' ? null : $note,
        ], $e];
    }

    /**
     * Checks a change to deliveries (the ticked rows, or one row).
     * @return array{0: array, 1: array<string,string>}
     */
    public static function validateDeliveryUpdate(array $in): array
    {
        $e = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($in['ids'] ?? [])), static fn ($i) => $i > 0)));
        if (!$ids) {
            $e['ids'] = 'Tick at least one student.';
        } elseif (count($ids) > 500) {
            $e['ids'] = 'Change up to 500 deliveries at a time.';
        }
        $set = [];
        if (array_key_exists('status', $in) && $in['status'] !== null && $in['status'] !== '') {
            if (!isset(self::STATUSES[$in['status']])) {
                $e['status'] = 'Choose a delivery step.';
            } else {
                $set['status'] = $in['status'];
            }
        }
        foreach (['courier_name' => 60, 'tracking_no' => 40, 'note' => 255, 'recipient_name' => 150, 'phone' => 20,
            'address_line_1' => 100, 'address_line_2' => 100, 'city' => 45, 'district' => 45, 'postal_code' => 10] as $k => $max) {
            if (!array_key_exists($k, $in)) {
                continue;
            }
            $v = self::text($in[$k]);
            if (mb_strlen($v) > $max) {
                $e[$k] = "Keep this under $max characters.";
            } elseif ($k === 'tracking_no' && $v !== '' && !preg_match('/^[A-Za-z0-9 \-\/]+$/', $v)) {
                $e[$k] = 'Use only letters, numbers, spaces, - and /.';
            } else {
                $set[$k] = $v === '' ? null : ($k === 'tracking_no' ? strtoupper($v) : $v);
            }
        }
        foreach (['dispatched_on', 'delivered_on'] as $k) {
            if (!array_key_exists($k, $in)) {
                continue;
            }
            $v = self::text($in[$k]);
            if ($v !== '' && !self::isDate($v)) {
                $e[$k] = 'Choose a valid date.';
            } else {
                $set[$k] = $v === '' ? null : $v;
            }
        }
        if (!$set && !$e) {
            $e['status'] = 'Nothing to change.';
        }
        return [['ids' => $ids, 'set' => $set], $e];
    }

    /**
     * Fills in the dates that follow from a step, so a tutor who marks "Sent" or "Delivered" does not have to type them.
     * Moving back to an earlier step clears the dates that no longer apply.
     */
    public static function withStepDates(array $set, string $today): array
    {
        $status = $set['status'] ?? null;
        if ($status === 'dispatched' && !array_key_exists('dispatched_on', $set)) {
            $set['dispatched_on_if_empty'] = $today;
        }
        if ($status === 'delivered') {
            $set['dispatched_on_if_empty'] = $today;
            if (!array_key_exists('delivered_on', $set)) {
                $set['delivered_on'] = $today;
            }
        }
        if ($status === 'pending' || $status === 'packed') {
            $set['dispatched_on'] = null;
            $set['delivered_on'] = null;
        }
        if ($status === 'dispatched' || $status === 'returned') {
            $set['delivered_on'] = null;
        }
        return $set;
    }

    /** Link where the student can follow the parcel, when the courier has a public tracking page. */
    public static function trackingUrl(?string $courier): ?string
    {
        $c = strtolower((string) $courier);
        return match (true) {
            str_contains($c, 'sri lanka post') || str_contains($c, 'speed post') => 'https://slpost.gov.lk/services/speed-post-courier-tracking/',
            str_contains($c, 'domex') => 'https://domex.lk/',
            default => null,
        };
    }

    /** The months a tute can be tied to: three months back to two months ahead, newest first. */
    public static function monthOptions(string $today): array
    {
        $first = new \DateTimeImmutable(substr($today, 0, 8) . '01');
        $out = [];
        for ($i = 2; $i >= -3; $i--) {
            $m = $first->modify(($i >= 0 ? '+' : '') . $i . ' months');
            $out[] = ['value' => $m->format('Y-m'), 'label' => $m->format('F Y')];
        }
        return $out;
    }

    public static function monthLabel(?string $month): ?string
    {
        return $month && self::isMonth($month) ? (new \DateTimeImmutable($month . '-01'))->format('F Y') : null;
    }

    public static function isMonth(string $v): bool
    {
        return (bool) preg_match('/^(20\d\d)-(0[1-9]|1[0-2])$/', $v);
    }

    public static function isDate(string $v): bool
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        return $d !== false && $d->format('Y-m-d') === $v;
    }

    private static function text(mixed $v): string
    {
        return is_scalar($v) ? trim(preg_replace('/\s+/u', ' ', (string) $v) ?? '') : '';
    }
}
