<?php
/**
 * Admin — Customers Page
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
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$data = $manager->getCustomers($search, $page);

$pageTitle = 'Customers';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Filter -->
<div class="filter-bar">
    <div class="search-input-wrapper">
        <span class="search-icon">🔍</span>
        <form method="GET" style="width:100%;">
            <input type="text" name="search" class="form-control" placeholder="Search customers by name or email..."
                   value="<?= s360_sanitize($search) ?>">
        </form>
    </div>
</div>

<!-- Customers Table -->
<div class="card">
    <div class="card-header">
        <h3>👥 Customers (<?= number_format($data['total']) ?>)</h3>
    </div>
    <div class="table-wrapper">
        <?php if (empty($data['customers'])): ?>
            <div class="empty-state">
                <div class="icon">👥</div>
                <h3>No customers found</h3>
                <p>Customers appear here once licenses are created.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Total Licenses</th>
                        <th>Active</th>
                        <th>Total Spent</th>
                        <th>First Purchase</th>
                        <th>Last Purchase</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['customers'] as $cust): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600; font-size: 14px;"><?= s360_sanitize($cust['customer_name']) ?></div>
                                <div class="text-muted" style="font-size: 12px;"><?= s360_sanitize($cust['customer_email']) ?></div>
                            </td>
                            <td><?= (int) $cust['total_licenses'] ?></td>
                            <td>
                                <span class="badge badge-active"><?= (int) $cust['active_licenses'] ?> Active</span>
                            </td>
                            <td style="font-weight: 600;">$<?= number_format($cust['total_spent'], 2) ?></td>
                            <td style="font-size:12px; color:var(--text-muted); white-space:nowrap;"><?= s360_format_date($cust['first_purchase'], 'M d, Y') ?></td>
                            <td style="font-size:12px; color:var(--text-muted); white-space:nowrap;"><?= s360_format_date($cust['last_purchase'], 'M d, Y') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if ($data['pages'] > 1): ?>
        <div class="card-footer">
            <div class="text-muted" style="font-size: 13px;">
                Page <?= $data['page'] ?> of <?= $data['pages'] ?>
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">← Prev</a>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($data['pages'], $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $data['pages']): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Next →</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
