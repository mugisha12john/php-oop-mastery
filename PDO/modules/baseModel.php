<?php
/**
 * BaseModel - Abstract base class for all data models
 * Provides common CRUD operations and database access
 */

require_once __DIR__ . '/../config/config.php';

abstract class BaseModel {
    protected $db;
    protected $table;

    /**
     * Constructor - Initialize database connection
     * Note: Fixed typo from __consturct to __construct
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Execute a prepared statement
     */
    protected function execute($sql, $params = []) {
        try {
            $stmt = $this->db->prepare($sql);
            foreach (array_values($params) as $index => $value) {
                $type = is_int($value) ? PDO::PARAM_INT : ($value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue($index + 1, $value, $type);
            }
            $stmt->execute();
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Database error: " . $e->getMessage());
        }
    }

    /**
     * Fetch one record
     */
    protected function fetchOne($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch all records
     */
    protected function fetchAll($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get last inserted ID
     */
    protected function lastInsertId() {
        return $this->db->lastInsertId();
    }

    /**
     * Get row count affected by last operation
     */
    protected function rowCount($stmt) {
        return $stmt->rowCount();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        $this->db->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        $this->db->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        $this->db->rollBack();
    }
}
