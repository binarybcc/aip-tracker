<?php
/**
 * AIP Tracker - Initial Setup Interview
 * Multi-step onboarding process for new users
 */

require_once '../config/config.php';

Helpers::requireLogin();

$db = (new Database())->connect();
$userId = Helpers::getCurrentUserId();

// Check if user has already completed setup
$stmt = $db->prepare("SELECT current_phase FROM user_profiles WHERE user_id = ?");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

if ($profile && $profile['current_phase'] !== 'setup') {
    Helpers::redirect('/dashboard.php', 'Setup already completed!', 'info');
}

$currentStep = (int)($_GET['step'] ?? 1);
$maxSteps = 5;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        Helpers::redirect('/setup/interview.php?step=' . $currentStep, 'Security error. Please try again.', 'error');
    }
    
    $data = Security::sanitizeInput($_POST);
    
    // Store step data in session
    if (!isset($_SESSION['setup_data'])) {
        $_SESSION['setup_data'] = [];
    }
    
    switch ($currentStep) {
        case 1: // Basic health goals
            $_SESSION['setup_data']['health_goals'] = $data['health_goals'];
            $_SESSION['setup_data']['elimination_duration'] = (int)$data['elimination_duration'];
            $_SESSION['setup_data']['start_date'] = $data['start_date'];
            break;
            
        case 2: // Current symptoms baseline
            $_SESSION['setup_data']['baseline_symptoms'] = [
                'digestive' => $data['digestive'] ?? [],
                'systemic' => $data['systemic'] ?? [],
                'skin' => $data['skin'] ?? [],
                'mood' => $data['mood'] ?? [],
                'sleep' => $data['sleep'] ?? [],
                'energy' => $data['energy'] ?? []
            ];
            break;
            
        case 3: // Motivation and preferences
            $_SESSION['setup_data']['motivation_style'] = $data['motivation_style'];
            $_SESSION['setup_data']['water_goal'] = (int)$data['water_goal'];
            break;
            
        case 4: // Reminder preferences
            $_SESSION['setup_data']['reminder_preferences'] = [
                'meal_reminders' => isset($data['meal_reminders']),
                'water_reminders' => isset($data['water_reminders']),
                'symptom_reminders' => isset($data['symptom_reminders']),
                'reminder_frequency' => $data['reminder_frequency'] ?? 'daily'
            ];
            break;
            
        case 5: // Final confirmation and save
            // Save all setup data to database
            try {
                $db->beginTransaction();
                
                // Create or update user profile
                $sql = "INSERT INTO user_profiles 
                        (user_id, current_phase, start_date, target_elimination_days, health_goals, 
                         baseline_symptoms, motivation_style, reminder_preferences, water_goal_ml) 
                        VALUES (?, 'elimination', ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        current_phase = 'elimination',
                        start_date = VALUES(start_date),
                        target_elimination_days = VALUES(target_elimination_days),
                        health_goals = VALUES(health_goals),
                        baseline_symptoms = VALUES(baseline_symptoms),
                        motivation_style = VALUES(motivation_style),
                        reminder_preferences = VALUES(reminder_preferences),
                        water_goal_ml = VALUES(water_goal_ml)";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $userId,
                    $_SESSION['setup_data']['start_date'],
                    $_SESSION['setup_data']['elimination_duration'],
                    $_SESSION['setup_data']['health_goals'],
                    json_encode($_SESSION['setup_data']['baseline_symptoms']),
                    $_SESSION['setup_data']['motivation_style'],
                    json_encode($_SESSION['setup_data']['reminder_preferences']),
                    $_SESSION['setup_data']['water_goal']
                ]);
                
                // Create initial achievement
                $sql = "INSERT INTO user_achievements 
                        (user_id, achievement_type, achievement_name, achievement_date, points_earned) 
                        VALUES (?, 'milestone', 'Setup Complete', CURDATE(), 100)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$userId]);
                
                $db->commit();
                
                // Clear setup data
                unset($_SESSION['setup_data']);
                
                Helpers::redirect('/dashboard.php', 'Welcome to your AIP journey! Let\'s start tracking.', 'success');
                
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Setup completion error: " . $e->getMessage());
                Helpers::redirect('/setup/interview.php?step=5', 'Error saving setup. Please try again.', 'error');
            }
            break;
    }
    
    // Move to next step
    if ($currentStep < $maxSteps) {
        Helpers::redirect('/setup/interview.php?step=' . ($currentStep + 1));
    }
}

$csrfToken = Security::generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIP Setup - Step <?= $currentStep ?> of <?= $maxSteps ?></title>
    <link rel="stylesheet" href="../assets/css/setup.css">
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>AIP Tracker Setup</h1>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= ($currentStep / $maxSteps) * 100 ?>%"></div>
            </div>
            <p class="step-indicator">Step <?= $currentStep ?> of <?= $maxSteps ?></p>
        </div>

        <div class="setup-content">
            <?php if ($currentStep === 1): ?>
                <h2>Let's Start Your AIP Journey</h2>
                <p>Tell us about your health goals and timeline.</p>
                
                <form method="POST" class="setup-form">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    
                    <div class="form-group">
                        <label for="health_goals">What are your main health goals?</label>
                        <textarea name="health_goals" id="health_goals" rows="4" required 
                                  placeholder="e.g., Reduce inflammation, improve digestion, increase energy..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date">When would you like to start?</label>
                        <input type="date" name="start_date" id="start_date" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="elimination_duration">How long do you want to do the elimination phase?</label>
                        <select name="elimination_duration" id="elimination_duration" required>
                            <option value="30">30 days (minimum recommended)</option>
                            <option value="42" selected>42 days (6 weeks - recommended)</option>
                            <option value="60">60 days (extended healing)</option>
                            <option value="90">90 days (comprehensive reset)</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Next Step</button>
                </form>

            <?php elseif ($currentStep === 2): ?>
                <h2>Current Symptom Assessment</h2>
                <p>Help us understand your baseline so we can track improvements.</p>
                
                <form method="POST" class="setup-form">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    
                    <div class="symptom-categories">
                        <div class="symptom-category">
                            <h3>Digestive Symptoms</h3>
                            <div class="checkbox-grid">
                                <label><input type="checkbox" name="digestive[]" value="bloating"> Bloating</label>
                                <label><input type="checkbox" name="digestive[]" value="gas"> Gas</label>
                                <label><input type="checkbox" name="digestive[]" value="constipation"> Constipation</label>
                                <label><input type="checkbox" name="digestive[]" value="diarrhea"> Diarrhea</label>
                                <label><input type="checkbox" name="digestive[]" value="stomach_pain"> Stomach Pain</label>
                                <label><input type="checkbox" name="digestive[]" value="acid_reflux"> Acid Reflux</label>
                            </div>
                        </div>
                        
                        <div class="symptom-category">
                            <h3>Systemic Symptoms</h3>
                            <div class="checkbox-grid">
                                <label><input type="checkbox" name="systemic[]" value="fatigue"> Fatigue</label>
                                <label><input type="checkbox" name="systemic[]" value="brain_fog"> Brain Fog</label>
                                <label><input type="checkbox" name="systemic[]" value="joint_pain"> Joint Pain</label>
                                <label><input type="checkbox" name="systemic[]" value="muscle_aches"> Muscle Aches</label>
                                <label><input type="checkbox" name="systemic[]" value="headaches"> Headaches</label>
                                <label><input type="checkbox" name="systemic[]" value="inflammation"> General Inflammation</label>
                            </div>
                        </div>
                        
                        <div class="symptom-category">
                            <h3>Skin Issues</h3>
                            <div class="checkbox-grid">
                                <label><input type="checkbox" name="skin[]" value="eczema"> Eczema</label>
                                <label><input type="checkbox" name="skin[]" value="acne"> Acne</label>
                                <label><input type="checkbox" name="skin[]" value="rashes"> Rashes</label>
                                <label><input type="checkbox" name="skin[]" value="dryness"> Dryness</label>
                                <label><input type="checkbox" name="skin[]" value="itching"> Itching</label>
                            </div>
                        </div>
                        
                        <div class="symptom-category">
                            <h3>Mood & Mental</h3>
                            <div class="checkbox-grid">
                                <label><input type="checkbox" name="mood[]" value="anxiety"> Anxiety</label>
                                <label><input type="checkbox" name="mood[]" value="depression"> Depression</label>
                                <label><input type="checkbox" name="mood[]" value="irritability"> Irritability</label>
                                <label><input type="checkbox" name="mood[]" value="mood_swings"> Mood Swings</label>
                            </div>
                        </div>
                        
                        <div class="symptom-category">
                            <h3>Sleep & Energy</h3>
                            <div class="checkbox-grid">
                                <label><input type="checkbox" name="sleep[]" value="insomnia"> Insomnia</label>
                                <label><input type="checkbox" name="sleep[]" value="restless_sleep"> Restless Sleep</label>
                                <label><input type="checkbox" name="energy[]" value="low_energy"> Low Energy</label>
                                <label><input type="checkbox" name="energy[]" value="afternoon_crash"> Afternoon Crash</label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Next Step</button>
                </form>

            <?php elseif ($currentStep === 3): ?>
                <h2>Motivation & Goals</h2>
                <p>Let's personalize your experience for maximum motivation.</p>
                
                <form method="POST" class="setup-form">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    
                    <div class="form-group">
                        <label>What motivates you most?</label>
                        <div class="radio-group">
                            <label class="radio-card">
                                <input type="radio" name="motivation_style" value="achievement" checked>
                                <div class="radio-content">
                                    <strong>Achievements & Badges</strong>
                                    <p>Unlock milestones and earn points</p>
                                </div>
                            </label>
                            <label class="radio-card">
                                <input type="radio" name="motivation_style" value="progress">
                                <div class="radio-content">
                                    <strong>Visual Progress</strong>
                                    <p>Charts, graphs, and trend analysis</p>
                                </div>
                            </label>
                            <label class="radio-card">
                                <input type="radio" name="motivation_style" value="data">
                                <div class="radio-content">
                                    <strong>Detailed Analytics</strong>
                                    <p>Deep insights and correlations</p>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="water_goal">Daily water goal (ml)?</label>
                        <select name="water_goal" id="water_goal" required>
                            <option value="1500">1.5 liters (1500ml)</option>
                            <option value="2000" selected>2.0 liters (2000ml)</option>
                            <option value="2500">2.5 liters (2500ml)</option>
                            <option value="3000">3.0 liters (3000ml)</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Next Step</button>
                </form>

            <?php elseif ($currentStep === 4): ?>
                <h2>Reminder Preferences</h2>
                <p>Set up helpful reminders to stay on track.</p>
                
                <form method="POST" class="setup-form">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    
                    <div class="form-group">
                        <label>What would you like reminders for?</label>
                        <div class="checkbox-list">
                            <label><input type="checkbox" name="meal_reminders" checked> Meal logging</label>
                            <label><input type="checkbox" name="water_reminders" checked> Water intake</label>
                            <label><input type="checkbox" name="symptom_reminders"> Daily symptom check-ins</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="reminder_frequency">Reminder frequency</label>
                        <select name="reminder_frequency" id="reminder_frequency" required>
                            <option value="daily" selected>Daily</option>
                            <option value="twice_daily">Twice daily</option>
                            <option value="weekly">Weekly only</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Next Step</button>
                </form>

            <?php elseif ($currentStep === 5): ?>
                <h2>Ready to Begin!</h2>
                <p>Review your setup and start your AIP journey.</p>
                
                <div class="setup-summary">
                    <div class="summary-item">
                        <strong>Start Date:</strong> 
                        <?= Helpers::formatDate($_SESSION['setup_data']['start_date'] ?? date('Y-m-d')) ?>
                    </div>
                    <div class="summary-item">
                        <strong>Elimination Duration:</strong> 
                        <?= $_SESSION['setup_data']['elimination_duration'] ?? 42 ?> days
                    </div>
                    <div class="summary-item">
                        <strong>Water Goal:</strong> 
                        <?= ($_SESSION['setup_data']['water_goal'] ?? 2000) / 1000 ?>L per day
                    </div>
                    <div class="summary-item">
                        <strong>Motivation Style:</strong> 
                        <?= ucfirst($_SESSION['setup_data']['motivation_style'] ?? 'achievement') ?>
                    </div>
                </div>
                
                <form method="POST" class="setup-form">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="terms_accepted" required>
                            I understand this app is for educational purposes only and does not replace medical advice.
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-large">Start My AIP Journey!</button>
                </form>
            <?php endif; ?>
        </div>
        
        <?php if ($currentStep > 1): ?>
        <div class="setup-navigation">
            <a href="?step=<?= $currentStep - 1 ?>" class="btn btn-secondary">← Previous</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>