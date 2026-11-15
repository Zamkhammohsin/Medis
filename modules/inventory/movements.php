<?php
/**
 * Medis - Stock Movements Audit Log
 */
$pageTitle = 'Stock Movements History';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

$currentUser = getCurrentUser();
$db = getDB();

$branchId = null;
if (isAdmin()) {
    if (isset($_GET['branch_id']) && $_GET['branch_id'] !== '' && $_GET['branch_id'] !== 'all') {
        $branchId = (int)$_GET['branch_id'];
    }
} else {
    $branchId = (int)$currentUser['branch_id'];
}

$type       = trim($_GET['type'] ?? '');
$medicineId = (int)($_GET['medicine_id'] ?? 0);
$fromDate   = trim($_GET['from_date'] ?? '');
$toDate     = trim($_GET['to_date'] ?? '');

$branches  = getAllBranches();
$medicines = $db->query("SELECT id, name FROM medicines ORDER BY name ASC")->fetchAll();

// Pagination
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where = ["1=1"];
$params = [];

if ($branchId) {
    $where[] = "sm.branch_id = ?";
    $params[] = $branchId;
}

if ($medicineId) {
    $where[] = "sm.medicine_id = ?";
    $params[] = $medicineId;
}

if ($type !== '' && in_array($type, ['purchase', 'sale', 'adjustment'])) {
    $where[] = "sm.type = ?";
    $params[] = $type;
}

if ($fromDate !== '') {
    $where[] = "DATE(sm.created_at) >= ?";
    $params[] = $fromDate;
}

if ($toDate !== '') {
    $where[] = "DATE(sm.created_at) <= ?";
    $params[] = $toDate;
}

$whereSql = implode(' AND ', $where);

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM stock_movements sm WHERE $whereSql");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages   = max(1, ceil($totalRecords / $limit));

// Fetch Records
$sql = "
    SELECT sm.*, m.name AS medicine_name, br.name AS branch_name, b.batch_number
    FROM stock_movements sm
    JOIN medicines m ON m.id = sm.medicine_id
    JOIN branches br ON br.id = sm.branch_id
    LEFT JOIN batches b ON b.id = sm.batch_id
    WHERE $whereSql
    ORDER BY sm.created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$movements = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-history"></i> Stock Movements Log</h2>
        <p class="page-subtitle">Immutable chronological audit log of all stock increases and deductions.</p>
    </div>
    <div class="page-actions d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/inventory/adjust.php" class="btn btn-warning">
            <i class="fas fa-sliders-h"></i> New Adjustment
        </a>
        <a href="<?= BASE_URL ?>/modules/inventory/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-boxes"></i> Inventory Overview
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row align-items-center gap-2">
            <?php if (isAdmin()): ?>
            <div class="col-md-2">
                <select name="branch_id" class="form-select form-select-sm">
                    <option value="all">All Branches</option>
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= $br['id'] ?>" <?= $branchId === (int)$br['id'] ? 'selected' : '' ?>><?= e($br['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-md-3">
                <select name="medicine_id" class="form-select form-select-sm">
                    <option value="">All Medicines</option>
                    <?php foreach ($medicines as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $medicineId === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Movement Types</option>
                    <option value="purchase" <?= $type === 'purchase' ? 'selected' : '' ?>>Purchases (+)</option>
                    <option value="sale" <?= $type === 'sale' ? 'selected' : '' ?>>Sales (-)</option>
                    <option value="adjustment" <?= $type === 'adjustment' ? 'selected' : '' ?>>Adjustments (+/-)</option>
                </select>
            </div>

            <div class="col-md-2">
                <input type="date" name="from_date" class="form-control form-control-sm" placeholder="From Date" value="<?= e($fromDate) ?>">
            </div>

            <div class="col-md-2">
                <input type="date" name="to_date" class="form-control form-control-sm" placeholder="To Date" value="<?= e($toDate) ?>">
            </div>

            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="fas fa-filter"></i></button>
                <a href="<?= BASE_URL ?>/modules/inventory/movements.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Log Table -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title m-0">Movement Records (<?= $totalRecords ?> found)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date & Time</th>
                        <th>Medicine</th>
                        <th>Branch</th>
                        <th>Batch</th>
                        <th>Type</th>
                        <th>Quantity Change</th>
                        <th>Reference / Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($movements)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No stock movement logs found for this filter.</td></tr>
                    <?php else: ?>
                        <?php foreach ($movements as $mov): ?>
                            <tr>
                                <td><?= formatDateTime($mov['created_at']) ?></td>
                                <td><strong><?= e($mov['medicine_name']) ?></strong></td>
                                <td><span class="badge badge-light"><?= e($mov['branch_name']) ?></span></td>
                                <td><?= $mov['batch_number'] ? '<code>' . e($mov['batch_number']) . '</code>' : '—' ?></td>
                                <td>
                                    <?php if ($mov['type'] === 'purchase'): ?>
                                        <span class="badge badge-success"><i class="fas fa-arrow-down"></i> Purchase In</span>
                                    <?php elseif ($mov['type'] === 'sale'): ?>
                                        <span class="badge badge-primary"><i class="fas fa-arrow-up"></i> Sale Out</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning"><i class="fas fa-sliders-h"></i> Adjustment</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($mov['quantity'] > 0): ?>
                                        <span class="text-success fw-bold">+<?= $mov['quantity'] ?> units</span>
                                    <?php else: ?>
                                        <span class="text-danger fw-bold"><?= $mov['quantity'] ?> units</span>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?= e($mov['notes'] ?? '—') ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <span class="text-muted">Page <?= $page ?> of <?= $totalPages ?></span>
        <div class="pagination d-flex gap-1">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&branch_id=<?= $branchId ?>&medicine_id=<?= $medicineId ?>&type=<?= $type ?>&from_date=<?= $fromDate ?>&to_date=<?= $toDate ?>" class="btn btn-xs btn-outline-secondary">&laquo; Prev</a>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php if ($p == 1 || $p == $totalPages || abs($p - $page) <= 2): ?>
                    <a href="?page=<?= $p ?>&branch_id=<?= $branchId ?>&medicine_id=<?= $medicineId ?>&type=<?= $type ?>&from_date=<?= $fromDate ?>&to_date=<?= $toDate ?>" class="btn btn-xs <?= $p === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&branch_id=<?= $branchId ?>&medicine_id=<?= $medicineId ?>&type=<?= $type ?>&from_date=<?= $fromDate ?>&to_date=<?= $toDate ?>" class="btn btn-xs btn-outline-secondary">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

