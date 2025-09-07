<?php
/**
 * AIP Tracker - Reintroduction Phase Scheduler
 * Manage systematic food reintroduction testing
 */

require_once '../config/config.php';

Helpers::requireLogin();

$db = (new Database())->connect();
$userId = Helpers::getCurrentUserId();

// Get user profile to check phase
$stmt = $db->prepare("SELECT current_phase, start_date, target_elimination_days FROM user_profiles WHERE user_id = ?");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

if (!$profile || $profile['current_phase'] === 'setup') {
    Helpers::redirect('/setup/interview.php', 'Please complete your setup first.', 'info');
}

// Calculate if ready for reintroduction
$daysActive = Helpers::daysSinceStart($profile['start_date']);
$isReadyForReintroduction = $daysActive >= $profile['target_elimination_days'] || $profile['current_phase'] !== 'elimination';

// Handle phase transition to reintroduction
if ($_POST['action'] === 'start_reintroduction' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        Helpers::redirect('/reintroduction/schedule.php', 'Security error. Please try again.', 'error');
    }
    
    try {
        $stmt = $db->prepare("UPDATE user_profiles SET current_phase = 'reintroduction' WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Award achievement
        $achievementStmt = $db->prepare("
            INSERT IGNORE INTO user_achievements 
            (user_id, achievement_type, achievement_name, achievement_date, points_earned) 
            VALUES (?, 'milestone', 'Reintroduction Phase Started', CURDATE(), 200)
        ");
        $achievementStmt->execute([$userId]);
        
        Helpers::redirect('/reintroduction/schedule.php', 'Welcome to the reintroduction phase! Time to discover your trigger foods.', 'success');
    } catch (Exception $e) {
        error_log("Phase transition error: " . $e->getMessage());
        Helpers::redirect('/reintroduction/schedule.php', 'Error updating phase. Please try again.', 'error');
    }
}

// Handle test scheduling
if ($_POST['action'] === 'schedule_test' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        Helpers::jsonResponse(['success' => false, 'message' => 'Security error'], 400);
    }
    
    $data = Security::sanitizeInput($_POST);
    $foodId = (int)$data['food_id'];
    $testDate = $data['test_date'];
    
    try {
        // Check if food is already being tested or has been tested
        $checkStmt = $db->prepare("
            SELECT id, test_status FROM reintroduction_tests 
            WHERE user_id = ? AND food_id = ? 
            ORDER BY created_at DESC LIMIT 1
        ");
        $checkStmt->execute([$userId, $foodId]);
        $existingTest = $checkStmt->fetch();
        
        if ($existingTest && in_array($existingTest['test_status'], ['planned', 'active'])) {
            Helpers::jsonResponse(['success' => false, 'message' => 'This food is already scheduled for testing.']);
        }
        
        // Schedule new test
        $stmt = $db->prepare("
            INSERT INTO reintroduction_tests 
            (user_id, food_id, test_start_date, test_status, next_test_date) 
            VALUES (?, ?, ?, 'planned', ?)
        ");
        
        $stmt->execute([$userId, $foodId, $testDate, $testDate]);
        
        Helpers::jsonResponse([
            'success' => true,
            'message' => 'Food test scheduled successfully!',
            'test_id' => $db->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        error_log("Test scheduling error: " . $e->getMessage());
        Helpers::jsonResponse(['success' => false, 'message' => 'Error scheduling test'], 500);
    }
}

// Handle test status updates
if ($_POST['action'] === 'update_test' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        Helpers::jsonResponse(['success' => false, 'message' => 'Security error'], 400);
    }
    
    $data = Security::sanitizeInput($_POST);
    $testId = (int)$data['test_id'];
    $status = $data['status'];
    $result = $data['result'] ?? null;
    $notes = $data['notes'] ?? '';
    
    try {
        $updateData = [
            'test_status' => $status,
            'notes' => $notes
        ];
        
        if ($status === 'completed') {
            $updateData['final_result'] = $result;
            $updateData['test_end_date'] = date('Y-m-d');
        }
        
        $setParts = [];
        $values = [];
        foreach ($updateData as $key => $value) {
            $setParts[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $testId;
        $values[] = $userId;
        
        $sql = "UPDATE reintroduction_tests SET " . implode(', ', $setParts) . " WHERE id = ? AND user_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        
        Helpers::jsonResponse([
            'success' => true,
            'message' => 'Test updated successfully!'
        ]);
        
    } catch (Exception $e) {
        error_log("Test update error: " . $e->getMessage());
        Helpers::jsonResponse(['success' => false, 'message' => 'Error updating test'], 500);
    }
}

// Get reintroduction foods (those not allowed in elimination)
$stmt = $db->prepare("
    SELECT id, name, category, subcategory, reintroduction_order 
    FROM food_database 
    WHERE elimination_allowed = 0 
    ORDER BY reintroduction_order ASC, category, name
");
$stmt->execute();
$reintroductionFoods = $stmt->fetchAll();

// Group by reintroduction order
$foodsByOrder = [];
foreach ($reintroductionFoods as $food) {
    $order = $food['reintroduction_order'] ?? 99;
    $foodsByOrder[$order][] = $food;
}
ksort($foodsByOrder);

// Get current and scheduled tests
$stmt = $db->prepare("
    SELECT rt.*, fd.name as food_name, fd.category, fd.subcategory
    FROM reintroduction_tests rt
    JOIN food_database fd ON rt.food_id = fd.id
    WHERE rt.user_id = ?
    ORDER BY rt.test_start_date DESC, rt.created_at DESC
");
$stmt->execute([$userId]);
$allTests = $stmt->fetchAll();

// Separate tests by status
$activeTests = array_filter($allTests, fn($test) => $test['test_status'] === 'active');
$plannedTests = array_filter($allTests, fn($test) => $test['test_status'] === 'planned');
$completedTests = array_filter($allTests, fn($test) => $test['test_status'] === 'completed');

// Get next suggested test date
$lastTestEnd = null;
if (!empty($completedTests)) {
    $lastTestEnd = max(array_column($completedTests, 'test_end_date'));
}

$suggestedDate = $lastTestEnd 
    ? date('Y-m-d', strtotime($lastTestEnd . ' + 7 days'))
    : date('Y-m-d');

$csrfToken = Security::generateCSRFToken();
$flashMessage = Helpers::getFlashMessage();

// Reintroduction order groups
$reintroductionGroups = [
    1 => [
        'name' => 'Stage 1: Egg Yolks & Seed Oils',
        'description' => 'Start with least inflammatory foods',
        'foods' => ['egg_yolk', 'avocado_oil', 'olive_oil']
    ],
    2 => [
        'name' => 'Stage 2: Herbs & Spices',
        'description' => 'Add back aromatic seasonings',
        'foods' => ['fresh_herbs', 'dried_herbs', 'mild_spices']
    ],
    3 => [
        'name' => 'Stage 3: Nuts & Seeds',
        'description' => 'Introduce tree nuts and seeds',
        'foods' => ['almonds', 'walnuts', 'sunflower_seeds']
    ],
    4 => [
        'name' => 'Stage 4: Nightshade Spices',
        'description' => 'Test nightshade-derived seasonings',
        'foods' => ['paprika', 'chili_powder']
    ],
    5 => [
        'name' => 'Stage 5: Whole Eggs',
        'description' => 'Add back egg whites with yolks',
        'foods' => ['whole_eggs']
    ],
    6 => [
        'name' => 'Stage 6: Nightshade Vegetables',
        'description' => 'Test inflammatory nightshades',
        'foods' => ['tomatoes', 'potatoes', 'peppers', 'eggplant']
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reintroduction Scheduler - AIP Tracker</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/reintroduction.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="../dashboard.php" class="logo">🌿 AIP Tracker</a>
                <nav class="quick-nav">
                    <a href="../dashboard.php" class="nav-link">Dashboard</a>
                    <a href="schedule.php" class="nav-link active">Reintroduction</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main">
        <div class="container">
            <?php if ($flashMessage): ?>
                <div class="alert alert-<?= $flashMessage['type'] ?>">
                    <?= htmlspecialchars($flashMessage['message']) ?>
                </div>
            <?php endif; ?>

            <div class="page-header">
                <h1>🔄 Reintroduction Phase</h1>
                <p class="page-subtitle">Systematically discover which foods work for your body</p>
                
                <?php if ($profile['current_phase'] === 'elimination'): ?>
                    <div class="phase-status elimination-status">
                        <div class="status-info">
                            <span class="status-label">Current Phase:</span>
                            <span class="status-value">Elimination</span>
                            <span class="status-detail">Day <?= $daysActive ?> of <?= $profile['target_elimination_days'] ?></span>
                        </div>
                        
                        <?php if ($isReadyForReintroduction): ?>
                            <div class="transition-ready">
                                <p><strong>🎉 Ready for reintroduction!</strong> You've completed your elimination phase.</p>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="start_reintroduction">
                                    <button type="submit" class="btn btn-primary">Start Reintroduction Phase</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="transition-pending">
                                <p>Complete your elimination phase before starting reintroduction.</p>
                                <div class="days-remaining">
                                    <?= max(0, $profile['target_elimination_days'] - $daysActive) ?> days remaining
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="phase-status reintroduction-status">
                        <div class="status-info">
                            <span class="status-label">Current Phase:</span>
                            <span class="status-value">Reintroduction</span>
                            <span class="status-detail"><?= count($completedTests) ?> foods tested</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($profile['current_phase'] !== 'elimination' || $isReadyForReintroduction): ?>

            <!-- Active Tests -->
            <?php if (!empty($activeTests)): ?>
                <div class="section active-tests-section">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">🔬 Current Tests</h2>
                            <p class="card-subtitle">Foods currently being tested</p>
                        </div>
                        
                        <div class="active-tests">
                            <?php foreach ($activeTests as $test): ?>
                                <div class="test-item active-test">
                                    <div class="test-food">
                                        <div class="food-name"><?= htmlspecialchars($test['food_name']) ?></div>
                                        <div class="food-category"><?= ucfirst($test['category']) ?></div>
                                    </div>
                                    
                                    <div class="test-timeline">
                                        <div class="timeline-day">
                                            <span class="day-label">Day 1</span>
                                            <span class="day-status completed">✓</span>
                                        </div>
                                        <div class="timeline-day">
                                            <span class="day-label">Day 2-6</span>
                                            <span class="day-status monitoring">👁️</span>
                                        </div>
                                        <div class="timeline-day">
                                            <span class="day-label">Day 7</span>
                                            <span class="day-status pending">⏳</span>
                                        </div>
                                    </div>
                                    
                                    <div class="test-actions">
                                        <button class="btn btn-small btn-success" 
                                                onclick="completeTest(<?= $test['id'] ?>, 'tolerated')">
                                            ✅ Tolerated
                                        </button>
                                        <button class="btn btn-small btn-warning" 
                                                onclick="completeTest(<?= $test['id'] ?>, 'not_tolerated')">
                                            ❌ Reaction
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Test Scheduling -->
            <?php if (empty($activeTests)): ?>
                <div class="section scheduling-section">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">📅 Schedule Next Test</h2>
                            <p class="card-subtitle">Choose a food to test next</p>
                        </div>
                        
                        <div class="reintroduction-stages">
                            <?php foreach ($reintroductionGroups as $stage => $group): ?>
                                <div class="stage-group">
                                    <div class="stage-header">
                                        <h3 class="stage-title"><?= $group['name'] ?></h3>
                                        <p class="stage-description"><?= $group['description'] ?></p>
                                    </div>
                                    
                                    <div class="stage-foods">
                                        <?php 
                                        $stageFoods = $foodsByOrder[$stage] ?? [];
                                        foreach ($stageFoods as $food):
                                            // Check if already tested
                                            $isCompleted = false;
                                            $isPending = false;
                                            foreach ($allTests as $test) {
                                                if ($test['food_id'] == $food['id']) {
                                                    if ($test['test_status'] === 'completed') $isCompleted = true;
                                                    if (in_array($test['test_status'], ['planned', 'active'])) $isPending = true;
                                                    break;
                                                }
                                            }
                                        ?>
                                            <div class="food-option <?= $isCompleted ? 'completed' : '' ?> <?= $isPending ? 'pending' : '' ?>" 
                                                 data-food-id="<?= $food['id'] ?>">
                                                <div class="food-info">
                                                    <div class="food-name"><?= htmlspecialchars($food['name']) ?></div>
                                                    <div class="food-category"><?= ucfirst($food['category']) ?></div>
                                                </div>
                                                
                                                <div class="food-status">
                                                    <?php if ($isCompleted): ?>
                                                        <span class="status-badge completed">✅ Tested</span>
                                                    <?php elseif ($isPending): ?>
                                                        <span class="status-badge pending">⏳ Scheduled</span>
                                                    <?php else: ?>
                                                        <button class="btn btn-primary btn-small schedule-btn" 
                                                                onclick="scheduleTest(<?= $food['id'] ?>, '<?= htmlspecialchars($food['name']) ?>')">
                                                            Schedule Test
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if (empty($stageFoods)): ?>
                                            <div class="stage-placeholder">
                                                <p class="text-secondary">Foods for this stage will be added in future updates.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Test Results History -->
            <?php if (!empty($completedTests)): ?>
                <div class="section results-section">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">📊 Test Results</h2>
                            <p class="card-subtitle">Your food tolerance history</p>
                        </div>
                        
                        <div class="results-summary">
                            <div class="summary-stats">
                                <div class="summary-stat">
                                    <span class="stat-value"><?= count($completedTests) ?></span>
                                    <span class="stat-label">Foods Tested</span>
                                </div>
                                <div class="summary-stat">
                                    <span class="stat-value text-success">
                                        <?= count(array_filter($completedTests, fn($t) => $t['final_result'] === 'tolerated')) ?>
                                    </span>
                                    <span class="stat-label">Tolerated</span>
                                </div>
                                <div class="summary-stat">
                                    <span class="stat-value text-error">
                                        <?= count(array_filter($completedTests, fn($t) => $t['final_result'] === 'not_tolerated')) ?>
                                    </span>
                                    <span class="stat-label">Reactions</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="test-history">
                            <?php foreach ($completedTests as $test): ?>
                                <div class="test-result">
                                    <div class="result-food">
                                        <div class="food-name"><?= htmlspecialchars($test['food_name']) ?></div>
                                        <div class="test-date"><?= Helpers::formatDate($test['test_start_date']) ?></div>
                                    </div>
                                    
                                    <div class="result-status">
                                        <?php if ($test['final_result'] === 'tolerated'): ?>
                                            <span class="result-badge tolerated">✅ Tolerated</span>
                                        <?php elseif ($test['final_result'] === 'not_tolerated'): ?>
                                            <span class="result-badge not-tolerated">❌ Reaction</span>
                                        <?php else: ?>
                                            <span class="result-badge inconclusive">❓ Inconclusive</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($test['notes']): ?>
                                        <div class="result-notes">
                                            <strong>Notes:</strong> <?= htmlspecialchars($test['notes']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Reintroduction Guidelines -->
            <div class="section guidelines-section">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">📋 Reintroduction Guidelines</h2>
                        <p class="card-subtitle">Follow the protocol for best results</p>
                    </div>
                    
                    <div class="guidelines-content">
                        <div class="guideline-steps">
                            <div class="guideline-step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h4>Day 1 - Introduction Day</h4>
                                    <p>Eat a small amount (1/2 tsp) of the test food, wait 15 minutes. If no reaction, eat 1 tsp and wait 2-3 hours. If still no reaction, eat a normal serving.</p>
                                </div>
                            </div>
                            
                            <div class="guideline-step">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <h4>Days 2-6 - Monitoring Period</h4>
                                    <p>Avoid the test food completely. Monitor for delayed reactions: digestive issues, skin problems, joint pain, mood changes, or other symptoms.</p>
                                </div>
                            </div>
                            
                            <div class="guideline-step">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <h4>Day 7 - Decision Day</h4>
                                    <p>If no reactions occurred, the food is likely tolerated and can be added back to your diet. If reactions occurred, avoid this food long-term.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="important-notes">
                            <h4>⚠️ Important Notes</h4>
                            <ul>
                                <li>Test only one food at a time</li>
                                <li>Wait at least 7 days between tests</li>
                                <li>Don't test during illness, stress, or poor sleep</li>
                                <li>Keep detailed notes about any reactions</li>
                                <li>Some reactions can be delayed up to 72 hours</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="../dashboard.php" class="btn btn-outline">← Back to Dashboard</a>
                <a href="../symptoms/track.php" class="btn btn-secondary">Track Symptoms →</a>
            </div>
        </div>
    </main>

    <!-- Test Completion Modal -->
    <div id="completion-modal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Complete Food Test</h3>
            </div>
            <div class="modal-body">
                <form id="completion-form">
                    <input type="hidden" id="test-id" value="">
                    <input type="hidden" id="test-result" value="">
                    
                    <div class="form-group">
                        <label for="test-notes" class="form-label">Notes about this test:</label>
                        <textarea id="test-notes" class="form-textarea" rows="4" 
                                  placeholder="Describe any reactions, symptoms, or observations..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveTestCompletion()">Complete Test</button>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = '<?= $csrfToken ?>';
        let currentTestId = null;
        let currentResult = null;

        function scheduleTest(foodId, foodName) {
            const testDate = prompt(`When would you like to start testing ${foodName}?\n\nEnter date (YYYY-MM-DD):`, '<?= $suggestedDate ?>');
            
            if (!testDate) return;
            
            if (!/^\d{4}-\d{2}-\d{2}$/.test(testDate)) {
                alert('Please enter a valid date in YYYY-MM-DD format');
                return;
            }
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=schedule_test&csrf_token=${csrfToken}&food_id=${foodId}&test_date=${testDate}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message || 'Error scheduling test');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            });
        }

        function completeTest(testId, result) {
            currentTestId = testId;
            currentResult = result;
            
            const modal = document.getElementById('completion-modal');
            const title = document.getElementById('modal-title');
            
            title.textContent = result === 'tolerated' ? 'Food Tolerated ✅' : 'Food Reaction ❌';
            
            document.getElementById('test-id').value = testId;
            document.getElementById('test-result').value = result;
            
            modal.classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('completion-modal').classList.add('hidden');
            document.getElementById('test-notes').value = '';
            currentTestId = null;
            currentResult = null;
        }

        function saveTestCompletion() {
            const notes = document.getElementById('test-notes').value;
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_test&csrf_token=${csrfToken}&test_id=${currentTestId}&status=completed&result=${currentResult}&notes=${encodeURIComponent(notes)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal();
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message || 'Error completing test');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            });
        }

        // Close modal when clicking outside
        document.getElementById('completion-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>