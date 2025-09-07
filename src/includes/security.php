<?php
/**
 * AIP Tracker - Security Functions
 */

class Security {
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        return isset($_SESSION[CSRF_TOKEN_NAME]) && 
               hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email format
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Hash password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate secure random string
     */
    public static function generateRandomString($length = 16) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Rate limiting check
     */
    public static function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300) {
        $key = 'rate_limit_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
        }
        
        $data = $_SESSION[$key];
        
        // Reset counter if time window expired
        if (time() - $data['first_attempt'] > $timeWindow) {
            $_SESSION[$key] = ['count' => 1, 'first_attempt' => time()];
            return true;
        }
        
        // Check if limit exceeded
        if ($data['count'] >= $maxAttempts) {
            return false;
        }
        
        // Increment counter
        $_SESSION[$key]['count']++;
        return true;
    }
    
    /**
     * Log security event
     */
    public static function logSecurityEvent($event, $details = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ];
        
        error_log('SECURITY: ' . json_encode($logEntry));
    }
}
?>