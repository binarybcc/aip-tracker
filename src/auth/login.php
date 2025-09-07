<?php
/**
 * AIP Tracker - User Login
 */

require_once '../config/config.php';

// Redirect if already logged in
if (Helpers::isLoggedIn()) {
    Helpers::redirect('/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security error. Please try again.';
    } else {
        $data = Security::sanitizeInput($_POST);
        
        if (empty($data['email']) || empty($data['password'])) {
            $errors[] = 'Email and password are required.';
        }
        
        // Rate limiting
        $rateLimitKey = 'login_' . $_SERVER['REMOTE_ADDR'];
        if (!Security::checkRateLimit($rateLimitKey, MAX_LOGIN_ATTEMPTS, LOCKOUT_DURATION)) {
            $errors[] = 'Too many login attempts. Please try again later.';
        }
        
        if (empty($errors)) {
            $db = (new Database())->connect();
            
            try {
                $stmt = $db->prepare("
                    SELECT id, email, password_hash, first_name, last_name, is_active, 
                           login_attempts, locked_until 
                    FROM users 
                    WHERE email = ?
                ");
                $stmt->execute([$data['email']]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Check if account is locked
                    if ($user['locked_until'] && new DateTime($user['locked_until']) > new DateTime()) {
                        $errors[] = 'Account is temporarily locked. Please try again later.';
                    } elseif (!$user['is_active']) {
                        $errors[] = 'Account is disabled. Please contact support.';
                    } elseif (Security::verifyPassword($data['password'], $user['password_hash'])) {
                        // Successful login
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                        
                        // Reset login attempts and update last login
                        $updateStmt = $db->prepare("
                            UPDATE users 
                            SET login_attempts = 0, locked_until = NULL, last_login = CURRENT_TIMESTAMP 
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$user['id']]);
                        
                        Security::logSecurityEvent('user_login', ['user_id' => $user['id'], 'email' => $user['email']]);
                        
                        // Redirect to appropriate page
                        $redirectUrl = $_SESSION['redirect_after_login'] ?? '/dashboard.php';
                        unset($_SESSION['redirect_after_login']);
                        
                        Helpers::redirect($redirectUrl, 'Welcome back!', 'success');
                    } else {
                        // Failed login - increment attempts
                        $newAttempts = $user['login_attempts'] + 1;
                        $lockedUntil = null;
                        
                        if ($newAttempts >= MAX_LOGIN_ATTEMPTS) {
                            $lockedUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
                        }
                        
                        $updateStmt = $db->prepare("
                            UPDATE users 
                            SET login_attempts = ?, locked_until = ? 
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$newAttempts, $lockedUntil, $user['id']]);
                        
                        Security::logSecurityEvent('failed_login', ['email' => $data['email'], 'attempts' => $newAttempts]);
                        
                        $errors[] = 'Invalid email or password.';
                    }
                } else {
                    $errors[] = 'Invalid email or password.';
                    Security::logSecurityEvent('failed_login', ['email' => $data['email'], 'reason' => 'user_not_found']);
                }
                
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $errors[] = 'Login failed. Please try again.';
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
    <title>Login - AIP Tracker</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">🌿 AIP Tracker</div>
                <h1 class="auth-title">Welcome Back</h1>
                <p class="auth-subtitle">Continue your healing journey</p>
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
                
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" name="email" id="email" 
                           class="form-input" required autofocus
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" name="password" id="password" 
                           class="form-input" required>
                </div>

                <button type="submit" class="btn btn-primary btn-large">Sign In</button>
            </form>

            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php" class="auth-link">Create Account</a></p>
                <p><a href="forgot-password.php" class="auth-link">Forgot your password?</a></p>
            </div>

            <div class="auth-testimonial">
                <div class="testimonial-content">
                    <p>"AIP Tracker helped me identify my trigger foods and track my healing progress. The symptom correlation features are incredible!"</p>
                    <div class="testimonial-author">— Sarah, AIP Success Story</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>