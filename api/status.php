<?php
/**
 * API: License Status
 * POST /api/status.php
 * Body: { "license_key": "..." }
 * 
 * Returns license details and activation count.
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
if (!s360_check_rate_limit('api_status_' . $ip)) {
    s360_json_error('Too many requests. Please try again later.', 429);
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

$licenseKey = trim($input['license_key'] ?? '');

if (empty($licenseKey)) {
    s360_json_error('Missing required field: license_key.');
}

// Get status
$manager = new S360_License_Manager();
$license = $manager->getLicenseByKey($licenseKey);

if (!$license) {
    s360_json_error('Invalid license key.', 404);
}

s360_json_success([
    'license' => [
        'license_key'      => $license['license_key'],
        'plan'             => $license['plan'],
        'status'           => $license['status'],
        'max_sites'        => (int) $license['max_sites'],
        'activation_count' => (int) $license['activation_count'],
        'activations'      => array_map(function($a) {
            return [
                'site_url'     => $a['site_url'],
                'activated_at' => $a['activated_at'],
            ];
        }, $license['activations']),
        'created_at'       => $license['created_at'],
        'expires_at'       => $license['expires_at'],
    ],
]);
