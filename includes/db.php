<?php
/**
 * MovieBook - Database Connection
 * 
 * Provides PDO database connection with error handling.
 */

require_once __DIR__ . '/config.php';

/**
 * Get database connection
 * @return PDO|null Returns PDO connection or null on failure
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error and return null
            error_log("Database connection failed: " . $e->getMessage());
            return null;
        }
    }
    
    return $pdo;
}

// Create connection for immediate use
$pdo = getDBConnection();
