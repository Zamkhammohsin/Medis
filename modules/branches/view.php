<?php
/**
 * Medis - Branch Detail View & Store Performance
 */
$pageTitle = 'Branch Performance';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin']);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    redirect(BASE_URL . '/modules/branches/index.php', 'Invalid branch ID', 'error');
}

$stmt = $db->prepare("
    SELECT b.*, u.name AS manager_name, u.email AS manager_email, u.phone AS manager_phone
    FROM branches b
    LEFT JOIN users u ON u.id = b.manager_id
    WHERE b.id = ?
");
$stmt->execute([$id]);
$branch = $stmt->fetch();
if (!$branch) {
    redirect(BASE_URL . '/modules/branches/index.php', 'Branch not found', 'error');
}

// Branch Stats
$todaySales = getTodaySales($id);
$monthSales = getMonthSales($id);
$lowStockCount = getLowStockCount($id);

// Active Stock
$stockStmt = $db->prepare("SELECT COALESCE(SUM(quantity), 0) FROM batches WHERE branch_id = ? AND expiry_date > CURDATE()");
$stockStmt->execute([$id]);
$activeStock = (int)$stockStmt->fetchColumn();

// Assigned Staff
$staffStmt = $db->prepare("SELECT id, name, email, role, status FROM users WHERE branch_id = ? ORDER BY role ASC, name ASC");
$staffStmt->execute([$id]);
$staffMembers = $staffStmt->fetchAll();

// Recent Sales
$recentSales = getRecentSales(6, $id);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-store text-primary"></i> <?= e($branch['name']) ?></h2>
        <p class="page-subtitle"><?= e($branch['address'] ?? 'No physical address listed') ?> &bull; Phone: <?= e($branch['phone'] ?? '—') ?></p>
    </div>
    <div class="page-actions d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/pos/index.php?branch_id=<?= $branch['id'] ?>" class="btn btn-primary">
            <i class="fas fa-cash-register"></i> Open POS for this Store
        </a>
        <a href="<?= BASE_URL ?>/modules/inventory/index.php?branch_id=<?= $branch['id'] ?>" class="btn btn-outline-info">
            <i class="fas fa-boxes"></i> Store Inventory
        </a>
        <a href="<?= BASE_URL ?>/modules/branches/edit.php?id=<?= $branch['id'] ?>" class="btn btn-outline-primary">
            <i class="fas fa-edit"></i> Edit
        </a>
        <a href="<?= BASE_URL ?>/modules/branches/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> All Branches
        </a>
    </div>
</div>

<!-- Performance Cards -->
<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-icon bg-success-light text-success"><i class="fas fa-money-bill-wave"></i></div>
        <div class="stat-details">
            <span class="stat-label">Today's Sales</span>
            <h3 class="stat-value text-success"><?= formatCurrency($todaySales) ?></h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-primary-light text-primary"><i class="fas fa-chart-line"></i></div>
        <div class="stat-details">
            <span class="stat-label">This Month's Sales</span>
            <h3 class="stat-value text-primary"><?= formatCurrency($monthSales) ?></h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-info-light text-info"><i class="fas fa-boxes"></i></div>
        <div class="stat-details">
            <span class="stat-label">Active Stock Units</span>
            <h3 class="stat-value"><?= number_format($activeStock) ?></h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $lowStockCount > 0 ? 'bg-warning-light text-warning' : 'bg-secondary-light text-secondary' ?>"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-details">
            <span class="stat-label">Low Stock Alerts</span>
            <h3 class="stat-value text-warning"><?= $lowStockCount ?> items</h3>
        </div>
    </div>
</div>

<div class="row">
    <!-- Assigned Staff Members -->
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title m-0"><i class="fas fa-users text-primary"></i> Assigned Store Personnel</h5>
                <a href="<?= BASE_URL ?>/modules/users/add.php?branch_id=<?= $branch['id'] ?>" class="btn btn-xs btn-outline-primary">
                    <i class="fas fa-user-plus"></i> Add Staff
                </a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if (empty($staffMembers)): ?>
                        <li class="list-group-item text-center text-muted py-4">No employees assigned to this branch location.</li>
                    <?php else: ?>
                        <?php foreach ($staffMembers as $u): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= e($u['name']) ?></strong>
                                    <div class="text-muted small"><?= e($u['email']) ?></div>
                                </div>
                                <div class="text-end">
                                    <span class="badge badge-role-<?= e($u['role']) ?>"><?= ucfirst(e($u['role'])) ?></span>
                                    <div class="small <?= $u['status'] === 'active' ? 'text-success' : 'text-danger' ?>"><?= ucfirst(e($u['status'])) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Recent Sales -->
    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title m-0"><i class="fas fa-receipt text-info"></i> Recent Store Transactions</h5>
                <a href="<?= BASE_URL ?>/modules/sales/index.php?branch_id=<?= $branch['id'] ?>" class="btn btn-xs btn-outline-info">View All Sales</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice</th>
                                <th>Cashier</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentSales)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No sales completed at this branch yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentSales as $s): ?>
                                    <tr>
                                        <td><strong><?= e($s['invoice_number']) ?></strong></td>
                                        <td><?= e($s['cashier'] ?? 'Staff') ?></td>
                                        <td><?= formatDateTime($s['created_at']) ?></td>
                                        <td><strong><?= formatCurrency((float)$s['total_amount']) ?></strong></td>
                                        <td><span class="badge badge-outline-secondary"><?= ucfirst(e($s['payment_method'])) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

