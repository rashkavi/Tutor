<?php
declare(strict_types=1);

namespace Enlixo\Classes;

use Enlixo\Support\FormSchema;

/**
 * The class creation form, defined once on the server and drawn by assets/js/enlixo/components/FormBuilder.js.
 *
 * Built-in fields have a fixed key and are saved to real columns. A field marked `core` can't be hidden.
 * Each tutor can hide optional fields and add their own fields (saved to class_custom_field_values).
 * Every field has an `info` text that shows behind the (i) icon.
 */
final class ClassFormSchema extends FormSchema
{
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
            ['key' => 'exam_type', 'section' => 'basics', 'label' => 'Exam type', 'type' => 'select', 'options' => 'exam_types', 'core' => true, 'required' => true],
            ['key' => 'year', 'section' => 'basics', 'label' => 'Exam year', 'type' => 'select', 'options' => 'years', 'core' => true, 'required' => true,
             'info' => 'The year your students sit the exam (their batch). Students and parents find the class by this year.', 'enter' => 'The exam year, not the year the class starts. A class that starts in 2025 for students sitting A/L in 2027 is a 2027 class.'],
            ['key' => 'subject', 'section' => 'basics', 'label' => 'Subject', 'type' => 'select', 'options' => 'subjects', 'core' => true, 'required' => true, 'searchable' => true],
            ['key' => 'medium', 'section' => 'basics', 'label' => 'Medium', 'type' => 'select', 'options' => 'mediums', 'core' => true, 'required' => true],
            ['key' => 'class_type', 'section' => 'basics', 'label' => 'Class type', 'type' => 'chips', 'default' => 'theory',
             'options' => [['value' => 'theory', 'label' => 'Theory'], ['value' => 'revision', 'label' => 'Revision'], ['value' => 'paper', 'label' => 'Paper class'], ['value' => 'other', 'label' => 'Other']],
             'info' => 'Tells students what kind of class this is, and keeps your theory, revision and paper classes apart in lists and reports.', 'enter' => 'Theory: you teach the syllabus. Revision: you go over it again before the exam. Paper class: past papers and model papers.'],
            ['key' => 'name', 'section' => 'basics', 'label' => 'Class name', 'type' => 'text', 'maxlength' => 100, 'auto' => true, 'width' => 'full',
             'info' => 'The name students, parents and your staff see everywhere: class list, timetable, payments and messages.', 'enter' => 'Leave it empty to use the name we suggest from the details above, e.g. "2027 A/L Physics Theory (Sinhala)". Up to 100 letters.'],
            ['key' => 'description', 'section' => 'basics', 'label' => 'Description', 'type' => 'textarea', 'maxlength' => 1000, 'placeholder' => 'What the class covers, what to bring, who it is for', 'width' => 'full'],

            ['key' => 'delivery_mode', 'section' => 'running', 'label' => 'How you teach', 'type' => 'chips', 'core' => true, 'required' => true, 'default' => 'physical',
             'options' => [['value' => 'physical', 'label' => 'Physical'], ['value' => 'online', 'label' => 'Online'], ['value' => 'hybrid', 'label' => 'Both']],
             'info' => 'Decides what the class needs: a hall for physical classes, Zoom links for online ones, or both.', 'enter' => 'Physical if students come to a hall, Online if you teach on Zoom, Both if you do some of each (you choose per weekly time below).'],
            ['key' => 'location', 'section' => 'running', 'label' => 'Hall / location', 'type' => 'text', 'maxlength' => 150, 'placeholder' => 'e.g. Sipwin Institute, Hall 3, Colombo 06', 'showIf' => ['delivery_mode', ['physical', 'hybrid']]],
            ['key' => 'schedule', 'section' => 'running', 'label' => 'Weekly class times', 'type' => 'schedule', 'width' => 'full',
             'info' => 'Each weekly time becomes a class day on your timetable every week, so attendance, recordings and Zoom links line up by themselves.', 'enter' => 'The day, start time and end time, e.g. Saturday 8:00 AM to 12:00 PM. Add one row per weekly class. Leave empty if the times change every week.'],
            ['key' => 'auto_zoom', 'section' => 'running', 'label' => 'Zoom meetings', 'type' => 'switch', 'default' => true, 'switchText' => 'Create Zoom meetings automatically', 'feature' => 'zoom.tutor_integration', 'showIf' => ['delivery_mode', ['online', 'hybrid']],
             'info' => 'For online times we create the Zoom meeting about two weeks ahead and share the link only with students who can join.', 'enter' => 'Turn on if you teach on Zoom. Connect your Zoom account first in Settings > Integrations.'],
            ['key' => 'starts_on', 'section' => 'running', 'label' => 'First class date', 'type' => 'date',
             'info' => 'Class days are added to the timetable from this date. Days before it are left empty.', 'enter' => 'The date of the very first class. Leave empty to start from today.'],

            ['key' => 'is_free', 'section' => 'fee', 'label' => 'Free class', 'type' => 'switch', 'default' => false, 'switchText' => 'This class is free'],
            ['key' => 'fee', 'section' => 'fee', 'label' => 'Monthly fee', 'type' => 'number', 'placeholder' => 'e.g. 2500', 'min' => 0, 'max' => 1000000, 'step' => 50, 'showIf' => ['is_free', [false]], 'prefix' => 'LKR'],
            ['key' => 'due_day', 'section' => 'fee', 'label' => 'Fee due on day', 'type' => 'number', 'min' => 1, 'max' => 28, 'default' => 10, 'showIf' => ['is_free', [false]],
             'info' => 'After this day of the month, students who have not paid show as in arrears and can get a reminder.', 'enter' => 'A day from 1 to 28, e.g. 10 means the fee is due by the 10th of every month.'],

            ['key' => 'enrollment_mode', 'section' => 'enrolment', 'label' => 'Who can join', 'type' => 'radio', 'default' => 'approval',
             'options' => [
                 ['value' => 'open', 'label' => 'Anyone with the class link', 'hint' => 'Students join straight away.'],
                 ['value' => 'approval', 'label' => 'I approve each student', 'hint' => 'Requests wait in Requested Students.'],
                 ['value' => 'invite', 'label' => 'Only students I add', 'hint' => 'The class is hidden from search.'],
             ],
             'info' => 'Controls how students get into this class.', 'enter' => 'Most tutors choose "I approve each student" so they can check the payment or the student first.'],
            ['key' => 'capacity', 'section' => 'enrolment', 'label' => 'Maximum students', 'type' => 'number', 'placeholder' => 'No limit', 'min' => 1, 'max' => 5000,
             'info' => 'New students can\'t join once this many are in the class.', 'enter' => 'The number of seats, e.g. 120 for your hall. Leave empty for no limit. Your package also has a total student limit.'],

            ['key' => 'cover', 'section' => 'basics', 'label' => 'Class cover', 'type' => 'image', 'width' => 'full'],
        ];
    }
}
