<?php
declare(strict_types=1);

namespace Enlixo\Classes;

use Enlixo\Core\HttpException;
use Enlixo\Entitlements\Entitlements;

/** Validates the class form and saves it. Field rules come from ClassFormSchema, so the form and the checks never drift apart. */
final class ClassCreator
{
    public const MAX_COVER_BYTES = 2 * 1024 * 1024;

    public function __construct(
        private readonly ClassFormRepository $repo,
        private readonly string $coverDir,
    ) {
    }

    /**
     * @param array $in   decoded form values
     * @param ?array $file one entry of $_FILES (the cover), or null
     * @return array{id:int, name:string, slots:int, fee:bool}
     */
    public function create(int $tutorId, int $userId, array $in, ?array $file, Entitlements $ent, string $now): array
    {
        $settings = $this->repo->settings($tutorId);
        $fields = [];
        foreach (ClassFormSchema::forTutor($settings) as $f) {
            $fields[$f['key']] = $f;
        }
        $lookups = $this->repo->lookups();
        $errors = [];
        $v = fn (string $k, $default = null) => array_key_exists($k, $fields) ? ($in[$k] ?? $default) : $default;
        $str = fn (string $k) => trim((string) ($v($k) ?? ''));

        // Required lookups
        $ids = [];
        foreach (['exam_type' => 'exam_types', 'year' => 'years', 'subject' => 'subjects', 'medium' => 'mediums'] as $k => $list) {
            $val = (string) ($in[$k] ?? '');
            if (!in_array($val, array_column($lookups[$list], 'value'), true)) {
                $errors[$k] = 'Please choose ' . strtolower($fields[$k]['label']) . '.';
            }
            $ids[$k] = (int) $val;
        }

        $classType = $str('class_type') ?: 'theory';
        if (!in_array($classType, ['theory', 'revision', 'paper', 'other'], true)) {
            $errors['class_type'] = 'Choose a class type.';
        }
        $mode = $str('delivery_mode') ?: 'physical';
        if (!in_array($mode, ['physical', 'online', 'hybrid'], true)) {
            $errors['delivery_mode'] = 'Choose how you teach.';
        }
        $enrol = $str('enrollment_mode') ?: 'approval';
        if (!in_array($enrol, ['open', 'approval', 'invite'], true)) {
            $errors['enrollment_mode'] = 'Choose who can join.';
        }

        $description = $str('description');
        if (mb_strlen($description) > 1000) {
            $errors['description'] = 'Keep the description under 1000 letters.';
        }
        $location = $mode === 'online' ? '' : $str('location');
        if (mb_strlen($location) > 150) {
            $errors['location'] = 'Keep the location under 150 letters.';
        }

        $capacity = $this->int($v('capacity'), 1, 5000, 'capacity', 'Maximum students must be between 1 and 5000.', $errors);
        $startsOn = $str('starts_on');
        if ($startsOn !== '' && !$this->isDate($startsOn)) {
            $errors['starts_on'] = 'Choose a valid date.';
        }

        // Fee
        $isFree = filter_var($v('is_free', false), FILTER_VALIDATE_BOOL);
        $fee = null;
        if (!$isFree && array_key_exists('fee', $fields)) {
            $amount = $v('fee');
            if ($amount !== null && $amount !== '') {
                if (!is_numeric($amount) || (float) $amount < 0 || (float) $amount > 1000000) {
                    $errors['fee'] = 'Enter the fee in rupees, e.g. 2500.';
                } else {
                    $due = $this->int($v('due_day', 10), 1, 28, 'due_day', 'Due day must be between 1 and 28.', $errors) ?? 10;
                    $fee = ['amount' => round((float) $amount, 2), 'due_day' => $due];
                }
            }
        }

        // Weekly times
        $slots = [];
        $autoZoom = array_key_exists('auto_zoom', $fields) && filter_var($v('auto_zoom', true), FILTER_VALIDATE_BOOL) && $ent->has('zoom.tutor_integration');
        foreach (array_slice((array) ($v('schedule') ?? []), 0, 14) as $i => $s) {
            $day = (int) ($s['weekday'] ?? 0);
            $start = (string) ($s['start'] ?? '');
            $end = (string) ($s['end'] ?? '');
            $slotMode = $mode === 'hybrid' ? (string) ($s['mode'] ?? 'physical') : $mode;
            if ($day < 1 || $day > 7 || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)) {
                $errors["schedule.$i"] = 'Choose a day, a start time and an end time.';
                continue;
            }
            if ($end <= $start) {
                $errors["schedule.$i"] = 'The end time must be after the start time.';
                continue;
            }
            if (!in_array($slotMode, ['physical', 'online'], true)) {
                $slotMode = 'physical';
            }
            foreach ($slots as $o) {
                if ($o['weekday'] === $day && $start . ':00' < $o['end'] && $o['start'] < $end . ':00') {
                    $errors["schedule.$i"] = 'This time overlaps another time on the same day.';
                    continue 2;
                }
            }
            $slots[] = ['weekday' => $day, 'start' => $start . ':00', 'end' => $end . ':00', 'mode' => $slotMode, 'zoom' => $autoZoom && $slotMode === 'online'];
        }

        // Custom fields
        $custom = [];
        foreach ($fields as $key => $f) {
            if (empty($f['custom'])) {
                continue;
            }
            $raw = $in[$key] ?? null;
            $val = is_bool($raw) ? ($raw ? 'Yes' : 'No') : trim((string) ($raw ?? ''));
            if ($val === '' || ($f['type'] === 'yesno' && $raw === null)) {
                if (!empty($f['required'])) {
                    $errors[$key] = 'Please fill in ' . $f['label'] . '.';
                }
                continue;
            }
            $ok = match ($f['type']) {
                'number' => is_numeric($val),
                'date' => $this->isDate($val),
                'select' => in_array($val, array_column($f['options'] ?? [], 'value'), true),
                'yesno' => in_array($val, ['Yes', 'No'], true),
                default => mb_strlen($val) <= 500,
            };
            if (!$ok) {
                $errors[$key] = 'Please check ' . $f['label'] . '.';
                continue;
            }
            $custom[$key] = $val;
        }

        // Name: suggested from the details when left empty
        $name = $str('name');
        if (!$errors && $name === '') {
            $typeLabel = ['theory' => 'Theory', 'revision' => 'Revision', 'paper' => 'Paper', 'other' => ''][$classType];
            $name = trim(sprintf('%s %s %s %s', $this->repo->lookupLabel('year', $ids['year']), $this->repo->lookupLabel('exam_type', $ids['exam_type']),
                $this->repo->lookupLabel('subjects', $ids['subject']), $typeLabel)) . ' (' . $this->repo->lookupLabel('medium', $ids['medium']) . ')';
        }
        if (mb_strlen($name) > 100) {
            $errors['name'] = 'Keep the class name under 100 letters.';
        } elseif ($name !== '' && $this->repo->nameTaken($tutorId, $name)) {
            $errors['name'] = 'You already have a class with this name.';
        }

        // Cover
        $cover = null;
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE && array_key_exists('cover', $fields)) {
            $cover = $this->checkCover($file, $errors);
        }

        if ($errors) {
            throw HttpException::validation($errors);
        }

        $coverName = '';
        if ($cover !== null) {
            $coverName = bin2hex(random_bytes(8)) . $cover['ext'];
            if (!is_dir($this->coverDir) || !move_uploaded_file($file['tmp_name'], $this->coverDir . '/' . $coverName)) {
                if (!@rename($file['tmp_name'], $this->coverDir . '/' . $coverName)) {
                    throw new HttpException(500, 'upload_failed', 'We could not save the cover image. Please try again.');
                }
            }
        }

        $id = $this->repo->create($tutorId, $userId, [
            'name' => $name, 'exam_type' => $ids['exam_type'], 'year' => $ids['year'], 'subject' => $ids['subject'], 'medium' => $ids['medium'],
            'class_type' => $classType, 'description' => $description ?: null, 'delivery_mode' => $mode, 'location' => $location ?: null,
            'capacity' => $capacity, 'enrollment_mode' => $enrol, 'starts_on' => $startsOn ?: null, 'is_free' => $isFree, 'cover' => $coverName,
        ], $slots, $fee, $custom, $now);

        return ['id' => $id, 'name' => $name, 'slots' => count($slots), 'fee' => $fee !== null];
    }

    private function int(mixed $raw, int $min, int $max, string $key, string $msg, array &$errors): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (!is_numeric($raw) || (int) $raw != $raw || (int) $raw < $min || (int) $raw > $max) {
            $errors[$key] = $msg;
            return null;
        }
        return (int) $raw;
    }

    private function isDate(string $s): bool
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $s);
        return $d !== false && $d->format('Y-m-d') === $s;
    }

    /** @return array{ext:string}|null */
    private function checkCover(array $file, array &$errors): ?array
    {
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            $errors['cover'] = 'The image did not upload. Please try again.';
            return null;
        }
        if (($file['size'] ?? 0) > self::MAX_COVER_BYTES) {
            $errors['cover'] = 'The image must be 2 MB or smaller.';
            return null;
        }
        // Trust the bytes, not the browser's file type (and no SVG: it can carry scripts).
        $info = @getimagesize($file['tmp_name']);
        $ext = match ($info[2] ?? null) { IMAGETYPE_JPEG => '.jpg', IMAGETYPE_PNG => '.png', IMAGETYPE_WEBP => '.webp', default => null };
        if ($ext === null) {
            $errors['cover'] = 'Use a JPG, PNG or WebP image.';
            return null;
        }
        return ['ext' => $ext];
    }
}
