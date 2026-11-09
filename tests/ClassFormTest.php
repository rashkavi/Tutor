<?php
declare(strict_types=1);

use Enlixo\Classes\ClassFormSchema;
use Enlixo\Core\HttpException;

test('Class form: core fields can never be hidden, own fields are cleaned', function () {
    $cfg = ClassFormSchema::sanitiseSettings([
        'hidden' => ['subject', 'description', 'nonsense'],
        'custom' => [
            ['label' => '  Institute name ', 'type' => 'text', 'info' => 'Where it runs'],
            ['label' => 'Unit', 'type' => 'select', 'options' => ['Unit 1', '', 'Unit 2', 'Unit 2']],
            ['key' => 'cf_hall_abc12', 'label' => 'Hall', 'type' => 'number', 'required' => 1],
        ],
    ]);
    T::eq(['description'], $cfg['hidden'], 'only optional fields hide');
    T::eq('Institute name', $cfg['custom'][0]['label']);
    T::true((bool) preg_match('/^cf_institute_name_[0-9a-f]{5}$/', $cfg['custom'][0]['key']), 'new field gets a stable key');
    T::eq([['value' => 'Unit 1', 'label' => 'Unit 1'], ['value' => 'Unit 2', 'label' => 'Unit 2']], $cfg['custom'][1]['options'], 'empty and duplicate choices dropped');
    T::eq('cf_hall_abc12', $cfg['custom'][2]['key'], 'existing key kept on rename');
    T::eq(true, $cfg['custom'][2]['required']);

    $fields = array_column(ClassFormSchema::forTutor(['hidden' => ['subject', 'description'], 'custom' => $cfg['custom']]), null, 'key');
    T::true(isset($fields['subject']), 'core field still shown');
    T::true(!isset($fields['description']), 'hidden field removed');
    T::eq('custom', $fields['cf_hall_abc12']['section']);
});

test('Class form: bad own fields are rejected with messages', function () {
    $e = T::throws(HttpException::class, fn () => ClassFormSchema::sanitiseSettings(['custom' => [['label' => '', 'type' => 'text'], ['label' => 'X', 'type' => 'select', 'options' => ['one']], ['label' => 'Y', 'type' => 'html']]]));
    T::eq(['custom.0.label', 'custom.1.options', 'custom.2.type'], array_keys($e?->details['fields'] ?? []));
});
