<?php
declare(strict_types=1);

namespace Enlixo\Classes;

use Enlixo\Core\HttpException;

/**
 * The class creation form, defined once on the server and drawn by assets/js/enlixo/components/FormBuilder.js.
 *
 * Built-in fields have a fixed key and are saved to real columns. A field marked `core` can't be hidden.
 * Each tutor can hide optional fields and add their own fields (saved to class_custom_field_values).
 * Every field has an `info` text that shows behind the (i) icon.
 */
final class ClassFormSchema
{
    public const CUSTOM_TYPES = ['text', 'number', 'date', 'select', 'yesno'];
    public const MAX_CUSTOM = 15;

    /** @return list<array{key:string,title:string,info:string}> */
    public static function sections(): array
    {
        return [
            ['key' => 'basics', 'title' => 'Class details', 'info' => 'What you teach and for which exam. Students search and filter by these.'],
            ['key' => 'running', 'title' => 'When and where', 'info' => 'Set your weekly times once. Class days are added to the timetable automatically, with Zoom links for online days.'],
            ['key' => 'fee', 'title' => 'Class fee', 'info' => 'The monthly fee students pay for this class. You can add discounts and late fees later from Payment Plans.'],
            ['key' => 'enrolment', 'title' => 'Who can join', 'info' => 'Control how students get into this class.'],
            ['key' => 'cover', 'title' => 'Cover image', 'info' => 'Shown on the class card in your app and in the student portal.'],
            ['key' => 'custom', 'title' => 'More details', 'info' => 'Your own fields. Add or change them with "Customise form".'],
        ];
    }

    /** Built-in fields. `options` with a string name are filled from lookup tables by the controller. */
    public static function builtIn(): array
    {
        return [
            ['key' => 'exam_type', 'section' => 'basics', 'label' => 'Exam type', 'type' => 'select', 'options' => 'exam_types', 'core' => true, 'required' => true,
             'info' => 'The exam your students are preparing for, e.g. A/L or Grade 5 Scholarship.'],
            ['key' => 'year', 'section' => 'basics', 'label' => 'Exam year', 'type' => 'select', 'options' => 'years', 'core' => true, 'required' => true,
             'info' => 'The year students sit the exam (their batch), not the year the class starts. A 2027 A/L class can start in 2025.'],
            ['key' => 'subject', 'section' => 'basics', 'label' => 'Subject', 'type' => 'select', 'options' => 'subjects', 'core' => true, 'required' => true, 'searchable' => true,
             'info' => 'The subject you teach in this class.'],
            ['key' => 'medium', 'section' => 'basics', 'label' => 'Medium', 'type' => 'select', 'options' => 'mediums', 'core' => true, 'required' => true,
             'info' => 'The language you teach in.'],
            ['key' => 'class_type', 'section' => 'basics', 'label' => 'Class type', 'type' => 'chips', 'default' => 'theory',
             'options' => [['value' => 'theory', 'label' => 'Theory'], ['value' => 'revision', 'label' => 'Revision'], ['value' => 'paper', 'label' => 'Paper class'], ['value' => 'other', 'label' => 'Other']],
             'info' => 'Theory covers the syllabus, Revision goes over it again before the exam, Paper class is for past papers and model papers.'],
            ['key' => 'name', 'section' => 'basics', 'label' => 'Class name', 'type' => 'text', 'maxlength' => 100, 'auto' => true, 'width' => 'full',
             'info' => 'We suggest a name from the details above. Change it if you like. Students see this name.'],
            ['key' => 'description', 'section' => 'basics', 'label' => 'Description', 'type' => 'textarea', 'maxlength' => 1000, 'width' => 'full',
             'info' => 'A short note for students: what the class covers, what to bring, who it is for.'],

            ['key' => 'delivery_mode', 'section' => 'running', 'label' => 'How you teach', 'type' => 'chips', 'core' => true, 'required' => true, 'default' => 'physical',
             'options' => [['value' => 'physical', 'label' => 'Physical'], ['value' => 'online', 'label' => 'Online'], ['value' => 'hybrid', 'label' => 'Both']],
             'info' => 'Physical: students come to a hall. Online: on Zoom. Both: some days in the hall, some online, or both at once.'],
            ['key' => 'location', 'section' => 'running', 'label' => 'Hall / location', 'type' => 'text', 'maxlength' => 150, 'showIf' => ['delivery_mode', ['physical', 'hybrid']],
             'info' => 'Where physical classes happen, e.g. "Sipwin Institute, Hall 3, Galle Road, Colombo 06".'],
            ['key' => 'schedule', 'section' => 'running', 'label' => 'Weekly class times', 'type' => 'schedule', 'width' => 'full',
             'info' => 'Add each weekly class time. Each one is added to your timetable every week. Leave empty if the times change every week; you can add class days one by one later.'],
            ['key' => 'auto_zoom', 'section' => 'running', 'label' => 'Create Zoom meetings automatically', 'type' => 'switch', 'default' => true, 'feature' => 'zoom.tutor_integration', 'showIf' => ['delivery_mode', ['online', 'hybrid']],
             'info' => 'For online class times we create the Zoom meeting about two weeks ahead and share the link with paid students. Connect Zoom in Settings > Integrations first.'],
            ['key' => 'starts_on', 'section' => 'running', 'label' => 'First class date', 'type' => 'date',
             'info' => 'Class days are added from this date. Leave empty to start from today.'],

            ['key' => 'is_free', 'section' => 'fee', 'label' => 'This is a free class', 'type' => 'switch', 'default' => false,
             'info' => 'Turn on for free seminars or trial classes. Students won\'t be asked to pay.'],
            ['key' => 'fee', 'section' => 'fee', 'label' => 'Monthly fee (LKR)', 'type' => 'number', 'min' => 0, 'max' => 1000000, 'step' => 50, 'showIf' => ['is_free', [false]], 'prefix' => 'LKR',
             'info' => 'What one student pays per month. Leave empty to set it later in Payment Plans.'],
            ['key' => 'due_day', 'section' => 'fee', 'label' => 'Fee due on day', 'type' => 'number', 'min' => 1, 'max' => 28, 'default' => 10, 'showIf' => ['is_free', [false]],
             'info' => 'Day of the month the fee is due, 1 to 28. After this day the student shows in arrears.'],

            ['key' => 'enrollment_mode', 'section' => 'enrolment', 'label' => 'Who can join', 'type' => 'radio', 'default' => 'approval',
             'options' => [
                 ['value' => 'open', 'label' => 'Anyone with the class link', 'hint' => 'Students join straight away.'],
                 ['value' => 'approval', 'label' => 'I approve each student', 'hint' => 'Requests wait in Requested Students.'],
                 ['value' => 'invite', 'label' => 'Only students I add', 'hint' => 'The class is hidden from search.'],
             ],
             'info' => 'Most tutors approve each student so they can check the payment first.'],
            ['key' => 'capacity', 'section' => 'enrolment', 'label' => 'Maximum students', 'type' => 'number', 'min' => 1, 'max' => 5000,
             'info' => 'Stop new joins when the hall or class is full. Leave empty for no limit. Your package also has a total student limit.'],

            ['key' => 'cover', 'section' => 'cover', 'label' => 'Class cover', 'type' => 'image', 'width' => 'full',
             'info' => 'JPG or PNG, up to 2 MB, wide (16:9) works best.'],
        ];
    }

    /** Fields after this tutor's settings: hidden ones dropped, own fields appended. */
    public static function forTutor(array $settings): array
    {
        $hidden = array_flip($settings['hidden'] ?? []);
        $fields = [];
        foreach (self::builtIn() as $f) {
            if (empty($f['core']) && isset($hidden[$f['key']])) {
                continue;
            }
            $fields[] = $f;
        }
        foreach ($settings['custom'] ?? [] as $c) {
            $fields[] = ['section' => 'custom', 'custom' => true, 'width' => $c['type'] === 'text' ? 'half' : null] + $c;
        }
        return $fields;
    }

    /** Fields a tutor may hide. */
    public static function hideable(): array
    {
        return array_values(array_map(
            static fn (array $f) => ['key' => $f['key'], 'label' => $f['label'], 'section' => $f['section']],
            array_filter(self::builtIn(), static fn (array $f) => empty($f['core'])),
        ));
    }

    /** Clean and validate a settings payload from the "Customise form" panel. */
    public static function sanitiseSettings(array $in): array
    {
        $hideable = array_column(self::hideable(), 'key');
        $hidden = array_values(array_intersect($hideable, array_map('strval', (array) ($in['hidden'] ?? []))));

        $custom = [];
        $errors = [];
        $seen = [];
        foreach (array_slice((array) ($in['custom'] ?? []), 0, self::MAX_CUSTOM) as $i => $c) {
            $label = trim((string) ($c['label'] ?? ''));
            $type = (string) ($c['type'] ?? 'text');
            if ($label === '' || mb_strlen($label) > 60) {
                $errors["custom.$i.label"] = 'Give the field a name (up to 60 letters).';
                continue;
            }
            if (!in_array($type, self::CUSTOM_TYPES, true)) {
                $errors["custom.$i.type"] = 'Choose a field type.';
                continue;
            }
            // Keep a stable key so saved values survive renames; new fields get one from the label.
            $key = (string) ($c['key'] ?? '');
            if (!preg_match('/^cf_[a-z0-9_]{1,36}$/', $key)) {
                $key = 'cf_' . substr(trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($label)), '_') ?: 'field', 0, 28) . '_' . substr(bin2hex(random_bytes(3)), 0, 5);
            }
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $field = ['key' => $key, 'label' => $label, 'type' => $type, 'required' => !empty($c['required']), 'info' => mb_substr(trim((string) ($c['info'] ?? '')), 0, 200)];
            if ($type === 'select') {
                $opts = array_values(array_unique(array_filter(array_map(static fn ($o) => mb_substr(trim((string) (is_array($o) ? ($o['value'] ?? '') : $o)), 0, 60), (array) ($c['options'] ?? [])), 'strlen')));
                if (count($opts) < 2) {
                    $errors["custom.$i.options"] = 'A dropdown needs at least two choices.';
                    continue;
                }
                $field['options'] = array_map(static fn (string $o) => ['value' => $o, 'label' => $o], array_slice($opts, 0, 30));
            }
            $custom[] = $field;
        }
        if ($errors) {
            throw HttpException::validation($errors);
        }
        return ['hidden' => $hidden, 'custom' => $custom];
    }
}
