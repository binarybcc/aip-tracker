<?php
/**
 * AIP Tracker - Main Dashboard
 * Motivational overview with progress tracking
 */

require_once 'config/config.php';

Helpers::requireLogin();

$db = (new Database())->connect();
$userId = Helpers::getCurrentUserId();

// Get user profile
$stmt = $db->prepare("
    SELECT up.*, u.first_name, u.last_name 
    FROM user_profiles up
    JOIN users u ON up.user_id = u.id 
    WHERE up.user_id = ?
");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

if (!$profile) {
    Helpers::redirect('/setup/interview.php', 'Please complete your setup first.', 'info');
}

// Calculate progress metrics
$today = date('Y-m-d');
$startDate = $profile['start_date'];
$daysActive = Helpers::daysSinceStart($startDate);
$targetDays = (int)$profile['target_elimination_days'];
$progressPercentage = Helpers::calculateProgress($daysActive, $targetDays);

// Get today's logging status
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT fl.meal_type) as meals_logged,
        COALESCE(SUM(wl.amount_ml), 0) as water_intake,
        COUNT(DISTINCT sl.symptom_type) as symptoms_logged
    FROM (SELECT ? as check_date) cd
    LEFT JOIN food_logs fl ON fl.user_id = ? AND fl.log_date = cd.check_date
    LEFT JOIN water_logs wl ON wl.user_id = ? AND wl.log_date = cd.check_date
    LEFT JOIN symptom_logs sl ON sl.user_id = ? AND sl.log_date = cd.check_date
");
$stmt->execute([$today, $userId, $userId, $userId]);
$todayStats = $stmt->fetch();

// Calculate streaks
$foodStreak = Helpers::calculateStreak($userId, 'food_logs', $db);
$waterStreak = Helpers::calculateStreak($userId, 'water_logs', $db);

// Get recent achievements
$stmt = $db->prepare("
    SELECT achievement_name, achievement_date, points_earned 
    FROM user_achievements 
    WHERE user_id = ? 
    ORDER BY achievement_date DESC 
    LIMIT 3
");
$stmt->execute([$userId]);
$recentAchievements = $stmt->fetchAll();

// Get motivational message
$motivationalMessage = Helpers::getMotivationalMessage($progressPercentage, $profile['current_phase']);

// Water intake percentage
$waterGoal = (int)$profile['water_goal_ml'];
$waterPercentage = $waterGoal > 0 ? min(100, round(($todayStats['water_intake'] / $waterGoal) * 100)) : 0;

// Weekly symptom trend (simplified)
$stmt = $db->prepare("
    SELECT 
        log_date,
        AVG(severity) as avg_severity,
        COUNT(*) as symptom_count
    FROM symptom_logs 
    WHERE user_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY log_date 
    ORDER BY log_date DESC
");
$stmt->execute([$userId]);
$weeklySymptoms = $stmt->fetchAll();

$csrfToken = Security::generateCSRFToken();
$flashMessage = Helpers::getFlashMessage();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIP Tracker Dashboard</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="dashboard.php" class="logo">🌿 AIP Tracker</a>
                <div class="user-menu">
                    <span>Welcome back, <?= htmlspecialchars($profile['first_name']) ?>!</span>
                    <a href="auth/logout.php" class="btn btn-outline btn-small">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="nav">
        <div class="container">
            <ul class="nav-list">
                <li><a href="dashboard.php" class="nav-link active">Dashboard</a></li>
                <li><a href="food/log.php" class="nav-link">Log Food</a></li>
                <li><a href="symptoms/track.php" class="nav-link">Track Symptoms</a></li>
                <li><a href="progress/reports.php" class="nav-link">Progress</a></li>
                <?php if ($profile['current_phase'] === 'reintroduction'): ?>
                <li><a href="reintroduction/schedule.php" class="nav-link">Reintroduction</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main">
        <div class="container">
            <?php if ($flashMessage): ?>
                <div class="alert alert-<?= $flashMessage['type'] ?>">
                    <?= htmlspecialchars($flashMessage['message']) ?>
                </div>
            <?php endif; ?>

            <!-- Motivational Header -->
            <div class="motivational-section">
                <div class="motivational-message">
                    <strong>💪 <?= $motivationalMessage ?></strong>
                </div>
                
                <div class="phase-indicator">
                    <div class="current-phase">
                        <span class="phase-label">Current Phase:</span>
                        <span class="phase-name"><?= ucfirst($profile['current_phase']) ?></span>
                        <?php if ($profile['current_phase'] === 'elimination'): ?>
                            <span class="phase-progress">Day <?= $daysActive ?> of <?= $targetDays ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Progress Overview -->
            <div class="grid grid-4">
                <!-- Overall Progress -->
                <div class="card stat-card">
                    <div class="progress-ring">
                        <svg viewBox="0 0 120 120">
                            <circle class="progress-ring-bg" cx="60" cy="60" r="54"/>
                            <circle class="progress-ring-fill" cx="60" cy="60" r="54" 
                                    stroke-dasharray="<?= 2 * pi() * 54 ?>" 
                                    stroke-dashoffset="<?= 2 * pi() * 54 * (1 - $progressPercentage/100) ?>"/>
                        </svg>
                        <div class="progress-text"><?= $progressPercentage ?>%</div>
                    </div>
                    <div class="stat-label">Overall Progress</div>
                </div>

                <!-- Food Logging Streak -->
                <div class="card stat-card">
                    <div class="streak-counter">
                        🔥 <span class="stat-value"><?= $foodStreak ?></span>
                    </div>
                    <div class="stat-label">Food Logging Streak</div>
                </div>

                <!-- Water Intake -->
                <div class="card stat-card">
                    <div class="stat-value text-primary"><?= $waterPercentage ?>%</div>
                    <div class="stat-label">Water Goal Today</div>
                    <div class="progress-bar mt-sm">
                        <div class="progress-fill" style="width: <?= $waterPercentage ?>%"></div>
                    </div>
                    <small class="text-secondary"><?= $todayStats['water_intake'] ?>ml / <?= $waterGoal ?>ml</small>
                </div>

                <!-- Today's Logging -->
                <div class="card stat-card">
                    <div class="stat-value"><?= $todayStats['meals_logged'] ?>/4</div>
                    <div class="stat-label">Meals Logged Today</div>
                    <?php if ($todayStats['meals_logged'] === 0): ?>
                        <div class="mt-sm">
                            <a href="food/log.php" class="btn btn-primary btn-small">Log First Meal</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions-section">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Quick Actions</h2>
                        <p class="card-subtitle">Log your daily activities</p>
                    </div>
                    
                    <div class="grid grid-3">
                        <a href="food/log.php" class="quick-action-btn">
                            <div class="quick-action-icon">🍎</div>
                            <div class="quick-action-label">Log Food</div>
                        </a>
                        <a href="water/log.php" class="quick-action-btn">
                            <div class="quick-action-icon">💧</div>
                            <div class="quick-action-label">Add Water</div>
                        </a>
                        <a href="symptoms/track.php" class="quick-action-btn">
                            <div class="quick-action-icon">📊</div>
                            <div class="quick-action-label">Track Symptoms</div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Two Column Layout -->
            <div class="grid grid-2">
                <!-- Achievements -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Recent Achievements</h2>
                        <p class="card-subtitle">Celebrate your progress!</p>
                    </div>
                    
                    <?php if (empty($recentAchievements)): ?>
                        <p class="text-secondary">Start logging to earn your first achievements!</p>
                    <?php else: ?>
                        <div class="achievements-list">
                            <?php foreach ($recentAchievements as $achievement): ?>
                                <div class="achievement-item">
                                    <div class="achievement-badge">
                                        🏆 <?= htmlspecialchars($achievement['achievement_name']) ?>
                                    </div>
                                    <div class="achievement-meta">
                                        <span class="achievement-date"><?= Helpers::formatDate($achievement['achievement_date']) ?></span>
                                        <span class="achievement-points">+<?= $achievement['points_earned'] ?> pts</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-md">
                            <a href="progress/achievements.php" class="btn btn-outline btn-small">View All Achievements</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Weekly Symptom Overview -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Symptom Trends</h2>
                        <p class="card-subtitle">Past 7 days overview</p>
                    </div>
                    
                    <?php if (empty($weeklySymptoms)): ?>
                        <p class="text-secondary">Start tracking symptoms to see trends here.</p>
                        <div class="mt-md">
                            <a href="symptoms/track.php" class="btn btn-primary btn-small">Track First Symptom</a>
                        </div>
                    <?php else: ?>
                        <div class="symptom-trend-chart">
                            <?php 
                            $totalSeverity = array_sum(array_column($weeklySymptoms, 'avg_severity'));
                            $avgSeverity = count($weeklySymptoms) > 0 ? round($totalSeverity / count($weeklySymptoms), 1) : 0;
                            ?>
                            <div class="trend-summary">
                                <div class="trend-stat">
                                    <span class="trend-value"><?= $avgSeverity ?>/10</span>
                                    <span class="trend-label">Avg Severity</span>
                                </div>
                                <div class="trend-stat">
                                    <span class="trend-value"><?= count($weeklySymptoms) ?></span>
                                    <span class="trend-label">Days Logged</span>
                                </div>
                            </div>
                            
                            <div class="mt-md">
                                <a href="symptoms/reports.php" class="btn btn-outline btn-small">Detailed Analysis</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Phase-Specific Information -->
            <?php if ($profile['current_phase'] === 'elimination'): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Elimination Phase Progress</h2>
                        <p class="card-subtitle">Stay strong! You're doing great.</p>
                    </div>
                    
                    <div class="elimination-progress">
                        <div class="progress-bar mb-md">
                            <div class="progress-fill" style="width: <?= $progressPercentage ?>%"></div>
                        </div>
                        
                        <div class="elimination-stats grid grid-3">
                            <div class="elimination-stat">
                                <span class="stat-value"><?= $daysActive ?></span>
                                <span class="stat-label">Days Completed</span>
                            </div>
                            <div class="elimination-stat">
                                <span class="stat-value"><?= max(0, $targetDays - $daysActive) ?></span>
                                <span class="stat-label">Days Remaining</span>
                            </div>
                            <div class="elimination-stat">
                                <span class="stat-value"><?= date('M j', strtotime($startDate . ' + ' . $targetDays . ' days')) ?></span>
                                <span class="stat-label">Target End Date</span>
                            </div>
                        </div>
                        
                        <?php if ($progressPercentage >= 80): ?>
                            <div class="alert alert-success">
                                <strong>🎉 Almost there!</strong> You're in the final stretch. Consider preparing for the reintroduction phase.
                                <div class="mt-sm">
                                    <a href="reintroduction/prepare.php" class="btn btn-primary btn-small">Prepare for Reintroduction</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="assets/js/dashboard.js"></script>
</body>
</html>