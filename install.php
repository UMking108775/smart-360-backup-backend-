<?php
/**
 * Database Installation & Setup Script
 * Smart 360 Backup WP — License Backend
 * 
 * Run this once to set up the database tables and create the default admin.
 * DELETE or protect this file after running it on production!
 */

define('S360_BACKEND', true);
require_once __DIR__ . '/config.php';

// Connect to MySQL without selecting database first
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:40px;color:#e74c3c;">Database connection failed: ' . $e->getMessage() . '</div>');
}

// Create database if not exists
$pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `" . DB_NAME . "`");

$prefix = DB_PREFIX;
$messages = [];

// ─── Create Tables ─────────────────────────────────────────────────────

// Admins table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `{$prefix}admins` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `email` VARCHAR(255) NOT NULL,
        `password_hash` VARCHAR(255) NOT NULL,
        `last_login` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$messages[] = '✅ Table `' . $prefix . 'admins` created.';

// Licenses table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `{$prefix}licenses` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `license_key` VARCHAR(64) NOT NULL UNIQUE,
        `customer_name` VARCHAR(255) NOT NULL,
        `customer_email` VARCHAR(255) NOT NULL,
        `plan` ENUM('free', 'pro') NOT NULL DEFAULT 'pro',
        `status` ENUM('active', 'expired', 'revoked') NOT NULL DEFAULT 'active',
        `max_sites` INT NOT NULL DEFAULT " . PRO_MAX_SITES . ",
        `purchase_amount` DECIMAL(10,2) DEFAULT 0.00,
        `notes` TEXT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `expires_at` DATETIME DEFAULT NULL,
        INDEX `idx_status` (`status`),
        INDEX `idx_email` (`customer_email`),
        INDEX `idx_plan` (`plan`),
        INDEX `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$messages[] = '✅ Table `' . $prefix . 'licenses` created.';

// Activations table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `{$prefix}activations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `license_id` INT NOT NULL,
        `site_url` VARCHAR(512) NOT NULL,
        `activated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `deactivated_at` DATETIME DEFAULT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        FOREIGN KEY (`license_id`) REFERENCES `{$prefix}licenses`(`id`) ON DELETE CASCADE,
        INDEX `idx_license` (`license_id`),
        INDEX `idx_site` (`site_url`(191)),
        INDEX `idx_active` (`deactivated_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$messages[] = '✅ Table `' . $prefix . 'activations` created.';

// Activity Log table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `{$prefix}activity_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `action` VARCHAR(100) NOT NULL,
        `details` TEXT DEFAULT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_action` (`action`),
        INDEX `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$messages[] = '✅ Table `' . $prefix . 'activity_log` created.';

// Settings table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `{$prefix}settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(100) NOT NULL UNIQUE,
        `setting_value` TEXT DEFAULT NULL,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$messages[] = '✅ Table `' . $prefix . 'settings` created.';

// ─── Create Default Admin ──────────────────────────────────────────────

$defaultAdmin = 'admin';
$defaultPass = 'Smart360@2026';
$defaultEmail = 'admin@ssatechs.com';

$existing = $pdo->query("SELECT COUNT(*) FROM `{$prefix}admins`")->fetchColumn();
if ($existing == 0) {
    $hash = password_hash($defaultPass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $stmt = $pdo->prepare("INSERT INTO `{$prefix}admins` (username, email, password_hash, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$defaultAdmin, $defaultEmail, $hash]);
    $messages[] = '✅ Default admin created — <strong>Username:</strong> ' . $defaultAdmin . ' | <strong>Password:</strong> ' . $defaultPass;
} else {
    $messages[] = 'ℹ️ Admin already exists, skipping.';
}

// ─── Insert Default Settings ───────────────────────────────────────────

$defaults = [
    'org_name'       => ORG_NAME,
    'org_url'        => ORG_URL,
    'app_name'       => APP_NAME,
    'smtp_host'      => '',
    'smtp_port'      => '587',
    'smtp_user'      => '',
    'smtp_pass'      => '',
    'smtp_from'      => $defaultEmail,
    'smtp_from_name' => APP_NAME,
];

$stmt = $pdo->prepare("INSERT IGNORE INTO `{$prefix}settings` (setting_key, setting_value) VALUES (?, ?)");
foreach ($defaults as $key => $value) {
    $stmt->execute([$key, $value]);
}
$messages[] = '✅ Default settings inserted.';

// ─── Create tmp directory ──────────────────────────────────────────────
$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/.htaccess', 'Deny from all');
    $messages[] = '✅ Temp directory created.';
}

// ─── Output ────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install — <?= APP_NAME ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .container { background: #1e293b; border-radius: 16px; padding: 40px; max-width: 600px; width: 100%; box-shadow: 0 25px 50px rgba(0,0,0,0.4); }
        h1 { font-size: 24px; margin-bottom: 8px; color: #38bdf8; }
        .subtitle { color: #94a3b8; margin-bottom: 24px; }
        .msg { padding: 12px 16px; border-radius: 8px; margin-bottom: 8px; background: #0f172a; font-size: 14px; line-height: 1.5; }
        .warning { background: #7c2d12; border-left: 4px solid #f97316; padding: 16px; border-radius: 8px; margin-top: 24px; }
        .warning strong { color: #fb923c; }
        a { color: #38bdf8; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚡ <?= APP_NAME ?> — Installation</h1>
        <p class="subtitle">Database setup complete</p>
        
        <?php foreach ($messages as $msg): ?>
            <div class="msg"><?= $msg ?></div>
        <?php endforeach; ?>
        
        <div class="warning">
            <strong>⚠️ Security Warning:</strong> Delete or rename this <code>install.php</code> file immediately after setup. 
            Leaving it accessible is a security risk.
        </div>
        
        <p style="margin-top: 24px; text-align: center;">
            <a href="admin/login.php">→ Go to Admin Panel Login</a>
        </p>
    </div>
</body>
</html>
