<?php
class CSRF {
    public static function generateToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyToken($token) {
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            throw new Exception('CSRF token validation failed');
        }
        return true;
    }

    public static function getHiddenInput() {
        return '<input type="hidden" name="csrf_token" value="' . self::generateToken() . '">';
    }
}
?>
