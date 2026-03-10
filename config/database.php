<?php

class Database {
    private $conn = null;

    public function connect() {
        if ($this->conn !== null) return $this->conn;

        $host     = getenv('PGHOST')     ?: 'localhost';
        $port     = getenv('PGPORT')     ?: '5432';
        $dbname   = getenv('PGDATABASE') ?: 'test_lms';
        $user     = getenv('PGUSER')     ?: 'postgres';
        $password = getenv('PGPASSWORD') ?: 'Yuth2__6';

        try {
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
            $this->conn = new PDO($dsn, $user, $password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Database connection failed: " . $e->getMessage()]);
            exit();
        }

        return $this->conn;
    }
}