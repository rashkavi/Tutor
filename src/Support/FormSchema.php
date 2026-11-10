<?php
declare(strict_types=1);

namespace Enlixo\Support;

use Enlixo\Core\HttpException;

/**
 * A form defined once on the server and drawn by assets/js/enlixo/components/FormBuilder.js, which each tutor can
 * customise on a settings page (assets/js/enlixo/components/FormSettings.js): hide optional fields, make fields
 * required, rename them, change the order and add their own fields.
 *
 * A subclass gives sections() and builtIn(). Built-in fields have a fixed key; a field marked `core` can't be hidden.
 * Used by the class form (Enlixo\Classes\ClassFormSchema) and the student form (Enlixo\Students\StudentFormSchema).
 */
abstract class FormSchema
{
    public const CUSTOM_TYPES = ['text', 'number', 'date', 'select', 'yesno'];
    public const MAX_CUSTOM = 15;

    /** @return list<array{key:string,title:string,info:string}> */
    abstract public static function sections(): array;

    /** @return list<array<string,mixed>> */
    abstract public static function builtIn(): array;

    /** Names shown in the "Type" column of the form settings page. */
    public const TYPE_NAMES = [
        'text' => 'Short text', 'textarea' => 'Long text', 'number' => 'Number', 'date' => 'Date', 'select' => 'List of choices',
        'chips' => 'List of choices', 'radio' => 'One of a few choices', 'switch' => 'On / off', 'yesno' => 'Yes / No', 'schedule' => 'Weekly times', 'image' => 'Image', 'email' => 'E-mail', 'tel' => 'Phone number',
    ];

    /** Optional built-in fields of these types can be made required by the tutor. Switches and choices always have a value. */
    protected const REQUIRABLE_TYPES = ['text', 'textarea', 'number', 'date', 'schedule', 'image', 'email', 'tel', 'select'];

    public static function canRequire(array $f): bool
    {
        return empty($f['core']) && empty($f['custom']) && in_array($f['type'], static::REQUIRABLE_TYPES, true);
    }

    protected static function emptySettings(): array
    {
        return ['hidden' => [], 'required' => [], 'labels' => [], 'order' => [], 'custom' => []];
    }

    /**
     * Every field (built-in and the tutor's own) after this tutor's settings, hidden ones included,
     * each with `enabled`, `canHide`, `canRequire` and `typeName`. Sorted by the tutor's order inside each section.
     */
    public static function allForTutor(array $settings): array
    {
        $settings += static::emptySettings();
        $hidden = array_flip($settings['hidden']);
        $required = array_flip($settings['required']);
        $sectionKeys = array_column(static::sections(), 'key');

        $fields = [];
        foreach (static::builtIn() as $f) {
            $f['default_label'] = $f['label'];
            foreach (['label', 'info', 'enter', 'placeholder'] as $k) {
                if (isset($settings['labels'][$f['key']][$k]) && $settings['labels'][$f['key']][$k] !== '') {
                    $f[$k] = $settings['labels'][$f['key']][$k];
                }
            }
            $f['canRequire'] = static::canRequire($f);
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
            $f['typeName'] = static::TYPE_NAMES[$f['type']] ?? $f['type'];
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
        return array_values(array_filter(static::allForTutor($settings), static fn (array $f) => $f['enabled']));
    }

    /** Fields a tutor may hide. */
    public static function hideable(): array
    {
        return array_values(array_map(
            static fn (array $f) => ['key' => $f['key'], 'label' => $f['label'], 'section' => $f['section']],
            array_filter(static::builtIn(), static fn (array $f) => empty($f['core'])),
        ));
    }

    /** Clean and validate a settings payload from the form settings page. Unknown keys are dropped. */
    public static function sanitiseSettings(array $in): array
    {
        $sectionKeys = array_column(static::sections(), 'key');
        $builtIn = array_column(static::builtIn(), null, 'key');

        $custom = [];
        $errors = [];
        $seen = [];
        foreach (array_slice((array) ($in['custom'] ?? []), 0, static::MAX_CUSTOM) as $i => $c) {
            $label = trim((string) ($c['label'] ?? ''));
            $type = (string) ($c['type'] ?? 'text');
            if ($label === '' || mb_strlen($label) > 60) {
                $errors["custom.$i.label"] = 'Give the field a name (up to 60 letters).';
                continue;
            }
            if (!in_array($type, static::CUSTOM_TYPES, true)) {
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
                'enter' => mb_substr(trim((string) ($c['enter'] ?? '')), 0, 300),
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
        $hideable = array_merge(array_column(static::hideable(), 'key'), $customKeys);
        $hidden = array_values(array_unique(array_intersect(array_map('strval', (array) ($in['hidden'] ?? [])), $hideable)));
        $requirable = array_keys(array_filter($builtIn, [static::class, 'canRequire']));
        $required = array_values(array_unique(array_intersect(array_map('strval', (array) ($in['required'] ?? [])), $requirable)));

        $labels = [];
        foreach ((array) ($in['labels'] ?? []) as $key => $o) {
            if (!isset($builtIn[$key]) || !is_array($o)) {
                continue;
            }
            $clean = [];
            foreach (['label' => 60, 'info' => 300, 'enter' => 300, 'placeholder' => 80] as $k => $max) {
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
