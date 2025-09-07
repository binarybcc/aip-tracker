<?php
/**
 * AIP Tracker - Database Configuration
 * Optimized for Nexcess shared hosting
 */

class Database {
    // Database credentials from config
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn = null;
    
    public function __construct() {
        $this->host = DB_HOST;
        $this->db_name = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
    }

    /**
     * Get database connection
     */
    public function connect() {
        if ($this->conn === null) {
            try {
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            } catch(PDOException $e) {
                error_log("Database connection error: " . $e->getMessage());
                throw new Exception("Database connection failed");
            }
        }
        return $this->conn;
    }

    /**
     * Close database connection
     */
    public function disconnect() {
        $this->conn = null;
    }
}
?>