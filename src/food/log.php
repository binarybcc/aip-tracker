<?php
/**
 * AIP Tracker - Food Logging Interface
 * Quick and easy meal tracking with AIP-compliant foods
 */

require_once '../config/config.php';

Helpers::requireLogin();

$db = (new Database())->connect();
$userId = Helpers::getCurrentUserId();
$today = date('Y-m-d');

// Handle food logging submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        Helpers::redirect('/food/log.php', 'Security error. Please try again.', 'error');
    }
    
    $data = Security::sanitizeInput($_POST);
    
    try {
        // Insert food log entry
        $stmt = $db->prepare("
            INSERT INTO food_logs (user_id, food_id, meal_type, portion_size, log_date, log_time, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $logTime = date('H:i:s');
        $stmt->execute([
            $userId,
            $data['food_id'],
            $data['meal_type'],
            $data['portion_size'],
            $data['log_date'] ?? $today,
            $logTime,
            $data['notes'] ?? ''
        ]);
        
        // Check for achievements
        $mealCount = 0;
        $checkStmt = $db->prepare("
            SELECT COUNT(DISTINCT meal_type) as meal_count 
            FROM food_logs 
            WHERE user_id = ? AND log_date = ?
        ");
        $checkStmt->execute([$userId, $data['log_date'] ?? $today]);
        $result = $checkStmt->fetch();
        $mealCount = $result['meal_count'];
        
        // Award achievement for logging all meals
        if ($mealCount >= 4) {
            $achievementStmt = $db->prepare("
                INSERT IGNORE INTO user_achievements 
                (user_id, achievement_type, achievement_name, achievement_date, points_earned) 
                VALUES (?, 'milestone', 'Complete Day Logged', ?, 50)
            ");
            $achievementStmt->execute([$userId, $data['log_date'] ?? $today]);
        }
        
        Helpers::redirect('/food/log.php', 'Food logged successfully! Keep it up!', 'success');
        
    } catch (Exception $e) {
        error_log("Food logging error: " . $e->getMessage());
        Helpers::redirect('/food/log.php', 'Error logging food. Please try again.', 'error');
    }
}

// Get AIP-compliant foods for dropdown
$stmt = $db->prepare("
    SELECT id, name, category, subcategory, common_portions 
    FROM food_database 
    WHERE elimination_allowed = 1 
    ORDER BY category, name
");
$stmt->execute();
$foods = $stmt->fetchAll();

// Group foods by category
$foodsByCategory = [];
foreach ($foods as $food) {
    $foodsByCategory[$food['category']][] = $food;
}

// Get today's logged meals
$stmt = $db->prepare("
    SELECT fl.*, fd.name as food_name, fd.category 
    FROM food_logs fl 
    JOIN food_database fd ON fl.food_id = fd.id 
    WHERE fl.user_id = ? AND fl.log_date = ? 
    ORDER BY fl.log_time DESC
");
$stmt->execute([$userId, $today]);
$todaysMeals = $stmt->fetchAll();

$csrfToken = Security::generateCSRFToken();
$flashMessage = Helpers::getFlashMessage();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Food - AIP Tracker</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/food-log.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="../dashboard.php" class="logo">🌿 AIP Tracker</a>
                <nav class="quick-nav">
                    <a href="../dashboard.php" class="nav-link">Dashboard</a>
                    <a href="log.php" class="nav-link active">Log Food</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main">
        <div class="container container-sm">
            <?php if ($flashMessage): ?>
                <div class="alert alert-<?= $flashMessage['type'] ?>">
                    <?= htmlspecialchars($flashMessage['message']) ?>
                </div>
            <?php endif; ?>

            <div class="page-header">
                <h1>🍎 Log Your Food</h1>
                <p class="page-subtitle">Track what you're eating to stay AIP compliant</p>
            </div>

            <!-- Quick Add Form -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Add Food Entry</h2>
                    <p class="card-subtitle">Quick logging for your meals and snacks</p>
                </div>

                <form method="POST" class="food-form" id="foodForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="meal_type" class="form-label">Meal Type</label>
                            <select name="meal_type" id="meal_type" class="form-select" required>
                                <option value="">Select meal type</option>
                                <option value="breakfast">🌅 Breakfast</option>
                                <option value="lunch">☀️ Lunch</option>
                                <option value="dinner">🌙 Dinner</option>
                                <option value="snack">🥜 Snack</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="log_date" class="form-label">Date</label>
                            <input type="date" name="log_date" id="log_date" 
                                   class="form-input" value="<?= $today ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="food_search" class="form-label">Search Food</label>
                        <input type="text" id="food_search" class="form-input" 
                               placeholder="Type to search AIP foods..." autocomplete="off">
                        <input type="hidden" name="food_id" id="food_id" required>
                    </div>

                    <div id="food_results" class="food-results hidden"></div>

                    <div class="form-group">
                        <label for="portion_size" class="form-label">Portion Size</label>
                        <select name="portion_size" id="portion_size" class="form-select" required>
                            <option value="">Select portion size</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea name="notes" id="notes" class="form-textarea" rows="3" 
                                  placeholder="How did you prepare it? Any reactions?"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-large" id="submitBtn" disabled>
                        Log Food Entry
                    </button>
                </form>
            </div>

            <!-- Today's Meals Summary -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Today's Meals</h2>
                    <p class="card-subtitle">What you've logged so far</p>
                </div>

                <?php if (empty($todaysMeals)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📝</div>
                        <p>No meals logged today yet.</p>
                        <p class="text-secondary">Start by adding your first meal above!</p>
                    </div>
                <?php else: ?>
                    <div class="meals-timeline">
                        <?php 
                        $mealIcons = [
                            'breakfast' => '🌅',
                            'lunch' => '☀️', 
                            'dinner' => '🌙',
                            'snack' => '🥜'
                        ];
                        ?>
                        <?php foreach ($todaysMeals as $meal): ?>
                            <div class="meal-entry">
                                <div class="meal-time">
                                    <span class="meal-icon"><?= $mealIcons[$meal['meal_type']] ?? '🍽️' ?></span>
                                    <span class="meal-time-text"><?= date('g:i A', strtotime($meal['log_time'])) ?></span>
                                </div>
                                <div class="meal-details">
                                    <div class="meal-food">
                                        <strong><?= htmlspecialchars($meal['food_name']) ?></strong>
                                        <span class="meal-category"><?= ucfirst($meal['category']) ?></span>
                                    </div>
                                    <div class="meal-portion"><?= htmlspecialchars($meal['portion_size']) ?></div>
                                    <?php if ($meal['notes']): ?>
                                        <div class="meal-notes"><?= htmlspecialchars($meal['notes']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="meal-type">
                                    <span class="meal-type-badge"><?= ucfirst($meal['meal_type']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="meals-summary">
                        <div class="summary-stats">
                            <div class="summary-stat">
                                <span class="stat-value"><?= count($todaysMeals) ?></span>
                                <span class="stat-label">Entries Today</span>
                            </div>
                            <div class="summary-stat">
                                <span class="stat-value"><?= count(array_unique(array_column($todaysMeals, 'meal_type'))) ?></span>
                                <span class="stat-label">Meal Types</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="../dashboard.php" class="btn btn-outline">← Back to Dashboard</a>
                <a href="../symptoms/track.php" class="btn btn-secondary">Track Symptoms →</a>
            </div>
        </div>
    </main>

    <script>
        // Food search functionality
        const foods = <?= json_encode($foods) ?>;
        const foodSearch = document.getElementById('food_search');
        const foodResults = document.getElementById('food_results');
        const foodIdInput = document.getElementById('food_id');
        const portionSelect = document.getElementById('portion_size');
        const submitBtn = document.getElementById('submitBtn');

        foodSearch.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            
            if (query.length < 2) {
                foodResults.classList.add('hidden');
                return;
            }

            const matches = foods.filter(food => 
                food.name.toLowerCase().includes(query) ||
                food.category.toLowerCase().includes(query)
            );

            if (matches.length === 0) {
                foodResults.innerHTML = '<div class="no-results">No AIP foods found</div>';
                foodResults.classList.remove('hidden');
                return;
            }

            let html = '';
            matches.slice(0, 8).forEach(food => {
                html += `
                    <div class="food-result-item" data-food-id="${food.id}">
                        <div class="food-name">${food.name}</div>
                        <div class="food-category">${food.category}</div>
                    </div>
                `;
            });

            foodResults.innerHTML = html;
            foodResults.classList.remove('hidden');

            // Add click handlers
            foodResults.querySelectorAll('.food-result-item').forEach(item => {
                item.addEventListener('click', function() {
                    const foodId = this.dataset.foodId;
                    const selectedFood = foods.find(f => f.id == foodId);
                    
                    foodSearch.value = selectedFood.name;
                    foodIdInput.value = foodId;
                    foodResults.classList.add('hidden');
                    
                    // Update portion options
                    updatePortionOptions(selectedFood);
                    
                    // Enable submit button
                    checkFormValidity();
                });
            });
        });

        function updatePortionOptions(food) {
            let portions = ['Small', 'Medium', 'Large'];
            
            if (food.common_portions) {
                try {
                    portions = JSON.parse(food.common_portions);
                } catch (e) {
                    console.log('Could not parse portions');
                }
            }

            portionSelect.innerHTML = '<option value="">Select portion size</option>';
            portions.forEach(portion => {
                const option = document.createElement('option');
                option.value = portion;
                option.textContent = portion;
                portionSelect.appendChild(option);
            });
        }

        function checkFormValidity() {
            const mealType = document.getElementById('meal_type').value;
            const foodId = document.getElementById('food_id').value;
            const portionSize = document.getElementById('portion_size').value;
            
            submitBtn.disabled = !(mealType && foodId && portionSize);
        }

        // Check form validity on input changes
        ['meal_type', 'portion_size'].forEach(id => {
            document.getElementById(id).addEventListener('change', checkFormValidity);
        });

        // Hide search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!foodSearch.contains(e.target) && !foodResults.contains(e.target)) {
                foodResults.classList.add('hidden');
            }
        });

        // Auto-select meal type based on time of day
        const currentHour = new Date().getHours();
        const mealTypeSelect = document.getElementById('meal_type');
        
        if (currentHour < 10) {
            mealTypeSelect.value = 'breakfast';
        } else if (currentHour < 14) {
            mealTypeSelect.value = 'lunch';
        } else if (currentHour < 19) {
            mealTypeSelect.value = 'dinner';
        } else {
            mealTypeSelect.value = 'snack';
        }
        
        checkFormValidity();
    </script>
</body>
</html>