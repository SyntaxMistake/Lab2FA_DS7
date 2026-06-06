<?php
session_start();

if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== 'SI') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../classes/CSRF.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

if (!CSRF::validarToken($_POST['csrf_token'] ?? null)) {
    echo json_encode(['error' => 'Token CSRF inválido']);
    exit;
}

$accion   = $_POST['accion']   ?? '';
$password = $_POST['password'] ?? '';

if ($accion === 'generar') {
    if ($password === '') {
        echo json_encode(['error' => 'Ingresa una contraseña']);
        exit;
    }
    echo json_encode(['hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 13])]);
    exit;
}

if ($accion === 'validar') {
    $hashIngresado = $_POST['hash_ingresado'] ?? '';
    if ($password === '' || $hashIngresado === '') {
        echo json_encode(['error' => 'Completa ambos campos']);
        exit;
    }
    echo json_encode(['coincide' => password_verify($password, $hashIngresado)]);
    exit;
}

echo json_encode(['error' => 'Acción desconocida']);
