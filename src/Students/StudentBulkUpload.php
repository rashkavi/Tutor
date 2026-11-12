<?php
declare(strict_types=1);

namespace Enlixo\Students;

use Enlixo\Core\HttpException;

/**
 * Upload Students Bulk (tutor/upload-student-bulk), the Tutor version of the ENLIXO V3 bulk upload:
 * template -> choose the file -> Check File (every row Ready / Problem, nothing saved) -> Import (ready rows only).
 *
 * The columns are the tutor's own student form (StudentFormSchema::forTutor), so a field the tutor switched off has no
 * column, a field they made required is required here too, their own fields get a column, and every row is checked by
 * the same StudentFormValidator as Add New Student. The class is chosen on the page, not in the file.
 *
 * Pure parts (columns, header matching, cell clean-up, row checks) are static so they can be tested without a database.
 */
final class StudentBulkUpload
{
    public const MAX_ROWS = 500;
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** Field types that can't come from a spreadsheet cell. */
    private const SKIP_TYPES = ['image'];

    /** A sample value for the instructions table and the template's "How to fill" sheet. */
    private const EXAMPLES = [
        'first_name' => 'Kasun', 'last_name' => 'Perera', 'full_name' => 'Kasun Nuwan Perera', 'name_with_initials' => 'K. N. Perera',
        'gender' => 'Male', 'date_of_birth' => '2008-03-15', 'nic' => '200807512345', 'school' => 'Royal College, Colombo 07',
        'whatsapp' => '0771234567', 'mobile' => '0712345678', 'email' => 'kasun@gmail.com',
        'address_line_1' => '12, Temple Road', 'address_line_2' => 'Nugegoda', 'city' => 'Maharagama', 'postal_code' => '10280',
        'guardian_relation' => 'Father', 'guardian_title' => 'Mr', 'guardian_first_name' => 'Sunil', 'guardian_last_name' => 'Perera',
        'guardian_whatsapp' => '0779876543', 'guardian_mobile' => '0719876543', 'guardian_email' => 'sunil@gmail.com',
    ];

    /**
     * The columns of the file for these form fields (enabled fields, in the tutor's order).
     * @return list<array{key:string, header:string, label:string, section:string, type:string, required:bool, help:string, example:string, list:?string}>
     */
    public static function columns(array $fields, array $lookups = []): array
    {
        $cols = [];
        $used = [];
        foreach ($fields as $f) {
            if ($f['key'] === 'class_id' || in_array($f['type'], self::SKIP_TYPES, true)) {
                continue;
            }
            $label = trim((string) $f['label']);
            $header = $f['section'] === 'guardian' && stripos($label, 'guardian') === false ? "Guardian $label" : $label;
            if (isset($used[self::norm($header)])) {
                $header .= ' (' . $f['key'] . ')';
            }
            $used[self::norm($header)] = true;
            $list = null;
            if ($f['type'] === 'select') {
                $list = is_array($f['options'] ?? null) ? 'field:' . $f['key'] : (string) ($f['options'] ?? '');
            } elseif ($f['type'] === 'yesno') {
                $list = 'yesno';
            }
            $cols[] = [
                'key' => $f['key'], 'header' => $header, 'label' => $label, 'section' => (string) $f['section'], 'type' => (string) $f['type'],
                'required' => !empty($f['required']), 'help' => self::help($f), 'example' => self::example($f, $lookups), 'list' => $list,
            ];
        }
        return $cols;
    }

    /** What to write in this column, in plain words. */
    private static function help(array $f): string
    {
        $max = isset($f['maxlength']) ? ' Up to ' . (int) $f['maxlength'] . ' letters.' : '';
        return match (true) {
            $f['key'] === 'full_name' => 'Leave empty to use the first and last name.' . $max,
            $f['key'] === 'whatsapp' => 'The student signs in and gets class messages on this number. 0771234567, or +44... for a number from another country. Must be different for every student.',
            $f['key'] === 'city' => 'A Sri Lankan city name, as in the Add New Student form.',
            $f['key'] === 'postal_code' => 'Five numbers.',
            $f['key'] === 'date_of_birth', $f['type'] === 'date' => 'A date: 2008-03-15 or 15/03/2008 (day / month / year).',
            $f['key'] === 'guardian_relation' => 'How the guardian is related. Fill the guardian columns completely or leave them all empty.',
            $f['section'] === 'guardian' && $f['type'] === 'tel' => 'The guardian\'s number, like 0771234567.',
            $f['type'] === 'tel' => 'A mobile number like 0771234567.',
            $f['type'] === 'email' => 'An e-mail address.',
            !empty($f['custom']) && trim((string) ($f['enter'] ?? '')) !== '' => trim((string) $f['enter']) . $max,
            $f['type'] === 'select' => 'One of the names in "Names you can use".',
            $f['type'] === 'yesno' => 'Yes or No.',
            $f['type'] === 'number' => 'A number.',
            str_starts_with((string) ($f['placeholder'] ?? ''), 'Enter ') => $f['placeholder'] . '.' . $max,
            default => 'Text.' . $max,
        };
    }

    private static function example(array $f, array $lookups): string
    {
        if (isset(self::EXAMPLES[$f['key']])) {
            return self::EXAMPLES[$f['key']];
        }
        return match ($f['type']) {
            'date' => '2026-01-15',
            'number' => '10',
            'yesno' => 'Yes',
            'email' => 'name@gmail.com',
            'tel' => '0771234567',
            'select' => (string) ((is_array($f['options'] ?? null) ? $f['options'] : ($lookups[$f['options'] ?? ''] ?? []))[0]['label'] ?? ''),
            default => (string) ($f['placeholder'] ?? ''),
        };
    }

    /** The choices a list column accepts: [label, value][] */
    public static function listChoices(array $col, array $fields, array $lookups): array
    {
        if ($col['list'] === 'yesno') {
            return [['label' => 'Yes', 'value' => 'Yes'], ['label' => 'No', 'value' => 'No']];
        }
        if ($col['list'] !== null && str_starts_with($col['list'], 'field:')) {
            foreach ($fields as $f) {
                if ($f['key'] === $col['key']) {
                    return array_values($f['options'] ?? []);
                }
            }
            return [];
        }
        return $col['list'] !== null ? array_values($lookups[$col['list']] ?? []) : [];
    }

    /** "First Name", "first_name", "FIRST NAME *" all match. */
    public static function norm(string $s): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', strtolower(str_replace('*', '', $s)));
    }

    /**
     * Which file column is which form field.
     * @return array{map: array<int,string>, unknown: list<string>, missing: list<string>, found: list<array{header:string,label:string}>}
     */
    public static function mapHeaders(array $headers, array $columns): array
    {
        $by = [];
        foreach ($columns as $c) {
            foreach ([$c['header'], $c['label'], $c['key']] as $name) {
                $by[self::norm($name)] ??= $c;
            }
        }
        $map = [];
        $unknown = [];
        $found = [];
        foreach ($headers as $i => $h) {
            $h = trim((string) $h);
            if ($h === '') {
                continue;
            }
            $c = $by[self::norm($h)] ?? null;
            if ($c === null || in_array($c['key'], $map, true)) {
                $unknown[] = $h;
                continue;
            }
            $map[$i] = $c['key'];
            $found[] = ['header' => $h, 'label' => $c['header']];
        }
        $missing = array_values(array_map(static fn ($c) => $c['header'], array_filter($columns, static fn ($c) => $c['required'] && !in_array($c['key'], $map, true))));
        return ['map' => $map, 'unknown' => $unknown, 'missing' => $missing, 'found' => $found];
    }

    /** Turns the cells of one row into form values: names -> ids for lists, any date format -> Y-m-d, Excel-eaten zeros back on phones. */
    public static function rowValues(array $cells, array $map, array $columns, array $fields, array $lookups): array
    {
        $cols = array_column($columns, null, 'key');
        $v = [];
        foreach ($map as $i => $key) {
            $raw = trim((string) ($cells[$i] ?? ''));
            $col = $cols[$key];
            if ($raw === '') {
                $v[$key] = '';
                continue;
            }
            $v[$key] = match (true) {
                $col['type'] === 'date' => self::date($raw) ?? $raw,
                $col['type'] === 'tel' => self::phone($raw),
                $col['type'] === 'yesno' => self::yesNo($raw),
                $col['type'] === 'select' => self::choice($raw, self::listChoices($col, $fields, $lookups)),
                default => $raw,
            };
        }
        return $v;
    }

    /** 2008-03-15, 2008/3/15, 15/03/2008, 15-03-2008, 15.03.2008 -> 2008-03-15 (day first, as in Sri Lanka). */
    public static function date(string $s): ?string
    {
        $s = trim($s);
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})(?:[ T].*)?$/', $s, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})(?: .*)?$/', $s, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }
        return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }

    /** Excel drops the first 0 of 0771234567 when the column is a number; put it back. */
    public static function phone(string $s): string
    {
        $n = (string) preg_replace('/[\s\-().]/', '', $s);
        if (preg_match('/^7\d{8}$/', $n)) {
            return '0' . $n;
        }
        if (preg_match('/^947\d{8}$/', $n)) {
            return '+' . $n;
        }
        return $s;
    }

    private static function yesNo(string $s): string
    {
        return match (strtolower($s)) {
            'yes', 'y', '1', 'true', 'ow', 'ඔව්' => 'Yes',
            'no', 'n', '0', 'false', 'naha', 'නැහැ' => 'No',
            default => $s,
        };
    }

    /** Accepts the name (any case) or the number of a choice. */
    private static function choice(string $s, array $choices): string
    {
        foreach ($choices as $c) {
            if ((string) $c['value'] === $s || self::norm((string) $c['label']) === self::norm($s)) {
                return (string) $c['value'];
            }
        }
        // "F" -> Female, "Moth" -> Mother: only when exactly one name starts like that
        $starts = array_values(array_filter($choices, static fn ($c) => self::norm($s) !== '' && str_starts_with(self::norm((string) $c['label']), self::norm($s))));
        return count($starts) === 1 ? (string) $starts[0]['value'] : $s; // else the validator says "Choose ... from the list"
    }

    /**
     * Read a .csv or .xlsx file: [headers, rows] (rows = list of [rowNumber, cells]); blank rows are left out.
     * @return array{0: list<string>, 1: list<array{0:int,1:list<string>}>}
     */
    public static function read(string $path, string $name): array
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $table = match ($ext) {
            'csv', 'txt' => self::readCsv($path),
            'xlsx', 'xls' => self::readSheet($path),
            default => throw new HttpException(422, 'bad_file', 'Choose a CSV file (.csv) or an Excel file (.xlsx).'),
        };
        $headers = array_map(static fn ($h) => trim((string) $h), array_shift($table) ?? []);
        if (!array_filter($headers, 'strlen')) {
            throw new HttpException(422, 'bad_file', 'The first row of the file must hold the column names. Download the template and fill the students under its header row.');
        }
        $rows = [];
        foreach ($table as $i => $cells) {
            if (!array_filter(array_map(static fn ($c) => trim((string) $c), $cells), 'strlen')) {
                continue;
            }
            $rows[] = [$i + 2, array_map(static fn ($c) => (string) $c, $cells)];
        }
        return [$headers, $rows];
    }

    private static function readCsv(string $path): array
    {
        $text = (string) file_get_contents($path);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252'); // Excel "CSV" on Windows
        }
        $first = strtok($text, "\r\n") ?: '';
        $delim = ',';
        foreach ([';', "\t"] as $d) {
            if (substr_count($first, $d) > substr_count($first, $delim)) {
                $delim = $d;
            }
        }
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $text);
        rewind($fh);
        $out = [];
        while (($row = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
            $out[] = $row;
            if (count($out) > self::MAX_ROWS + 50) {
                break;
            }
        }
        fclose($fh);
        return $out;
    }

    private static function readSheet(string $path): array
    {
        self::spreadsheetLib();
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
            $sheet = $reader->load($path)->getSheet(0);
        } catch (\Throwable $e) {
            throw new HttpException(422, 'bad_file', 'This Excel file could not be read. Save it again as .xlsx (or as CSV) and try again.');
        }
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $lastRow = min($sheet->getHighestDataRow(), self::MAX_ROWS + 50);
        $out = [];
        for ($r = 1; $r <= $lastRow; $r++) {
            $row = [];
            for ($c = 1; $c <= $lastCol; $c++) {
                $cell = $sheet->getCell([$c, $r]);
                $val = $cell->getValue();
                if ($val instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                    $val = $val->getPlainText();
                }
                if (is_numeric($val) && !is_string($val) && \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                    $val = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $val)->format('Y-m-d');
                } elseif (is_float($val) && floor($val) === $val && abs($val) < 1e15) {
                    $val = number_format($val, 0, '', ''); // 200807512345, not 2.00807E+11
                } elseif (is_bool($val)) {
                    $val = $val ? 'Yes' : 'No';
                }
                $row[] = (string) $val;
            }
            $out[] = $row;
        }
        return $out;
    }

    public static function spreadsheetLib(): void
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            $auto = dirname(__DIR__, 2) . '/vendor/autoload.php';
            if (!is_file($auto)) {
                throw new HttpException(503, 'no_excel', 'Excel files are not available on this server. Use the CSV template instead.');
            }
            require_once $auto;
        }
    }

    /**
     * Check every row. Nothing is saved.
     * $existing(whatsapp) -> ?['student_id'=>int, 'name'=>string, 'in_class'=>bool, 'of_tutor'=>bool (active in one of this tutor's classes)]
     * @return array<string,mixed>
     */
    public static function check(array $headers, array $rows, array $fields, array $lookups, callable $existing, string $today): array
    {
        $columns = self::columns($fields, $lookups);
        $m = self::mapHeaders($headers, $columns);
        $byKey = array_column($columns, null, 'key');
        // only the fields that have a column are checked; a required field without a column is reported once, above the rows
        $checked = array_values(array_filter($fields, static fn ($f) => in_array($f['key'], $m['map'], true)));
        $headerOf = array_flip($m['map']);
        $seen = [];
        $out = [];
        foreach (array_slice($rows, 0, self::MAX_ROWS) as [$rowNo, $cells]) {
            $v = self::rowValues($cells, $m['map'], $columns, $fields, $lookups);
            $errors = [];
            $clean = [];
            try {
                $clean = StudentFormValidator::validate($checked, $lookups, $v, $today);
            } catch (HttpException $e) {
                foreach ($e->details['fields'] ?? [] as $key => $msg) {
                    $i = $headerOf[$key] ?? null;
                    if ($msg === 'Enter a valid date.') {
                        $msg = 'Write a real date as 2008-03-15 or 15/03/2008 (day / month / year).';
                    }
                    $errors[] = ['column' => $i !== null ? (string) $headers[$i] : ($byKey[$key]['header'] ?? $key), 'value' => $i !== null ? trim((string) ($cells[$i] ?? '')) : '', 'message' => (string) $msg];
                }
            }
            $wa = $clean['whatsapp'] ?? StudentFormValidator::phone((string) ($v['whatsapp'] ?? '')) ?? '';
            $waCol = $byKey['whatsapp']['header'] ?? 'WhatsApp Number';
            $note = '';
            $kind = 'new';
            $adds = true; // counts against the package's student limit
            if ($wa !== '' && isset($seen[$wa])) {
                $errors[] = ['column' => $waCol, 'value' => (string) ($v['whatsapp'] ?? ''), 'message' => "The same WhatsApp number is on row {$seen[$wa]}. Every student needs their own number."];
            } elseif ($wa !== '') {
                $seen[$wa] = $rowNo;
                $found = $errors ? null : $existing($wa);
                if ($found && $found['in_class']) {
                    $errors[] = ['column' => $waCol, 'value' => (string) ($v['whatsapp'] ?? ''), 'message' => "{$found['name']} already has this number and is already in this class."];
                } elseif ($found) {
                    $kind = 'existing';
                    $adds = empty($found['of_tutor']);
                    $note = "Already registered as {$found['name']}. They join this class without a second account.";
                }
            }
            $name = trim(($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? ''));
            $out[] = [
                'row' => $rowNo, 'name' => $name !== '' ? $name : (string) ($v['full_name'] ?? ''), 'whatsapp' => (string) ($v['whatsapp'] ?? ''),
                'email' => (string) ($v['email'] ?? ''), 'kind' => $errors ? 'problem' : $kind, 'note' => $note, 'adds' => !$errors && $adds, 'errors' => $errors,
                'cells' => $errors ? array_slice(array_pad($cells, count($headers), ''), 0, count($headers)) : null,
                'values' => $errors ? null : $clean,
            ];
        }
        $ready = array_filter($out, static fn ($r) => $r['kind'] !== 'problem');
        return [
            'headers' => $headers,
            'columns' => $m['found'], 'unknown' => $m['unknown'], 'missing' => $m['missing'],
            'total' => count($rows), 'checked' => count($out), 'too_many' => count($rows) > self::MAX_ROWS,
            'ready' => count($ready), 'new' => count(array_filter($ready, static fn ($r) => $r['kind'] === 'new')),
            'existing' => count(array_filter($ready, static fn ($r) => $r['kind'] === 'existing')),
            'problems' => count($out) - count($ready),
            'adds' => count(array_filter($ready, static fn ($r) => $r['adds'])),
            'problem_count' => array_sum(array_map(static fn ($r) => count($r['errors']), $out)),
            'rows' => $out,
        ];
    }
}
