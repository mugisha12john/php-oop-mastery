<?php
/**
 * Database Configuration for MLMS
 * Centralized config with connection factory
 */

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $dsn = getenv('DB_DSN') ?: "mysql:host=localhost;dbname=money_lending_db;charset=utf8mb4";
        $user = getenv('DB_USER') ?: "app_user";
        $password = getenv('DB_PASSWORD');
        if ($password === false) {
            $password = "buban1234";
        }

        try {
            $this->connection = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            if (PHP_SAPI === 'cli') {
                throw $e;
            }
            http_response_code(503);
            exit('Database unavailable. Please check the database configuration.');
        }
    }

    /**
     * Singleton pattern - get database connection
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    /**
     * Get PDO connection
     */
    public function getConnection() {
        return $this->connection;
    }
}

// Global connection for legacy compatibility (optional)
$conn = Database::getInstance()->getConnection();
