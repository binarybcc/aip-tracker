<?php
/**
 * AIP Tracker - PDF Export for Healthcare Providers
 * Generate comprehensive progress summary in PDF format
 */

require_once '../config/config.php';

Helpers::requireLogin();

// Simple HTML to PDF conversion for shared hosting compatibility
$db = (new Database())->connect();
$userId = Helpers::getCurrentUserId();

// Get user profile
$stmt = $db->prepare("
    SELECT up.*, u.first_name, u.last_name, u.email
    FROM user_profiles up
    JOIN users u ON up.user_id = u.id 
    WHERE up.user_id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    Helpers::redirect('/setup/interview.php', 'Please complete your setup first.', 'info');
}

$period = $_GET['period'] ?? '30';
$dateFilter = $period === 'all' ? '' : "AND log_date >= DATE_SUB(CURDATE(), INTERVAL $period DAY)";

// Get summary data
$stmt = $db->prepare("
    SELECT 
        symptom_type,
        AVG(severity) as avg_severity,
        COUNT(*) as log_count,
        MIN(log_date) as first_log,
        MAX(log_date) as last_log
    FROM symptom_logs 
    WHERE user_id = ? $dateFilter
    GROUP BY symptom_type
    ORDER BY symptom_type
");
$stmt->execute([$userId]);
$symptomSummary = $stmt->fetchAll();

// Get reintroduction results
$stmt = $db->prepare("
    SELECT rt.*, fd.name as food_name, fd.category
    FROM reintroduction_tests rt
    JOIN food_database fd ON rt.food_id = fd.id
    WHERE rt.user_id = ? AND rt.test_status = 'completed'
    ORDER BY rt.test_start_date DESC
");
$stmt->execute([$userId]);
$reintroTests = $stmt->fetchAll();

// Calculate compliance rates
$stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT log_date) as total_days,
        SUM(CASE WHEN meal_count >= 3 THEN 1 ELSE 0 END) as compliant_days
    FROM (
        SELECT log_date, COUNT(DISTINCT meal_type) as meal_count
        FROM food_logs 
        WHERE user_id = ? $dateFilter
        GROUP BY log_date
    ) daily_meals
");
$stmt->execute([$userId]);
$compliance = $stmt->fetch();

$complianceRate = $compliance['total_days'] > 0 ? 
    round(($compliance['compliant_days'] / $compliance['total_days']) * 100) : 0;

// Set headers for PDF download
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: inline; filename="AIP_Progress_Summary_' . date('Y-m-d') . '.html"');

$startDate = $user['start_date'];
$daysActive = Helpers::daysSinceStart($startDate);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>AIP Progress Summary - <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2E8B57;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2E8B57;
            margin-bottom: 10px;
        }
        .patient-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .metric-card {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #2E8B57;
        }
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #2E8B57;
            display: block;
            margin-bottom: 5px;
        }
        .metric-label {
            font-size: 12px;
            color: #666;
            font-weight: 500;
        }
        .section {
            margin-bottom: 30px;
        }
        .section h2 {
            color: #2E8B57;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .symptom-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .symptom-table th,
        .symptom-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .symptom-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .severity-low { color: #28a745; }
        .severity-medium { color: #ffc107; }
        .severity-high { color: #dc3545; }
        .reintro-results {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .tolerated-list, .reaction-list {
            padding: 15px;
            border-radius: 8px;
        }
        .tolerated-list {
            background: #d4edda;
            border: 1px solid #c3e6cb;
        }
        .reaction-list {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        .food-item {
            padding: 5px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        .food-item:last-child {
            border-bottom: none;
        }
        .disclaimer {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 8px;
            font-size: 14px;
            margin-top: 30px;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #666;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        @media print {
            body { margin: 0; padding: 15px; }
            .header { page-break-after: avoid; }
            .section { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🌿 AIP Progress Summary</h1>
        <h2><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
        <p>Generated on <?= date('F j, Y') ?> | Report Period: <?= $period === 'all' ? 'All Time' : "$period days" ?></p>
    </div>

    <div class="patient-info">
        <strong>Patient Information:</strong><br>
        Name: <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?><br>
        Email: <?= htmlspecialchars($user['email']) ?><br>
        AIP Start Date: <?= Helpers::formatDate($user['start_date']) ?><br>
        Current Phase: <?= ucfirst($user['current_phase']) ?><br>
        Days on Protocol: <?= $daysActive ?> days
    </div>

    <div class="metrics-grid">
        <div class="metric-card">
            <span class="metric-value"><?= $daysActive ?></span>
            <span class="metric-label">Days on AIP</span>
        </div>
        <div class="metric-card">
            <span class="metric-value"><?= $complianceRate ?>%</span>
            <span class="metric-label">Food Logging</span>
        </div>
        <div class="metric-card">
            <span class="metric-value"><?= count($symptomSummary) ?></span>
            <span class="metric-label">Symptom Categories</span>
        </div>
        <div class="metric-card">
            <span class="metric-value"><?= count($reintroTests) ?></span>
            <span class="metric-label">Foods Tested</span>
        </div>
    </div>

    <?php if (!empty($symptomSummary)): ?>
    <div class="section">
        <h2>📊 Symptom Summary</h2>
        <table class="symptom-table">
            <thead>
                <tr>
                    <th>Symptom Category</th>
                    <th>Average Severity</th>
                    <th>Total Logs</th>
                    <th>Tracking Period</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($symptomSummary as $symptom): ?>
                <tr>
                    <td><?= ucfirst(str_replace('_', ' ', $symptom['symptom_type'])) ?></td>
                    <td class="<?= $symptom['avg_severity'] <= 3 ? 'severity-low' : ($symptom['avg_severity'] <= 6 ? 'severity-medium' : 'severity-high') ?>">
                        <?= round($symptom['avg_severity'], 1) ?>/10
                    </td>
                    <td><?= $symptom['log_count'] ?></td>
                    <td><?= Helpers::formatDate($symptom['first_log']) ?> - <?= Helpers::formatDate($symptom['last_log']) ?></td>
                    <td>
                        <?php if ($symptom['avg_severity'] <= 3): ?>
                            <span class="severity-low">✓ Well Managed</span>
                        <?php elseif ($symptom['avg_severity'] <= 6): ?>
                            <span class="severity-medium">⚠ Moderate</span>
                        <?php else: ?>
                            <span class="severity-high">⚡ Needs Attention</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($reintroTests)): ?>
    <div class="section">
        <h2>🧪 Food Reintroduction Results</h2>
        <div class="reintro-results">
            <div class="tolerated-list">
                <h3>✅ Successfully Tolerated Foods</h3>
                <?php 
                $tolerated = array_filter($reintroTests, fn($test) => $test['final_result'] === 'tolerated');
                if (!empty($tolerated)): ?>
                    <?php foreach ($tolerated as $test): ?>
                        <div class="food-item">
                            <strong><?= htmlspecialchars($test['food_name']) ?></strong><br>
                            <small>Category: <?= ucfirst($test['category']) ?> | Tested: <?= Helpers::formatDate($test['test_start_date']) ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p><em>No foods successfully reintroduced yet.</em></p>
                <?php endif; ?>
            </div>

            <div class="reaction-list">
                <h3>❌ Foods Causing Reactions</h3>
                <?php 
                $reactions = array_filter($reintroTests, fn($test) => $test['final_result'] === 'not_tolerated');
                if (!empty($reactions)): ?>
                    <?php foreach ($reactions as $test): ?>
                        <div class="food-item">
                            <strong><?= htmlspecialchars($test['food_name']) ?></strong><br>
                            <small>Category: <?= ucfirst($test['category']) ?> | Tested: <?= Helpers::formatDate($test['test_start_date']) ?></small>
                            <?php if ($test['notes']): ?>
                                <br><small>Notes: <?= htmlspecialchars($test['notes']) ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p><em>No problematic foods identified yet.</em></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="section">
        <h2>📈 Progress Notes</h2>
        <p><strong>Overall Assessment:</strong></p>
        <ul>
            <li>Patient has been following AIP protocol for <?= $daysActive ?> days</li>
            <li>Food logging compliance rate: <?= $complianceRate ?>%</li>
            <li>Currently in <?= ucfirst($user['current_phase']) ?> phase</li>
            <?php if (!empty($tolerated)): ?>
                <li>Successfully reintroduced <?= count($tolerated) ?> food(s)</li>
            <?php endif; ?>
            <?php if (!empty($reactions)): ?>
                <li>Identified <?= count($reactions) ?> trigger food(s) to avoid</li>
            <?php endif; ?>
        </ul>

        <?php if (!empty($symptomSummary)): ?>
        <p><strong>Symptom Management:</strong></p>
        <ul>
            <?php
            $lowSeverity = array_filter($symptomSummary, fn($s) => $s['avg_severity'] <= 3);
            $highSeverity = array_filter($symptomSummary, fn($s) => $s['avg_severity'] > 6);
            ?>
            <?php if (!empty($lowSeverity)): ?>
                <li>Well-managed symptom categories: <?= implode(', ', array_map(fn($s) => ucfirst($s['symptom_type']), $lowSeverity)) ?></li>
            <?php endif; ?>
            <?php if (!empty($highSeverity)): ?>
                <li>Areas needing attention: <?= implode(', ', array_map(fn($s) => ucfirst($s['symptom_type']), $highSeverity)) ?></li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>
    </div>

    <div class="disclaimer">
        <strong>⚠️ Medical Disclaimer:</strong> This report is generated from patient self-reported data using the AIP Tracker application. The information contained herein is for educational and tracking purposes only and should not be considered as medical advice, diagnosis, or treatment recommendations. Please consult with qualified healthcare professionals for medical decisions and treatment plans.
    </div>

    <div class="footer">
        Generated by AIP Tracker | <?= date('Y-m-d H:i T') ?><br>
        Report covers period: <?= $period === 'all' ? 'All time since ' . Helpers::formatDate($user['start_date']) : "Last $period days" ?>
    </div>

    <script>
        // Auto-print dialog for PDF generation
        window.onload = function() {
            if (window.location.search.includes('print=1')) {
                setTimeout(function() {
                    window.print();
                }, 500);
            }
        };
    </script>
</body>
</html>