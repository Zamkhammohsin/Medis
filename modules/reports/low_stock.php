<?php
/**
 * Medis - Low Stock Audit Report
 */
$pageTitle = 'Low Stock Report';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

$currentUser = getCurrentUser();
$db = getDB();

$branches = getAllBranches();
$branchId = null;

if (isAdmin()) {
    if (isset($_GET['branch_id']) && $_GET['branch_id'] !== '' && $_GET['branch_id'] !== 'all') {
        $branchId = (int)$_GET['branch_id'];
    }
} else {
    $branchId = (int)$currentUser['branch_id'];
}

$branchCondition = $branchId ? "AND b.branch_id = $branchId" : "";

$sql = "
    SELECT m.id, m.name, m.generic_name, m.min_stock_level, m.purchase_price,
           c.name AS category_name,
           COALESCE(SUM(b.quantity), 0) AS total_stock
    FROM medicines m
    LEFT JOIN batches b ON b.medicine_id = m.id AND b.expiry_date > CURDATE() $branchCondition
    LEFT JOIN categories c ON c.id = m.category_id
    WHERE m.status = 'active'
    GROUP BY m.id, m.name, m.generic_name, m.min_stock_level, m.purchase_price, c.name
    HAVING total_stock < m.min_stock_level
    ORDER BY (m.min_stock_level - total_stock) DESC
";
$stmt = $db->query($sql);
$lowStockItems = $stmt->fetchAll();

$criticalOutOfStock = 0;
$totalEstimatedRestockCost = 0.0;

foreach ($lowStockItems as $it) {
    if ((int)$it['total_stock'] === 0) {
        $criticalOutOfStock++;
    }
    $deficit = (int)$it['min_stock_level'] - (int)$it['total_stock'];
    $suggestedReorder = max($deficit * 2, 20); // order buffer
    $totalEstimatedRestockCost += ($suggestedReorder * (float)$it['purchase_price']);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-exclamation-triangle text-warning"></i> Low Stock & Shortage Report</h2>
        <p class="page-subtitle">Inventory replenishment recommendations based on minimum threshold safety buffers.</p>
    </div>
    <div class="page-actions d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/purchases/add.php" class="btn btn-primary">
            <i class="fas fa-cart-plus"></i> Create Restock Purchase Order
        </a>
        <button type="button" class="btn btn-secondary" onclick="window.print()">
            <i class="fas fa-print"></i> Print
        </button>
        <a href="<?= BASE_URL ?>/modules/reports/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Reports Hub
        </a>
    </div>
</div>

<!-- Branch Filter -->
<?php if (isAdmin()): ?>
<div class="card mb-4 shadow-sm">
    <div class="card-body py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="text-muted"><i class="fas fa-filter"></i> Select Branch:</span>
        <form method="GET" action="" id="lowStockBranchForm" class="m-0">
            <select name="branch_id" class="form-select form-select-sm" onchange="document.getElementById('lowStockBranchForm').submit()">
                <option value="all" <?= $branchId === null ? 'selected' : '' ?>>All Branches (Consolidated Shortages)</option>
                <?php foreach ($branches as $br): ?>
                    <option value="<?= $br['id'] ?>" <?= $branchId === (int)$br['id'] ? 'selected' : '' ?>><?= e($br['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-icon bg-warning-light text-warning"><i class="fas fa-exclamation-circle"></i></div>
        <div class="stat-details">
            <span class="stat-label">Total Low-Stock Items</span>
            <h3 class="stat-value text-warning"><?= count($lowStockItems) ?> Products</h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-danger-light text-danger"><i class="fas fa-times-circle"></i></div>
        <div class="stat-details">
            <span class="stat-label">Completely Out of Stock</span>
            <h3 class="stat-value text-danger"><?= $criticalOutOfStock ?> Products</h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-primary-light text-primary"><i class="fas fa-wallet"></i></div>
        <div class="stat-details">
            <span class="stat-label">Estimated Replenishment Cost</span>
            <h3 class="stat-value"><?= formatCurrency($totalEstimatedRestockCost) ?></h3>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title m-0">Shortage Inventory Table</h5>
        <span class="badge badge-warning"><?= count($lowStockItems) ?> items needing order</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 data-table">
                <thead class="table-light">
                    <tr>
                        <th>Medicine</th>
                        <th>Generic Formula</th>
                        <th>Category</th>
                        <th class="text-center">Current Stock</th>
                        <th class="text-center">Min Threshold</th>
                        <th class="text-center">Deficit</th>
                        <th class="text-center">Recommended Order</th>
                        <th class="text-end">Est. Cost</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lowStockItems)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted"><i class="fas fa-check-circle text-success"></i> All medicines are comfortably stocked above their minimum thresholds.</td></tr>
                    <?php else: ?>
                        <?php foreach ($lowStockItems as $it): ?>
                            <?php
                            $stock = (int)$it['total_stock'];
                            $min = (int)$it['min_stock_level'];
                            $deficit = $min - $stock;
                            $reorder = max($deficit * 2, 20);
                            $estCost = $reorder * (float)$it['purchase_price'];
                            ?>
                            <tr>
                                <td><strong><a href="<?= BASE_URL ?>/modules/medicines/view.php?id=<?= $it['id'] ?>"><?= e($it['name']) ?></a></strong></td>
                                <td><?= e($it['generic_name'] ?? '—') ?></td>
                                <td><span class="badge badge-light"><?= e($it['category_name'] ?? 'General') ?></span></td>
                                <td class="text-center">
                                    <?php if ($stock === 0): ?>
                                        <span class="badge badge-danger">OUT OF STOCK (0)</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning"><?= $stock ?> units</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= $min ?> units</td>
                                <td class="text-center text-danger fw-bold">-<?= $deficit ?></td>
                                <td class="text-center"><strong><?= $reorder ?> units</strong></td>
                                <td class="text-end"><?= formatCurrency($estCost) ?></td>
                                <td class="text-center">
                                    <a href="<?= BASE_URL ?>/modules/purchases/add.php" class="btn btn-xs btn-primary">
                                        <i class="fas fa-cart-plus"></i> Reorder
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

