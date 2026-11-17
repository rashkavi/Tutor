<?php
/**
 * Staff members of a tutor (Settings → Staff & Roles, src/Http/Controllers/StaffController.php) on the older pages.
 *
 * A staff member signs in on the tutor sign-in page (tutor/api/signInWithEmail.php). The session is a Tutor session
 * whose user_id is the EMPLOYER tutor (so every older page shows the tutor's classes and students), plus
 *   staff_user_id   their own users.user_id (the API then works as AuthContext kind 'staff', see
 *                   LegacySessionAuthenticator, and gives only what their role allows)
 *   staff_role_id   their tutor_roles row
 * enlixo_staff_page_allowed() keeps them out of pages their role does not cover and out of the tutor's own
 * subscription / billing pages; the side menu hides those items.
 */
if (!function_exists('enlixo_staff_employer')) {
    /** The tutor this staff member works for, when the link, both accounts and the tutor's GROW feature are on. */
    function enlixo_staff_employer(int $staffUserId): ?array
    {
        require_once dirname(__DIR__, 2) . '/src/Legacy/Bridge.php';
        $app = \Enlixo\Legacy\Bridge::app();
        if ($app === null || $staffUserId <= 0) {
            return null;
        }
        try {
            $row = $app->db()->one(
                'SELECT s.tutor_id, s.tutor_roles_tutor_role_id role_id, t.user_fname, t.user_lname
                   FROM tutor_staff s JOIN users t ON t.user_id = s.tutor_id
                  WHERE s.staff_user_id = :u AND s.status_status_id = 1 AND t.status_status_id = 1
                  ORDER BY s.tutor_staff_id DESC LIMIT 1',
                ['u' => $staffUserId],
            );
            if (!$row || !$app->entitlements()->has((int) $row['tutor_id'], 'staff.custom_roles')) {
                return null;
            }
            return ['tutor_id' => (int) $row['tutor_id'], 'role_id' => (int) $row['role_id'], 'tutor_name' => trim($row['user_fname'] . ' ' . $row['user_lname'])];
        } catch (\Throwable $e) {
            error_log('Staff employer: ' . $e->getMessage());
            return null;
        }
    }

    /** @return list<string> permission keys the staff member has now (role ∩ the tutor's plan) */
    function enlixo_staff_permissions(int $tutorId, int $staffUserId): array
    {
        static $cache = [];
        $key = $tutorId . ':' . $staffUserId;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        require_once dirname(__DIR__, 2) . '/src/Legacy/Bridge.php';
        $app = \Enlixo\Legacy\Bridge::app();
        if ($app === null) {
            return $cache[$key] = [];
        }
        try {
            return $cache[$key] = $app->permissions()->effective(new \Enlixo\Core\AuthContext($staffUserId, 'staff', $tutorId));
        } catch (\Throwable $e) {
            return $cache[$key] = [];
        }
    }

    /**
     * The permission a page needs. null = only the tutor (subscription, billing, settings that change the account).
     * Pages that are not listed are open to every staff member (dashboard, profile, sign-out).
     */
    function enlixo_staff_page_permission(string $page): ?string
    {
        static $map = [
            'students' => 'students.view', 'student-application' => 'students.view', 'requested-students' => 'students.view',
            'add-student' => 'students.manage', 'add-student-to-class' => 'students.manage', 'add-registered-student-to-class' => 'students.manage',
            'edit-student' => 'students.manage', 'upload-student-bulk' => 'students.manage',
            'classes' => 'classes.view', 'class-details' => 'classes.view', 'class-students' => 'classes.view', 'class-tutes' => 'classes.view',
            'create-class' => 'classes.manage', 'edit-class' => 'classes.manage',
            'time-table' => 'classes.view', 'class-time-table' => 'classes.view', 'lecture-details' => 'classes.view',
            'schedule-class' => 'timetable.manage', 'edit-lecture' => 'timetable.manage',
            'recordings' => 'recordings.view', 'class-recordings' => 'recordings.view', 'class-recording' => 'recordings.view', 'recording-watch' => 'recordings.view',
            'watch-recording' => 'recordings.view', 'class-pre-recordings' => 'recordings.view', 'watch-pre-recording' => 'recordings.view',
            'add-recording' => 'recordings.manage', 'edit-recording' => 'recordings.manage', 'upload-lecture-recording' => 'recordings.manage',
            'add-pre-recorded-class' => 'recordings.manage', 'edit-pre-recording' => 'recordings.manage',
            'tutes' => 'tutes.manage', 'add-tute' => 'tutes.manage', 'downloadable-tute' => 'tutes.manage', 'couriered-tute' => 'tutes.manage',
            'couriered-tute-view' => 'tutes.manage', 'tute-parcel' => 'tutes.manage',
            'attendance' => 'attendance.mark', 'attendance-dashboard' => 'attendance.mark', 'mark-attendance' => 'attendance.mark',
            'class-attendance' => 'attendance.mark', 'time-table-attendance' => 'attendance.mark',
            'fees' => 'payments.view', 'student-fees' => 'payments.view', 'fee-details' => 'payments.view', 'fee-receipt' => 'payments.view',
            'payments' => 'payments.view', 'class-payments' => 'payments.view', 'student-payment' => 'payments.view',
            'record-payment' => 'payments.approve', 'fee-concession' => 'fees.manage', 'class-payment-plans' => 'fees.manage',
            'add-payment-plan' => 'fees.manage', 'edit-payment-plan' => 'fees.manage',
            'message-center' => 'messages.send', 'send-message' => 'messages.send', 'message' => 'messages.send',
            'intergrations' => 'settings.integrations', 'zoom-connect' => 'settings.integrations', 'whatsapp-settings' => 'settings.integrations',
            'staff' => 'settings.staff',
        ];
        static $ownerOnly = ['subscription', 'subscription-payments', 'subscription-payment-details', 'upgrade', 'pricing', 'billing', 'checkout',
            'invoice', 'renew', 'pay-subscription-renewal', 'payment-pending', 'class-form-settings', 'student-form-settings', 'fee-settings',
            'attendance-settings', 'recording-types', 'my-profile'];
        if (in_array($page, $ownerOnly, true)) {
            return null;
        }
        return $map[$page] ?? '';
    }

    /** Is the signed-in Tutor session a staff member, and may they open this page? true for the tutor. */
    function enlixo_staff_page_allowed(array $session, string $page): bool
    {
        $staff = (int) ($session['staff_user_id'] ?? 0);
        if ($staff <= 0) {
            return true;
        }
        $need = enlixo_staff_page_permission($page);
        if ($need === null) {
            return false;
        }
        return $need === '' || in_array($need, enlixo_staff_permissions((int) $session['user_id'], $staff), true);
    }
}
