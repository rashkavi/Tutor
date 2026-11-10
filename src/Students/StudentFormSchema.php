<?php
declare(strict_types=1);

namespace Enlixo\Students;

use Enlixo\Support\FormSchema;

/**
 * The student form. One form, used in three places:
 *   - the public application form (apply?t=...), which fills Requested Students,
 *   - Add New Student (tutor/add-student), which registers a student straight away,
 *   - the application page, which shows the answers in the same sections.
 * The tutor customises it on Settings > Student Form (tutor/student-form-settings).
 *
 * Built-in fields with `column` are saved to the existing tables (login_details, students, student_has_address,
 * parents) when the student is registered. The rest (nic, school, the tutor's own fields) go to
 * student_custom_field_values.
 */
final class StudentFormSchema extends FormSchema
{
    public static function sections(): array
    {
        return [
            ['key' => 'class', 'title' => 'Class', 'sub' => 'The class you want to join', 'icon' => 'classes', 'info' => 'The class the student joins. Only your active classes are listed.'],
            ['key' => 'personal', 'title' => 'Personal Details', 'sub' => 'Your name and personal details', 'icon' => 'user', 'info' => 'Who the student is. The name is shown on the class list, attendance and payments.'],
            ['key' => 'contact', 'title' => 'Contact Details', 'sub' => 'How we reach you', 'icon' => 'communication', 'info' => 'Class messages, Zoom links and payment reminders go to the WhatsApp number.'],
            ['key' => 'guardian', 'title' => 'Guardian Details', 'sub' => 'Fill this in completely, or leave it all empty', 'icon' => 'students', 'info' => 'A parent or guardian. They can follow attendance and payments from the parent portal.'],
            ['key' => 'custom', 'title' => 'More Details', 'sub' => 'A few more questions', 'icon' => 'class-form', 'info' => 'Your own fields. Add or change them in Settings > Student Form.'],
        ];
    }

    public static function builtIn(): array
    {
        return [
            ['key' => 'class_id', 'section' => 'class', 'label' => 'Class', 'type' => 'select', 'options' => 'classes', 'core' => true, 'required' => true, 'width' => 'half',
             'info' => 'The class the student joins. After approval they see its timetable, recordings and fees.', 'enter' => 'Pick one class. You can add the student to more classes later.'],

            ['key' => 'photo', 'section' => 'personal', 'label' => 'Profile Photo', 'type' => 'image', 'aspect' => 1, 'hint' => 'A clear, recent photo of the face. JPG or PNG; you can crop it to a square after choosing.'],
            ['key' => 'first_name', 'section' => 'personal', 'label' => 'First Name', 'type' => 'text', 'maxlength' => 45, 'core' => true, 'required' => true, 'placeholder' => 'Enter the first name'],
            ['key' => 'last_name', 'section' => 'personal', 'label' => 'Last Name', 'type' => 'text', 'maxlength' => 45, 'core' => true, 'required' => true, 'placeholder' => 'Enter the last name'],
            ['key' => 'full_name', 'section' => 'personal', 'label' => 'Full Name', 'type' => 'text', 'maxlength' => 150, 'auto' => true, 'placeholder' => 'Enter the full name',
             'help' => 'Filled from the first and last name when it is left empty.'],
            ['key' => 'name_with_initials', 'section' => 'personal', 'label' => 'Name with Initials', 'type' => 'text', 'maxlength' => 45, 'placeholder' => 'e.g. K. R. Perera'],
            ['key' => 'gender', 'section' => 'personal', 'label' => 'Gender', 'type' => 'select', 'options' => 'genders', 'core' => true, 'required' => true],
            ['key' => 'date_of_birth', 'section' => 'personal', 'label' => 'Date of Birth', 'type' => 'date', 'core' => true, 'required' => true],
            ['key' => 'nic', 'section' => 'personal', 'label' => 'NIC Number', 'type' => 'text', 'maxlength' => 12, 'placeholder' => 'e.g. 200712345678'],
            ['key' => 'school', 'section' => 'personal', 'label' => 'School', 'type' => 'text', 'maxlength' => 100, 'placeholder' => 'e.g. Royal College, Colombo 07'],

            ['key' => 'whatsapp', 'section' => 'contact', 'label' => 'WhatsApp Number', 'type' => 'tel', 'maxlength' => 15, 'core' => true, 'required' => true, 'placeholder' => 'e.g. 0771234567',
             'info' => 'The student signs in and gets class messages, Zoom links and payment reminders on this number.', 'enter' => 'A Sri Lankan mobile number like 0771234567, or a number with its country code like +44 7700 900123.'],
            ['key' => 'mobile', 'section' => 'contact', 'label' => 'Mobile Number', 'type' => 'tel', 'maxlength' => 15, 'placeholder' => 'If different from WhatsApp'],
            ['key' => 'email', 'section' => 'contact', 'label' => 'E-mail', 'type' => 'email', 'maxlength' => 100, 'placeholder' => 'e.g. name@gmail.com'],
            ['key' => 'address_line_1', 'section' => 'contact', 'label' => 'Address Line 1', 'type' => 'text', 'maxlength' => 100, 'placeholder' => 'House number and street'],
            ['key' => 'address_line_2', 'section' => 'contact', 'label' => 'Address Line 2', 'type' => 'text', 'maxlength' => 100, 'placeholder' => 'Optional'],
            ['key' => 'city', 'section' => 'contact', 'label' => 'City', 'type' => 'text', 'maxlength' => 60, 'suggest' => 'cities', 'placeholder' => 'Start typing your city'],
            ['key' => 'postal_code', 'section' => 'contact', 'label' => 'Postal Code', 'type' => 'text', 'maxlength' => 5, 'placeholder' => 'e.g. 10350'],

            ['key' => 'guardian_relation', 'section' => 'guardian', 'label' => 'Relationship', 'type' => 'select', 'options' => 'relations'],
            ['key' => 'guardian_title', 'section' => 'guardian', 'label' => 'Title', 'type' => 'select', 'options' => 'titles'],
            ['key' => 'guardian_first_name', 'section' => 'guardian', 'label' => 'First Name', 'type' => 'text', 'maxlength' => 45, 'placeholder' => 'Guardian\'s first name'],
            ['key' => 'guardian_last_name', 'section' => 'guardian', 'label' => 'Last Name', 'type' => 'text', 'maxlength' => 45, 'placeholder' => 'Guardian\'s last name'],
            ['key' => 'guardian_whatsapp', 'section' => 'guardian', 'label' => 'WhatsApp Number', 'type' => 'tel', 'maxlength' => 15, 'placeholder' => 'e.g. 0771234567'],
            ['key' => 'guardian_mobile', 'section' => 'guardian', 'label' => 'Mobile Number', 'type' => 'tel', 'maxlength' => 15, 'placeholder' => 'If different from WhatsApp'],
            ['key' => 'guardian_email', 'section' => 'guardian', 'label' => 'E-mail', 'type' => 'email', 'maxlength' => 100, 'placeholder' => 'e.g. parent@gmail.com'],
        ];
    }

    /** Guardian fields: all filled or all empty. */
    public const GUARDIAN = ['guardian_relation', 'guardian_title', 'guardian_first_name', 'guardian_last_name', 'guardian_whatsapp', 'guardian_mobile', 'guardian_email'];

    /** Built-in fields with no column in the old tables; saved with the student's own fields. */
    public const EXTRA = ['nic', 'school'];
}
