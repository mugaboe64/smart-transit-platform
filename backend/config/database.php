<?php

class Database
{
    private string $host = "localhost";
    private string $db_name = "smart_transit";
    private string $username = "root";
    private string $password = "";

    public function connect(): PDO
    {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";

            $pdo = new PDO(
                $dsn,
                $this->username,
                $this->password
            );

            $pdo->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            $pdo->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );

            return $pdo;

        } catch (PDOException $e) {
            http_response_code(500);

            die("Database connection failed: " . $e->getMessage());
        }
    }
}