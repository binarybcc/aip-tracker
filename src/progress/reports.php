<?php
/**
 * AIP Tracker - Progress Analytics and Reports
 * Comprehensive visualization of user progress and trends
 */

require_once '../config/config.php';

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

$startDate = $profile['start_date'];
$daysActive = Helpers::daysSinceStart($startDate);

// Time period filter
$period = $_GET['period'] ?? '30';
$validPeriods = ['7', '30', '90', 'all'];
if (!in_array($period, $validPeriods)) $period = '30';

$dateFilter = $period === 'all' ? '' : "AND log_date >= DATE_SUB(CURDATE(), INTERVAL $period DAY)";

// Get symptom trends over time
$stmt = $db->prepare("
    SELECT 
        log_date,
        symptom_type,
        AVG(severity) as avg_severity,
        COUNT(*) as symptom_count,
        MAX(severity) as max_severity,
        MIN(severity) as min_severity
    FROM symptom_logs 
    WHERE user_id = ? $dateFilter
    GROUP BY log_date, symptom_type 
    ORDER BY log_date ASC, symptom_type
");
$stmt->execute([$userId]);
$symptomTrends = $stmt->fetchAll();

// Get food logging consistency
$stmt = $db->prepare("
    SELECT 
        log_date,
        COUNT(DISTINCT meal_type) as meals_logged,
        COUNT(*) as total_entries
    FROM food_logs 
    WHERE user_id = ? $dateFilter
    GROUP BY log_date 
    ORDER BY log_date ASC
");
$stmt->execute([$userId]);
$foodConsistency = $stmt->fetchAll();

// Get water intake trends
$stmt = $db->prepare("
    SELECT 
        log_date,
        SUM(amount_ml) as total_water,
        COUNT(*) as water_entries,
        AVG(amount_ml) as avg_per_entry
    FROM water_logs 
    WHERE user_id = ? $dateFilter
    GROUP BY log_date 
    ORDER BY log_date ASC
");
$stmt->execute([$userId]);
$waterTrends = $stmt->fetchAll();

// Get reintroduction progress
$stmt = $db->prepare("
    SELECT 
        rt.*,
        fd.name as food_name,
        fd.category,
        fd.reintroduction_order
    FROM reintroduction_tests rt
    JOIN food_database fd ON rt.food_id = fd.id
    WHERE rt.user_id = ?
    ORDER BY rt.test_start_date DESC
");
$stmt->execute([$userId]);
$reintroductionTests = $stmt->fetchAll();

// Calculate overall statistics
$totalSymptomLogs = 0;
$avgSymptomSeverity = 0;
$symptomsByCategory = [];

foreach ($symptomTrends as $trend) {
    $totalSymptomLogs += $trend['symptom_count'];
    $avgSymptomSeverity += ($trend['avg_severity'] * $trend['symptom_count']);
    
    if (!isset($symptomsByCategory[$trend['symptom_type']])) {
        $symptomsByCategory[$trend['symptom_type']] = [
            'total_logs' => 0,
            'avg_severity' => 0,
            'trend' => []
        ];
    }
    $symptomsByCategory[$trend['symptom_type']]['total_logs'] += $trend['symptom_count'];
    $symptomsByCategory[$trend['symptom_type']]['trend'][] = $trend;
}

if ($totalSymptomLogs > 0) {
    $avgSymptomSeverity = round($avgSymptomSeverity / $totalSymptomLogs, 1);
}

// Calculate category averages
foreach ($symptomsByCategory as $category => &$data) {
    $totalSeverity = 0;
    $count = 0;
    foreach ($data['trend'] as $point) {
        $totalSeverity += ($point['avg_severity'] * $point['symptom_count']);
        $count += $point['symptom_count'];
    }
    $data['avg_severity'] = $count > 0 ? round($totalSeverity / $count, 1) : 0;
}

// Food compliance statistics
$totalFoodDays = count($foodConsistency);
$compliantDays = count(array_filter($foodConsistency, fn($day) => $day['meals_logged'] >= 3));
$complianceRate = $totalFoodDays > 0 ? round(($compliantDays / $totalFoodDays) * 100) : 0;

// Water goal achievement
$waterGoal = (int)$profile['water_goal_ml'];
$waterGoalDays = 0;
$totalWaterDays = count($waterTrends);

foreach ($waterTrends as $day) {
    if ($day['total_water'] >= $waterGoal) {
        $waterGoalDays++;
    }
}
$waterComplianceRate = $totalWaterDays > 0 ? round(($waterGoalDays / $totalWaterDays) * 100) : 0;

// Prepare chart data
$chartData = [
    'symptom_trends' => [],
    'food_consistency' => [],
    'water_trends' => []
];

// Group symptom data by date for charts
$symptomChartData = [];
foreach ($symptomTrends as $trend) {
    if (!isset($symptomChartData[$trend['log_date']])) {
        $symptomChartData[$trend['log_date']] = [];
    }
    $symptomChartData[$trend['log_date']][$trend['symptom_type']] = $trend['avg_severity'];
}

$chartData['symptom_trends'] = $symptomChartData;
$chartData['food_consistency'] = array_column($foodConsistency, 'meals_logged', 'log_date');
$chartData['water_trends'] = array_column($waterTrends, 'total_water', 'log_date');

$csrfToken = Security::generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Reports - AIP Tracker</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/reports.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="../dashboard.php" class="logo">🌿 AIP Tracker</a>
                <nav class="quick-nav">
                    <a href="../dashboard.php" class="nav-link">Dashboard</a>
                    <a href="reports.php" class="nav-link active">Progress Reports</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main">
        <div class="container">
            <div class="page-header">
                <h1>📈 Progress Analytics</h1>
                <p class="page-subtitle">Visualize your AIP journey and improvements over time</p>
                
                <!-- Time Period Filter -->
                <div class="period-filters">
                    <a href="?period=7" class="period-filter <?= $period === '7' ? 'active' : '' ?>">7 Days</a>
                    <a href="?period=30" class="period-filter <?= $period === '30' ? 'active' : '' ?>">30 Days</a>
                    <a href="?period=90" class="period-filter <?= $period === '90' ? 'active' : '' ?>">90 Days</a>
                    <a href="?period=all" class="period-filter <?= $period === 'all' ? 'active' : '' ?>">All Time</a>
                </div>
            </div>

            <!-- Key Metrics Overview -->
            <div class="metrics-overview">
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon">📅</div>
                        <div class="metric-content">
                            <div class="metric-value"><?= $daysActive ?></div>
                            <div class="metric-label">Days on AIP</div>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon">🍽️</div>
                        <div class="metric-content">
                            <div class="metric-value"><?= $complianceRate ?>%</div>
                            <div class="metric-label">Food Logging</div>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon">💧</div>
                        <div class="metric-content">
                            <div class="metric-value"><?= $waterComplianceRate ?>%</div>
                            <div class="metric-label">Water Goals</div>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon">📊</div>
                        <div class="metric-content">
                            <div class="metric-value"><?= $avgSymptomSeverity ?></div>
                            <div class="metric-label">Avg Symptoms</div>
                        </div>
                    </div>

                    <div class="metric-card">
                        <div class="metric-icon">🧪</div>
                        <div class="metric-content">
                            <div class="metric-value"><?= count($reintroductionTests) ?></div>
                            <div class="metric-label">Foods Tested</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <!-- Symptom Trends Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h2>Symptom Severity Trends</h2>
                        <p>Track how your symptoms change over time</p>
                    </div>
                    <div class="chart-container">
                        <canvas id="symptomChart" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- Food & Water Compliance -->
                <div class="chart-row">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>Food Logging Consistency</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="foodChart" width="400" height="200"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>Water Intake Trends</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="waterChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Symptom Categories Breakdown -->
                <?php if (!empty($symptomsByCategory)): ?>
                <div class="chart-card">
                    <div class="chart-header">
                        <h2>Symptoms by Category</h2>
                        <p>Compare severity across different symptom types</p>
                    </div>
                    <div class="chart-container">
                        <canvas id="categoryChart" width="400" height="200"></canvas>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Detailed Analysis -->
            <div class="analysis-section">
                <div class="analysis-grid">
                    <!-- Improvement Indicators -->
                    <div class="analysis-card">
                        <div class="card-header">
                            <h3>📈 Improvement Indicators</h3>
                        </div>
                        
                        <?php
                        $improvements = [];
                        
                        // Check symptom trends
                        if (count($symptomTrends) >= 7) {
                            $recentAvg = 0;
                            $earlierAvg = 0;
                            $recentCount = 0;
                            $earlierCount = 0;
                            
                            foreach ($symptomTrends as $i => $trend) {
                                if ($i < count($symptomTrends) / 2) {
                                    $earlierAvg += $trend['avg_severity'];
                                    $earlierCount++;
                                } else {
                                    $recentAvg += $trend['avg_severity'];
                                    $recentCount++;
                                }
                            }
                            
                            if ($earlierCount > 0 && $recentCount > 0) {
                                $earlierAvg /= $earlierCount;
                                $recentAvg /= $recentCount;
                                
                                if ($recentAvg < $earlierAvg) {
                                    $improvement = round((($earlierAvg - $recentAvg) / $earlierAvg) * 100);
                                    $improvements[] = "Symptoms improved by {$improvement}%";
                                }
                            }
                        }
                        
                        // Check food consistency improvement
                        if ($complianceRate >= 80) {
                            $improvements[] = "Excellent food logging consistency";
                        }
                        
                        // Check water compliance
                        if ($waterComplianceRate >= 70) {
                            $improvements[] = "Good hydration habits established";
                        }
                        
                        // Check reintroduction progress
                        $toleratedFoods = count(array_filter($reintroductionTests, fn($test) => $test['final_result'] === 'tolerated'));
                        if ($toleratedFoods > 0) {
                            $improvements[] = "Successfully reintroduced {$toleratedFoods} food" . ($toleratedFoods > 1 ? 's' : '');
                        }
                        ?>
                        
                        <?php if (!empty($improvements)): ?>
                            <div class="improvement-list">
                                <?php foreach ($improvements as $improvement): ?>
                                    <div class="improvement-item">
                                        <span class="improvement-icon">✅</span>
                                        <span class="improvement-text"><?= $improvement ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-secondary">Keep logging consistently to see improvement trends!</p>
                        <?php endif; ?>
                    </div>

                    <!-- Reintroduction Summary -->
                    <?php if (!empty($reintroductionTests)): ?>
                    <div class="analysis-card">
                        <div class="card-header">
                            <h3>🧪 Reintroduction Results</h3>
                        </div>
                        
                        <div class="reintro-summary">
                            <?php
                            $tolerated = array_filter($reintroductionTests, fn($t) => $t['final_result'] === 'tolerated');
                            $reactions = array_filter($reintroductionTests, fn($t) => $t['final_result'] === 'not_tolerated');
                            $pending = array_filter($reintroductionTests, fn($t) => in_array($t['test_status'], ['planned', 'active']));
                            ?>
                            
                            <div class="reintro-stats">
                                <div class="reintro-stat tolerated">
                                    <span class="stat-value"><?= count($tolerated) ?></span>
                                    <span class="stat-label">Tolerated</span>
                                </div>
                                <div class="reintro-stat reactions">
                                    <span class="stat-value"><?= count($reactions) ?></span>
                                    <span class="stat-label">Reactions</span>
                                </div>
                                <div class="reintro-stat pending">
                                    <span class="stat-value"><?= count($pending) ?></span>
                                    <span class="stat-label">Pending</span>
                                </div>
                            </div>
                            
                            <?php if (!empty($tolerated)): ?>
                                <div class="safe-foods">
                                    <h4>✅ Your Safe Foods:</h4>
                                    <div class="food-tags">
                                        <?php foreach (array_slice($tolerated, 0, 8) as $test): ?>
                                            <span class="food-tag safe"><?= htmlspecialchars($test['food_name']) ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($tolerated) > 8): ?>
                                            <span class="food-tag more">+<?= count($tolerated) - 8 ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($reactions)): ?>
                                <div class="trigger-foods">
                                    <h4>❌ Trigger Foods to Avoid:</h4>
                                    <div class="food-tags">
                                        <?php foreach (array_slice($reactions, 0, 6) as $test): ?>
                                            <span class="food-tag trigger"><?= htmlspecialchars($test['food_name']) ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($reactions) > 6): ?>
                                            <span class="food-tag more">+<?= count($reactions) - 6 ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Export Options -->
            <div class="export-section">
                <div class="card">
                    <div class="card-header">
                        <h3>📊 Export Your Data</h3>
                        <p>Download your progress data to share with healthcare providers</p>
                    </div>
                    
                    <div class="export-options">
                        <a href="../export/summary-pdf.php?period=<?= $period ?>" class="btn btn-primary" target="_blank">
                            📄 Progress Summary (PDF)
                        </a>
                        <a href="../export/detailed-csv.php?period=<?= $period ?>" class="btn btn-outline">
                            📊 Detailed Data (CSV)
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="../dashboard.php" class="btn btn-outline">← Back to Dashboard</a>
                <a href="../symptoms/track.php" class="btn btn-secondary">Track Symptoms →</a>
            </div>
        </div>
    </main>

    <script>
        // Chart configuration
        Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        Chart.defaults.color = '#6C757D';
        Chart.defaults.plugins.legend.display = true;
        Chart.defaults.plugins.legend.position = 'bottom';

        const chartColors = {
            primary: '#2E8B57',
            secondary: '#4682B4',
            accent: '#FF6B35',
            success: '#28A745',
            warning: '#FFC107',
            error: '#DC3545',
            digestive: '#FF6B35',
            systemic: '#4682B4', 
            skin: '#28A745',
            mood: '#6F42C1',
            sleep: '#17A2B8',
            energy: '#FFC107'
        };

        // Symptom Trends Chart
        const symptomData = <?= json_encode($chartData['symptom_trends']) ?>;
        const symptomDates = Object.keys(symptomData).sort();
        const symptomCategories = ['digestive', 'systemic', 'skin', 'mood', 'sleep', 'energy'];
        
        const symptomDatasets = symptomCategories.map(category => ({
            label: category.charAt(0).toUpperCase() + category.slice(1),
            data: symptomDates.map(date => symptomData[date]?.[category] || null),
            borderColor: chartColors[category],
            backgroundColor: chartColors[category] + '20',
            fill: false,
            tension: 0.4,
            pointRadius: 4,
            pointHoverRadius: 6
        }));

        new Chart(document.getElementById('symptomChart'), {
            type: 'line',
            data: {
                labels: symptomDates.map(date => new Date(date).toLocaleDateString()),
                datasets: symptomDatasets.filter(ds => ds.data.some(val => val !== null))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 10,
                        title: {
                            display: true,
                            text: 'Severity (1-10)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Lower scores indicate improvement'
                    }
                }
            }
        });

        // Food Consistency Chart
        const foodData = <?= json_encode($chartData['food_consistency']) ?>;
        const foodDates = Object.keys(foodData).sort();

        new Chart(document.getElementById('foodChart'), {
            type: 'bar',
            data: {
                labels: foodDates.map(date => new Date(date).toLocaleDateString()),
                datasets: [{
                    label: 'Meals Logged',
                    data: foodDates.map(date => foodData[date]),
                    backgroundColor: chartColors.primary + '80',
                    borderColor: chartColors.primary,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 4,
                        title: {
                            display: true,
                            text: 'Meals per Day'
                        }
                    }
                }
            }
        });

        // Water Intake Chart
        const waterData = <?= json_encode($chartData['water_trends']) ?>;
        const waterDates = Object.keys(waterData).sort();
        const waterGoal = <?= $waterGoal ?>;

        new Chart(document.getElementById('waterChart'), {
            type: 'line',
            data: {
                labels: waterDates.map(date => new Date(date).toLocaleDateString()),
                datasets: [{
                    label: 'Water Intake (ml)',
                    data: waterDates.map(date => waterData[date]),
                    borderColor: chartColors.secondary,
                    backgroundColor: chartColors.secondary + '20',
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Goal Line',
                    data: waterDates.map(() => waterGoal),
                    borderColor: chartColors.success,
                    borderDash: [5, 5],
                    fill: false,
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Milliliters (ml)'
                        }
                    }
                }
            }
        });

        // Category Breakdown Chart
        <?php if (!empty($symptomsByCategory)): ?>
        const categoryData = <?= json_encode($symptomsByCategory) ?>;
        const categories = Object.keys(categoryData);
        
        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: categories.map(cat => cat.charAt(0).toUpperCase() + cat.slice(1)),
                datasets: [{
                    data: categories.map(cat => categoryData[cat].avg_severity),
                    backgroundColor: categories.map(cat => chartColors[cat] || chartColors.primary),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Average severity by category'
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>