<?php
/**
 * Admin Layout Header — Shared across all admin pages
 * Include at the top of each admin page after auth check
 */

if (!defined('S360_BACKEND')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

$currentAdmin = S360_Auth::getCurrentAdmin();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= s360_sanitize($pageTitle ?? 'Admin') ?> — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <?php if (!empty($extraHead)): ?>
        <?= $extraHead ?>
    <?php endif; ?>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar Overlay (mobile) -->
        <div class="sidebar-overlay"></div>

        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-icon">🛡️</div>
                <div class="brand-text">
                    <h2>Smart 360 Backup</h2>
                    <span>License Manager</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                        <span class="nav-icon">📊</span> Dashboard
                    </a>
                    <a href="licenses.php" class="nav-link <?= $currentPage === 'licenses' ? 'active' : '' ?>">
                        <span class="nav-icon">🔑</span> Licenses
                    </a>
                    <a href="customers.php" class="nav-link <?= $currentPage === 'customers' ? 'active' : '' ?>">
                        <span class="nav-icon">👥</span> Customers
                    </a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="analytics.php" class="nav-link <?= $currentPage === 'analytics' ? 'active' : '' ?>">
                        <span class="nav-icon">📈</span> Analytics
                    </a>
                    <a href="settings.php" class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
                        <span class="nav-icon">⚙️</span> Settings
                    </a>
                </div>
            </nav>

            <div class="sidebar-footer">
                <div class="admin-info">
                    <div class="admin-avatar"><?= strtoupper(substr($currentAdmin['username'] ?? 'A', 0, 1)) ?></div>
                    <div>
                        <div class="admin-name"><?= s360_sanitize($currentAdmin['username'] ?? 'Admin') ?></div>
                        <div class="admin-role">Administrator</div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="topbar">
                <div>
                    <button class="mobile-menu-toggle">☰</button>
                    <h1><?= s360_sanitize($pageTitle ?? 'Dashboard') ?></h1>
                    <div class="breadcrumb"><?= APP_NAME ?> / <?= s360_sanitize($pageTitle ?? 'Dashboard') ?></div>
                </div>
                <div class="topbar-actions">
                    <a href="login.php?logout=1" class="btn btn-outline btn-sm">↗ Logout</a>
                </div>
            </div>
