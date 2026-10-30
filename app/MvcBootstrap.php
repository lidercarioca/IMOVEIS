<?php
namespace App {

// Bootstrap mínimo compatível para endpoints que esperam App\Mvc* classes

// Disponibiliza funções de autenticação legadas e a conexão PDO global
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config/database.php';

    class MvcServices {
        public static function getDatabase() {
            global $pdo;
            return $pdo;
        }
    }
}

namespace App\Security {
    class Auth {
        public static function check() {
            return isset($_SESSION['user_id']);
        }

        public static function userId() {
            return $_SESSION['user_id'] ?? null;
        }

        public static function isAdmin() {
            if (!isset($_SESSION['role'])) {
                return false;
            }
            $role = strtolower(trim($_SESSION['role']));
            return $role === 'admin' || $role === 'administrator';
        }
    }
}
