<?php
/**
 * Admin — Licenses Management
 * Smart 360 Backup WP — License Backend
 */

define('S360_BACKEND', true);
require_once dirname(__DIR__) . '/config.php';
require_once INCLUDES_PATH . '/db.php';
require_once INCLUDES_PATH . '/helpers.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/license-manager.php';

S360_Auth::requireLogin();

$manager = new S360_License_Manager();
$message = '';
$messageType = '';

// ─── Handle Actions ────────────────────────────────────────────────────

// Create license
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (s360_verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $result = $manager->createLicense([
            'customer_name'  => trim($_POST['customer_name'] ?? ''),
            'customer_email' => trim($_POST['customer_email'] ?? ''),
            'plan'           => $_POST['plan'] ?? 'pro',
            'max_sites'      => (int) ($_POST['max_sites'] ?? PRO_MAX_SITES),
            'purchase_amount'=> (float) ($_POST['purchase_amount'] ?? 0),
            'notes'          => trim($_POST['notes'] ?? ''),
        ]);
        $message = 'License created successfully! Key: ' . $result['license_key'];
        $messageType = 'success';
    } else {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    }
}

// Revoke license
if (($_GET['action'] ?? '') === 'revoke' && !empty($_GET['id'])) {
    if (s360_verify_csrf_token($_GET['token'] ?? '')) {
        $manager->revokeLicense((int) $_GET['id']);
        $message = 'License revoked successfully.';
        $messageType = 'success';
    }
}

// Delete license
if (($_GET['action'] ?? '') === 'delete' && !empty($_GET['id'])) {
    if (s360_verify_csrf_token($_GET['token'] ?? '')) {
        $manager->deleteLicense((int) $_GET['id']);
        $message = 'License deleted permanently.';
        $messageType = 'success';
    }
}

// Reactivate license
if (($_GET['action'] ?? '') === 'activate' && !empty($_GET['id'])) {
    if (s360_verify_csrf_token($_GET['token'] ?? '')) {
        $manager->updateLicense((int) $_GET['id'], ['status' => 'active']);
        $message = 'License reactivated successfully.';
        $messageType = 'success';
    }
}

// ─── Get Licenses ──────────────────────────────────────────────────────

$filters = [
    'status' => $_GET['status'] ?? '',
    'plan'   => $_GET['plan'] ?? '',
    'search' => $_GET['search'] ?? '',
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$data = $manager->getLicenses($filters, $page);
$csrfToken = s360_generate_csrf_token();

$pageTitle = 'Licenses';
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>" data-auto-fade>
        <?= $messageType === 'success' ? '✓' : '⚠️' ?> <?= s360_sanitize($message) ?>
    </div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="filter-bar">
    <div class="search-input-wrapper">
        <span class="search-icon">🔍</span>
        <form method="GET" style="width:100%;">
            <input type="text" name="search" class="form-control" placeholder="Search by key, name, or email..." 
                   value="<?= s360_sanitize($filters['search']) ?>">
            <?php if ($filters['status']): ?><input type="hidden" name="status" value="<?= s360_sanitize($filters['status']) ?>"><?php endif; ?>
            <?php if ($filters['plan']): ?><input type="hidden" name="plan" value="<?= s360_sanitize($filters['plan']) ?>"><?php endif; ?>
        </form>
    </div>
    <select class="form-control" onchange="applyFilter('status', this.value)">
        <option value="">All Status</option>
        <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="revoked" <?= $filters['status'] === 'revoked' ? 'selected' : '' ?>>Revoked</option>
        <option value="expired" <?= $filters['status'] === 'expired' ? 'selected' : '' ?>>Expired</option>
    </select>
    <select class="form-control" onchange="applyFilter('plan', this.value)">
        <option value="">All Plans</option>
        <option value="pro" <?= $filters['plan'] === 'pro' ? 'selected' : '' ?>>Pro</option>
        <option value="free" <?= $filters['plan'] === 'free' ? 'selected' : '' ?>>Free</option>
    </select>
    <button class="btn btn-primary" data-modal-open="createModal">+ New License</button>
</div>

<!-- Licenses Table -->
<div class="card">
    <div class="card-header">
        <h3>🔑 Licenses (<?= number_format($data['total']) ?>)</h3>
    </div>
    <div class="table-wrapper">
        <?php if (empty($data['licenses'])): ?>
            <div class="empty-state">
                <div class="icon">🔑</div>
                <h3>No licenses found</h3>
                <p>Create your first license to get started.</p>
                <button class="btn btn-primary" data-modal-open="createModal">+ Create License</button>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>License Key</th>
                        <th>Customer</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Sites</th>
                        <th>Revenue</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['licenses'] as $lic): ?>
                        <tr>
                            <td>
                                <span class="copy-text" data-copy="<?= s360_sanitize($lic['license_key']) ?>">
                                    <?= s360_sanitize(substr($lic['license_key'], 0, 15)) ?>...
                                    <span class="copy-icon">📋</span>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 600; font-size: 13px;"><?= s360_sanitize($lic['customer_name']) ?></div>
                                <div class="text-muted" style="font-size: 12px;"><?= s360_sanitize($lic['customer_email']) ?></div>
                            </td>
                            <td><span class="badge badge-<?= $lic['plan'] ?>"><?= strtoupper($lic['plan']) ?></span></td>
                            <td><span class="badge badge-<?= $lic['status'] ?>"><?= ucfirst($lic['status']) ?></span></td>
                            <td><?= (int) $lic['active_sites'] ?> / <?= (int) $lic['max_sites'] ?></td>
                            <td>$<?= number_format($lic['purchase_amount'], 2) ?></td>
                            <td style="white-space:nowrap; font-size:12px; color:var(--text-muted);"><?= s360_format_date($lic['created_at'], 'M d, Y') ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <?php if ($lic['status'] === 'active'): ?>
                                        <a href="?action=revoke&id=<?= $lic['id'] ?>&token=<?= $csrfToken ?>" 
                                           class="btn btn-warning btn-icon btn-sm" title="Revoke"
                                           onclick="return confirm('Revoke this license?')">⊘</a>
                                    <?php else: ?>
                                        <a href="?action=activate&id=<?= $lic['id'] ?>&token=<?= $csrfToken ?>" 
                                           class="btn btn-success btn-icon btn-sm" title="Reactivate">✓</a>
                                    <?php endif; ?>
                                    <a href="?action=delete&id=<?= $lic['id'] ?>&token=<?= $csrfToken ?>" 
                                       class="btn btn-danger btn-icon btn-sm" title="Delete"
                                       onclick="return confirm('Permanently delete this license? This cannot be undone.')">✕</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if ($data['pages'] > 1): ?>
        <div class="card-footer">
            <div class="text-muted" style="font-size: 13px;">
                Page <?= $data['page'] ?> of <?= $data['pages'] ?> (<?= number_format($data['total']) ?> total)
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&<?= http_build_query($filters) ?>">← Prev</a>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($data['pages'], $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&<?= http_build_query($filters) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $data['pages']): ?>
                    <a href="?page=<?= $page + 1 ?>&<?= http_build_query($filters) ?>">Next →</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Create License Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal">
        <div class="modal-header">
            <h3>🔑 Create New License</h3>
            <button class="modal-close">✕</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label for="customer_name">Customer Name *</label>
                        <input type="text" id="customer_name" name="customer_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="customer_email">Customer Email *</label>
                        <input type="email" id="customer_email" name="customer_email" class="form-control" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label for="plan">Plan</label>
                        <select id="plan" name="plan" class="form-control">
                            <option value="pro">Pro (Lifetime)</option>
                            <option value="free">Free</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="max_sites">Max Sites</label>
                        <input type="number" id="max_sites" name="max_sites" class="form-control" value="<?= PRO_MAX_SITES ?>" min="1">
                    </div>
                </div>
                <div class="form-group">
                    <label for="purchase_amount">Purchase Amount ($)</label>
                    <input type="number" id="purchase_amount" name="purchase_amount" class="form-control" value="0" step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" class="form-control" placeholder="Optional internal notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Create License</button>
            </div>
        </form>
    </div>
</div>

<script>
function applyFilter(key, value) {
    const url = new URL(window.location);
    if (value) {
        url.searchParams.set(key, value);
    } else {
        url.searchParams.delete(key);
    }
    url.searchParams.delete('page');
    window.location = url.toString();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
