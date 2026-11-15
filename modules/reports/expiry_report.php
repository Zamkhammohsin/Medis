<?php
/**
 * Medis - Batch Expiry Audit Report
 */
$pageTitle = 'Batch Expiry Report';
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

$timeframe = trim($_GET['timeframe'] ?? 'all'); // 'expired', '7', '30', '90', 'all'

$where = ["b.quantity > 0"];
$params = [];

if ($branchId) {
    $where[] = "b.branch_id = ?";
    $params[] = $branchId;
}

if ($timeframe === 'expired') {
    $where[] = "b.expiry_date <= CURDATE()";
} elseif ($timeframe === '7') {
    $where[] = "b.expiry_date > CURDATE() AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
} elseif ($timeframe === '30') {
    $where[] = "b.expiry_date > CURDATE() AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
} elseif ($timeframe === '90') {
    $where[] = "b.expiry_date > CURDATE() AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)";
}

$whereSql = implode(' AND ', $where);

$sql = "
    SELECT b.*, m.name AS medicine_name, m.generic_name, br.name AS branch_name,
           DATEDIFF(b.expiry_date, CURDATE()) AS days_left,
           (b.quantity * b.purchase_price) AS valuation
    FROM batches b
    JOIN medicines m ON m.id = b.medicine_id
    JOIN branches br ON br.id = b.branch_id
    WHERE $whereSql
    ORDER BY b.expiry_date ASC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$batches = $stmt->fetchAll();

// Counts for summary cards
$expCount = getExpiredCount($branchId);
$exp7     = count(getExpiringMedicines(7, $branchId));
$exp30    = count(getExpiringMedicines(30, $branchId));
$exp90    = count(getExpiringMedicines(90, $branchId));

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-hourglass-end text-danger"></i> Batch Expiry Audit Report</h2>
        <p class="page-subtitle">Identify expired and short-dated pharmaceutical batches to prevent dispensing and plan disposal.</p>
    </div>
    <div class="page-actions d-flex gap-2">
        <button type="button" class="btn btn-secondary" onclick="window.print()">
            <i class="fas fa-print"></i> Print
        </button>
        <a href="<?= BASE_URL ?>/modules/reports/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Reports Hub
        </a>
    </div>
</div>

<!-- Quick Stat Cards -->
<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-icon bg-danger-light text-danger"><i class="fas fa-ban"></i></div>
        <div class="stat-details">
            <span class="stat-label">Already Expired</span>
            <h3 class="stat-value text-danger"><?= $expCount ?> Batches</h3>
            <span class="stat-subtext"><a href="?timeframe=expired&branch_id=<?= $branchId ?>" class="text-danger">View Expired &rarr;</a></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-danger-light text-danger"><i class="fas fa-exclamation-circle"></i></div>
        <div class="stat-details">
            <span class="stat-label">Critical (Within 7 Days)</span>
            <h3 class="stat-value text-danger"><?= $exp7 ?> Batches</h3>
            <span class="stat-subtext"><a href="?timeframe=7&branch_id=<?= $branchId ?>" class="text-danger">View 7-Day &rarr;</a></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-warning-light text-warning"><i class="fas fa-clock"></i></div>
        <div class="stat-details">
            <span class="stat-label">Within 30 Days</span>
            <h3 class="stat-value text-warning"><?= $exp30 ?> Batches</h3>
            <span class="stat-subtext"><a href="?timeframe=30&branch_id=<?= $branchId ?>" class="text-warning">View 30-Day &rarr;</a></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-primary-light text-primary"><i class="fas fa-calendar-check"></i></div>
        <div class="stat-details">
            <span class="stat-label">Within 90 Days</span>
            <h3 class="stat-value"><?= $exp90 ?> Batches</h3>
            <span class="stat-subtext"><a href="?timeframe=90&branch_id=<?= $branchId ?>" class="text-primary">View 90-Day &rarr;</a></span>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row align-items-center gap-2">
            <div class="col-md-4">
                <select name="timeframe" class="form-select form-select-sm">
                    <option value="all" <?= $timeframe === 'all' ? 'selected' : '' ?>>All Batches with Stock</option>
                    <option value="expired" <?= $timeframe === 'expired' ? 'selected' : '' ?>>Expired Items (Overdue)</option>
                    <option value="7" <?= $timeframe === '7' ? 'selected' : '' ?>>Expiring within 7 Days</option>
                    <option value="30" <?= $timeframe === '30' ? 'selected' : '' ?>>Expiring within 30 Days</option>
                    <option value="90" <?= $timeframe === '90' ? 'selected' : '' ?>>Expiring within 90 Days</option>
                </select>
            </div>

            <?php if (isAdmin()): ?>
            <div class="col-md-4">
                <select name="branch_id" class="form-select form-select-sm">
                    <option value="all">All Branches</option>
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= $br['id'] ?>" <?= $branchId === (int)$br['id'] ? 'selected' : '' ?>><?= e($br['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="fas fa-filter"></i> Filter</button>
                <a href="<?= BASE_URL ?>/modules/reports/expiry_report.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title m-0">Batches (<?= count($batches) ?> records)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 data-table">
                <thead class="table-light">
                    <tr>
                        <th>Medicine</th>
                        <th>Batch #</th>
                        <th>Branch</th>
                        <th class="text-center">Remaining Stock</th>
                        <th>Cost Value</th>
                        <th>Expiry Date</th>
                        <th>Time Remaining</th>
                        <th>Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($batches)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted"><i class="fas fa-check-circle text-success"></i> No batches matching selected expiry condition.</td></tr>
                    <?php else: ?>
                        <?php foreach ($batches as $b): ?>
                            <tr>
                                <td>
                                    <strong><a href="<?= BASE_URL ?>/modules/medicines/view.php?id=<?= $b['medicine_id'] ?>"><?= e($b['medicine_name']) ?></a></strong>
                                    <div class="text-muted small"><?= e($b['generic_name'] ?? '') ?></div>
                                </td>
                                <td><code><?= e($b['batch_number']) ?></code></td>
                                <td><?= e($b['branch_name']) ?></td>
                                <td class="text-center"><strong><?= $b['quantity'] ?> units</strong></td>
                                <td><?= formatCurrency((float)$b['valuation']) ?></td>
                                <td><strong><?= formatDate($b['expiry_date']) ?></strong></td>
                                <td>
                                    <?php if ($b['days_left'] < 0): ?>
                                        <span class="text-danger fw-bold">Expired <?= abs($b['days_left']) ?> days ago</span>
                                    <?php else: ?>
                                        <span class="<?= $b['days_left'] <= 30 ? 'text-danger fw-bold' : ($b['days_left'] <= 90 ? 'text-warning fw-bold' : 'text-success') ?>">
                                            <?= $b['days_left'] ?> days left
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($b['days_left'] < 0): ?>
                                        <span class="badge badge-danger">EXPIRED</span>
                                    <?php elseif ($b['days_left'] <= 7): ?>
                                        <span class="badge badge-danger">Critical (&le; 7d)</span>
                                    <?php elseif ($b['days_left'] <= 30): ?>
                                        <span class="badge badge-warning">Short Expiry (&le; 30d)</span>
                                    <?php elseif ($b['days_left'] <= 90): ?>
                                        <span class="badge badge-warning">Near Expiry (&le; 90d)</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">OK (&gt; 90d)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="<?= BASE_URL ?>/modules/inventory/adjust.php?batch_id=<?= $b['id'] ?>&medicine_id=<?= $b['medicine_id'] ?>&branch_id=<?= $b['branch_id'] ?>" class="btn btn-xs btn-outline-warning" title="Adjust / Write Off">
                                        <i class="fas fa-sliders-h"></i> Adjust
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

