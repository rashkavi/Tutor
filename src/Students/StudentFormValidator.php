<?php
declare(strict_types=1);

namespace Enlixo\Students;

use Enlixo\Core\HttpException;

/**
 * Checks answers to the student form against the tutor's fields (StudentFormSchema::forTutor) and the lookups.
 * Returns clean values keyed by field key; unknown keys and hidden fields are dropped.
 * Throws HttpException::validation with one message per field.
 */
final class StudentFormValidator
{
    /**
     * @param list<array<string,mixed>> $fields  enabled fields after the tutor's settings
     * @param array<string,mixed>       $lookups StudentFormRepository::lookups()
     */
    public static function validate(array $fields, array $lookups, array $in, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $out = [];
        $errors = [];
        $byKey = array_column($fields, null, 'key');

        foreach ($fields as $f) {
            $key = $f['key'];
            if ($f['type'] === 'image') {
                continue; // the photo comes as a file
            }
            $raw = $in[$key] ?? '';
            $v = is_scalar($raw) ? trim((string) $raw) : '';
            if ($f['type'] === 'yesno') {
                $v = in_array($v, ['Yes', 'No'], true) ? $v : '';
            }
            $label = (string) $f['label'];

            if ($key === 'full_name' && $v === '') {
                $v = trim(trim((string) ($in['first_name'] ?? '')) . ' ' . trim((string) ($in['last_name'] ?? '')));
            }
            if ($v === '') {
                if (!empty($f['required'])) {
                    $errors[$key] = $f['type'] === 'select' ? "Choose the $label." : "Enter the $label.";
                }
                $out[$key] = '';
                continue;
            }
            if (isset($f['maxlength']) && mb_strlen($v) > (int) $f['maxlength']) {
                $errors[$key] = "Keep this under {$f['maxlength']} letters.";
                continue;
            }
            switch ($f['type']) {
                case 'email':
                    if (!filter_var($v, FILTER_VALIDATE_EMAIL)) {
                        $errors[$key] = 'Enter a valid e-mail address, like name@gmail.com.';
                        continue 2;
                    }
                    break;
                case 'tel':
                    $n = self::phone($v);
                    if ($n === null) {
                        $errors[$key] = 'Enter a valid phone number, like 0771234567.';
                        continue 2;
                    }
                    $v = $n;
                    break;
                case 'date':
                    $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
                    if (!$d || $d->format('Y-m-d') !== $v) {
                        $errors[$key] = 'Enter a valid date.';
                        continue 2;
                    }
                    if ($key === 'date_of_birth' && ($v > $today || $v < '1900-01-01')) {
                        $errors[$key] = 'Enter the real date of birth.';
                        continue 2;
                    }
                    break;
                case 'number':
                    if (!is_numeric($v)) {
                        $errors[$key] = 'Enter a number.';
                        continue 2;
                    }
                    break;
                case 'select':
                    $opts = is_array($f['options'] ?? null) ? $f['options'] : ($lookups[$f['options'] ?? ''] ?? []);
                    if (!in_array($v, array_map(static fn ($o) => (string) $o['value'], $opts), true)) {
                        $errors[$key] = "Choose the $label from the list.";
                        continue 2;
                    }
                    break;
            }
            $out[$key] = $v;
        }

        // Guardian: fill it in completely, or leave it all empty (like the old form).
        $guardian = array_values(array_intersect(StudentFormSchema::GUARDIAN, array_keys($byKey)));
        $filled = array_filter($guardian, static fn ($k) => ($out[$k] ?? '') !== '' || isset($errors[$k]));
        if ($filled) {
            foreach (['guardian_relation' => 'Choose how the guardian is related.', 'guardian_first_name' => 'Enter the guardian\'s first name.', 'guardian_whatsapp' => 'Enter the guardian\'s WhatsApp number.'] as $k => $msg) {
                if (isset($byKey[$k]) && ($out[$k] ?? '') === '' && !isset($errors[$k])) {
                    $errors[$k] = $msg;
                }
            }
        }

        // Address: the city has to be one we know, and the address line goes with it.
        if (($out['address_line_1'] ?? '') === '' && (($out['address_line_2'] ?? '') !== '' || ($out['postal_code'] ?? '') !== '') && isset($byKey['address_line_1']) && !isset($errors['address_line_1'])) {
            $errors['address_line_1'] = 'Enter the first line of the address.';
        }
        if (($out['city'] ?? '') !== '' && !isset($errors['city'])) {
            $match = null;
            foreach ($lookups['cities'] ?? [] as $c) {
                if (mb_strtolower($c) === mb_strtolower($out['city'])) {
                    $match = $c;
                    break;
                }
            }
            if ($match === null) {
                $errors['city'] = 'Choose your city from the list.';
            } else {
                $out['city'] = $match;
            }
        }
        if (($out['postal_code'] ?? '') !== '' && !preg_match('/^\d{5}$/', $out['postal_code']) && !isset($errors['postal_code'])) {
            $errors['postal_code'] = 'A postal code has 5 numbers, like 10350.';
        }

        if ($errors) {
            throw HttpException::validation($errors);
        }
        return $out;
    }

    /** Sri Lankan 07XXXXXXXX → +947XXXXXXXX; other numbers need their country code. Same rules as the old pages. */
    public static function phone(string $number): ?string
    {
        $n = (string) preg_replace('/[^\d+]/', '', $number);
        if (preg_match('/^0(7\d{8})$/', $n, $m)) {
            return '+94' . $m[1];
        }
        if (preg_match('/^94(7\d{8})$/', $n, $m)) {
            return '+94' . $m[1];
        }
        if (preg_match('/^\+[1-9]\d{7,14}$/', $n)) {
            return $n;
        }
        return null;
    }
}
