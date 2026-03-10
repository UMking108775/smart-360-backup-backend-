<?php
/**
 * Admin Login Page
 * Smart 360 Backup WP — License Backend
 */

define('S360_BACKEND', true);
require_once dirname(__DIR__) . '/config.php';
require_once INCLUDES_PATH . '/db.php';
require_once INCLUDES_PATH . '/helpers.php';
require_once INCLUDES_PATH . '/auth.php';

S360_Auth::startSession();

// Handle logout
if (isset($_GET['logout'])) {
    S360_Auth::logout();
    header('Location: login.php');
    exit;
}

// Redirect if already logged in
if (S360_Auth::isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Handle login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $auth = new S360_Auth();
        if ($auth->login($username, $password)) {
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-logo">
                <div class="icon">🛡️</div>
                <h1><?= APP_NAME ?></h1>
                <p>License Management Portal</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error" data-auto-fade>
                    ⚠️ <?= s360_sanitize($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           placeholder="Enter your username" 
                           value="<?= s360_sanitize($_POST['username'] ?? '') ?>" 
                           required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block">
                    Sign In →
                </button>
            </form>

            <p class="text-center mt-2" style="font-size: 12px; color: var(--text-muted);">
                <?= ORG_NAME ?> &middot; <a href="<?= ORG_URL ?>" target="_blank"><?= str_replace(['https://', 'http://'], '', ORG_URL) ?></a>
            </p>
        </div>
    </div>

    <script src="../assets/js/admin.js"></script>
</body>
</html>
