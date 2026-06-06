<?php

require_once __DIR__ . '/../classes/Sanitizador.php';
require_once __DIR__ . '/../classes/TOTP.php';

class Auth {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // Busca al usuario por nombre de usuario
    private function buscarUsuario(string $usuario): array|false {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM usuarios WHERE Usuario = :usuario LIMIT 1'
        );
        $stmt->execute([':usuario' => $usuario]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Detecta anomalía: >=5 intentos fallidos del mismo usuario o IP en los últimos 5 minutos
    private function detectarAnomalia(string $usuario): bool {
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM intentos_login
             WHERE (Usuario = :usuario OR ipRemoto = :ip)
               AND timestamp >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)'
        );
        $stmt->execute([':usuario' => $usuario, ':ip' => $ip]);
        return (int) $stmt->fetchColumn() >= 5;
    }

    // Registra un intento fallido en intentos_login
    private function registrarIntento(string $usuario): void {
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $anomalia = $this->detectarAnomalia($usuario) ? 1 : 0;
        $stmt     = $this->pdo->prepare(
            'INSERT INTO intentos_login (Usuario, ipRemoto, deteccion_anomalia)
             VALUES (:usuario, :ip, :anomalia)'
        );
        $stmt->execute([':usuario' => $usuario, ':ip' => $ip, ':anomalia' => $anomalia]);
    }

    // Establece la sesión autenticada después de pasar el 2FA
    private function establecerSesion(array $user): void {
        session_regenerate_id(true);
        $_SESSION['autenticado']    = 'SI';
        $_SESSION['fase_qr_ok']     = true;
        $_SESSION['usuario_id']     = $user['id'];
        $_SESSION['usuario']        = $user['Usuario'];
        $_SESSION['usuario_nombre'] = $user['Nombre'];
        unset(
            $_SESSION['pre_2fa'],
            $_SESSION['usuario_temporal_id'],
            $_SESSION['usuario_temporal']
        );
    }

    // Registra el login exitoso en trazabilidad_acciones
    private function trazabilidad(int $idUsuario, string $usuario): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO trazabilidad_acciones (Tabla, Acciones, CodigoRegistro, Usuario)
             VALUES (\'usuarios\', \'LOGIN\', :id, :usuario)'
        );
        $stmt->execute([':id' => $idUsuario, ':usuario' => $usuario]);
    }

    // Verifica credenciales (usuario + contraseña) y crea sesión pre-2FA
    public function login(string $usuario, string $password): array {
        $usuario = Sanitizador::usuario($usuario);
        $user    = $this->buscarUsuario($usuario);

        if (!$user || !password_verify($password, $user['HashMagic'])) {
            $this->registrarIntento($usuario);
            return ['ok' => false, 'mensaje' => 'Usuario o contraseña incorrectos.'];
        }

        $_SESSION['pre_2fa']             = true;
        $_SESSION['usuario_temporal_id'] = $user['id'];
        $_SESSION['usuario_temporal']    = $user['Usuario'];

        return ['ok' => true, 'mensaje' => 'Contraseña correcta. Verifica tu código 2FA.'];
    }

    // Verifica el código TOTP y completa la autenticación
    public function verificar2FA(string $codigo): bool {
        if (!isset($_SESSION['usuario_temporal_id'])) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM usuarios WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $_SESSION['usuario_temporal_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        if (!TOTP::validarCodigo($user['secret_2fa'], $codigo)) {
            $this->registrarIntento($user['Usuario']);
            return false;
        }

        $this->establecerSesion($user);
        $this->trazabilidad($user['id'], $user['Usuario']);

        return true;
    }
}
