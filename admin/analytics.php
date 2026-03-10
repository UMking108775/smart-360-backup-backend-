<?php
/**
 * Admin — Analytics Page
 * Smart 360 Backup WP — License Backend
 */

define('S360_BACKEND', true);
require_once dirname(__DIR__) . '/config.php';
require_once INCLUDES_PATH . '/db.php';
require_once INCLUDES_PATH . '/helpers.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/license-manager.php';

S360_Auth::requireLogin();

$manager = new S360_License_Manager();
$stats = $manager->getStats();

// Get additional analytics data
$db = S360_Database::getInstance();
$prefix = DB_PREFIX;

// Top domains by activations
$topDomains = $db->fetchAll(
    "SELECT site_url, COUNT(*) as cnt 
     FROM {$prefix}activations 
     WHERE deactivated_at IS NULL 
     GROUP BY site_url 
     ORDER BY cnt DESC 
     LIMIT 10"
);

// Weekly activations trend (last 8 weeks)
$weeklyActivations = $db->fetchAll(
    "SELECT YEARWEEK(activated_at, 1) as week, 
            MIN(DATE(activated_at)) as week_start,
            COUNT(*) as cnt 
     FROM {$prefix}activations 
     WHERE activated_at >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
     AND deactivated_at IS NULL
     GROUP BY week 
     ORDER BY week ASC"
);

// Plan distribution
$planDist = $db->fetchAll(
    "SELECT plan, COUNT(*) as cnt FROM {$prefix}licenses GROUP BY plan"
);

$pageTitle = 'Analytics';
$extraHead = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Stats Row -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">🔑</div>
        <div class="stat-value"><?= number_format($stats['active_licenses']) ?></div>
        <div class="stat-label">Active Licenses</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">🌐</div>
        <div class="stat-value"><?= number_format($stats['total_activations']) ?></div>
        <div class="stat-label">Active Sites</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">💰</div>
        <div class="stat-value">$<?= number_format($stats['total_revenue'], 2) ?></div>
        <div class="stat-label">Total Revenue</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon cyan">📊</div>
        <div class="stat-value"><?= $stats['active_licenses'] > 0 ? '$' . number_format($stats['total_revenue'] / max(1, $stats['active_licenses']), 2) : '$0' ?></div>
        <div class="stat-label">Avg. Revenue / License</div>
    </div>
</div>

<!-- Charts -->
<div class="charts-grid">
    <!-- Monthly Licenses -->
    <div class="card">
        <div class="card-header">
            <h3>📈 Monthly License Growth</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="monthlyLicenses"></canvas>
            </div>
        </div>
    </div>

    <!-- Plan Distribution -->
    <div class="card">
        <div class="card-header">
            <h3>🎯 Plan Distribution</h3>
        </div>
        <div class="card-body">
            <div class="chart-container" style="height: 260px; display: flex; align-items: center; justify-content: center;">
                <canvas id="planChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="charts-grid">
    <!-- Weekly Activations -->
    <div class="card">
        <div class="card-header">
            <h3>📅 Weekly Activations (Last 8 Weeks)</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Domains -->
    <div class="card">
        <div class="card-header">
            <h3>🌐 Top Active Domains</h3>
        </div>
        <div class="card-body">
            <?php if (empty($topDomains)): ?>
                <div class="empty-state" style="padding:20px;"><p>No activations yet</p></div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Domain</th>
                            <th>Activations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topDomains as $i => $d): ?>
                            <tr>
                                <td style="color: var(--text-muted);"><?= $i + 1 ?></td>
                                <td style="font-weight: 500;"><?= s360_sanitize($d['site_url']) ?></td>
                                <td><span class="badge badge-active"><?= $d['cnt'] ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script>
const chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { grid: { color: "rgba(255,255,255,0.04)" }, ticks: { color: "#64748b", font: { size: 11 } } },
        y: { grid: { color: "rgba(255,255,255,0.04)" }, ticks: { color: "#64748b", font: { size: 11 } }, beginAtZero: true }
    }
};

// Monthly Licenses
new Chart(document.getElementById("monthlyLicenses"), {
    type: "bar",
    data: {
        labels: ' . json_encode(array_column($stats['monthly_licenses'], 'month')) . ',
        datasets: [{
            data: ' . json_encode(array_column($stats['monthly_licenses'], 'count')) . ',
            backgroundColor: "rgba(59,130,246,0.5)",
            borderColor: "#3b82f6",
            borderWidth: 1,
            borderRadius: 6
        }]
    },
    options: chartDefaults
});

// Plan Distribution
new Chart(document.getElementById("planChart"), {
    type: "doughnut",
    data: {
        labels: ' . json_encode(array_map(fn($p) => ucfirst($p['plan']), $planDist)) . ',
        datasets: [{
            data: ' . json_encode(array_column($planDist, 'cnt')) . ',
            backgroundColor: ["#3b82f6", "#64748b"],
            borderWidth: 0,
            hoverOffset: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: "bottom", labels: { color: "#94a3b8", padding: 20, font: { size: 13 } } }
        }
    }
});

// Weekly Activations
new Chart(document.getElementById("weeklyChart"), {
    type: "line",
    data: {
        labels: ' . json_encode(array_column($weeklyActivations, 'week_start')) . ',
        datasets: [{
            data: ' . json_encode(array_column($weeklyActivations, 'cnt')) . ',
            borderColor: "#10b981",
            backgroundColor: "rgba(16,185,129,0.1)",
            fill: true,
            tension: 0.4,
            pointRadius: 5,
            pointBackgroundColor: "#10b981"
        }]
    },
    options: chartDefaults
});
</script>';

require_once __DIR__ . '/includes/footer.php';
?>
