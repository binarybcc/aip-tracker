<?php
/**
 * Nexcess Hosting Configuration
 * Copy this to config/config.php and update with your actual values
 */

// Environment configuration
define('APP_NAME', 'AIP Tracker');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'https://yourdomain.com/aip-tracker/'); // Update with your domain

// Security settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 86400); // 24 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 1800); // 30 minutes

// Nexcess Database Configuration
// Update these with your actual database credentials from Nexcess
define('DB_HOST', 'localhost'); // Usually localhost on Nexcess
define('DB_NAME', 'your_database_name'); // Your database name
define('DB_USER', 'your_db_username'); // Your database username
define('DB_PASS', 'your_db_password'); // Your database password

// Timezone - Update to your timezone
date_default_timezone_set('America/New_York');

// Error reporting - IMPORTANT: Set to false for production
define('DEBUG_MODE', false);
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Session configuration for shared hosting
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_lifetime', SESSION_LIFETIME);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS'])); // Enable for HTTPS
ini_set('session.use_strict_mode', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/helpers.php';

// Optional: Set memory and execution limits for shared hosting
ini_set('memory_limit', '128M');
ini_set('max_execution_time', 30);

// File upload limits (if needed in future)
ini_set('upload_max_filesize', '2M');
ini_set('post_max_size', '2M');
ini_set('max_file_uploads', 5);
?>