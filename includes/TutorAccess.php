<?php
/**
 * Students and parents of a tutor whose ENLIXO subscription has ended (suspended or cancelled) cannot sign in or
 * use the portal; their data is kept and everything opens again as soon as the tutor pays or ENLIXO reactivates
 * them (SaaS practice: the school goes offline, nothing is deleted).
 *   tutor_access_student(int $studentId) / tutor_access_parent(int $parentId) -> true when at least one of the
 *   student's (or children's) active classes belongs to a tutor whose subscription is on.
 * Before migration 007 (no status column) everyone is allowed. Used by portal/ and parent/ sign-in and side menus.
 */
require_once __DIR__ . '/connection.php';

const TUTOR_ACCESS_CLOSED = ['suspended', 'cancelled'];
const PARENT_PLAN_MESSAGE = "Parent login is not part of your tutor's ENLIXO plan yet, so you get class updates by WhatsApp or e-mail instead. Ask your tutor about the parent app.";
const TUTOR_ACCESS_MESSAGE = "Your tutor's ENLIXO account is paused at the moment, so the class portal is closed. Nothing is lost: it opens again as soon as your tutor renews. Please contact your tutor.";

if (!function_exists('tutor_access_student')) {
    function tutor_access_open_sql(string $studentIdsSql): string
    {
        // the latest subscription row of each tutor decides; tutors without a row (old data) count as open
        return "SELECT 1 FROM students_has_classes h
                  JOIN classes c ON c.class_id = h.classes_class_id
                 WHERE h.students_student_id IN ($studentIdsSql) AND h.status_status_id = 1
                   AND COALESCE((SELECT s.status FROM users_has_payment_plans s WHERE s.users_user_id = c.tutor_id ORDER BY s.users_has_payment_plan_id DESC LIMIT 1), 'active')
                       NOT IN ('" . implode("','", TUTOR_ACCESS_CLOSED) . "')
                 LIMIT 1";
    }

    /** No active class at all is not a lock (the sign-in already says "no access to any course"). */
    function tutor_access_student(int $studentId): bool
    {
        try {
            $any = Database::search("SELECT 1 FROM students_has_classes WHERE students_student_id = '" . $studentId . "' AND status_status_id = 1 LIMIT 1");
            if (!$any || $any->num_rows === 0) {
                return true;
            }
            $r = Database::search(tutor_access_open_sql("'" . $studentId . "'"));
            return $r && $r->num_rows > 0;
        } catch (\Throwable $e) {
            return true; // before migration 007
        }
    }

    function tutor_access_parent(int $parentId): bool
    {
        try {
            $kids = "SELECT students_student_id FROM parents_has_students WHERE parents_parent_id = '" . $parentId . "'";
            $any = Database::search("SELECT 1 FROM students_has_classes WHERE students_student_id IN ($kids) AND status_status_id = 1 LIMIT 1");
            if (!$any || $any->num_rows === 0) {
                return true;
            }
            $r = Database::search(tutor_access_open_sql($kids));
            return $r && $r->num_rows > 0;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Parent login is a STARTER (and up) feature: a parent can sign in when at least one tutor of their children's
     * active classes has 'parents.login' in their plan. FREE tutors' parents get WhatsApp / e-mail notices instead.
     */
    function tutor_parent_login_allowed(int $parentId): bool
    {
        try {
            $kids = "SELECT students_student_id FROM parents_has_students WHERE parents_parent_id = '" . $parentId . "'";
            $r = Database::search("SELECT DISTINCT c.tutor_id FROM students_has_classes h JOIN classes c ON c.class_id = h.classes_class_id
                                    WHERE h.students_student_id IN ($kids) AND h.status_status_id = 1");
            if (!$r || $r->num_rows === 0) {
                return true; // no class yet: the sign-in already says so
            }
            require_once dirname(__DIR__) . '/src/Legacy/Bridge.php';
            $app = \Enlixo\Legacy\Bridge::app();
            if ($app === null) {
                return true;
            }
            while ($row = $r->fetch_assoc()) {
                if ($app->entitlements()->has((int) $row['tutor_id'], 'parents.login')) {
                    return true;
                }
            }
            return false;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /** On a portal page: sign the person out and send them to the sign-in page with the reason. */
    function tutor_access_close_session(string $signInUrl): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        header('Location: ' . $signInUrl . '?paused=1');
        echo '<script>location.replace(' . json_encode($signInUrl . '?paused=1') . ');</script>';
        exit;
    }
}
