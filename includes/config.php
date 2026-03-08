<?php
/**
 * MovieBook - Database Configuration
 * 
 * This file contains database connection settings and global configuration.
 */

// Error reporting - log errors but don't display in UI
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Suppress display in browser
ini_set('log_errors', 1);       // Log to PHP error log
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Default XAMPP MySQL has no password
define('DB_NAME', 'moviebook');

// Application Configuration
define('SITE_NAME', 'MovieBook');
define('SITE_URL', 'http://localhost/Moviebook');

// Session Configuration
define('SESSION_LIFETIME', 86400); // 24 hours in seconds
