<?php
declare(strict_types=1);

namespace Enlixo\Http\Auth;

use Enlixo\Core\AuthContext;
use Enlixo\Core\Request;

interface Authenticator
{
    public function authenticate(Request $request): ?AuthContext;
}
