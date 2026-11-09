<?php
declare(strict_types=1);

namespace Enlixo\Catalog;

/**
 * Single source of truth for package features, taken from the ENLIXO pricing sheet.
 * `bin/seed.php` writes this to the `features`, `payment_plan_features` and `addons` tables,
 * and `bin/export-catalog.php` writes it to JSON for the frontend.
 *
 * Code never checks plan names. It asks for a feature key: $entitlements->has('recordings.expiry').
 */
final class FeatureCatalog
{
    public const PLANS = [
        'start' => ['id' => 1, 'name' => 'START', 'student_limit' => 50,  'price' => 'LKR 2,500 – 3,000 / month'],
        'grow'  => ['id' => 2, 'name' => 'GROW',  'student_limit' => 150, 'price' => 'LKR 5,000 – 6,000 / month'],
        'pro'   => ['id' => 3, 'name' => 'PRO',   'student_limit' => 300, 'price' => 'LKR 8,000 – 10,000 / month'],
    ];

    private const ALL = ['start', 'grow', 'pro'];
    private const GROW_UP = ['grow', 'pro'];
    private const PRO = ['pro'];
    private const ADDON = [];

    /**
     * key => [name, category, plans, description, addon?]
     * @return array<string, array{name:string, category:string, plans:list<string>, description:string, addon?:array{key:string,name:string,pricing:string}}>
     */
    public static function features(): array
    {
        return [
            // Class & Student Management
            'classes.setup'          => ['name' => 'Subject / Grade / Language Setup', 'category' => 'Class & Student Management', 'plans' => self::ALL, 'description' => 'Create classes by subject, grade and medium.'],
            'students.registration'  => ['name' => 'Student Registration', 'category' => 'Class & Student Management', 'plans' => self::ALL, 'description' => 'Register students and approve join requests.'],
            'parents.login'          => ['name' => 'Parent Login', 'category' => 'Class & Student Management', 'plans' => self::ALL, 'description' => 'Parents log in to follow their children.'],
            'timetable.manage'       => ['name' => 'Timetable Management', 'category' => 'Class & Student Management', 'plans' => self::ALL, 'description' => 'Plan lessons per class.'],
            'classes.scheduling'     => ['name' => 'Physical & Online Class Scheduling', 'category' => 'Class & Student Management', 'plans' => self::ALL, 'description' => 'Weekly schedules for physical and online classes.'],
            'tutes.manage'           => ['name' => 'Tute / Material Management', 'category' => 'Class & Student Management', 'plans' => self::ALL, 'description' => 'Share tutes and materials per class.'],
            'exams.mcq'              => ['name' => 'MCQ Exams + Grading', 'category' => 'Class & Student Management', 'plans' => self::ALL, 'description' => 'Online MCQ papers with auto marking.'],
            'fees.class_based'       => ['name' => 'Class-Based Fee Setup', 'category' => 'Class & Student Management', 'plans' => self::ALL, 'description' => 'Monthly fee per class.'],
            'fees.advanced'          => ['name' => 'Advanced Class-Based Fee Logic', 'category' => 'Class & Student Management', 'plans' => self::PRO, 'description' => 'Discounts, penalties and custom fee rules per student.'],

            // Payment Management
            'payments.bank_tracking'   => ['name' => 'Bank Transfer Tracking', 'category' => 'Payment Management', 'plans' => self::ALL, 'description' => 'Students upload bank slips.'],
            'payments.manual_approval' => ['name' => 'Manual Payment Approval', 'category' => 'Payment Management', 'plans' => self::ALL, 'description' => 'Approve or reject payments.'],
            'payments.online_card'     => ['name' => 'Online Card Payments (Tutor Gateway)', 'category' => 'Payment Management', 'plans' => self::GROW_UP, 'description' => 'Accept card payments with your own gateway.'],
            'payments.arrears'         => ['name' => 'Arrears Tracking Dashboard', 'category' => 'Payment Management', 'plans' => self::PRO, 'description' => 'See who owes what, across all classes.'],
            'payments.parent_dashboard'=> ['name' => 'Parent Payment Dashboard', 'category' => 'Payment Management', 'plans' => self::PRO, 'description' => 'Parents see and pay their children\'s fees.'],
            'payments.enviiz_gateway'  => ['name' => 'ENVIIZ Payment Gateway', 'category' => 'Payment Management', 'plans' => self::ADDON, 'description' => 'Card payments through the ENVIIZ gateway.', 'addon' => ['key' => 'enviiz_gateway', 'name' => 'ENVIIZ Payment Gateway', 'pricing' => '+ LKR 2,000/month + 2–3% per transaction']],

            // Classes & Recordings
            'zoom.tutor_integration'      => ['name' => 'Tutor Zoom Integration', 'category' => 'Classes & Recordings', 'plans' => self::ALL, 'description' => 'Connect your Zoom and auto-create class meetings.'],
            'zoom.enviiz_account'         => ['name' => 'ENVIIZ Zoom Account', 'category' => 'Classes & Recordings', 'plans' => self::ADDON, 'description' => 'Use our licensed Zoom account.', 'addon' => ['key' => 'enviiz_zoom', 'name' => 'ENVIIZ Zoom Account', 'pricing' => 'LKR 1,500–2,000/month']],
            'recordings.upload'           => ['name' => 'Non-Secured Recordings (Tutor Upload)', 'category' => 'Classes & Recordings', 'plans' => self::ALL, 'description' => 'Share class recordings with students.'],
            'recordings.controlled_access'=> ['name' => 'Controlled Recording Access', 'category' => 'Classes & Recordings', 'plans' => self::PRO, 'description' => 'Limit how long each student can watch a recording.'],
            'recordings.expiry'           => ['name' => 'Expiry-Based Recording Access', 'category' => 'Classes & Recordings', 'plans' => self::PRO, 'description' => 'Recordings close automatically after a date.'],
            'recordings.secure_streaming' => ['name' => 'ENVIIZ Secure Streaming', 'category' => 'Classes & Recordings', 'plans' => self::ADDON, 'description' => 'DRM streaming with watermark.', 'addon' => ['key' => 'enviiz_streaming', 'name' => 'ENVIIZ Secure Streaming', 'pricing' => 'From LKR 1,500/month']],

            // Communication
            'comms.email'          => ['name' => 'Email Notifications', 'category' => 'Communication', 'plans' => self::ALL, 'description' => 'Email students and parents.'],
            'comms.whatsapp'       => ['name' => 'WhatsApp (Tutor API)', 'category' => 'Communication', 'plans' => self::GROW_UP, 'description' => 'Send WhatsApp messages with your API.'],
            'comms.sms'            => ['name' => 'SMS (Tutor Gateway)', 'category' => 'Communication', 'plans' => self::GROW_UP, 'description' => 'Send SMS with your gateway.'],
            'comms.enviiz_sms'     => ['name' => 'ENVIIZ SMS Service', 'category' => 'Communication', 'plans' => self::ADDON, 'description' => 'Pay-as-you-go SMS.', 'addon' => ['key' => 'enviiz_sms', 'name' => 'ENVIIZ SMS Service', 'pricing' => 'Pay-as-you-go']],
            'comms.enviiz_whatsapp'=> ['name' => 'ENVIIZ WhatsApp API', 'category' => 'Communication', 'plans' => self::ADDON, 'description' => 'WhatsApp without your own API.', 'addon' => ['key' => 'enviiz_whatsapp', 'name' => 'ENVIIZ WhatsApp API', 'pricing' => 'Conversation-based pricing']],

            // Attendance & ID
            'attendance.manual'  => ['name' => 'Manual Attendance', 'category' => 'Attendance & ID', 'plans' => self::ALL, 'description' => 'Mark attendance by hand.'],
            'attendance.qr'      => ['name' => 'QR-Based Attendance', 'category' => 'Attendance & ID', 'plans' => self::ALL, 'description' => 'Scan student QR codes.'],
            'attendance.export'  => ['name' => 'Attendance Reports Export', 'category' => 'Attendance & ID', 'plans' => self::GROW_UP, 'description' => 'Export attendance to Excel / PDF.'],
            'id.digital'         => ['name' => 'Digital Student ID (QR)', 'category' => 'Attendance & ID', 'plans' => self::GROW_UP, 'description' => 'Each student gets a QR ID card in the app.'],
            'id.physical_cards'  => ['name' => 'Physical ID Card Printing', 'category' => 'Attendance & ID', 'plans' => self::ADDON, 'description' => 'Printed PVC ID cards.', 'addon' => ['key' => 'physical_id', 'name' => 'Physical ID Card Setup', 'pricing' => 'One-time + per-card cost']],

            // Reporting & Analytics
            'reports.basic'          => ['name' => 'Basic Reports', 'category' => 'Reporting & Analytics', 'plans' => self::ALL, 'description' => 'Student and payment summaries.'],
            'reports.attendance'     => ['name' => 'Attendance Analytics', 'category' => 'Reporting & Analytics', 'plans' => self::GROW_UP, 'description' => 'Attendance trends per class.'],
            'reports.payments'       => ['name' => 'Payment Analytics', 'category' => 'Reporting & Analytics', 'plans' => self::GROW_UP, 'description' => 'Income trends and collection rates.'],
            'reports.class_advanced' => ['name' => 'Advanced Class Analytics', 'category' => 'Reporting & Analytics', 'plans' => self::PRO, 'description' => 'Exam, watch-time and engagement insights.'],

            // Team (not on the pricing sheet yet: GROW and PRO, to be confirmed)
            'staff.custom_roles' => ['name' => 'Staff Accounts & Custom Roles', 'category' => 'Team', 'plans' => self::GROW_UP, 'description' => 'Add assistants and choose exactly what each role can do.'],
        ];
    }

    /** Cheapest plan that includes the feature, or null for add-on-only features. */
    public static function lowestPlanFor(string $featureKey): ?string
    {
        $plans = self::features()[$featureKey]['plans'] ?? [];
        foreach (array_keys(self::PLANS) as $plan) {
            if (in_array($plan, $plans, true)) {
                return $plan;
            }
        }
        return null;
    }

    /** @return list<string> */
    public static function featuresForPlan(string $planKey): array
    {
        return array_keys(array_filter(self::features(), static fn ($f) => in_array($planKey, $f['plans'], true)));
    }
}
