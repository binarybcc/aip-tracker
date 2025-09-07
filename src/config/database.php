<?php
/**
 * AIP Tracker - Database Configuration
 * Version 0.2.1 - PHP 8.2+ Optimized with Readonly Classes
 * Optimized for Nexcess shared hosting
 */

/**
 * Readonly database connection configuration (PHP 8.2+)
 * Provides immutable database settings with type safety
 */
readonly class DatabaseConnection
{
    public function __construct(
        public string $host,
        public string $name,
        public string $user,
        public string $password,
        public string $charset = 'utf8mb4'
    ) {
        // Validation in readonly constructor
        if (empty($this->host) || empty($this->name) || empty($this->user)) {
            throw new InvalidArgumentException('Database configuration cannot have empty required fields');
        }
    }

    /**
     * Create database connection from constants
     */
    public static function fromConstants(): self
    {
        return new self(
            host: DB_HOST,
            name: DB_NAME,
            user: DB_USER,
            password: DB_PASS
        );
    }

    /**
     * Get DSN string for PDO connection
     */
    public function getDSN(): string
    {
        return "mysql:host={$this->host};dbname={$this->name};charset={$this->charset}";
    }

    /**
     * Get PDO options optimized for PHP 8.2+
     */
    public function getPDOOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset} COLLATE {$this->charset}_unicode_ci"
        ];
    }
}

class Database {
    private DatabaseConnection $config;
    private ?PDO $conn = null;
    
    public function __construct(DatabaseConnection $config = null) {
        $this->config = $config ?? DatabaseConnection::fromConstants();
    }

    /**
     * Get database connection with PHP 8.2+ optimizations
     */
    public function connect(): PDO {
        if ($this->conn === null) {
            try {
                $this->conn = new PDO(
                    $this->config->getDSN(),
                    $this->config->user,
                    $this->config->password,
                    $this->config->getPDOOptions()
                );
                
                // PHP 8.2+ performance optimizations
                if (PHP_VERSION_ID >= 80200) {
                    $this->conn->setAttribute(PDO::ATTR_PERSISTENT, false);
                    $this->conn->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
                }
            } catch(PDOException $e) {
                error_log("Database connection error: " . $e->getMessage());
                throw new Exception("Database connection failed: " . $e->getMessage());
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