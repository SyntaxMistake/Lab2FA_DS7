<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Sanitizador.php';

$db  = new Database();
$pdo = $db->conectar();

$campo = $_GET['campo'] ?? '';
$valor = trim($_GET['valor'] ?? '');

switch ($campo) {
    case 'usuario':
        $valor   = Sanitizador::usuario($valor);
        $columna = 'Usuario';
        $error   = 'El usuario ya está en uso.';
        break;
    case 'correo':
        $valor   = Sanitizador::email($valor);
        $columna = 'Correo';
        $error   = 'El correo ya está registrado.';
        break;
    default:
        echo json_encode(true);
        exit;
}

if ($valor === '') {
    echo json_encode(true);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE {$columna} = :valor LIMIT 1");
$stmt->execute([':valor' => $valor]);

// true  → disponible (válido para jQuery Validate)
// string → no disponible (jQuery Validate lo muestra como mensaje de error)
echo json_encode($stmt->fetch() === false ? true : $error);
