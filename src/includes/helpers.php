<?php
/**
 * AIP Tracker - Helper Functions
 */

class Helpers {
    
    /**
     * Redirect with message
     */
    public static function redirect($url, $message = null, $type = 'info') {
        if ($message) {
            $_SESSION['flash_message'] = ['message' => $message, 'type' => $type];
        }
        header("Location: " . $url);
        exit();
    }
    
    /**
     * Get and clear flash message
     */
    public static function getFlashMessage() {
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $message;
        }
        return null;
    }
    
    /**
     * Format date for display
     */
    public static function formatDate($date, $format = 'M j, Y') {
        return date($format, strtotime($date));
    }
    
    /**
     * Calculate days since start
     */
    public static function daysSinceStart($startDate) {
        $start = new DateTime($startDate);
        $now = new DateTime();
        return $start->diff($now)->days;
    }
    
    /**
     * Generate progress percentage
     */
    public static function calculateProgress($current, $target) {
        if ($target <= 0) return 0;
        return min(100, round(($current / $target) * 100));
    }
    
    /**
     * Get current user ID
     */
    public static function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Require login
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            self::redirect('/auth/login.php', 'Please log in to continue.', 'warning');
        }
    }
    
    /**
     * Convert symptoms array to readable format
     */
    public static function formatSymptoms($symptoms) {
        if (is_string($symptoms)) {
            $symptoms = json_decode($symptoms, true);
        }
        
        if (!is_array($symptoms)) {
            return 'No symptoms recorded';
        }
        
        $formatted = [];
        foreach ($symptoms as $category => $items) {
            if (is_array($items) && !empty($items)) {
                $formatted[] = ucfirst($category) . ': ' . implode(', ', $items);
            }
        }
        
        return implode(' | ', $formatted);
    }
    
    /**
     * Get motivational message based on user progress
     */
    public static function getMotivationalMessage($progress, $phase) {
        $messages = [
            'setup' => [
                'Welcome to your AIP journey!',
                'Every step forward is progress.',
                'You\'ve got this!'
            ],
            'elimination' => [
                'Stay strong during elimination!',
                'Your body is healing with every meal choice.',
                'Progress, not perfection.',
                'Each compliant day brings you closer to feeling better.'
            ],
            'reintroduction' => [
                'Discovery phase - you\'re learning so much!',
                'Every test teaches you about your body.',
                'You\'re building your personalized healing plan.'
            ],
            'maintenance' => [
                'You\'ve found your balance!',
                'Consistency is your superpower.',
                'You\'ve transformed your health!'
            ]
        ];
        
        $phaseMessages = $messages[$phase] ?? $messages['setup'];
        return $phaseMessages[array_rand($phaseMessages)];
    }
    
    /**
     * Calculate streak from logs
     */
    public static function calculateStreak($userId, $logTable, $db) {
        $sql = "SELECT DISTINCT log_date 
                FROM {$logTable} 
                WHERE user_id = ? 
                ORDER BY log_date DESC 
                LIMIT 30";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($dates)) return 0;
        
        $streak = 0;
        $currentDate = new DateTime();
        
        foreach ($dates as $date) {
            $logDate = new DateTime($date);
            $diff = $currentDate->diff($logDate)->days;
            
            if ($diff === $streak) {
                $streak++;
                $currentDate->sub(new DateInterval('P1D'));
            } else {
                break;
            }
        }
        
        return $streak;
    }
    
    /**
     * JSON response helper
     */
    public static function jsonResponse($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}
?>