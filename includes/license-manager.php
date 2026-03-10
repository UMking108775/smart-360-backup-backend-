<?php
/**
 * License Manager
 * Smart 360 Backup WP — License Backend
 */

if (!defined('S360_BACKEND')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

class S360_License_Manager {

    private $db;

    public function __construct() {
        $this->db = S360_Database::getInstance();
    }

    // ─── License CRUD ──────────────────────────────────────────────────

    /**
     * Create a new license
     */
    public function createLicense(array $data): array {
        $licenseKey = s360_generate_license_key();

        $id = $this->db->insert(DB_PREFIX . 'licenses', [
            'license_key'    => $licenseKey,
            'customer_name'  => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'plan'           => $data['plan'] ?? 'pro',
            'status'         => 'active',
            'max_sites'      => $data['max_sites'] ?? PRO_MAX_SITES,
            'purchase_amount'=> $data['purchase_amount'] ?? 0,
            'notes'          => $data['notes'] ?? '',
            'created_at'     => date('Y-m-d H:i:s'),
            'expires_at'     => null, // Lifetime license
        ]);

        s360_log_activity('license_created', "Key: {$licenseKey}, Customer: {$data['customer_email']}");

        return $this->getLicenseById($id);
    }

    /**
     * Get license by ID
     */
    public function getLicenseById(int $id): ?array {
        $license = $this->db->fetchOne(
            "SELECT * FROM " . DB_PREFIX . "licenses WHERE id = ?",
            [$id]
        );
        if ($license) {
            $license['activations'] = $this->getActivations($license['id']);
            $license['activation_count'] = count($license['activations']);
        }
        return $license;
    }

    /**
     * Get license by key
     */
    public function getLicenseByKey(string $key): ?array {
        $license = $this->db->fetchOne(
            "SELECT * FROM " . DB_PREFIX . "licenses WHERE license_key = ?",
            [$key]
        );
        if ($license) {
            $license['activations'] = $this->getActivations($license['id']);
            $license['activation_count'] = count($license['activations']);
        }
        return $license;
    }

    /**
     * Get all licenses with optional filters
     */
    public function getLicenses(array $filters = [], int $page = 1, int $perPage = 20): array {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'l.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['plan'])) {
            $where[] = 'l.plan = ?';
            $params[] = $filters['plan'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(l.license_key LIKE ? OR l.customer_name LIKE ? OR l.customer_email LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $whereStr = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM " . DB_PREFIX . "licenses l WHERE {$whereStr}",
            $params
        )['cnt'];

        $licenses = $this->db->fetchAll(
            "SELECT l.*, 
                    (SELECT COUNT(*) FROM " . DB_PREFIX . "activations a WHERE a.license_id = l.id AND a.deactivated_at IS NULL) as active_sites
             FROM " . DB_PREFIX . "licenses l 
             WHERE {$whereStr} 
             ORDER BY l.created_at DESC 
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'licenses'  => $licenses,
            'total'     => (int) $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'pages'     => ceil($total / $perPage),
        ];
    }

    /**
     * Update license
     */
    public function updateLicense(int $id, array $data): bool {
        $allowed = ['customer_name', 'customer_email', 'status', 'plan', 'max_sites', 'notes', 'purchase_amount', 'expires_at'];
        $updateData = array_intersect_key($data, array_flip($allowed));

        if (empty($updateData)) return false;

        $this->db->update(DB_PREFIX . 'licenses', $updateData, 'id = ?', [$id]);
        s360_log_activity('license_updated', "License ID: {$id}, Fields: " . implode(', ', array_keys($updateData)));
        return true;
    }

    /**
     * Revoke a license
     */
    public function revokeLicense(int $id): bool {
        $this->db->update(DB_PREFIX . 'licenses', ['status' => 'revoked'], 'id = ?', [$id]);

        // Deactivate all active activations
        $this->db->update(
            DB_PREFIX . 'activations',
            ['deactivated_at' => date('Y-m-d H:i:s')],
            'license_id = ? AND deactivated_at IS NULL',
            [$id]
        );

        s360_log_activity('license_revoked', "License ID: {$id}");
        return true;
    }

    /**
     * Delete license permanently
     */
    public function deleteLicense(int $id): bool {
        $this->db->delete(DB_PREFIX . 'activations', 'license_id = ?', [$id]);
        $this->db->delete(DB_PREFIX . 'licenses', 'id = ?', [$id]);
        s360_log_activity('license_deleted', "License ID: {$id}");
        return true;
    }

    // ─── Activations ───────────────────────────────────────────────────

    /**
     * Activate a license for a site
     */
    public function activateLicense(string $licenseKey, string $siteUrl): array {
        $license = $this->getLicenseByKey($licenseKey);

        if (!$license) {
            return ['success' => false, 'error' => 'Invalid license key.'];
        }

        if ($license['status'] !== 'active') {
            return ['success' => false, 'error' => 'License is ' . $license['status'] . '.'];
        }

        // Check expiry
        if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
            $this->db->update(DB_PREFIX . 'licenses', ['status' => 'expired'], 'id = ?', [$license['id']]);
            return ['success' => false, 'error' => 'License has expired.'];
        }

        $normalizedUrl = s360_normalize_url($siteUrl);

        // Check if already activated on this site
        $existing = $this->db->fetchOne(
            "SELECT * FROM " . DB_PREFIX . "activations WHERE license_id = ? AND site_url = ? AND deactivated_at IS NULL",
            [$license['id'], $normalizedUrl]
        );

        if ($existing) {
            return [
                'success' => true,
                'message' => 'License already activated on this site.',
                'license' => $this->formatLicenseResponse($license),
            ];
        }

        // Check max sites limit
        $activeCount = $this->db->count(
            DB_PREFIX . 'activations',
            'license_id = ? AND deactivated_at IS NULL',
            [$license['id']]
        );

        if ($activeCount >= $license['max_sites']) {
            return ['success' => false, 'error' => "Maximum site limit ({$license['max_sites']}) reached. Deactivate another site first."];
        }

        // Create activation
        $this->db->insert(DB_PREFIX . 'activations', [
            'license_id'   => $license['id'],
            'site_url'     => $normalizedUrl,
            'activated_at' => date('Y-m-d H:i:s'),
            'ip_address'   => s360_get_client_ip(),
        ]);

        s360_log_activity('license_activated', "Key: {$licenseKey}, Site: {$normalizedUrl}");

        // Refresh license data
        $license = $this->getLicenseByKey($licenseKey);

        return [
            'success' => true,
            'message' => 'License activated successfully.',
            'license' => $this->formatLicenseResponse($license),
        ];
    }

    /**
     * Deactivate a license from a site
     */
    public function deactivateLicense(string $licenseKey, string $siteUrl): array {
        $license = $this->getLicenseByKey($licenseKey);

        if (!$license) {
            return ['success' => false, 'error' => 'Invalid license key.'];
        }

        $normalizedUrl = s360_normalize_url($siteUrl);

        $result = $this->db->update(
            DB_PREFIX . 'activations',
            ['deactivated_at' => date('Y-m-d H:i:s')],
            'license_id = ? AND site_url = ? AND deactivated_at IS NULL',
            [$license['id'], $normalizedUrl]
        );

        if ($result === 0) {
            return ['success' => false, 'error' => 'No active activation found for this site.'];
        }

        s360_log_activity('license_deactivated', "Key: {$licenseKey}, Site: {$normalizedUrl}");

        return ['success' => true, 'message' => 'License deactivated successfully.'];
    }

    /**
     * Validate a license for a site
     */
    public function validateLicense(string $licenseKey, string $siteUrl): array {
        $license = $this->getLicenseByKey($licenseKey);

        if (!$license) {
            return ['valid' => false, 'error' => 'Invalid license key.'];
        }

        if ($license['status'] !== 'active') {
            return ['valid' => false, 'error' => 'License is ' . $license['status'] . '.'];
        }

        // Check expiry
        if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
            return ['valid' => false, 'error' => 'License has expired.'];
        }

        $normalizedUrl = s360_normalize_url($siteUrl);

        $activation = $this->db->fetchOne(
            "SELECT * FROM " . DB_PREFIX . "activations WHERE license_id = ? AND site_url = ? AND deactivated_at IS NULL",
            [$license['id'], $normalizedUrl]
        );

        if (!$activation) {
            return ['valid' => false, 'error' => 'License is not activated for this site.'];
        }

        return [
            'valid'   => true,
            'license' => $this->formatLicenseResponse($license),
        ];
    }

    /**
     * Get activations for a license
     */
    public function getActivations(int $licenseId): array {
        return $this->db->fetchAll(
            "SELECT * FROM " . DB_PREFIX . "activations WHERE license_id = ? AND deactivated_at IS NULL ORDER BY activated_at DESC",
            [$licenseId]
        );
    }

    // ─── Statistics ────────────────────────────────────────────────────

    /**
     * Get dashboard statistics
     */
    public function getStats(): array {
        $db = $this->db;
        $prefix = DB_PREFIX;

        return [
            'total_licenses'    => $db->count($prefix . 'licenses'),
            'active_licenses'   => $db->count($prefix . 'licenses', "status = 'active'"),
            'revoked_licenses'  => $db->count($prefix . 'licenses', "status = 'revoked'"),
            'expired_licenses'  => $db->count($prefix . 'licenses', "status = 'expired'"),
            'total_activations' => $db->count($prefix . 'activations', 'deactivated_at IS NULL'),
            'total_revenue'     => $db->fetchOne("SELECT COALESCE(SUM(purchase_amount), 0) as total FROM {$prefix}licenses")['total'],
            'today_licenses'    => $db->count($prefix . 'licenses', 'DATE(created_at) = CURDATE()'),
            'today_activations' => $db->count($prefix . 'activations', 'DATE(activated_at) = CURDATE() AND deactivated_at IS NULL'),
            'recent_activity'   => $db->fetchAll(
                "SELECT * FROM {$prefix}activity_log ORDER BY created_at DESC LIMIT 10"
            ),
            'monthly_licenses'  => $db->fetchAll(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
                 FROM {$prefix}licenses 
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                 GROUP BY month ORDER BY month ASC"
            ),
            'monthly_revenue'   => $db->fetchAll(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COALESCE(SUM(purchase_amount), 0) as total 
                 FROM {$prefix}licenses 
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                 GROUP BY month ORDER BY month ASC"
            ),
        ];
    }

    /**
     * Get customers list
     */
    public function getCustomers(string $search = '', int $page = 1, int $perPage = 20): array {
        $where = '1=1';
        $params = [];

        if ($search) {
            $where = '(customer_name LIKE ? OR customer_email LIKE ?)';
            $params = ['%' . $search . '%', '%' . $search . '%'];
        }

        $offset = ($page - 1) * $perPage;

        $total = $this->db->fetchOne(
            "SELECT COUNT(DISTINCT customer_email) as cnt FROM " . DB_PREFIX . "licenses WHERE {$where}",
            $params
        )['cnt'];

        $customers = $this->db->fetchAll(
            "SELECT customer_email, customer_name, 
                    COUNT(*) as total_licenses,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_licenses,
                    COALESCE(SUM(purchase_amount), 0) as total_spent,
                    MIN(created_at) as first_purchase,
                    MAX(created_at) as last_purchase
             FROM " . DB_PREFIX . "licenses 
             WHERE {$where}
             GROUP BY customer_email, customer_name
             ORDER BY last_purchase DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'customers' => $customers,
            'total'     => (int) $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'pages'     => ceil($total / $perPage),
        ];
    }

    // ─── Private Helpers ───────────────────────────────────────────────

    /**
     * Format license data for API response (strip sensitive fields)
     */
    private function formatLicenseResponse(array $license): array {
        return [
            'license_key'      => $license['license_key'],
            'plan'             => $license['plan'],
            'status'           => $license['status'],
            'max_sites'        => (int) $license['max_sites'],
            'activation_count' => (int) ($license['activation_count'] ?? 0),
            'expires_at'       => $license['expires_at'],
            'features'         => $this->getPlanFeatures($license['plan']),
        ];
    }

    /**
     * Get features available for a plan
     */
    private function getPlanFeatures(string $plan): array {
        $features = [
            'free' => [
                'full_site_backup'  => false,
                'cloud_storage'     => false,
                'max_backup_size'   => 524288000, // 500 MB
                'max_schedules'     => 1,
                'migration'         => false,
                'multisite'         => false,
                'priority_support'  => false,
                'auto_update'       => false,
            ],
            'pro' => [
                'full_site_backup'  => true,
                'cloud_storage'     => true,
                'max_backup_size'   => 0, // Unlimited
                'max_schedules'     => 0, // Unlimited
                'migration'         => true,
                'multisite'         => true,
                'priority_support'  => true,
                'auto_update'       => true,
            ],
        ];

        return $features[$plan] ?? $features['free'];
    }
}
