<?php

class Sanitizador {

    public static function texto(string $valor): string {
        return htmlspecialchars(strip_tags(trim($valor)), ENT_QUOTES, 'UTF-8');
    }

    public static function email(string $email): string {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }

    public static function usuario(string $usuario): string {
        return preg_replace('/[^a-zA-Z0-9_]/', '', trim($usuario));
    }

    public static function entero(mixed $valor): int {
        return (int) filter_var($valor, FILTER_SANITIZE_NUMBER_INT);
    }

    public static function booleano(mixed $valor): bool {
        return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }

    public static function sexo(string $sexo): string {
        $sexo = strtoupper(trim($sexo));
        return in_array($sexo, ['M', 'F'], true) ? $sexo : 'M';
    }
}
