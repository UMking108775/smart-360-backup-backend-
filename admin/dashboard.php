<?php
/**
 * Admin Dashboard
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

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">🔑</div>
        <div class="stat-value"><?= number_format($stats['total_licenses']) ?></div>
        <div class="stat-label">Total Licenses</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">✓</div>
        <div class="stat-value"><?= number_format($stats['active_licenses']) ?></div>
        <div class="stat-label">Active Licenses</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon cyan">🌐</div>
        <div class="stat-value"><?= number_format($stats['total_activations']) ?></div>
        <div class="stat-label">Active Sites</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">💰</div>
        <div class="stat-value">$<?= number_format($stats['total_revenue'], 2) ?></div>
        <div class="stat-label">Total Revenue</div>
    </div>
</div>

<!-- Charts Row -->
<div class="charts-grid">
    <div class="card">
        <div class="card-header">
            <h3>📈 Licenses Over Time</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="licensesChart"></canvas>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3>💵 Revenue Over Time</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Quick Stats + Recent Activity -->
<div class="charts-grid">
    <!-- Today's Stats -->
    <div class="card">
        <div class="card-header">
            <h3>📅 Today</h3>
        </div>
        <div class="card-body">
            <div class="grid-2">
                <div class="stat-card" style="border: none; padding: 16px;">
                    <div class="stat-value" style="font-size: 22px;"><?= $stats['today_licenses'] ?></div>
                    <div class="stat-label">New Licenses</div>
                </div>
                <div class="stat-card" style="border: none; padding: 16px;">
                    <div class="stat-value" style="font-size: 22px;"><?= $stats['today_activations'] ?></div>
                    <div class="stat-label">New Activations</div>
                </div>
            </div>
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-light);">
                <div class="d-flex align-center gap-1 mb-1">
                    <span class="badge badge-revoked"><?= number_format($stats['revoked_licenses']) ?></span>
                    <span class="text-muted" style="font-size: 13px;">Revoked</span>
                </div>
                <div class="d-flex align-center gap-1">
                    <span class="badge badge-expired"><?= number_format($stats['expired_licenses']) ?></span>
                    <span class="text-muted" style="font-size: 13px;">Expired</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header">
            <h3>🕐 Recent Activity</h3>
        </div>
        <div class="card-body">
            <?php if (empty($stats['recent_activity'])): ?>
                <div class="empty-state" style="padding: 20px;">
                    <p>No recent activity</p>
                </div>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach ($stats['recent_activity'] as $activity): ?>
                        <li class="activity-item">
                            <span class="activity-dot"></span>
                            <div>
                                <div class="activity-text">
                                    <strong><?= s360_sanitize($activity['action']) ?></strong>
                                    <?php if ($activity['details']): ?>
                                        — <?= s360_sanitize(s360_truncate($activity['details'], 80)) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-time"><?= s360_format_date($activity['created_at']) ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
const months = ' . json_encode(array_column($stats['monthly_licenses'], 'month')) . ';
const licenseCounts = ' . json_encode(array_column($stats['monthly_licenses'], 'count')) . ';
const revenueTotals = ' . json_encode(array_column($stats['monthly_revenue'], 'total')) . ';

const chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { grid: { color: "rgba(255,255,255,0.04)" }, ticks: { color: "#64748b", font: { size: 11 } } },
        y: { grid: { color: "rgba(255,255,255,0.04)" }, ticks: { color: "#64748b", font: { size: 11 } }, beginAtZero: true }
    }
};

new Chart(document.getElementById("licensesChart"), {
    type: "bar",
    data: {
        labels: months,
        datasets: [{
            data: licenseCounts,
            backgroundColor: "rgba(59,130,246,0.5)",
            borderColor: "#3b82f6",
            borderWidth: 1,
            borderRadius: 6,
        }]
    },
    options: chartDefaults
});

new Chart(document.getElementById("revenueChart"), {
    type: "line",
    data: {
        labels: months,
        datasets: [{
            data: revenueTotals,
            borderColor: "#10b981",
            backgroundColor: "rgba(16,185,129,0.1)",
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: "#10b981",
        }]
    },
    options: chartDefaults
});
</script>';

require_once __DIR__ . '/includes/footer.php';
?>
