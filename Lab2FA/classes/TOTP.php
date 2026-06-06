<?php

class TOTP {
    private static string $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generarSecreto(int $length = 16): string {
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Chars[random_int(0, strlen(self::$base32Chars) - 1)];
        }

        return $secret;
    }

    private static function base32Decode(string $base32): string {
        $base32 = strtoupper($base32);
        $binary = '';
        $buffer = 0;
        $bitsLeft = 0;

        for ($i = 0; $i < strlen($base32); $i++) {
            $char = $base32[$i];

            if ($char === '=') {
                break;
            }

            $value = strpos(self::$base32Chars, $char);

            if ($value === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $value;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $binary .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $binary;
    }

    public static function generarCodigo(string $secret, ?int $timeSlice = null): string {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretKey = self::base32Decode($secret);

        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);

        $offset = ord(substr($hash, -1)) & 0x0F;

        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $otp = $binary % 1000000;

        return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
    }

    public static function validarCodigo(string $secret, string $codigo): bool {
        $codigo = trim($codigo);

        for ($i = -1; $i <= 1; $i++) {
            if (self::generarCodigo($secret, floor(time() / 30) + $i) === $codigo) {
                return true;
            }
        }

        return false;
    }

    public static function generarURLQR(string $usuario, string $secret): string {
        $issuer = "Lab2FA";
        $label = rawurlencode($issuer . ":" . $usuario);

        $otpauth = "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}";

        return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpauth);
    }
}