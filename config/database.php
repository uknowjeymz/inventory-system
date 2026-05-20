<?php
class Database {
    private $host = "localhost";
    private $db_name = "u557699544_ucc_inventory";
    private $username = "u557699544_ucc_inventory";
    private $password = "@J4p8uoE";
    public $conn;

     public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                                   $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // IMPORTANT: Set timezone to Asia/Manila for this connection
            $this->conn->exec("SET time_zone = '+08:00'");
            
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>