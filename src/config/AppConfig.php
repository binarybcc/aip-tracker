<?php
/**
 * AIP Tracker - Application Configuration (PHP 8.2+ Readonly Class)
 * 
 * Readonly class provides immutable configuration with type safety
 * and performance optimizations available in PHP 8.2+
 */

readonly class AppConfig 
{
    /**
     * Immutable application configuration
     * 
     * @param string $appName Application name
     * @param string $appVersion Current version
     * @param string $baseUrl Base URL for the application
     * @param int $sessionLifetime Session timeout in seconds
     * @param int $maxLoginAttempts Maximum failed login attempts
     * @param int $lockoutDuration Lockout duration in seconds
     * @param bool $debugMode Debug mode flag
     * @param string $timezone Application timezone
     */
    public function __construct(
        public string $appName = 'AIP Tracker',
        public string $appVersion = '0.2.1',
        public string $baseUrl = 'http://localhost:8080',
        public int $sessionLifetime = 86400,     // 24 hours
        public int $maxLoginAttempts = 5,
        public int $lockoutDuration = 1800,      // 30 minutes
        public bool $debugMode = false,
        public string $timezone = 'America/New_York'
    ) {
        // Validation in constructor (readonly classes can have logic)
        if ($this->sessionLifetime < 300) {
            throw new InvalidArgumentException('Session lifetime must be at least 5 minutes');
        }
        
        if ($this->maxLoginAttempts < 1) {
            throw new InvalidArgumentException('Max login attempts must be at least 1');
        }
        
        if (empty($this->baseUrl) || !filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Base URL must be a valid URL');
        }
    }

    /**
     * Create configuration from environment/request context
     */
    public static function fromEnvironment(): self
    {
        $isLocalhost = ($_SERVER['HTTP_HOST'] ?? 'localhost') === 'localhost';
        
        return new self(
            baseUrl: $isLocalhost ? 'http://localhost:8080' : 'https://' . ($_SERVER['HTTP_HOST'] ?? ''),
            debugMode: $isLocalhost,
        );
    }

    /**
     * Get performance information about PHP 8.2+ optimizations
     */
    public function getPerformanceInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'readonly_classes_enabled' => PHP_VERSION_ID >= 80200,
            'performance_boost' => PHP_VERSION_ID >= 80200 ? '15% faster than PHP 8.1' : 'Upgrade to PHP 8.2+ recommended',
            'security_enhancements' => PHP_VERSION_ID >= 80200 ? 'Enhanced random generation' : 'Basic security'
        ];
    }

    /**
     * Convert to legacy array format for backward compatibility
     */
    public function toArray(): array
    {
        return [
            'APP_NAME' => $this->appName,
            'APP_VERSION' => $this->appVersion,
            'BASE_URL' => $this->baseUrl,
            'SESSION_LIFETIME' => $this->sessionLifetime,
            'MAX_LOGIN_ATTEMPTS' => $this->maxLoginAttempts,
            'LOCKOUT_DURATION' => $this->lockoutDuration,
            'DEBUG_MODE' => $this->debugMode,
            'TIMEZONE' => $this->timezone
        ];
    }
}