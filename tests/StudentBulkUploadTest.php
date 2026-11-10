<?php
declare(strict_types=1);

use Enlixo\Students\StudentBulkTemplate;
use Enlixo\Students\StudentBulkUpload;
use Enlixo\Students\StudentFormSchema;

$bulkLookups = [
    'classes' => [['value' => '7', 'label' => '2027 A/L Physics']],
    'genders' => [['value' => '1', 'label' => 'Male'], ['value' => '2', 'label' => 'Female']],
    'titles' => [['value' => '1', 'label' => 'Mr']],
    'relations' => [['value' => '1', 'label' => 'Mother'], ['value' => '2', 'label' => 'Father']],
    'cities' => ['Colombo 07', 'Kandy'],
];

test('Bulk upload: the columns follow the tutor\'s student form', function () use ($bulkLookups) {
    $settings = ['hidden' => ['nic'], 'required' => ['school'], 'labels' => ['school' => ['label' => 'Present School']],
        'custom' => [['key' => 'cf_stream_ab12c', 'label' => 'Stream', 'type' => 'select', 'section' => 'custom', 'required' => true, 'options' => [['value' => 'Maths', 'label' => 'Maths'], ['value' => 'Bio', 'label' => 'Bio']]]]];
    $cols = array_column(StudentBulkUpload::columns(StudentFormSchema::forTutor($settings), $bulkLookups), null, 'key');
    T::true(!isset($cols['class_id']) && !isset($cols['photo']), 'class is chosen on the page, the photo can\'t be in a file');
    T::true(!isset($cols['nic']), 'a hidden field has no column');
    T::eq('Present School', $cols['school']['header'], 'renamed field keeps the tutor\'s name');
    T::true($cols['school']['required'] && $cols['whatsapp']['required'] && !$cols['email']['required']);
    T::eq('Guardian First Name', $cols['guardian_first_name']['header'], 'guardian columns are told apart from the student\'s');
    T::eq('Stream', $cols['cf_stream_ab12c']['header'], 'the tutor\'s own field gets a column');
    $csv = StudentBulkTemplate::csv(array_values($cols));
    T::eq(array_column($cols, 'header'), str_getcsv(trim(substr($csv, 3)), ',', '"', ''), 'CSV template = the header row (after the UTF-8 mark)');
});

test('Bulk upload: headers match in any case or order; missing required columns are listed', function () use ($bulkLookups) {
    $cols = StudentBulkUpload::columns(StudentFormSchema::forTutor([]), $bulkLookups);
    $m = StudentBulkUpload::mapHeaders(['whatsapp number', 'FIRST NAME *', 'Last name', 'Favourite Colour', 'gender'], $cols);
    T::eq([0 => 'whatsapp', 1 => 'first_name', 2 => 'last_name', 4 => 'gender'], $m['map']);
    T::eq(['Favourite Colour'], $m['unknown']);
    T::eq(['Date of Birth'], $m['missing']);
});

test('Bulk upload: cells are cleaned like a person would expect', function () {
    T::eq('2008-03-15', StudentBulkUpload::date('15/03/2008'), 'day / month / year');
    T::eq('2008-03-05', StudentBulkUpload::date('2008/3/5'));
    T::eq(null, StudentBulkUpload::date('31/02/2008'), 'not a real date');
    T::eq('0771234567', StudentBulkUpload::phone('771234567'), 'Excel ate the first 0');
    T::eq('+94771234567', StudentBulkUpload::phone('94771234567'));
});

test('Bulk upload: every row is Ready or has its problems; duplicates and students already in the class are caught', function () use ($bulkLookups) {
    $fields = StudentFormSchema::forTutor([]);
    $headers = ['First Name', 'Last Name', 'Gender', 'Date of Birth', 'WhatsApp Number', 'E-mail', 'Guardian Relationship', 'Guardian First Name', 'Guardian WhatsApp Number'];
    $rows = [
        [2, ['Nethmi', 'Perera', 'female', '02/04/2008', '771234567', '', 'Mother', 'Kamala', '0779876543']],
        [3, ['Kasun', 'Silva', 'Male', '2008-01-01', '0771234567', 'not-an-email', '', '', '']],
        [4, ['Amaya', 'Fernando', '3', '2008-01-01', '0712223334', '', '', '', '']],
        [5, ['Dinuka', 'Bandara', 'Male', '2007-05-05', '0700000001', '', '', '', '']],
        [6, ['Hiruni', 'Silva', '2', '2007-05-05', '0700000002', '', '', '', '']],
    ];
    $existing = static fn (string $wa) => match ($wa) {
        '+94700000001' => ['student_id' => 5, 'name' => 'Dinuka B', 'in_class' => true, 'of_tutor' => true],
        '+94700000002' => ['student_id' => 6, 'name' => 'Hiruni S', 'in_class' => false, 'of_tutor' => false],
        default => null,
    };
    $r = StudentBulkUpload::check($headers, $rows, $fields, $bulkLookups, $existing, '2026-11-10');
    $by = array_column($r['rows'], null, 'row');
    T::eq('new', $by[2]['kind'], 'female + 02/04/2008 + 771234567 are understood');
    T::eq('2', $by[2]['values']['gender']);
    T::eq('2008-04-02', $by[2]['values']['date_of_birth']);
    T::eq('problem', $by[3]['kind']);
    T::eq(['E-mail', 'WhatsApp Number'], array_column($by[3]['errors'], 'column'), 'every problem of the row at once: bad e-mail and the WhatsApp number of row 2');
    T::eq(['Gender'], array_column($by[4]['errors'], 'column'), 'gender 3 is not in the list');
    T::true(str_contains($by[5]['errors'][0]['message'] ?? '', 'already in this class'));
    T::eq('existing', $by[6]['kind'], 'a student of another tutor is added to the class');
    T::eq([2, 3, 1, 1, 2], [$r['ready'], $r['problems'], $r['new'], $r['existing'], $r['adds']]);
    T::eq(9, count($by[3]['cells']), 'problem rows keep their cells for the "fix these rows" file');

    $dup = StudentBulkUpload::check($headers, [$rows[0], [7, ['Nimal', 'Perera', 'Male', '2008-01-01', '+94771234567', '', '', '', '']]], $fields, $bulkLookups, static fn () => null, '2026-11-10');
    T::true(str_contains($dup['rows'][1]['errors'][0]['message'] ?? '', 'row 2'), 'the same WhatsApp twice in the file');
});

test('Bulk upload: CSV with ; and Excel files are read the same way', function () use ($bulkLookups) {
    $csv = tempnam(sys_get_temp_dir(), 'bu');
    file_put_contents($csv, "\xEF\xBB\xBFFirst Name;Last Name\r\nNethmi;Perera\r\n;;\r\nKasun;\"Silva, Jr\"\r\n");
    [$h, $rows] = StudentBulkUpload::read($csv, 'students.csv');
    T::eq(['First Name', 'Last Name'], $h);
    T::eq([[2, ['Nethmi', 'Perera']], [4, ['Kasun', 'Silva, Jr']]], $rows, 'blank rows are skipped, row numbers stay as in the file');
    unlink($csv);

    $cols = StudentBulkUpload::columns(StudentFormSchema::forTutor([]), $bulkLookups);
    $xlsx = tempnam(sys_get_temp_dir(), 'bu') . '.xlsx';
    file_put_contents($xlsx, StudentBulkTemplate::xlsx($cols, ['Gender' => ['Male', 'Female']]));
    [$h2, $rows2] = StudentBulkUpload::read($xlsx, 'template.xlsx');
    T::eq(array_column($cols, 'header'), $h2, 'the Excel template is read back with the same headers');
    T::eq([], $rows2, 'an empty template has no students');
    unlink($xlsx);
});
