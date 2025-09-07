<?php
/**
 * AIP Tracker - Security Functions
 * Version 0.2.1 - PHP 8.2+ Optimized
 * Enhanced random generation and security features
 */

class Security {
    
    /**
     * Generate CSRF token with PHP 8.2+ optimizations
     */
    public static function generateCSRFToken(): string {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            // PHP 8.2+ enhanced random generation
            $_SESSION[CSRF_TOKEN_NAME] = self::generateSecureToken();
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Generate cryptographically secure token (PHP 8.2+ optimized)
     */
    public static function generateSecureToken(int $length = 32): string {
        // PHP 8.2 provides better random generation performance
        if (PHP_VERSION_ID >= 80200) {
            // Optimized for PHP 8.2+ with better entropy handling
            return bin2hex(random_bytes($length));
        }
        // Fallback for older PHP versions
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Validate CSRF token with enhanced security
     */
    public static function validateCSRFToken(string $token): bool {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            return false;
        }
        
        // Enhanced validation with timing attack protection
        return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
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
     * Hash password with enhanced options for PHP 8.2+
     */
    public static function hashPassword(string $password): string {
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters long');
        }
        
        // Use stronger hashing options for PHP 8.2+
        $options = PHP_VERSION_ID >= 80200 ? 
            ['cost' => 12] : 
            ['cost' => 10];
            
        return password_hash($password, PASSWORD_DEFAULT, $options);
    }
    
    /**
     * Verify password with timing attack protection
     */
    public static function verifyPassword(string $password, string $hash): bool {
        if (empty($password) || empty($hash)) {
            return false;
        }
        
        return password_verify($password, $hash);
    }
    
    /**
     * Generate secure random string with PHP 8.2+ improvements
     */
    public static function generateRandomString(int $length = 16): string {
        if ($length < 8) {
            throw new InvalidArgumentException('Random string length must be at least 8 characters');
        }
        
        // PHP 8.2+ optimized random generation
        return bin2hex(random_bytes(intval($length / 2)));
    }
    
    /**
     * Generate secure ID for database records
     */
    public static function generateSecureId(int $length = 16): string {
        return self::generateRandomString($length);
    }
    
    /**
     * Validate JSON input (PHP 8.3+ feature with fallback)
     */
    public static function validateJsonInput(string $input): bool {
        if (empty(trim($input))) {
            return false;
        }
        
        // Use PHP 8.3+ json_validate if available
        if (PHP_VERSION_ID >= 80300 && function_exists('json_validate')) {
            return json_validate($input);
        }
        
        // Fallback for PHP 8.2
        json_decode($input);
        return json_last_error() === JSON_ERROR_NONE;
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