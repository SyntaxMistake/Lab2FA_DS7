<?php

class Database {
    private array $config;

    public function __construct() {
        $this->config = require __DIR__ . '/../config/database.php';
    }

    public function conectar(): PDO {
        try {
            $dsn = "mysql:host={$this->config['host']};dbname={$this->config['database']};charset={$this->config['charset']}";
            $pdo = new PDO($dsn, $this->config['user'], $this->config['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            return $pdo;
        } catch (PDOException $e) {
            die('Error crítico de conexión: ' . $e->getMessage());
        }
    }
}
