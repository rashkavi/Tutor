<?php
declare(strict_types=1);

namespace Enlixo\Students;

use Enlixo\Core\HttpException;
use Enlixo\Entitlements\Entitlements;

/**
 * Requested Students and Add New Student, both from the tutor's student form.
 *
 *   apply()       public link → a 'pending' application + "Received" in the history
 *   addStudent()  tutor's Add New Student → registered straight away
 *   changeStatus() pending ⇄ in_review ⇄ on_hold → approved (registers the student) | rejected (→ reopen to pending)
 */
final class ApplicationService
{
    /** Which status can go to which. Approved is final. */
    public const MOVES = [
        'pending' => ['in_review', 'on_hold', 'approved', 'rejected'],
        'in_review' => ['on_hold', 'approved', 'rejected'],
        'on_hold' => ['in_review', 'approved', 'rejected'],
        'rejected' => ['pending'],
        'approved' => [],
    ];
    public const ACTION_NAMES = ['pending' => 'Reopened', 'in_review' => 'In Review', 'on_hold' => 'On Hold', 'approved' => 'Approved', 'rejected' => 'Rejected'];
    public const MAX_PHOTO_BYTES = 3 * 1024 * 1024;
    public const MAX_PER_IP_PER_HOUR = 20;

    public function __construct(
        private readonly StudentFormRepository $forms,
        private readonly ApplicationRepository $apps,
        private readonly StudentRegistrar $registrar,
        private readonly string $photoDir,
    ) {
    }

    /** Enabled fields of this tutor's form. On the public form the class list only offers active classes. */
    public function fields(int $tutorId): array
    {
        return StudentFormSchema::forTutor($this->forms->settings($tutorId));
    }

    /** Keys stored in student_custom_field_values: NIC, school and the tutor's own fields. */
    public static function extraKeys(array $fields): array
    {
        return array_values(array_map(static fn ($f) => $f['key'], array_filter($fields, static fn ($f) => !empty($f['custom']) || in_array($f['key'], StudentFormSchema::EXTRA, true))));
    }

    /** @return array{id:int, ref:string, duplicate:bool} */
    public function apply(string $token, array $in, ?array $photoFile, ?string $ip, string $now): array
    {
        $link = $this->apps->findLink($token);
        if ($link === null) {
            throw HttpException::notFound('This application link is not valid. Ask your tutor for a new one.');
        }
        if (!$link['is_open']) {
            throw new HttpException(410, 'link_closed', 'This tutor is not taking applications right now.');
        }
        if (trim((string) ($in['website'] ?? '')) !== '') { // honeypot: people never see this field
            throw new HttpException(422, 'validation_failed', 'Please try again.');
        }
        if ($ip !== null && $this->apps->recentFromIp($ip, (new \DateTimeImmutable($now))->modify('-1 hour')->format('Y-m-d H:i:s')) >= self::MAX_PER_IP_PER_HOUR) {
            throw new HttpException(429, 'too_many', 'Too many applications from this connection. Please try again in an hour.');
        }
        $tutorId = $link['tutor_id'];
        $fields = $this->fields($tutorId);
        $values = StudentFormValidator::validate($fields, $this->forms->lookups($tutorId), (array) ($in['values'] ?? []), substr($now, 0, 10));
        $errors = [];
        $photo = $this->checkPhoto($photoFile, $fields, $errors);
        if (empty($in['confirm'])) {
            $errors['confirm'] = 'Tick the box to confirm your details are correct.';
        }
        if ($errors) {
            throw HttpException::validation($errors);
        }
        $classId = ($values['class_id'] ?? '') !== '' ? (int) $values['class_id'] : null;
        if (($dup = $this->apps->openDuplicate($tutorId, $classId, $values['whatsapp'])) !== null) {
            return ['id' => (int) $dup['id'], 'ref' => (string) $dup['ref'], 'duplicate' => true];
        }
        $message = mb_substr(trim((string) ($in['message'] ?? '')), 0, 1000);
        $created = $this->apps->create($tutorId, $classId, $values, $this->storePhoto($photoFile, $photo), $message, $ip, $now);
        return $created + ['duplicate' => false];
    }

    /** Add New Student: same form, registered at once. */
    public function addStudent(int $tutorId, int $userId, array $in, ?array $photoFile, Entitlements $ent, string $now): array
    {
        $fields = $this->fields($tutorId);
        $values = StudentFormValidator::validate($fields, $this->forms->lookups($tutorId), $in, substr($now, 0, 10));
        $errors = [];
        $photo = $this->checkPhoto($photoFile, $fields, $errors);
        if ($errors) {
            throw HttpException::validation($errors);
        }
        if (!$ent->canAddStudents()) {
            throw new HttpException(402, 'student_limit', "Your package allows {$ent->studentLimit} students and you have {$ent->studentCount}. Upgrade to add more.", ['feature' => 'students.registration']);
        }
        $result = $this->registrar->register($tutorId, $userId, $values, self::extraKeys($fields), $this->storePhoto($photoFile, $photo), $now, true);
        return $result + ['name' => $result['existing_name'] ?? trim($values['first_name'] . ' ' . $values['last_name'])];
    }

    /** Move an application. Approving registers the student in the chosen class. */
    public function changeStatus(int $tutorId, int $userId, int $id, string $to, string $note, ?int $classId, Entitlements $ent, string $now): array
    {
        $app = $this->apps->find($tutorId, $id) ?? throw HttpException::notFound('This application was not found.');
        $from = (string) $app['status'];
        if (!in_array($to, self::MOVES[$from] ?? [], true)) {
            throw new HttpException(409, 'bad_move', $from === 'approved' ? 'This application is already approved.' : 'This application can\'t be moved to that status.');
        }
        $note = mb_substr(trim($note), 0, 500);
        if (in_array($to, ['rejected', 'on_hold'], true) && $note === '') {
            throw HttpException::validation(['note' => $to === 'rejected' ? 'Say why, so the student knows what to fix.' : 'Say what you are waiting for.']);
        }

        $studentId = null;
        $remark = $note;
        if ($to === 'approved') {
            $values = $app['data'];
            $values['class_id'] = (string) ($classId ?? $app['classes_class_id'] ?? '');
            if ($values['class_id'] === '') {
                throw HttpException::validation(['class_id' => 'Choose the class to add this student to.']);
            }
            $missing = array_filter(['first_name', 'last_name', 'gender', 'date_of_birth', 'whatsapp'], static fn ($k) => ($values[$k] ?? '') === '');
            if ($missing) {
                throw HttpException::validation(['note' => 'This application is missing ' . implode(', ', str_replace('_', ' ', $missing)) . '. Put it on hold and ask the student.']);
            }
            if (!$ent->canAddStudents()) {
                throw new HttpException(402, 'student_limit', "Your package allows {$ent->studentLimit} students and you have {$ent->studentCount}. Upgrade to approve more.", ['feature' => 'students.registration']);
            }
            $fieldKeys = array_merge(StudentFormSchema::EXTRA, array_map(static fn ($f) => $f['key'], array_filter(StudentFormSchema::allForTutor($this->forms->settings($tutorId)), static fn ($f) => !empty($f['custom']))));
            $result = $this->registrar->register($tutorId, $userId, $values, $fieldKeys, $app['photo'] ?: null, $now);
            $studentId = $result['student_id'];
            $remark = trim(($result['already_in_class'] ? 'Already in this class.' : ($result['existing'] ? 'Already had an account (' . $result['existing_name'] . '); added to the class.' : 'Registered as a new student.')) . ($note !== '' ? " $note" : ''));
        }
        $this->apps->setStatus($id, $to, in_array($to, ['rejected', 'on_hold'], true) ? $note : null, $userId, $now, $studentId);
        $this->apps->addHistory($id, $userId, self::ACTION_NAMES[$to], $remark !== '' ? $remark : null, $now);
        return ['status' => $to, 'student_id' => $studentId];
    }

    /** @return array{ext:string}|null */
    private function checkPhoto(?array $file, array $fields, array &$errors): ?array
    {
        $field = null;
        foreach ($fields as $f) {
            if ($f['key'] === 'photo') {
                $field = $f;
            }
        }
        $none = $file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE;
        if ($field === null || $none) {
            if ($field !== null && !empty($field['required'])) {
                $errors['photo'] = 'Add a photo.';
            }
            return null;
        }
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > self::MAX_PHOTO_BYTES) {
            $errors['photo'] = 'The photo did not upload or is too big. Use one under 3 MB.';
            return null;
        }
        $info = @getimagesize($file['tmp_name']);
        $ext = match ($info[2] ?? null) { IMAGETYPE_JPEG => '.jpg', IMAGETYPE_PNG => '.png', IMAGETYPE_WEBP => '.webp', default => null };
        if ($ext === null) {
            $errors['photo'] = 'Use a JPG, PNG or WebP photo.';
            return null;
        }
        return ['ext' => $ext];
    }

    private function storePhoto(?array $file, ?array $photo): ?string
    {
        if ($photo === null || $file === null) {
            return null;
        }
        $name = bin2hex(random_bytes(8)) . $photo['ext'];
        if (!is_dir($this->photoDir) || !move_uploaded_file($file['tmp_name'], $this->photoDir . '/' . $name)) {
            if (!@rename($file['tmp_name'], $this->photoDir . '/' . $name)) {
                throw new HttpException(500, 'upload_failed', 'We could not save the photo. Please try again.');
            }
        }
        return $name;
    }
}
