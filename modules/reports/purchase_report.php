<?php
/**
 * Medis - Procurement & Purchase Report
 */
$pageTitle = 'Purchase Report';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

$currentUser = getCurrentUser();
$db = getDB();

$branches  = getAllBranches();
$suppliers = $db->query("SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$branchId   = null;
if (isAdmin()) {
    if (isset($_GET['branch_id']) && $_GET['branch_id'] !== '' && $_GET['branch_id'] !== 'all') {
        $branchId = (int)$_GET['branch_id'];
    }
} else {
    $branchId = (int)$currentUser['branch_id'];
}

$supplierId = (int)($_GET['supplier_id'] ?? 0);
$fromDate   = trim($_GET['from_date'] ?? date('Y-01-01'));
$toDate     = trim($_GET['to_date'] ?? date('Y-m-d'));
$status     = trim($_GET['status'] ?? 'all');

$where = [
    "DATE(p.created_at) >= ?",
    "DATE(p.created_at) <= ?"
];
$params = [$fromDate, $toDate];

if ($branchId) {
    $where[] = "p.branch_id = ?";
    $params[] = $branchId;
}

if ($supplierId) {
    $where[] = "p.supplier_id = ?";
    $params[] = $supplierId;
}

if ($status !== 'all' && in_array($status, ['received', 'pending', 'cancelled'])) {
    $where[] = "p.status = ?";
    $params[] = $status;
}

$whereSql = implode(' AND ', $where);

$sql = "
    SELECT p.*, s.name AS supplier_name, br.name AS branch_name,
           (SELECT COUNT(*) FROM purchase_items WHERE purchase_id = p.id) AS item_count
    FROM purchases p
    LEFT JOIN suppliers s ON s.id = p.supplier_id
    JOIN branches br ON br.id = p.branch_id
    WHERE $whereSql
    ORDER BY p.created_at DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$purchases = $stmt->fetchAll();

$totalValReceived = 0.0;
$receivedCount = 0;
$pendingCount  = 0;
$cancelCount   = 0;

foreach ($purchases as $p) {
    if ($p['status'] === 'received') {
        $totalValReceived += (float)$p['total_amount'];
        $receivedCount++;
    } elseif ($p['status'] === 'pending') {
        $pendingCount++;
    } else {
        $cancelCount++;
    }
}

// Chart: monthly procurement trends
$trendSql = "
    SELECT DATE_FORMAT(p.created_at, '%b %Y') AS m_label,
           COALESCE(SUM(p.total_amount), 0) AS m_total
    FROM purchases p
    WHERE $whereSql AND p.status = 'received'
    GROUP BY DATE_FORMAT(p.created_at, '%Y-%m'), m_label
    ORDER BY p.created_at ASC
";
$trendStmt = $db->prepare($trendSql);
$trendStmt->execute($params);
$trendRows = $trendStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$chartLabels = array_keys($trendRows);
$chartData   = array_values($trendRows);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-truck-loading text-primary"></i> Procurement & Purchase Report</h2>
        <p class="page-subtitle">Track capital expenditure, supplier orders, and shipments from <strong><?= formatDate($fromDate) ?></strong> to <strong><?= formatDate($toDate) ?></strong></p>
    </div>
    <div class="page-actions d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/purchases/add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> New PO
        </a>
        <button type="button" class="btn btn-secondary" onclick="window.print()">
            <i class="fas fa-print"></i> Print
        </button>
        <a href="<?= BASE_URL ?>/modules/reports/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Reports Hub
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row align-items-center gap-2">
            <div class="col-md-2">
                <label class="form-label small text-muted">From Date:</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= e($fromDate) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted">To Date:</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= e($toDate) ?>">
            </div>

            <?php if (isAdmin()): ?>
            <div class="col-md-2">
                <label class="form-label small text-muted">Branch:</label>
                <select name="branch_id" class="form-select form-select-sm">
                    <option value="all">All Branches</option>
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= $br['id'] ?>" <?= $branchId === (int)$br['id'] ? 'selected' : '' ?>><?= e($br['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-md-2">
                <label class="form-label small text-muted">Supplier:</label>
                <select name="supplier_id" class="form-select form-select-sm">
                    <option value="">All Suppliers</option>
                    <?php foreach ($suppliers as $sup): ?>
                        <option value="<?= $sup['id'] ?>" <?= $supplierId === (int)$sup['id'] ? 'selected' : '' ?>><?= e($sup['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted">Status:</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="received" <?= $status === 'received' ? 'selected' : '' ?>>Received</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>

            <div class="col-md-1 d-flex align-items-end" style="margin-top: 24px;">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i></button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-icon bg-success-light text-success"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="stat-details">
            <span class="stat-label">Received Procurement Spend</span>
            <h3 class="stat-value text-success"><?= formatCurrency($totalValReceived) ?></h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-primary-light text-primary"><i class="fas fa-box-open"></i></div>
        <div class="stat-details">
            <span class="stat-label">Orders Received</span>
            <h3 class="stat-value"><?= $receivedCount ?> Orders</h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-warning-light text-warning"><i class="fas fa-clock"></i></div>
        <div class="stat-details">
            <span class="stat-label">Pending Delivery</span>
            <h3 class="stat-value text-warning"><?= $pendingCount ?> Orders</h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-danger-light text-danger"><i class="fas fa-ban"></i></div>
        <div class="stat-details">
            <span class="stat-label">Cancelled Orders</span>
            <h3 class="stat-value text-danger"><?= $cancelCount ?> Orders</h3>
        </div>
    </div>
</div>

<!-- Chart -->
<?php if (!empty($chartLabels)): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="card-title m-0"><i class="fas fa-chart-line text-primary"></i> Monthly Procurement Trends (Received Value in PKR)</h5></div>
    <div class="card-body">
        <div style="height: 240px;">
            <canvas id="procurementChart"></canvas>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Orders Table -->
<div class="card shadow-sm">
    <div class="card-header"><h5 class="card-title m-0">Purchase Invoices (<?= count($purchases) ?> records)</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 data-table">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Branch</th>
                        <th>Items</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchases)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No purchase records found for this criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($purchases as $p): ?>
                            <tr>
                                <td><strong><a href="<?= BASE_URL ?>/modules/purchases/view.php?id=<?= $p['id'] ?>"><code><?= e($p['invoice_number']) ?></code></a></strong></td>
                                <td><?= formatDate($p['created_at']) ?></td>
                                <td><strong><?= e($p['supplier_name'] ?? 'Direct') ?></strong></td>
                                <td><span class="badge badge-light"><?= e($p['branch_name']) ?></span></td>
                                <td><?= $p['item_count'] ?> items</td>
                                <td><strong><?= formatCurrency((float)$p['total_amount']) ?></strong></td>
                                <td>
                                    <?php if ($p['status'] === 'received'): ?>
                                        <span class="badge badge-success">Received</span>
                                    <?php elseif ($p['status'] === 'pending'): ?>
                                        <span class="badge badge-warning">Pending</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Cancelled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/modules/purchases/view.php?id=<?= $p['id'] ?>" class="btn btn-xs btn-outline-info">
                                        <i class="fas fa-eye"></i> Details
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

<?php if (!empty($chartLabels)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('procurementChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Procurement Spend (PKR)',
                    data: <?= json_encode($chartData) ?>,
                    backgroundColor: '#1e3a5f',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: function(v) { return 'PKR ' + v.toLocaleString(); } }
                    }
                }
            }
        });
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

