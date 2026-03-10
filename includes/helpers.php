<?php
/**
 * Helper Functions
 * Smart 360 Backup WP — License Backend
 */

if (!defined('S360_BACKEND')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

/**
 * Generate a secure random license key
 */
function s360_generate_license_key(): string {
    $bytes = random_bytes(LICENSE_KEY_LENGTH / 2);
    $key = strtoupper(bin2hex($bytes));
    // Format: XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX
    return implode('-', str_split($key, 4));
}

/**
 * Sanitize string input
 */
function s360_sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 */
function s360_is_valid_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate URL
 */
function s360_is_valid_url(string $url): bool {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Normalize site URL (strip protocol, www, trailing slash)
 */
function s360_normalize_url(string $url): string {
    $url = strtolower(trim($url));
    $url = preg_replace('#^https?://#', '', $url);
    $url = preg_replace('#^www\.#', '', $url);
    $url = rtrim($url, '/');
    return $url;
}

/**
 * Send JSON response and exit
 */
function s360_json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send JSON error response
 */
function s360_json_error(string $message, int $status = 400): void {
    s360_json_response(['success' => false, 'error' => $message], $status);
}

/**
 * Send JSON success response
 */
function s360_json_success(array $data = [], string $message = 'Success'): void {
    s360_json_response(array_merge(['success' => true, 'message' => $message], $data));
}

/**
 * Get client IP address
 */
function s360_get_client_ip(): string {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = explode(',', $_SERVER[$header])[0];
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Simple rate limiter using file-based storage
 */
function s360_check_rate_limit(string $identifier): bool {
    $rate_dir = BASE_PATH . '/tmp/rate_limits';
    if (!is_dir($rate_dir)) {
        mkdir($rate_dir, 0755, true);
    }

    $file = $rate_dir . '/' . md5($identifier) . '.json';

    $data = ['requests' => [], 'blocked_until' => 0];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?: $data;
    }

    $now = time();

    // Check if currently blocked
    if ($data['blocked_until'] > $now) {
        return false;
    }

    // Remove expired entries
    $data['requests'] = array_filter($data['requests'], fn($t) => $t > ($now - RATE_LIMIT_WINDOW));

    // Check limit
    if (count($data['requests']) >= RATE_LIMIT_REQUESTS) {
        $data['blocked_until'] = $now + RATE_LIMIT_WINDOW;
        file_put_contents($file, json_encode($data), LOCK_EX);
        return false;
    }

    // Record this request
    $data['requests'][] = $now;
    file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}

/**
 * Log activity to database
 */
function s360_log_activity(string $action, string $details = '', ?string $ip = null): void {
    try {
        $db = S360_Database::getInstance();
        $db->insert(DB_PREFIX . 'activity_log', [
            'action'     => $action,
            'details'    => $details,
            'ip_address' => $ip ?? s360_get_client_ip(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Exception $e) {
        // Silently fail — logging should not break the app
    }
}

/**
 * Format date for display
 */
function s360_format_date(string $date, string $format = 'M d, Y h:i A'): string {
    return date($format, strtotime($date));
}

/**
 * Truncate string with ellipsis
 */
function s360_truncate(string $str, int $length = 50): string {
    if (strlen($str) <= $length) return $str;
    return substr($str, 0, $length) . '...';
}

/**
 * Generate CSRF token
 */
function s360_generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function s360_verify_csrf_token(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validate HMAC signature for API requests
 */
function s360_verify_api_signature(string $payload, string $signature): bool {
    $expected = hash_hmac('sha256', $payload, API_SECRET_KEY);
    return hash_equals($expected, $signature);
}

/**
 * Clean up old rate limit files (run periodically)
 */
function s360_cleanup_rate_limits(): void {
    $rate_dir = BASE_PATH . '/tmp/rate_limits';
    if (!is_dir($rate_dir)) return;

    $files = glob($rate_dir . '/*.json');
    $now = time();
    foreach ($files as $file) {
        if (($now - filemtime($file)) > 3600) {
            @unlink($file);
        }
    }
}
