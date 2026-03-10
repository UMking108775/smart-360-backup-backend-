<?php
/**
 * Authentication Handler
 * Smart 360 Backup WP — License Backend
 */

if (!defined('S360_BACKEND')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

class S360_Auth {

    private $db;

    public function __construct() {
        $this->db = S360_Database::getInstance();
    }

    /**
     * Start secure session
     */
    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    /**
     * Attempt login with username and password
     */
    public function login(string $username, string $password): bool {
        $admin = $this->db->fetchOne(
            "SELECT * FROM " . DB_PREFIX . "admins WHERE username = ?",
            [$username]
        );

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            s360_log_activity('login_failed', "Username: {$username}");
            return false;
        }

        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);

        $_SESSION['admin_id']       = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_email']    = $admin['email'];
        $_SESSION['login_time']     = time();
        $_SESSION['last_activity']  = time();

        // Update last login
        $this->db->update(
            DB_PREFIX . 'admins',
            ['last_login' => date('Y-m-d H:i:s')],
            'id = ?',
            [$admin['id']]
        );

        s360_log_activity('login_success', "Admin: {$username}");
        return true;
    }

    /**
     * Check if user is authenticated
     */
    public static function isLoggedIn(): bool {
        if (empty($_SESSION['admin_id'])) {
            return false;
        }

        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
            self::logout();
            return false;
        }

        // Update last activity
        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Require authentication — redirect to login if not authenticated
     */
    public static function requireLogin(): void {
        self::startSession();
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    /**
     * Logout and destroy session
     */
    public static function logout(): void {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Get current admin info
     */
    public static function getCurrentAdmin(): ?array {
        if (!self::isLoggedIn()) return null;
        return [
            'id'       => $_SESSION['admin_id'],
            'username' => $_SESSION['admin_username'],
            'email'    => $_SESSION['admin_email'],
        ];
    }

    /**
     * Change admin password
     */
    public function changePassword(int $adminId, string $currentPassword, string $newPassword): bool {
        $admin = $this->db->fetchOne(
            "SELECT * FROM " . DB_PREFIX . "admins WHERE id = ?",
            [$adminId]
        );

        if (!$admin || !password_verify($currentPassword, $admin['password_hash'])) {
            return false;
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $this->db->update(
            DB_PREFIX . 'admins',
            ['password_hash' => $hash],
            'id = ?',
            [$adminId]
        );

        s360_log_activity('password_changed', "Admin ID: {$adminId}");
        return true;
    }

    /**
     * Create a new admin (used by install script)
     */
    public function createAdmin(string $username, string $email, string $password): string {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        return $this->db->insert(DB_PREFIX . 'admins', [
            'username'      => $username,
            'email'         => $email,
            'password_hash' => $hash,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }
}
