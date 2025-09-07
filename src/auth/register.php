<?php
/**
 * AIP Tracker - User Registration
 */

require_once '../config/config.php';

// Redirect if already logged in
if (Helpers::isLoggedIn()) {
    Helpers::redirect('/dashboard.php');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security error. Please try again.';
    } else {
        $data = Security::sanitizeInput($_POST);
        
        // Validation
        if (empty($data['first_name'])) {
            $errors[] = 'First name is required.';
        }
        
        if (empty($data['last_name'])) {
            $errors[] = 'Last name is required.';
        }
        
        if (empty($data['email']) || !Security::validateEmail($data['email'])) {
            $errors[] = 'Valid email address is required.';
        }
        
        if (empty($data['password']) || strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        
        if ($data['password'] !== $data['confirm_password']) {
            $errors[] = 'Passwords do not match.';
        }
        
        if (!isset($data['terms'])) {
            $errors[] = 'You must agree to the terms of service.';
        }
        
        // Rate limiting
        if (!Security::checkRateLimit('register_' . $_SERVER['REMOTE_ADDR'], 3, 3600)) {
            $errors[] = 'Too many registration attempts. Please try again later.';
        }
        
        if (empty($errors)) {
            $db = (new Database())->connect();
            
            try {
                // Check if email already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$data['email']]);
                
                if ($stmt->fetch()) {
                    $errors[] = 'An account with this email already exists.';
                } else {
                    // Create new user
                    $hashedPassword = Security::hashPassword($data['password']);
                    
                    $stmt = $db->prepare("
                        INSERT INTO users (email, password_hash, first_name, last_name, timezone) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    $timezone = $data['timezone'] ?? 'America/New_York';
                    
                    if ($stmt->execute([$data['email'], $hashedPassword, $data['first_name'], $data['last_name'], $timezone])) {
                        $userId = $db->lastInsertId();
                        
                        // Log the user in
                        $_SESSION['user_id'] = $userId;
                        $_SESSION['user_email'] = $data['email'];
                        $_SESSION['user_name'] = $data['first_name'] . ' ' . $data['last_name'];
                        
                        // Update last login
                        $updateStmt = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                        $updateStmt->execute([$userId]);
                        
                        Security::logSecurityEvent('user_registered', ['user_id' => $userId, 'email' => $data['email']]);
                        
                        Helpers::redirect('/setup/interview.php', 'Welcome to AIP Tracker! Let\'s get you set up.', 'success');
                    } else {
                        $errors[] = 'Registration failed. Please try again.';
                    }
                }
            } catch (Exception $e) {
                error_log("Registration error: " . $e->getMessage());
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}

$csrfToken = Security::generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - AIP Tracker</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">🌿 AIP Tracker</div>
                <h1 class="auth-title">Create Your Account</h1>
                <p class="auth-subtitle">Start your autoimmune protocol journey today</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" name="first_name" id="first_name" 
                               class="form-input" required
                               value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" name="last_name" id="last_name" 
                               class="form-input" required
                               value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" name="email" id="email" 
                           class="form-input" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="timezone" class="form-label">Timezone</label>
                    <select name="timezone" id="timezone" class="form-select">
                        <option value="America/New_York">Eastern Time (ET)</option>
                        <option value="America/Chicago">Central Time (CT)</option>
                        <option value="America/Denver">Mountain Time (MT)</option>
                        <option value="America/Los_Angeles">Pacific Time (PT)</option>
                        <option value="America/Anchorage">Alaska Time (AKT)</option>
                        <option value="Pacific/Honolulu">Hawaii Time (HST)</option>
                        <option value="Europe/London">London (GMT)</option>
                        <option value="Europe/Paris">Paris (CET)</option>
                        <option value="Asia/Tokyo">Tokyo (JST)</option>
                        <option value="Australia/Sydney">Sydney (AEST)</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" 
                               class="form-input" required minlength="8">
                        <div class="form-help">Minimum 8 characters</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" 
                               class="form-input" required minlength="8">
                    </div>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="terms" required>
                        I agree that this app is for educational purposes only and does not replace professional medical advice. I understand I should consult with healthcare providers for medical decisions.
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-large">Create Account</button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="login.php" class="auth-link">Sign In</a></p>
            </div>

            <div class="auth-features">
                <div class="feature-list">
                    <div class="feature-item">
                        <span class="feature-icon">🍎</span>
                        <span class="feature-text">Track AIP-compliant foods</span>
                    </div>
                    <div class="feature-item">
                        <span class="feature-icon">📊</span>
                        <span class="feature-text">Monitor symptoms & improvements</span>
                    </div>
                    <div class="feature-item">
                        <span class="feature-icon">🎯</span>
                        <span class="feature-text">Manage reintroduction phases</span>
                    </div>
                    <div class="feature-item">
                        <span class="feature-icon">📈</span>
                        <span class="feature-text">Visualize progress & trends</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Auto-detect timezone
        if (Intl && Intl.DateTimeFormat) {
            const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            const timezoneSelect = document.getElementById('timezone');
            
            for (let option of timezoneSelect.options) {
                if (option.value === userTimezone) {
                    option.selected = true;
                    break;
                }
            }
        }
    </script>
</body>
</html>