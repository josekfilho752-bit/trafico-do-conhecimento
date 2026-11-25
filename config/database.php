<?php
declare(strict_types=1);

class Database
{
    private string $host;
    private string $dbName;
    private string $username;
    private string $password;
    private ?PDO $conn = null;

    public function __construct()
    {
        $this->host     = getenv('DB_HOST') ?: 'db';
        $this->dbName   = getenv('DB_NAME') ?: 'trafico_conhecimento';
        $this->username = getenv('DB_USER') ?: 'user_livraria';
        $this->password = getenv('DB_PASS') ?: 'senha_segura_123';
    }

    public function getConnection(): PDO
    {
        if ($this->conn instanceof PDO) {
            return $this->conn;
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $this->host,
            $this->dbName
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
        ];

        $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        return $this->conn;
    }
}
