<?php
/**
 * AIP Tracker - Main Configuration
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Environment configuration
define('APP_NAME', 'AIP Tracker');
define('APP_VERSION', '0.2.0');
define('BASE_URL', 'http://localhost:8080'); // Local development

// Security settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 86400); // 24 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 1800); // 30 minutes

// Database settings
define('DB_HOST', 'db');
define('DB_NAME', 'aip_tracker');
define('DB_USER', 'aip_user');
define('DB_PASS', 'aip_secure_pass_2024');

// Timezone
date_default_timezone_set('America/New_York'); // Update as needed

// Error reporting (disable in production)
if ($_SERVER['HTTP_HOST'] === 'localhost') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    define('DEBUG_MODE', true);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    define('DEBUG_MODE', false);
}

// Include required files
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/helpers.php';
?>