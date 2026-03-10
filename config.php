<?php
/**
 * Smart 360 Backup WP — License Backend Configuration
 * SSA Technologies (ssatechs.com)
 * 
 * IMPORTANT: Update these values before deploying to production.
 */

// Prevent direct access
if (!defined('S360_BACKEND')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

// ─── Database Configuration ────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 's360_license');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
define('DB_PREFIX', 's360_');

// ─── Application Settings ──────────────────────────────────────────────
define('APP_NAME', 'Smart 360 Backup WP');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'https://ssatechs.com/license');  // Update to your actual URL
define('ORG_NAME', 'SSA Technologies');
define('ORG_URL', 'https://ssatechs.com');

// ─── Security ──────────────────────────────────────────────────────────
define('API_SECRET_KEY', 'CHANGE_THIS_TO_A_RANDOM_64_CHAR_STRING');
define('SESSION_LIFETIME', 86400); // 24 hours
define('RATE_LIMIT_REQUESTS', 60);  // Max API requests per minute per IP
define('RATE_LIMIT_WINDOW', 60);    // Rate limit window in seconds
define('BCRYPT_COST', 12);

// ─── License Defaults ──────────────────────────────────────────────────
define('DEFAULT_MAX_SITES', 1);        // Free tier: 1 site per license
define('PRO_MAX_SITES', 100);          // Pro tier: 100 sites per license
define('LICENSE_KEY_LENGTH', 32);
define('LICENSE_CHECK_INTERVAL', 604800); // Plugin checks every 7 days

// ─── Paths ─────────────────────────────────────────────────────────────
define('BASE_PATH', __DIR__);
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('ADMIN_PATH', BASE_PATH . '/admin');
define('API_PATH', BASE_PATH . '/api');
define('ASSETS_PATH', BASE_PATH . '/assets');

// ─── Timezone ──────────────────────────────────────────────────────────
date_default_timezone_set('UTC');

// ─── Error Reporting (disable in production) ───────────────────────────
// Set to false in production
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
