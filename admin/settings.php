<?php
/**
 * Admin — Settings Page
 * Smart 360 Backup WP — License Backend
 */

define('S360_BACKEND', true);
require_once dirname(__DIR__) . '/config.php';
require_once INCLUDES_PATH . '/db.php';
require_once INCLUDES_PATH . '/helpers.php';
require_once INCLUDES_PATH . '/auth.php';

S360_Auth::requireLogin();

$db = S360_Database::getInstance();
$prefix = DB_PREFIX;
$message = '';
$messageType = '';

// ─── Handle settings update ────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!s360_verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
        $messageType = 'error';
    } else {
        $action = $_POST['form_action'] ?? '';

        if ($action === 'general') {
            $settings = ['org_name', 'org_url', 'app_name'];
            foreach ($settings as $key) {
                $val = trim($_POST[$key] ?? '');
                $db->query(
                    "INSERT INTO {$prefix}settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?",
                    [$key, $val, $val]
                );
            }
            $message = 'General settings saved successfully.';
            $messageType = 'success';
            s360_log_activity('settings_updated', 'General settings updated');
        }

        if ($action === 'smtp') {
            $settings = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_from', 'smtp_from_name'];
            foreach ($settings as $key) {
                $val = trim($_POST[$key] ?? '');
                $db->query(
                    "INSERT INTO {$prefix}settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?",
                    [$key, $val, $val]
                );
            }
            $message = 'SMTP settings saved successfully.';
            $messageType = 'success';
            s360_log_activity('settings_updated', 'SMTP settings updated');
        }

        if ($action === 'password') {
            $auth = new S360_Auth();
            $admin = S360_Auth::getCurrentAdmin();
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (empty($current) || empty($new)) {
                $message = 'Please fill in all password fields.';
                $messageType = 'error';
            } elseif ($new !== $confirm) {
                $message = 'New passwords do not match.';
                $messageType = 'error';
            } elseif (strlen($new) < 8) {
                $message = 'Password must be at least 8 characters.';
                $messageType = 'error';
            } elseif ($auth->changePassword($admin['id'], $current, $new)) {
                $message = 'Password changed successfully.';
                $messageType = 'success';
            } else {
                $message = 'Current password is incorrect.';
                $messageType = 'error';
            }
        }
    }
}

// ─── Load current settings ─────────────────────────────────────────────

function getSetting(string $key, string $default = ''): string {
    global $db, $prefix;
    $row = $db->fetchOne("SELECT setting_value FROM {$prefix}settings WHERE setting_key = ?", [$key]);
    return $row ? $row['setting_value'] : $default;
}

$csrfToken = s360_generate_csrf_token();
$pageTitle = 'Settings';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>" data-auto-fade>
        <?= $messageType === 'success' ? '✓' : '⚠️' ?> <?= s360_sanitize($message) ?>
    </div>
<?php endif; ?>

<div class="charts-grid">
    <!-- General Settings -->
    <div class="card">
        <div class="card-header">
            <h3>🏢 General Settings</h3>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="form_action" value="general">
            <div class="card-body">
                <div class="form-group">
                    <label for="app_name">Application Name</label>
                    <input type="text" id="app_name" name="app_name" class="form-control" 
                           value="<?= s360_sanitize(getSetting('app_name', APP_NAME)) ?>">
                </div>
                <div class="form-group">
                    <label for="org_name">Organization Name</label>
                    <input type="text" id="org_name" name="org_name" class="form-control" 
                           value="<?= s360_sanitize(getSetting('org_name', ORG_NAME)) ?>">
                </div>
                <div class="form-group">
                    <label for="org_url">Organization URL</label>
                    <input type="url" id="org_url" name="org_url" class="form-control" 
                           value="<?= s360_sanitize(getSetting('org_url', ORG_URL)) ?>">
                </div>
            </div>
            <div class="card-footer">
                <span></span>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <!-- Change Password -->
    <div class="card">
        <div class="card-header">
            <h3>🔒 Change Password</h3>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="form_action" value="password">
            <div class="card-body">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>
            </div>
            <div class="card-footer">
                <span></span>
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<!-- SMTP Settings -->
<div class="card mt-3">
    <div class="card-header">
        <h3>📧 SMTP / Email Settings</h3>
    </div>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="form_action" value="smtp">
        <div class="card-body">
            <div class="grid-2">
                <div class="form-group">
                    <label for="smtp_host">SMTP Host</label>
                    <input type="text" id="smtp_host" name="smtp_host" class="form-control" 
                           value="<?= s360_sanitize(getSetting('smtp_host')) ?>" placeholder="smtp.gmail.com">
                </div>
                <div class="form-group">
                    <label for="smtp_port">SMTP Port</label>
                    <input type="number" id="smtp_port" name="smtp_port" class="form-control" 
                           value="<?= s360_sanitize(getSetting('smtp_port', '587')) ?>">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label for="smtp_user">SMTP Username</label>
                    <input type="text" id="smtp_user" name="smtp_user" class="form-control" 
                           value="<?= s360_sanitize(getSetting('smtp_user')) ?>">
                </div>
                <div class="form-group">
                    <label for="smtp_pass">SMTP Password</label>
                    <input type="password" id="smtp_pass" name="smtp_pass" class="form-control" 
                           value="<?= s360_sanitize(getSetting('smtp_pass')) ?>">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label for="smtp_from">From Email</label>
                    <input type="email" id="smtp_from" name="smtp_from" class="form-control" 
                           value="<?= s360_sanitize(getSetting('smtp_from')) ?>">
                </div>
                <div class="form-group">
                    <label for="smtp_from_name">From Name</label>
                    <input type="text" id="smtp_from_name" name="smtp_from_name" class="form-control" 
                           value="<?= s360_sanitize(getSetting('smtp_from_name', APP_NAME)) ?>">
                </div>
            </div>
        </div>
        <div class="card-footer">
            <span class="text-muted" style="font-size: 12px;">Used for license notification emails</span>
            <button type="submit" class="btn btn-primary">Save SMTP</button>
        </div>
    </form>
</div>

<!-- API Info -->
<div class="card mt-3">
    <div class="card-header">
        <h3>🔗 API Endpoints</h3>
    </div>
    <div class="card-body">
        <p class="text-muted mb-2" style="font-size:13px;">These endpoints are used by the WordPress plugin to communicate with this server.</p>
        <table>
            <thead>
                <tr>
                    <th>Endpoint</th>
                    <th>Method</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code style="color:var(--primary);">/api/activate.php</code></td>
                    <td><span class="badge badge-active">POST</span></td>
                    <td>Activate license on a site</td>
                </tr>
                <tr>
                    <td><code style="color:var(--primary);">/api/deactivate.php</code></td>
                    <td><span class="badge badge-active">POST</span></td>
                    <td>Deactivate license from a site</td>
                </tr>
                <tr>
                    <td><code style="color:var(--primary);">/api/validate.php</code></td>
                    <td><span class="badge badge-active">POST</span></td>
                    <td>Validate license for periodic checks</td>
                </tr>
                <tr>
                    <td><code style="color:var(--primary);">/api/status.php</code></td>
                    <td><span class="badge badge-active">POST</span></td>
                    <td>Get license details and activations</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
