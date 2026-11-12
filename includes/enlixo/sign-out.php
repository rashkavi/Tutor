<?php
/**
 * One sign-out for every role (tutor/, admin/, portal/ = Student, parent/), used by each folder's api/signOut.php:
 *     enlixo_sign_out('Tutor');
 *
 * - Signs out only the role of that folder. One browser can be signed in as admin and tutor at the same time;
 *   signing out of the tutor side leaves the admin side signed in, and the other way round.
 *   (Before this, admin/api/signOut.php signed out the tutor, and parent/api/signOut.php never signed the parent out.)
 * - Marks this device as no longer active (tutors and admins have a users_devices row).
 * - Clears the "just signed in" cookie so the welcome card does not show for the next person.
 * - Answers a fetch() with JSON and a normal click with a redirect to that folder's sign-in page.
 */
require_once dirname(__DIR__) . '/UserClass.php';
require_once dirname(__DIR__) . '/connection.php';

function enlixo_sign_out(string $role): void
{
    $cookies = ['Tutor' => 'enviiz_lms_user_login', 'Admin' => 'enviiz_lms_admin_login', 'Student' => 'enviiz_lms_student_login', 'Parent' => 'enviiz_lms_parent_login'];
    $user = SessionUser::getCurrentUserObject($role);

    if ($user && in_array($role, ['Tutor', 'Admin'], true) && !empty($user['user_id'])) {
        try {
            $agentFile = dirname(__DIR__, 2) . '/vendor/autoload.php';
            if (is_file($agentFile)) {
                require_once $agentFile;
                $agent = new Jenssegers\Agent\Agent();
                $deviceType = $agent->isDesktop() ? 'Desktop' : ($agent->isPhone() ? 'Mobile' : 'Tablet');
                Database::setUpConnection();
                $stmt = Database::$connection->prepare(
                    'UPDATE users_devices SET is_active = 0 WHERE users_user_id = ? AND device_name = ? AND device_type = ? AND browser = ? AND os = ?'
                );
                if ($stmt) {
                    $id = (int) $user['user_id'];
                    $name = (string) $agent->device();
                    $browser = (string) $agent->browser();
                    $os = (string) $agent->platform();
                    $stmt->bind_param('issss', $id, $name, $deviceType, $browser, $os);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } catch (Throwable $e) {
            error_log('Sign out (device): ' . $e->getMessage()); // never stop a sign out because of this
        }
    }

    SessionUser::destroySession($role);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    if (isset($cookies[$role]) && isset($_COOKIE[$cookies[$role]])) {
        setcookie($cookies[$role], '', time() - 3600, '/');
    }

    $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode(['done' => true, 'redirect' => 'index']);
        exit;
    }
    header('Location: ../index.php');
    exit;
}
