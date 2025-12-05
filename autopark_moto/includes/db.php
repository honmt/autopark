<?php
// includes/db.php

class Database {
    private $host = "localhost";
    private $db_name = "autopark_moto";
    private $username = "root";
    private $password = "";
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            echo "Connection error: " . $e->getMessage();
            die();
        }
        
        return $this->conn;
    }
}

// Функция для получения соединения с БД
function getDBConnection() {
    static $db = null;
    
    if ($db === null) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    return $db;
}
?>