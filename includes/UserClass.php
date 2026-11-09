<?php
session_start();

class SessionUser
{

    private static $proRoles = ['Admin'];

    private static $userRoles = [
        "Admin" => ["role_key" => "enviiz_lms_admin", "signed_in_key" => "enviiz_lms_admin_signed_in"],
        "Tutor" => ["role_key" => "enviiz_lms_tutor", "signed_in_key" => "enviiz_lms_tutor_signed_in"],
        "Student" => ["role_key" => "enviiz_lms_student", "signed_in_key" => "enviiz_lms_student_signed_in"],
        "Parent" => ["role_key" => "enviiz_lms_parent", "signed_in_key" => "enviiz_lms_parent_signed_in"],
    ];

    public static function hasValidSession()
    {
        foreach (self::$userRoles as $role => $sessionKeys) {
            if (!empty($_SESSION[$sessionKeys['role_key']]) && !empty($_SESSION[$sessionKeys['signed_in_key']])) {
                if (self::getCurrentUserRole('Tutor') === "Tutor" || self::getCurrentUserRole('Admin') === "Admin") {
                    return true;
                }
            }
        }
        return false;
    }


    public static function startSession($userRole, $data)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);

        if (isset(self::$userRoles[$userRole])) {
            $_SESSION[self::$userRoles[$userRole]['signed_in_key']] = true;
            $_SESSION[self::$userRoles[$userRole]['role_key']] = $data;
        }
    }

    // Get current user role
    public static function getCurrentUserRole($userRole)
    {
        foreach (self::$userRoles as $role => $sessionKeys) {
            if (!empty($_SESSION[$sessionKeys['signed_in_key']]) && $_SESSION[$sessionKeys['signed_in_key']] === true) {
                if ($userRole == $role) {
                    return $role;
                }
            }
        }
        return null;
    }

    // Check if user is in a pro role
    public static function ifUserIsPro($userRole)
    {
        return in_array($userRole, self::$proRoles, true);
    }

    public static function ifUserIsAdmin()
    {
        return self::getCurrentUserRole('Admin') === "Admin";
    }

    public static function ifUserIsTutor()
    {
        return self::getCurrentUserRole('Tutor') === "Tutor";
    }

    public static function ifUserIsStudent()
    {
        return self::getCurrentUserRole('Student') === "Student";
    }

    public static function ifUserIsParent()
    {
        return self::getCurrentUserRole('Parent') === "Parent";
    }

    public static function getCurrentUserObject($userRole)
    {
        foreach (self::$userRoles as $role => $sessionKeys) {
            if (!empty($_SESSION[$sessionKeys['signed_in_key']]) && $_SESSION[$sessionKeys['signed_in_key']] === true) {
                if ($userRole == $role) {
                    return $_SESSION[$sessionKeys['role_key']];
                }
            }
        }
        return null;
    }

    // Destroy the session
    public static function destroySession($userRole)
    {
        foreach (self::$userRoles as $role => $sessionKeys) {
            if ($userRole == $role) {
                unset($_SESSION[$sessionKeys['signed_in_key']]);
                unset($_SESSION[$sessionKeys['role_key']]);
            }
        }
    }

    public static function updateSession($role, $data)
    {
        if (array_key_exists($role, self::$userRoles)) {
            if (isset($_SESSION[self::$userRoles[$role]['signed_in_key']]) && $_SESSION[self::$userRoles[$role]['signed_in_key']] === true) {
                $_SESSION[self::$userRoles[$role]['role_key']] = $data;
            }
        }
    }
}
