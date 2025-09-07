<?php
/**
 * AIP Tracker - Symptom Tracking Interface
 * Comprehensive symptom logging with severity tracking and correlation analysis
 */

require_once '../config/config.php';

Helpers::requireLogin();

$db = (new Database())->connect();
$userId = Helpers::getCurrentUserId();
$today = date('Y-m-d');

// Handle symptom logging submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        Helpers::jsonResponse(['success' => false, 'message' => 'Security error'], 400);
    }
    
    $data = Security::sanitizeInput($_POST);
    
    try {
        $db->beginTransaction();
        
        // Delete existing symptoms for today if resubmitting
        if (isset($data['update_existing'])) {
            $deleteStmt = $db->prepare("
                DELETE FROM symptom_logs 
                WHERE user_id = ? AND log_date = ?
            ");
            $deleteStmt->execute([$userId, $today]);
        }
        
        // Insert new symptom entries
        $insertStmt = $db->prepare("
            INSERT INTO symptom_logs 
            (user_id, log_date, log_time, symptom_type, symptom_name, severity, duration_hours, notes, triggers_suspected) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $logTime = date('H:i:s');
        $symptomsLogged = 0;
        
        foreach ($data['symptoms'] ?? [] as $symptomType => $symptoms) {
            if (!is_array($symptoms)) continue;
            
            foreach ($symptoms as $symptomName => $symptomData) {
                $severity = (int)($symptomData['severity'] ?? 0);
                if ($severity > 0) {
                    $insertStmt->execute([
                        $userId,
                        $today,
                        $logTime,
                        $symptomType,
                        $symptomName,
                        $severity,
                        $symptomData['duration'] ?? null,
                        $symptomData['notes'] ?? '',
                        $symptomData['triggers'] ?? ''
                    ]);
                    $symptomsLogged++;
                }
            }
        }
        
        // Add general notes if provided
        if (!empty($data['general_notes'])) {
            $insertStmt->execute([
                $userId,
                $today,
                $logTime,
                'general',
                'Daily Notes',
                1, // Minimal severity for notes entry
                null,
                $data['general_notes'],
                ''
            ]);
        }
        
        // Award achievement for consistent tracking
        if ($symptomsLogged > 0) {
            $checkStmt = $db->prepare("
                SELECT COUNT(DISTINCT log_date) as days_logged 
                FROM symptom_logs 
                WHERE user_id = ? 
                AND log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ");
            $checkStmt->execute([$userId]);
            $result = $checkStmt->fetch();
            
            if ($result['days_logged'] >= 7) {
                $achievementStmt = $db->prepare("
                    INSERT IGNORE INTO user_achievements 
                    (user_id, achievement_type, achievement_name, achievement_date, points_earned) 
                    VALUES (?, 'milestone', 'Weekly Symptom Tracking', ?, 75)
                ");
                $achievementStmt->execute([$userId, $today]);
            }
        }
        
        $db->commit();
        
        Helpers::jsonResponse([
            'success' => true,
            'message' => 'Symptoms logged successfully!',
            'symptoms_logged' => $symptomsLogged
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Symptom tracking error: " . $e->getMessage());
        Helpers::jsonResponse(['success' => false, 'message' => 'Error saving symptoms'], 500);
    }
}

// Get today's existing symptoms
$stmt = $db->prepare("
    SELECT symptom_type, symptom_name, severity, duration_hours, notes, triggers_suspected
    FROM symptom_logs 
    WHERE user_id = ? AND log_date = ?
    ORDER BY symptom_type, symptom_name
");
$stmt->execute([$userId, $today]);
$todaysSymptoms = $stmt->fetchAll();

// Organize existing symptoms by type and name
$existingSymptoms = [];
foreach ($todaysSymptoms as $symptom) {
    $existingSymptoms[$symptom['symptom_type']][$symptom['symptom_name']] = $symptom;
}

// Get recent symptom trends (last 7 days)
$stmt = $db->prepare("
    SELECT 
        log_date,
        symptom_type,
        AVG(severity) as avg_severity,
        COUNT(*) as symptom_count
    FROM symptom_logs 
    WHERE user_id = ? 
    AND log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY log_date, symptom_type 
    ORDER BY log_date DESC, symptom_type
");
$stmt->execute([$userId]);
$recentTrends = $stmt->fetchAll();

// Get user's baseline symptoms for comparison
$stmt = $db->prepare("SELECT baseline_symptoms FROM user_profiles WHERE user_id = ?");
$stmt->execute([$userId]);
$profile = $stmt->fetch();
$baselineSymptoms = $profile ? json_decode($profile['baseline_symptoms'], true) : [];

$csrfToken = Security::generateCSRFToken();

// Define symptom categories and options
$symptomCategories = [
    'digestive' => [
        'name' => 'Digestive Symptoms',
        'icon' => '🤢',
        'symptoms' => [
            'bloating' => 'Bloating',
            'gas' => 'Gas',
            'constipation' => 'Constipation',
            'diarrhea' => 'Diarrhea',
            'stomach_pain' => 'Stomach Pain',
            'acid_reflux' => 'Acid Reflux',
            'nausea' => 'Nausea',
            'cramping' => 'Cramping'
        ]
    ],
    'systemic' => [
        'name' => 'Systemic Symptoms',
        'icon' => '💪',
        'symptoms' => [
            'fatigue' => 'Fatigue',
            'brain_fog' => 'Brain Fog',
            'joint_pain' => 'Joint Pain',
            'muscle_aches' => 'Muscle Aches',
            'headaches' => 'Headaches',
            'inflammation' => 'General Inflammation',
            'dizziness' => 'Dizziness',
            'weakness' => 'Weakness'
        ]
    ],
    'skin' => [
        'name' => 'Skin Issues',
        'icon' => '🧴',
        'symptoms' => [
            'eczema' => 'Eczema',
            'acne' => 'Acne',
            'rashes' => 'Rashes',
            'dryness' => 'Dryness',
            'itching' => 'Itching',
            'hives' => 'Hives',
            'sensitivity' => 'Sensitivity',
            'redness' => 'Redness'
        ]
    ],
    'mood' => [
        'name' => 'Mood & Mental',
        'icon' => '🧠',
        'symptoms' => [
            'anxiety' => 'Anxiety',
            'depression' => 'Depression',
            'irritability' => 'Irritability',
            'mood_swings' => 'Mood Swings',
            'stress' => 'Stress',
            'mental_clarity' => 'Poor Mental Clarity',
            'concentration' => 'Concentration Issues',
            'memory' => 'Memory Problems'
        ]
    ],
    'sleep' => [
        'name' => 'Sleep & Rest',
        'icon' => '😴',
        'symptoms' => [
            'insomnia' => 'Insomnia',
            'restless_sleep' => 'Restless Sleep',
            'frequent_waking' => 'Frequent Waking',
            'early_waking' => 'Early Waking',
            'difficulty_falling_asleep' => 'Difficulty Falling Asleep',
            'nightmares' => 'Nightmares',
            'sleep_quality' => 'Poor Sleep Quality',
            'daytime_sleepiness' => 'Daytime Sleepiness'
        ]
    ],
    'energy' => [
        'name' => 'Energy & Vitality',
        'icon' => '⚡',
        'symptoms' => [
            'low_energy' => 'Low Energy',
            'afternoon_crash' => 'Afternoon Crash',
            'morning_fatigue' => 'Morning Fatigue',
            'exhaustion' => 'Exhaustion',
            'sluggishness' => 'Sluggishness',
            'lack_motivation' => 'Lack of Motivation',
            'burnout' => 'Burnout',
            'adrenal_fatigue' => 'Adrenal Fatigue'
        ]
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Symptom Tracker - AIP Tracker</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/symptom-track.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="../dashboard.php" class="logo">🌿 AIP Tracker</a>
                <nav class="quick-nav">
                    <a href="../dashboard.php" class="nav-link">Dashboard</a>
                    <a href="track.php" class="nav-link active">Track Symptoms</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main">
        <div class="container">
            <div class="page-header">
                <h1>📊 Track Your Symptoms</h1>
                <p class="page-subtitle">Monitor how you're feeling to identify patterns and improvements</p>
                <div class="date-display">
                    <span class="current-date"><?= date('l, F j, Y') ?></span>
                </div>
            </div>

            <!-- Quick Status -->
            <?php if (!empty($todaysSymptoms)): ?>
                <div class="alert alert-success">
                    <strong>✅ Symptoms logged for today!</strong> 
                    You can update your entries below if needed.
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <strong>📝 Ready to log symptoms for today.</strong> 
                    Rate your symptoms from 1-10 (1 = very mild, 10 = severe).
                </div>
            <?php endif; ?>

            <!-- Symptom Tracking Form -->
            <form id="symptomForm" class="symptom-form">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <?php if (!empty($todaysSymptoms)): ?>
                    <input type="hidden" name="update_existing" value="1">
                <?php endif; ?>

                <?php foreach ($symptomCategories as $categoryKey => $category): ?>
                    <div class="symptom-category-card">
                        <div class="category-header">
                            <h2 class="category-title">
                                <span class="category-icon"><?= $category['icon'] ?></span>
                                <?= $category['name'] ?>
                            </h2>
                            <div class="category-summary" id="summary-<?= $categoryKey ?>">
                                <span class="symptoms-count">0 symptoms</span>
                                <span class="avg-severity">Avg: 0</span>
                            </div>
                        </div>

                        <div class="symptoms-grid">
                            <?php foreach ($category['symptoms'] as $symptomKey => $symptomName): ?>
                                <?php 
                                $existingData = $existingSymptoms[$categoryKey][$symptomKey] ?? null;
                                $currentSeverity = $existingData ? $existingData['severity'] : 0;
                                $isBaseline = isset($baselineSymptoms[$categoryKey]) && 
                                            in_array($symptomKey, $baselineSymptoms[$categoryKey] ?? []);
                                ?>
                                
                                <div class="symptom-item <?= $isBaseline ? 'baseline-symptom' : '' ?>" 
                                     data-category="<?= $categoryKey ?>" data-symptom="<?= $symptomKey ?>">
                                    
                                    <div class="symptom-header">
                                        <label class="symptom-label">
                                            <?= $symptomName ?>
                                            <?php if ($isBaseline): ?>
                                                <span class="baseline-indicator" title="This was in your baseline assessment">📍</span>
                                            <?php endif; ?>
                                        </label>
                                    </div>

                                    <!-- Severity Slider -->
                                    <div class="severity-control">
                                        <input type="range" 
                                               name="symptoms[<?= $categoryKey ?>][<?= $symptomKey ?>][severity]"
                                               class="severity-slider"
                                               min="0" max="10" 
                                               value="<?= $currentSeverity ?>"
                                               data-category="<?= $categoryKey ?>"
                                               data-symptom="<?= $symptomKey ?>">
                                        <div class="severity-labels">
                                            <span class="severity-label">None</span>
                                            <span class="severity-value" id="severity-<?= $categoryKey ?>-<?= $symptomKey ?>">
                                                <?= $currentSeverity ?>
                                            </span>
                                            <span class="severity-label">Severe</span>
                                        </div>
                                    </div>

                                    <!-- Additional Details (shown when severity > 0) -->
                                    <div class="symptom-details" 
                                         id="details-<?= $categoryKey ?>-<?= $symptomKey ?>"
                                         style="display: <?= $currentSeverity > 0 ? 'block' : 'none' ?>">
                                        
                                        <div class="detail-row">
                                            <label class="detail-label">Duration (hours):</label>
                                            <input type="number" 
                                                   name="symptoms[<?= $categoryKey ?>][<?= $symptomKey ?>][duration]"
                                                   class="detail-input duration-input"
                                                   min="0" max="24" step="0.5"
                                                   value="<?= $existingData['duration_hours'] ?? '' ?>"
                                                   placeholder="How long?">
                                        </div>

                                        <div class="detail-row">
                                            <label class="detail-label">Suspected Triggers:</label>
                                            <input type="text" 
                                                   name="symptoms[<?= $categoryKey ?>][<?= $symptomKey ?>][triggers]"
                                                   class="detail-input triggers-input"
                                                   value="<?= $existingData['triggers_suspected'] ?? '' ?>"
                                                   placeholder="e.g., stress, food, weather">
                                        </div>

                                        <div class="detail-row">
                                            <label class="detail-label">Notes:</label>
                                            <textarea name="symptoms[<?= $categoryKey ?>][<?= $symptomKey ?>][notes]"
                                                      class="detail-textarea"
                                                      rows="2"
                                                      placeholder="Additional details..."><?= $existingData['notes'] ?? '' ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- General Notes Section -->
                <div class="general-notes-section">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">📝 General Notes</h2>
                            <p class="card-subtitle">Overall observations, mood, or context for today</p>
                        </div>
                        
                        <textarea name="general_notes" 
                                  class="general-notes-textarea"
                                  rows="4"
                                  placeholder="How are you feeling overall? Any important context, stress levels, sleep quality, or observations about your day..."><?= $existingSymptoms['general']['Daily Notes']['notes'] ?? '' ?></textarea>
                    </div>
                </div>

                <!-- Submit Section -->
                <div class="submit-section">
                    <div class="form-summary" id="formSummary">
                        <div class="summary-stats">
                            <div class="summary-stat">
                                <span class="stat-value" id="totalSymptoms">0</span>
                                <span class="stat-label">Symptoms</span>
                            </div>
                            <div class="summary-stat">
                                <span class="stat-value" id="avgSeverity">0</span>
                                <span class="stat-label">Avg Severity</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-large" id="submitBtn">
                        <?= !empty($todaysSymptoms) ? 'Update Symptoms' : 'Log Symptoms' ?>
                    </button>

                    <div class="submit-help">
                        <p>💡 <strong>Tip:</strong> Consistent tracking helps identify patterns and improvements over time.</p>
                    </div>
                </div>
            </form>

            <!-- Recent Trends -->
            <?php if (!empty($recentTrends)): ?>
                <div class="trends-section">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">📈 Recent Trends</h2>
                            <p class="card-subtitle">Last 7 days overview</p>
                        </div>
                        
                        <div class="trends-grid">
                            <?php 
                            $trendsByDate = [];
                            foreach ($recentTrends as $trend) {
                                $trendsByDate[$trend['log_date']][$trend['symptom_type']] = $trend;
                            }
                            ?>
                            
                            <?php foreach (array_slice($trendsByDate, 0, 5) as $date => $dayTrends): ?>
                                <div class="trend-day">
                                    <div class="trend-date">
                                        <?= date('M j', strtotime($date)) ?>
                                        <?php if ($date === $today): ?>
                                            <span class="today-badge">Today</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="trend-symptoms">
                                        <?php foreach ($dayTrends as $type => $trend): ?>
                                            <div class="trend-symptom">
                                                <span class="trend-type"><?= ucfirst($type) ?></span>
                                                <span class="trend-severity severity-<?= min(10, round($trend['avg_severity'])) ?>">
                                                    <?= round($trend['avg_severity'], 1) ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="trends-footer">
                            <a href="reports.php" class="btn btn-outline btn-small">View Detailed Reports →</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="../dashboard.php" class="btn btn-outline">← Back to Dashboard</a>
                <a href="../food/log.php" class="btn btn-secondary">Log Food →</a>
            </div>
        </div>
    </main>

    <!-- Success Modal -->
    <div id="success-modal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-icon">📊</div>
            <div class="modal-message">Symptoms logged successfully!</div>
            <div class="modal-stats" id="modal-stats">
                <span id="symptoms-logged">0</span> symptoms tracked
            </div>
            <button class="btn btn-primary" onclick="closeModal()">Continue</button>
        </div>
    </div>

    <script src="../assets/js/symptom-tracker.js"></script>
    <script>
        // Initialize symptom tracker
        const symptomTracker = new SymptomTracker({
            csrfToken: '<?= $csrfToken ?>',
            existingSymptoms: <?= json_encode($existingSymptoms) ?>,
            baselineSymptoms: <?= json_encode($baselineSymptoms) ?>
        });
    </script>
</body>
</html>