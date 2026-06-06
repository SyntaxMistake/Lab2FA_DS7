<?php

class CSRF {
    public static function generarToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function validarToken(?string $token): bool {
        return isset($_SESSION['csrf_token']) &&
               isset($token) &&
               hash_equals($_SESSION['csrf_token'], $token);
    }
}