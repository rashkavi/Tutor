<?php
declare(strict_types=1);

namespace Enlixo\Http\Auth;

use Enlixo\Core\AuthContext;
use Enlixo\Core\Request;

/**
 * Uses the login the existing pages already create (includes/UserClass.php → SessionUser),
 * so /api/v1 works for anyone signed in to tutor/, portal/ or admin/ without a second login.
 */
final class LegacySessionAuthenticator implements Authenticator
{
    public function __construct(private readonly string $userClassFile)
    {
    }

    public function authenticate(Request $request): ?AuthContext
    {
        require_once $this->userClassFile; // starts the PHP session

        if (\SessionUser::ifUserIsTutor()) {
            $u = \SessionUser::getCurrentUserObject('Tutor');
            return new AuthContext((int) $u['user_id'], 'tutor', (int) $u['user_id']);
        }
        if (\SessionUser::ifUserIsStudent()) {
            $u = \SessionUser::getCurrentUserObject('Student');
            return new AuthContext((int) ($u['login_details_id'] ?? 0), 'student', null, (int) $u['student_id']);
        }
        if (\SessionUser::ifUserIsAdmin()) {
            $u = \SessionUser::getCurrentUserObject('Admin');
            return new AuthContext((int) $u['user_id'], 'admin');
        }
        return null;
    }
}
