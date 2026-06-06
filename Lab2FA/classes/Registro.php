<?php

require_once __DIR__ . '/../classes/Sanitizador.php';
require_once __DIR__ . '/../classes/TOTP.php';

class Registro {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // Verifica si el nombre de usuario ya existe en la BD
    public function usuarioExiste(string $usuario): bool {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM usuarios WHERE Usuario = :usuario LIMIT 1'
        );
        $stmt->execute([':usuario' => $usuario]);
        return $stmt->fetch() !== false;
    }

    // Verifica si el correo ya existe en la BD
    public function correoExiste(string $correo): bool {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM usuarios WHERE Correo = :correo LIMIT 1'
        );
        $stmt->execute([':correo' => $correo]);
        return $stmt->fetch() !== false;
    }

    // Valida que los campos cumplan las reglas de negocio
    private function validarDatos(array $d): array {
        foreach (['nombre', 'apellido', 'usuario', 'correo', 'password', 'confirmar_password'] as $campo) {
            if (trim($d[$campo] ?? '') === '') {
                return ['ok' => false, 'mensaje' => 'Todos los campos son obligatorios.'];
            }
        }

        if (!filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'mensaje' => 'El correo no tiene un formato válido.'];
        }

        if ($d['password'] !== $d['confirmar_password']) {
            return ['ok' => false, 'mensaje' => 'Las contraseñas no coinciden.'];
        }

        if (strlen($d['password']) < 6) {
            return ['ok' => false, 'mensaje' => 'La contraseña debe tener al menos 6 caracteres.'];
        }

        return ['ok' => true];
    }

    // Genera el hash bcrypt con cost 13
    private function hashContrasena(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 13]);
    }

    // Inserta el usuario en la tabla y retorna el id generado
    private function guardar(
        string $nombre,
        string $apellido,
        string $sexo,
        string $usuario,
        string $correo,
        string $hash,
        string $secreto
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO usuarios (Nombre, Apellido, Sexo, Usuario, Correo, HashMagic, secret_2fa)
             VALUES (:nombre, :apellido, :sexo, :usuario, :correo, :hash, :secreto)'
        );
        $stmt->execute([
            ':nombre'   => $nombre,
            ':apellido' => $apellido,
            ':sexo'     => $sexo,
            ':usuario'  => $usuario,
            ':correo'   => $correo,
            ':hash'     => $hash,
            ':secreto'  => $secreto,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // Registra la acción de registro en la tabla de trazabilidad
    private function trazabilidad(int $idUsuario, string $usuario): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO trazabilidad_acciones (Tabla, Acciones, CodigoRegistro, Usuario)
             VALUES (\'usuarios\', \'INSERT\', :id, :usuario)'
        );
        $stmt->execute([':id' => $idUsuario, ':usuario' => $usuario]);
    }

    // Orquesta el proceso completo de registro
    public function registrar(array $datos): array {
        $nombre   = Sanitizador::texto($datos['nombre']             ?? '');
        $apellido = Sanitizador::texto($datos['apellido']           ?? '');
        $sexo     = Sanitizador::sexo($datos['sexo']               ?? '');
        $usuario  = Sanitizador::usuario($datos['usuario']          ?? '');
        $correo   = Sanitizador::email($datos['correo']             ?? '');
        $password = $datos['password']                              ?? '';
        $confirmar = $datos['confirmar_password']                   ?? '';

        $val = $this->validarDatos([
            'nombre'             => $nombre,
            'apellido'           => $apellido,
            'usuario'            => $usuario,
            'correo'             => $correo,
            'password'           => $password,
            'confirmar_password' => $confirmar,
        ]);

        if (!$val['ok']) {
            return $val;
        }

        if ($this->usuarioExiste($usuario)) {
            return ['ok' => false, 'mensaje' => 'El nombre de usuario ya está en uso.'];
        }

        if ($this->correoExiste($correo)) {
            return ['ok' => false, 'mensaje' => 'El correo ya está registrado.'];
        }

        $hash    = $this->hashContrasena($password);
        $secreto = TOTP::generarSecreto();
        $id      = $this->guardar($nombre, $apellido, $sexo, $usuario, $correo, $hash, $secreto);

        $this->trazabilidad($id, $usuario);

        return [
            'ok'      => true,
            'mensaje' => 'Usuario registrado correctamente.',
            'usuario' => $usuario,
            'secreto' => $secreto,
        ];
    }
}
