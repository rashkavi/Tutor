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
            ['key' => 'enrolment', 'title' => 'Enrolment', 'info' => 'Control how students get into this class.'],
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
            ['key' => 'description', 'section' => 'basics', 'label' => 'Description', 'type' => 'textarea', 'maxlength' => 1000, 'placeholder' => 'What the class covers, what to bring, who it is for', 'width' => 'full',
             'info' => 'A short note for students: what the class covers, what to bring, who it is for.'],

            ['key' => 'delivery_mode', 'section' => 'running', 'label' => 'How you teach', 'type' => 'chips', 'core' => true, 'required' => true, 'default' => 'physical',
             'options' => [['value' => 'physical', 'label' => 'Physical'], ['value' => 'online', 'label' => 'Online'], ['value' => 'hybrid', 'label' => 'Both']],
             'info' => 'Physical: students come to a hall. Online: on Zoom. Both: some days in the hall, some online, or both at once.'],
            ['key' => 'location', 'section' => 'running', 'label' => 'Hall / location', 'type' => 'text', 'maxlength' => 150, 'placeholder' => 'e.g. Sipwin Institute, Hall 3, Colombo 06', 'showIf' => ['delivery_mode', ['physical', 'hybrid']],
             'info' => 'Where physical classes happen, e.g. "Sipwin Institute, Hall 3, Galle Road, Colombo 06".'],
            ['key' => 'schedule', 'section' => 'running', 'label' => 'Weekly class times', 'type' => 'schedule', 'width' => 'full',
             'info' => 'Add each weekly class time. Each one is added to your timetable every week. Leave empty if the times change every week; you can add class days one by one later.'],
            ['key' => 'auto_zoom', 'section' => 'running', 'label' => 'Zoom meetings', 'type' => 'switch', 'default' => true, 'switchText' => 'Create Zoom meetings automatically', 'feature' => 'zoom.tutor_integration', 'showIf' => ['delivery_mode', ['online', 'hybrid']],
             'info' => 'For online class times we create the Zoom meeting about two weeks ahead and share the link with paid students. Connect Zoom in Settings > Integrations first.'],
            ['key' => 'starts_on', 'section' => 'running', 'label' => 'First class date', 'type' => 'date',
             'info' => 'Class days are added from this date. Leave empty to start from today.'],

            ['key' => 'is_free', 'section' => 'fee', 'label' => 'Free class', 'type' => 'switch', 'default' => false, 'switchText' => 'This class is free',
             'info' => 'Turn on for free seminars or trial classes. Students won\'t be asked to pay.'],
            ['key' => 'fee', 'section' => 'fee', 'label' => 'Monthly fee', 'type' => 'number', 'placeholder' => 'e.g. 2500', 'min' => 0, 'max' => 1000000, 'step' => 50, 'showIf' => ['is_free', [false]], 'prefix' => 'LKR',
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
            ['key' => 'capacity', 'section' => 'enrolment', 'label' => 'Maximum students', 'type' => 'number', 'placeholder' => 'No limit', 'min' => 1, 'max' => 5000,
             'info' => 'Stop new joins when the hall or class is full. Leave empty for no limit. Your package also has a total student limit.'],

            ['key' => 'cover', 'section' => 'basics', 'label' => 'Class cover', 'type' => 'image', 'width' => 'full',
             'info' => 'JPG or PNG, up to 2 MB, wide (16:9) works best.'],
        ];
    }

    /** Names shown in the "Type" column of the form settings page. */
    public const TYPE_NAMES = [
        'text' => 'Short text', 'textarea' => 'Long text', 'number' => 'Number', 'date' => 'Date', 'select' => 'List of choices',
        'chips' => 'List of choices', 'radio' => 'One of a few choices', 'switch' => 'On / off', 'yesno' => 'Yes / No', 'schedule' => 'Weekly times', 'image' => 'Image',
    ];

    /** Optional built-in fields of these types can be made required by the tutor. Switches and choices always have a value. */
    private const REQUIRABLE_TYPES = ['text', 'textarea', 'number', 'date', 'schedule', 'image'];

    public static function canRequire(array $f): bool
    {
        return empty($f['core']) && empty($f['custom']) && in_array($f['type'], self::REQUIRABLE_TYPES, true);
    }

    private static function emptySettings(): array
    {
        return ['hidden' => [], 'required' => [], 'labels' => [], 'order' => [], 'custom' => []];
    }

    /**
     * Every field (built-in and the tutor's own) after this tutor's settings, hidden ones included,
     * each with `enabled`, `canHide`, `canRequire` and `typeName`. Sorted by the tutor's order inside each section.
     */
    public static function allForTutor(array $settings): array
    {
        $settings += self::emptySettings();
        $hidden = array_flip($settings['hidden']);
        $required = array_flip($settings['required']);
        $sectionKeys = array_column(self::sections(), 'key');

        $fields = [];
        foreach (self::builtIn() as $f) {
            $f['default_label'] = $f['label'];
            foreach (['label', 'info', 'placeholder'] as $k) {
                if (isset($settings['labels'][$f['key']][$k]) && $settings['labels'][$f['key']][$k] !== '') {
                    $f[$k] = $settings['labels'][$f['key']][$k];
                }
            }
            $f['canRequire'] = self::canRequire($f);
            if ($f['canRequire'] && isset($required[$f['key']])) {
                $f['required'] = true;
            }
            $f['canHide'] = empty($f['core']);
            $f['enabled'] = !$f['canHide'] || !isset($hidden[$f['key']]);
            $fields[] = $f;
        }
        foreach ($settings['custom'] as $c) {
            $section = in_array($c['section'] ?? 'custom', $sectionKeys, true) ? ($c['section'] ?? 'custom') : 'custom';
            $fields[] = ['custom' => true, 'width' => null, 'canRequire' => true, 'canHide' => true, 'enabled' => !isset($hidden[$c['key']])]
                + ['section' => $section] + $c;
        }
        foreach ($fields as &$f) {
            $f['typeName'] = self::TYPE_NAMES[$f['type']] ?? $f['type'];
        }
        unset($f);

        // Order inside each section: the tutor's list first, then anything not in it in its default place.
        $pos = [];
        foreach ($fields as $i => $f) {
            $list = $settings['order'][$f['section']] ?? [];
            $at = array_search($f['key'], $list, true);
            $pos[$i] = [array_search($f['section'], $sectionKeys, true), $at === false ? 1000 + $i : $at, $i];
        }
        uksort($fields, static fn ($a, $b) => $pos[$a] <=> $pos[$b]);
        return array_values($fields);
    }

    /** Fields shown on the Create a Class form for this tutor: the enabled ones, in the tutor's order. */
    public static function forTutor(array $settings): array
    {
        return array_values(array_filter(self::allForTutor($settings), static fn (array $f) => $f['enabled']));
    }

    /** Fields a tutor may hide. */
    public static function hideable(): array
    {
        return array_values(array_map(
            static fn (array $f) => ['key' => $f['key'], 'label' => $f['label'], 'section' => $f['section']],
            array_filter(self::builtIn(), static fn (array $f) => empty($f['core'])),
        ));
    }

    /** Clean and validate a settings payload from the form settings page. Unknown keys are dropped. */
    public static function sanitiseSettings(array $in): array
    {
        $sectionKeys = array_column(self::sections(), 'key');
        $builtIn = array_column(self::builtIn(), null, 'key');

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
            $section = (string) ($c['section'] ?? 'custom');
            $field = [
                'key' => $key, 'label' => $label, 'type' => $type, 'required' => !empty($c['required']),
                'info' => mb_substr(trim((string) ($c['info'] ?? '')), 0, 300),
                'section' => in_array($section, $sectionKeys, true) ? $section : 'custom',
            ];
            $placeholder = mb_substr(trim((string) ($c['placeholder'] ?? '')), 0, 80);
            if ($placeholder !== '' && in_array($type, ['text', 'number'], true)) {
                $field['placeholder'] = $placeholder;
            }
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

        $customKeys = array_column($custom, 'key');
        $hideable = array_merge(array_column(self::hideable(), 'key'), $customKeys);
        $hidden = array_values(array_unique(array_intersect(array_map('strval', (array) ($in['hidden'] ?? [])), $hideable)));
        $requirable = array_keys(array_filter($builtIn, [self::class, 'canRequire']));
        $required = array_values(array_unique(array_intersect(array_map('strval', (array) ($in['required'] ?? [])), $requirable)));

        $labels = [];
        foreach ((array) ($in['labels'] ?? []) as $key => $o) {
            if (!isset($builtIn[$key]) || !is_array($o)) {
                continue;
            }
            $clean = [];
            foreach (['label' => 60, 'info' => 300, 'placeholder' => 80] as $k => $max) {
                $v = trim((string) ($o[$k] ?? ''));
                if (mb_strlen($v) > $max) {
                    $errors["labels.$key.$k"] = $k === 'label' ? "Keep the field name under $max letters." : "Keep this under $max letters.";
                    continue;
                }
                if ($v !== '' && $v !== ($builtIn[$key][$k] ?? '')) {
                    $clean[$k] = $v;
                }
            }
            if ($clean) {
                $labels[$key] = $clean;
            }
        }

        $sectionOf = array_column($builtIn, 'section', 'key') + array_column($custom, 'section', 'key');
        $order = [];
        foreach ((array) ($in['order'] ?? []) as $section => $keys) {
            if (!in_array($section, $sectionKeys, true)) {
                continue;
            }
            $list = array_values(array_unique(array_filter(array_map('strval', (array) $keys), static fn ($k) => ($sectionOf[$k] ?? null) === $section)));
            if ($list) {
                $order[$section] = $list;
            }
        }

        if ($errors) {
            throw HttpException::validation($errors);
        }
        return ['hidden' => $hidden, 'required' => $required, 'labels' => $labels, 'order' => $order, 'custom' => $custom];
    }
}
