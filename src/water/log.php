<?php
/**
 * AIP Tracker - Water Logging
 * Quick water intake tracking with goal monitoring
 */

require_once '../config/config.php';

Helpers::requireLogin();

$db = (new Database())->connect();
$userId = Helpers::getCurrentUserId();
$today = date('Y-m-d');

// Get user water goal
$stmt = $db->prepare("SELECT water_goal_ml FROM user_profiles WHERE user_id = ?");
$stmt->execute([$userId]);
$profile = $stmt->fetch();
$waterGoal = $profile ? (int)$profile['water_goal_ml'] : 2000;

// Handle water logging submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        Helpers::jsonResponse(['success' => false, 'message' => 'Security error'], 400);
    }
    
    $amount = (int)($_POST['amount'] ?? 0);
    
    if ($amount <= 0 || $amount > 1000) {
        Helpers::jsonResponse(['success' => false, 'message' => 'Invalid amount'], 400);
    }
    
    try {
        // Insert water log entry
        $stmt = $db->prepare("
            INSERT INTO water_logs (user_id, log_date, log_time, amount_ml) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $today,
            date('H:i:s'),
            $amount
        ]);
        
        // Get updated total
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(amount_ml), 0) as total 
            FROM water_logs 
            WHERE user_id = ? AND log_date = ?
        ");
        $stmt->execute([$userId, $today]);
        $result = $stmt->fetch();
        $todayTotal = (int)$result['total'];
        
        // Check for goal achievement
        $goalReached = $todayTotal >= $waterGoal;
        if ($goalReached) {
            // Check if already awarded today
            $checkStmt = $db->prepare("
                SELECT id FROM user_achievements 
                WHERE user_id = ? AND achievement_name = 'Daily Water Goal' AND achievement_date = ?
            ");
            $checkStmt->execute([$userId, $today]);
            
            if (!$checkStmt->fetch()) {
                $achievementStmt = $db->prepare("
                    INSERT INTO user_achievements 
                    (user_id, achievement_type, achievement_name, achievement_date, points_earned) 
                    VALUES (?, 'milestone', 'Daily Water Goal', ?, 25)
                ");
                $achievementStmt->execute([$userId, $today]);
            }
        }
        
        $percentage = min(100, round(($todayTotal / $waterGoal) * 100));
        
        Helpers::jsonResponse([
            'success' => true,
            'message' => 'Water logged successfully!',
            'total' => $todayTotal,
            'goal' => $waterGoal,
            'percentage' => $percentage,
            'goal_reached' => $goalReached
        ]);
        
    } catch (Exception $e) {
        error_log("Water logging error: " . $e->getMessage());
        Helpers::jsonResponse(['success' => false, 'message' => 'Error logging water'], 500);
    }
}

// Get today's water intake
$stmt = $db->prepare("
    SELECT SUM(amount_ml) as total, COUNT(*) as entries 
    FROM water_logs 
    WHERE user_id = ? AND log_date = ?
");
$stmt->execute([$userId, $today]);
$todayStats = $stmt->fetch();
$todayTotal = (int)($todayStats['total'] ?? 0);
$todayEntries = (int)($todayStats['entries'] ?? 0);
$percentage = min(100, round(($todayTotal / $waterGoal) * 100));

// Get recent water entries
$stmt = $db->prepare("
    SELECT amount_ml, log_time 
    FROM water_logs 
    WHERE user_id = ? AND log_date = ? 
    ORDER BY log_time DESC 
    LIMIT 10
");
$stmt->execute([$userId, $today]);
$recentEntries = $stmt->fetchAll();

$csrfToken = Security::generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Tracking - AIP Tracker</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/water-log.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="../dashboard.php" class="logo">🌿 AIP Tracker</a>
                <nav class="quick-nav">
                    <a href="../dashboard.php" class="nav-link">Dashboard</a>
                    <a href="log.php" class="nav-link active">Water Tracker</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main">
        <div class="container container-sm">
            <div class="page-header">
                <h1>💧 Water Tracker</h1>
                <p class="page-subtitle">Stay hydrated for optimal healing</p>
            </div>

            <!-- Water Progress -->
            <div class="card water-progress-card">
                <div class="water-goal-visual">
                    <div class="water-bottle">
                        <div class="water-fill" style="height: <?= $percentage ?>%"></div>
                        <div class="water-level-text">
                            <span class="current-amount"><?= $todayTotal ?>ml</span>
                            <span class="goal-amount">/ <?= $waterGoal ?>ml</span>
                        </div>
                    </div>
                    
                    <div class="progress-stats">
                        <div class="progress-percentage"><?= $percentage ?>%</div>
                        <div class="progress-label">Daily Goal</div>
                        
                        <?php if ($percentage >= 100): ?>
                            <div class="goal-reached">
                                🎉 Goal Reached!
                            </div>
                        <?php else: ?>
                            <div class="remaining">
                                <?= $waterGoal - $todayTotal ?>ml remaining
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Add Buttons -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Quick Add</h2>
                    <p class="card-subtitle">Tap to log water intake</p>
                </div>

                <div class="quick-add-buttons" id="quickAddButtons">
                    <button class="quick-add-btn" data-amount="250">
                        <div class="btn-icon">🥤</div>
                        <div class="btn-label">Glass</div>
                        <div class="btn-amount">250ml</div>
                    </button>
                    
                    <button class="quick-add-btn" data-amount="500">
                        <div class="btn-icon">🍶</div>
                        <div class="btn-label">Bottle</div>
                        <div class="btn-amount">500ml</div>
                    </button>
                    
                    <button class="quick-add-btn" data-amount="750">
                        <div class="btn-icon">🏺</div>
                        <div class="btn-label">Large Bottle</div>
                        <div class="btn-amount">750ml</div>
                    </button>
                    
                    <button class="quick-add-btn" data-amount="100">
                        <div class="btn-icon">☕</div>
                        <div class="btn-label">Tea Cup</div>
                        <div class="btn-amount">100ml</div>
                    </button>
                </div>

                <!-- Custom Amount -->
                <div class="custom-amount-section">
                    <div class="custom-amount-input">
                        <label for="custom-amount">Custom Amount (ml):</label>
                        <input type="number" id="custom-amount" min="1" max="1000" placeholder="Enter amount">
                        <button id="add-custom" class="btn btn-outline btn-small">Add</button>
                    </div>
                </div>
            </div>

            <!-- Today's Entries -->
            <?php if (!empty($recentEntries)): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Today's Water Log</h2>
                    <p class="card-subtitle"><?= $todayEntries ?> entries • <?= $todayTotal ?>ml total</p>
                </div>

                <div class="water-entries">
                    <?php foreach ($recentEntries as $entry): ?>
                        <div class="water-entry">
                            <div class="entry-time">
                                <?= date('g:i A', strtotime($entry['log_time'])) ?>
                            </div>
                            <div class="entry-amount">
                                <span class="amount-value"><?= $entry['amount_ml'] ?>ml</span>
                            </div>
                            <div class="entry-visual">
                                <div class="water-drops">
                                    <?php
                                    $drops = min(5, ceil($entry['amount_ml'] / 100));
                                    for ($i = 0; $i < $drops; $i++) {
                                        echo '💧';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Motivation & Tips -->
            <div class="card hydration-tips">
                <div class="card-header">
                    <h2 class="card-title">💡 Hydration Tips</h2>
                </div>
                
                <div class="tips-content">
                    <div class="tip">
                        <strong>🌅 Morning:</strong> Start with 500ml upon waking to kickstart hydration.
                    </div>
                    <div class="tip">
                        <strong>🍽️ With Meals:</strong> Drink water 30 minutes before meals for better digestion.
                    </div>
                    <div class="tip">
                        <strong>⏰ Regular Intervals:</strong> Set reminders every 1-2 hours throughout the day.
                    </div>
                    <div class="tip">
                        <strong>🌿 AIP Friendly:</strong> Try herbal teas like ginger or chamomile for variety.
                    </div>
                </div>
            </div>

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
            <div class="modal-icon">💧</div>
            <div class="modal-message">Water logged successfully!</div>
            <div class="modal-stats">
                <span id="modal-total">0ml</span> / <span id="modal-goal">0ml</span>
            </div>
            <button class="btn btn-primary" onclick="closeModal()">Continue</button>
        </div>
    </div>

    <script>
        const csrfToken = '<?= $csrfToken ?>';
        let currentTotal = <?= $todayTotal ?>;
        let waterGoal = <?= $waterGoal ?>;

        // Quick add button functionality
        document.getElementById('quickAddButtons').addEventListener('click', function(e) {
            const btn = e.target.closest('.quick-add-btn');
            if (!btn) return;

            const amount = parseInt(btn.dataset.amount);
            logWater(amount, btn);
        });

        // Custom amount functionality
        document.getElementById('add-custom').addEventListener('click', function() {
            const customInput = document.getElementById('custom-amount');
            const amount = parseInt(customInput.value);

            if (!amount || amount <= 0 || amount > 1000) {
                alert('Please enter a valid amount between 1 and 1000ml');
                return;
            }

            logWater(amount, this);
            customInput.value = '';
        });

        // Enter key support for custom amount
        document.getElementById('custom-amount').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('add-custom').click();
            }
        });

        function logWater(amount, btn) {
            // Disable button temporarily
            const originalText = btn.innerHTML;
            btn.innerHTML = '<div class="btn-loading">⏳</div>';
            btn.disabled = true;

            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `csrf_token=${csrfToken}&amount=${amount}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    updateWaterProgress(data.total, data.percentage, data.goal_reached);
                    
                    // Show success feedback
                    showSuccessModal(data);
                    
                    // Add entry to recent entries
                    addRecentEntry(amount);
                } else {
                    alert(data.message || 'Error logging water');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            })
            .finally(() => {
                // Re-enable button
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        function updateWaterProgress(total, percentage, goalReached) {
            const waterFill = document.querySelector('.water-fill');
            const currentAmount = document.querySelector('.current-amount');
            const progressPercentage = document.querySelector('.progress-percentage');
            const remaining = document.querySelector('.remaining');

            waterFill.style.height = percentage + '%';
            currentAmount.textContent = total + 'ml';
            progressPercentage.textContent = percentage + '%';

            if (goalReached && remaining) {
                remaining.innerHTML = '🎉 Goal Reached!';
                remaining.className = 'goal-reached';
            } else if (remaining) {
                remaining.textContent = (waterGoal - total) + 'ml remaining';
            }

            currentTotal = total;
        }

        function showSuccessModal(data) {
            const modal = document.getElementById('success-modal');
            const modalTotal = document.getElementById('modal-total');
            const modalGoal = document.getElementById('modal-goal');

            modalTotal.textContent = data.total + 'ml';
            modalGoal.textContent = data.goal + 'ml';

            modal.classList.remove('hidden');

            if (data.goal_reached) {
                document.querySelector('.modal-message').textContent = '🎉 Daily goal reached!';
            }

            // Auto-close after 3 seconds
            setTimeout(() => {
                closeModal();
            }, 3000);
        }

        function closeModal() {
            document.getElementById('success-modal').classList.add('hidden');
        }

        function addRecentEntry(amount) {
            const entriesContainer = document.querySelector('.water-entries');
            if (!entriesContainer) {
                // If no entries exist, reload page to show the entries section
                setTimeout(() => location.reload(), 1500);
                return;
            }

            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                hour12: true 
            });

            const drops = Math.min(5, Math.ceil(amount / 100));
            let dropsHtml = '';
            for (let i = 0; i < drops; i++) {
                dropsHtml += '💧';
            }

            const entryHtml = `
                <div class="water-entry new-entry">
                    <div class="entry-time">${timeString}</div>
                    <div class="entry-amount">
                        <span class="amount-value">${amount}ml</span>
                    </div>
                    <div class="entry-visual">
                        <div class="water-drops">${dropsHtml}</div>
                    </div>
                </div>
            `;

            entriesContainer.insertAdjacentHTML('afterbegin', entryHtml);

            // Highlight new entry
            setTimeout(() => {
                document.querySelector('.new-entry')?.classList.remove('new-entry');
            }, 2000);
        }

        // Close modal when clicking outside
        document.getElementById('success-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>