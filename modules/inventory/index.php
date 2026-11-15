<?php
/**
 * Medis - Inventory Overview
 */
$pageTitle = 'Inventory Management';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

$currentUser = getCurrentUser();
$db = getDB();

// Determine Branch Filter
$selectedBranchId = null;
if (isAdmin()) {
    if (isset($_GET['branch_id']) && $_GET['branch_id'] !== '' && $_GET['branch_id'] !== 'all') {
        $selectedBranchId = (int)$_GET['branch_id'];
    }
} else {
    $selectedBranchId = $currentUser['branch_id'];
}

$branches = getAllBranches();
$activeTab = $_GET['tab'] ?? $_GET['filter'] ?? 'all';

// 1. Total Stock Value Query
$valParams = [];
$valBranch = '';
if ($selectedBranchId) {
    $valBranch = 'AND branch_id = ?';
    $valParams[] = $selectedBranchId;
}
$stockValStmt = $db->prepare("SELECT COALESCE(SUM(quantity * purchase_price), 0) FROM batches WHERE quantity > 0 AND expiry_date > CURDATE() $valBranch");
$stockValStmt->execute($valParams);
$totalStockValue = (float)$stockValStmt->fetchColumn();

// 2. Low Stock Count
$lowStockList = getLowStockMedicines($selectedBranchId);
$lowStockCount = count($lowStockList);

// 3. Expiring in 30 days
$exp30List = getExpiringMedicines(30, $selectedBranchId);
$exp30Count = count($exp30List);

// 4. Expired Batches
$expParams = [];
$expBranch = '';
if ($selectedBranchId) {
    $expBranch = 'AND b.branch_id = ?';
    $expParams[] = $selectedBranchId;
}
$expiredStmt = $db->prepare("
    SELECT b.*, m.name AS medicine_name, br.name AS branch_name,
           DATEDIFF(b.expiry_date, CURDATE()) AS days_left
    FROM batches b
    JOIN medicines m ON m.id = b.medicine_id
    JOIN branches br ON br.id = b.branch_id
    WHERE b.expiry_date <= CURDATE() AND b.quantity > 0 $expBranch
    ORDER BY b.expiry_date ASC
");
$expiredStmt->execute($expParams);
$expiredList = $expiredStmt->fetchAll();
$expiredCount = count($expiredList);

// Fetch All Stock Table Data (Aggregated by medicine & branch)
$allStockParams = [];
$allStockBranch = '';
if ($selectedBranchId) {
    $allStockBranch = 'WHERE b.branch_id = ?';
    $allStockParams[] = $selectedBranchId;
}

$allStockSql = "
    SELECT m.id AS medicine_id, m.name AS medicine_name, m.generic_name, m.min_stock_level,
           c.name AS category_name, br.id AS branch_id, br.name AS branch_name,
           COUNT(b.id) AS batch_count,
           SUM(CASE WHEN b.expiry_date > CURDATE() THEN b.quantity ELSE 0 END) AS valid_stock,
           SUM(CASE WHEN b.expiry_date <= CURDATE() THEN b.quantity ELSE 0 END) AS expired_stock,
           SUM(b.quantity * b.purchase_price) AS total_val
    FROM batches b
    JOIN medicines m ON m.id = b.medicine_id
    JOIN branches br ON br.id = b.branch_id
    LEFT JOIN categories c ON c.id = m.category_id
    $allStockBranch
    GROUP BY m.id, br.id
    ORDER BY m.name ASC
";
$allStockStmt = $db->prepare($allStockSql);
$allStockStmt->execute($allStockParams);
$allStockRows = $allStockStmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-boxes"></i> Inventory & Batch Management</h2>
        <p class="page-subtitle">Track branch-wise stock levels, FIFO batches, and automated expiry monitoring.</p>
    </div>
    <div class="page-actions d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/inventory/adjust.php" class="btn btn-warning">
            <i class="fas fa-sliders-h"></i> Stock Adjustment
        </a>
        <a href="<?= BASE_URL ?>/modules/inventory/movements.php" class="btn btn-outline-secondary">
            <i class="fas fa-history"></i> Stock Movements Log
        </a>
    </div>
</div>

<!-- Branch Filter Bar -->
<?php if (isAdmin()): ?>
<div class="card mb-4 shadow-sm">
    <div class="card-body py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span class="text-muted"><i class="fas fa-filter"></i> Filter Store Inventory:</span>
        <form method="GET" action="" id="invBranchForm" class="d-flex align-items-center gap-2 m-0">
            <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
            <select name="branch_id" class="form-select form-select-sm" onchange="document.getElementById('invBranchForm').submit()">
                <option value="all" <?= $selectedBranchId === null ? 'selected' : '' ?>>All Branches (Consolidated)</option>
                <?php foreach ($branches as $br): ?>
                    <option value="<?= $br['id'] ?>" <?= $selectedBranchId === (int)$br['id'] ? 'selected' : '' ?>>
                        <?= e($br['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-icon bg-success-light text-success"><i class="fas fa-vault"></i></div>
        <div class="stat-details">
            <span class="stat-label">Total Stock Value</span>
            <h3 class="stat-value"><?= formatCurrency($totalStockValue) ?></h3>
            <span class="stat-subtext">Based on cost price</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-warning-light text-warning"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-details">
            <span class="stat-label">Low Stock Alerts</span>
            <h3 class="stat-value text-warning"><?= number_format($lowStockCount) ?></h3>
            <span class="stat-subtext">Below safety threshold</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-danger-light text-danger"><i class="fas fa-clock"></i></div>
        <div class="stat-details">
            <span class="stat-label">Expiring Soon (30d)</span>
            <h3 class="stat-value text-danger"><?= number_format($exp30Count) ?></h3>
            <span class="stat-subtext">Batches needing priority sale</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-secondary-light text-secondary"><i class="fas fa-ban"></i></div>
        <div class="stat-details">
            <span class="stat-label">Expired Products</span>
            <h3 class="stat-value"><?= number_format($expiredCount) ?></h3>
            <span class="stat-subtext">Non-sellable inventory</span>
        </div>
    </div>
</div>

<!-- Tab Navigation -->
<div class="card shadow-sm">
    <div class="card-header p-0" style="background:#fff;">
        <div class="d-flex border-bottom" style="overflow-x:auto;">
            <a href="?branch_id=<?= $selectedBranchId ?>&tab=all" class="p-3 text-decoration-none <?= in_array($activeTab, ['all', '']) ? 'border-bottom border-primary text-primary fw-bold' : 'text-muted' ?>">
                <i class="fas fa-cubes"></i> All Branch Stock (<?= count($allStockRows) ?>)
            </a>
            <a href="?branch_id=<?= $selectedBranchId ?>&tab=low_stock" class="p-3 text-decoration-none <?= $activeTab === 'low_stock' ? 'border-bottom border-warning text-warning fw-bold' : 'text-muted' ?>">
                <i class="fas fa-exclamation-triangle"></i> Low Stock (<?= $lowStockCount ?>)
            </a>
            <a href="?branch_id=<?= $selectedBranchId ?>&tab=expiring" class="p-3 text-decoration-none <?= $activeTab === 'expiring' ? 'border-bottom border-danger text-danger fw-bold' : 'text-muted' ?>">
                <i class="fas fa-calendar-times"></i> Expiring Soon (<?= $exp30Count ?>)
            </a>
            <a href="?branch_id=<?= $selectedBranchId ?>&tab=expired" class="p-3 text-decoration-none <?= $activeTab === 'expired' ? 'border-bottom border-dark text-dark fw-bold' : 'text-muted' ?>">
                <i class="fas fa-ban"></i> Expired (<?= $expiredCount ?>)
            </a>
        </div>
    </div>

    <div class="card-body p-0">
        <?php if ($activeTab === 'low_stock'): ?>
            <!-- TAB: Low Stock -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 data-table">
                    <thead class="table-light">
                        <tr>
                            <th>Medicine</th>
                            <th>Category</th>
                            <th>Min Level</th>
                            <th>Available Units</th>
                            <th>Deficit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lowStockList)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-check-circle text-success"></i> No medicines are running low.</td></tr>
                        <?php else: ?>
                            <?php foreach ($lowStockList as $item): ?>
                                <tr>
                                    <td><strong><?= e($item['name']) ?></strong></td>
                                    <td><span class="badge badge-light"><?= e($item['category'] ?? 'General') ?></span></td>
                                    <td><?= $item['min_stock_level'] ?></td>
                                    <td><span class="badge badge-danger"><?= $item['total_stock'] ?></span></td>
                                    <td><span class="text-danger fw-bold">-<?= max(0, $item['min_stock_level'] - $item['total_stock']) ?></span></td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/modules/purchases/add.php?medicine_id=<?= $item['id'] ?>" class="btn btn-xs btn-primary">
                                            <i class="fas fa-shopping-cart"></i> Reorder
                                        </a>
                                        <a href="<?= BASE_URL ?>/modules/inventory/adjust.php?medicine_id=<?= $item['id'] ?>" class="btn btn-xs btn-outline-warning">
                                            <i class="fas fa-sliders-h"></i> Adjust
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($activeTab === 'expiring'): ?>
            <!-- TAB: Expiring Soon -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 data-table">
                    <thead class="table-light">
                        <tr>
                            <th>Medicine</th>
                            <th>Batch #</th>
                            <th>Branch</th>
                            <th>Quantity</th>
                            <th>Expiry Date</th>
                            <th>Days Left</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($exp30List)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-check-circle text-success"></i> No batches expiring within 30 days.</td></tr>
                        <?php else: ?>
                            <?php foreach ($exp30List as $exp): ?>
                                <tr>
                                    <td><strong><?= e($exp['name']) ?></strong></td>
                                    <td><code><?= e($exp['batch_number']) ?></code></td>
                                    <td><?= e($exp['branch_name']) ?></td>
                                    <td><strong><?= $exp['quantity'] ?></strong></td>
                                    <td><?= formatDate($exp['expiry_date']) ?></td>
                                    <td><span class="badge badge-danger"><?= $exp['days_left'] ?> days</span></td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/modules/inventory/adjust.php?batch_id=<?= $exp['id'] ?? '' ?>" class="btn btn-xs btn-outline-warning">Adjust</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($activeTab === 'expired'): ?>
            <!-- TAB: Expired -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 data-table">
                    <thead class="table-light">
                        <tr>
                            <th>Medicine</th>
                            <th>Batch #</th>
                            <th>Branch</th>
                            <th>Quantity (Blocked)</th>
                            <th>Expiry Date</th>
                            <th>Expired Ago</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expiredList)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-check-circle text-success"></i> No expired items in stock.</td></tr>
                        <?php else: ?>
                            <?php foreach ($expiredList as $exp): ?>
                                <tr>
                                    <td><strong><?= e($exp['medicine_name']) ?></strong></td>
                                    <td><code><?= e($exp['batch_number']) ?></code></td>
                                    <td><?= e($exp['branch_name']) ?></td>
                                    <td><span class="badge badge-danger"><?= $exp['quantity'] ?> units</span></td>
                                    <td><?= formatDate($exp['expiry_date']) ?></td>
                                    <td><span class="text-danger fw-bold"><?= abs($exp['days_left']) ?> days ago</span></td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/modules/inventory/adjust.php?medicine_id=<?= $exp['medicine_id'] ?>&branch_id=<?= $exp['branch_id'] ?>" class="btn btn-xs btn-danger">
                                            <i class="fas fa-trash-alt"></i> Dispose / Write Off
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <!-- TAB: All Stock -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 data-table">
                    <thead class="table-light">
                        <tr>
                            <th>Medicine</th>
                            <th>Category</th>
                            <th>Branch</th>
                            <th>Batches</th>
                            <th>Sellable Stock</th>
                            <th>Expired Stock</th>
                            <th>Inventory Value</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allStockRows)): ?>
                            <tr><td colspan="9" class="text-center py-4 text-muted">No inventory records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($allStockRows as $row): ?>
                                <tr>
                                    <td>
                                        <strong><a href="<?= BASE_URL ?>/modules/medicines/view.php?id=<?= $row['medicine_id'] ?>"><?= e($row['medicine_name']) ?></a></strong>
                                        <div class="text-muted small"><?= e($row['generic_name'] ?? '') ?></div>
                                    </td>
                                    <td><span class="badge badge-light"><?= e($row['category_name'] ?? '—') ?></span></td>
                                    <td><span class="badge badge-light"><?= e($row['branch_name']) ?></span></td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/modules/inventory/batch_detail.php?medicine_id=<?= $row['medicine_id'] ?>&branch_id=<?= $row['branch_id'] ?>" class="badge badge-outline-secondary">
                                            <?= $row['batch_count'] ?> batches &rarr;
                                        </a>
                                    </td>
                                    <td><strong><?= $row['valid_stock'] ?></strong></td>
                                    <td>
                                        <?php if ($row['expired_stock'] > 0): ?>
                                            <span class="badge badge-danger"><?= $row['expired_stock'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= formatCurrency((float)$row['total_val']) ?></td>
                                    <td>
                                        <?php if ($row['valid_stock'] <= 0): ?>
                                            <span class="badge badge-danger">Out of Stock</span>
                                        <?php elseif ($row['valid_stock'] < $row['min_stock_level']): ?>
                                            <span class="badge badge-warning">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Sufficient</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/modules/inventory/batch_detail.php?medicine_id=<?= $row['medicine_id'] ?>&branch_id=<?= $row['branch_id'] ?>" class="btn btn-xs btn-outline-info" title="View Batch Breakdown">
                                            <i class="fas fa-layer-group"></i> Batches
                                        </a>
                                        <a href="<?= BASE_URL ?>/modules/inventory/adjust.php?medicine_id=<?= $row['medicine_id'] ?>&branch_id=<?= $row['branch_id'] ?>" class="btn btn-xs btn-outline-warning" title="Adjust Stock">
                                            <i class="fas fa-sliders-h"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

