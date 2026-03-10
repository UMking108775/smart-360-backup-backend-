<?php
/**
 * Google Drive OAuth Callback
 * Smart 360 Backup — License Backend
 *
 * Google redirects here after the user authorizes.
 * This page redirects back to the user's WordPress site with the auth code.
 */

// Prevent direct browsing
if (empty($_GET['code']) && empty($_GET['error'])) {
    http_response_code(400);
    die('Invalid request.');
}

// Error from Google
if (!empty($_GET['error'])) {
    $state = json_decode(base64_decode($_GET['state'] ?? ''), true);
    $return = $state['return_url'] ?? 'https://ssatechs.com';
    header('Location: ' . $return . '&s360_gdrive_error=' . urlencode($_GET['error']));
    exit;
}

// Success — get the state to know where to redirect back
$state = json_decode(base64_decode($_GET['state'] ?? ''), true);

if (empty($state['return_url'])) {
    http_response_code(400);
    die('Missing return URL in state parameter.');
}

$code       = $_GET['code'];
$return_url = $state['return_url'];

// Redirect back to the user's WordPress site with the authorization code
// The plugin will exchange this code for tokens
$redirect = $return_url . '&code=' . urlencode($code);

header('Location: ' . $redirect);
exit;
