<?php
declare(strict_types=1);

use Enlixo\Core\HttpException;
use Enlixo\Students\ApplicationService;
use Enlixo\Students\StudentFormSchema;
use Enlixo\Students\StudentFormValidator;

$lookups = [
    'classes' => [['value' => '7', 'label' => '2027 A/L Physics']],
    'genders' => [['value' => '1', 'label' => 'Male'], ['value' => '2', 'label' => 'Female']],
    'titles' => [['value' => '1', 'label' => 'Mr']],
    'relations' => [['value' => '1', 'label' => 'Mother']],
    'cities' => ['Colombo 07', 'Kandy'],
];
$good = ['class_id' => '7', 'first_name' => ' Nethmi ', 'last_name' => 'Perera', 'gender' => '2', 'date_of_birth' => '2008-04-02', 'whatsapp' => '077 123 4567'];

test('Student form: core fields stay, optional ones hide, the same settings code as the class form', function () {
    $cfg = StudentFormSchema::sanitiseSettings(['hidden' => ['whatsapp', 'nic', 'photo'], 'required' => ['school', 'gender', 'photo']]);
    T::eq(['nic', 'photo'], $cfg['hidden'], 'WhatsApp is core and can\'t be hidden');
    T::eq(['school', 'photo'], $cfg['required'], 'core fields are already required');
    $keys = array_column(StudentFormSchema::forTutor($cfg), 'key');
    T::true(in_array('whatsapp', $keys, true) && !in_array('nic', $keys, true));
});

test('Student form: answers are cleaned (phone, full name, city) and hidden fields dropped', function () use ($lookups, $good) {
    $fields = StudentFormSchema::forTutor(['hidden' => ['school']]);
    $v = StudentFormValidator::validate($fields, $lookups, $good + ['city' => 'kandy', 'school' => 'Royal', 'evil' => 'x'], '2026-11-10');
    T::eq('+94771234567', $v['whatsapp']);
    T::eq('Nethmi Perera', $v['full_name'], 'full name filled from first + last');
    T::eq('Kandy', $v['city'], 'city matched to the list');
    T::true(!isset($v['school']) && !isset($v['evil']), 'hidden and unknown keys dropped');
});

test('Student form: wrong answers get one message per field', function () use ($lookups, $good) {
    $fields = StudentFormSchema::forTutor([]);
    $e = T::throws(HttpException::class, fn () => StudentFormValidator::validate($fields, $lookups, array_merge($good, [
        'class_id' => '99', 'whatsapp' => '12345', 'email' => 'nope', 'date_of_birth' => '2030-01-01', 'city' => 'Atlantis', 'guardian_last_name' => 'Perera',
    ]), '2026-11-10'));
    T::eq(['class_id', 'date_of_birth', 'whatsapp', 'email', 'guardian_relation', 'guardian_first_name', 'guardian_whatsapp', 'city'], array_keys($e?->details['fields'] ?? []));
});

test('Applications: approved is final, rejected can only be reopened', function () {
    T::eq([], ApplicationService::MOVES['approved']);
    T::eq(['pending'], ApplicationService::MOVES['rejected']);
    T::true(in_array('approved', ApplicationService::MOVES['on_hold'], true));
    T::eq('+94771234567', StudentFormValidator::phone('94771234567'));
    T::eq('+447700900123', StudentFormValidator::phone('+44 7700 900123'));
    T::eq(null, StudentFormValidator::phone('0112345678'), 'landline is not a WhatsApp number');
});
