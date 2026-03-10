<?php
/**
 * Main Entry / Router
 * Smart 360 Backup WP — License Backend
 */

define('S360_BACKEND', true);
require_once __DIR__ . '/config.php';
require_once INCLUDES_PATH . '/db.php';
require_once INCLUDES_PATH . '/helpers.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/license-manager.php';

// Default: redirect to admin panel
header('Location: admin/login.php');
exit;
