<?php
/**
 * API: Activate License
 * POST /api/activate.php
 * Body: { "license_key": "...", "site_url": "..." }
 */

define('S360_BACKEND', true);
require_once dirname(__DIR__) . '/config.php';
require_once INCLUDES_PATH . '/db.php';
require_once INCLUDES_PATH . '/helpers.php';
require_once INCLUDES_PATH . '/license-manager.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Signature');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    s360_json_error('Method not allowed.', 405);
}

// Rate limiting
$ip = s360_get_client_ip();
if (!s360_check_rate_limit('api_activate_' . $ip)) {
    s360_json_error('Too many requests. Please try again later.', 429);
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

$licenseKey = trim($input['license_key'] ?? '');
$siteUrl    = trim($input['site_url'] ?? '');

if (empty($licenseKey) || empty($siteUrl)) {
    s360_json_error('Missing required fields: license_key, site_url.');
}

if (!s360_is_valid_url($siteUrl) && !s360_is_valid_url('https://' . $siteUrl)) {
    s360_json_error('Invalid site URL.');
}

// Process activation
$manager = new S360_License_Manager();
$result = $manager->activateLicense($licenseKey, $siteUrl);

if ($result['success']) {
    s360_json_success($result, $result['message']);
} else {
    s360_json_error($result['error']);
}
