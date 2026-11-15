<?php
/**
 * Medis - Top-Selling Medicines Report
 */
$pageTitle = 'Top-Selling Medicines';
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

$fromDate = trim($_GET['from_date'] ?? date('Y-01-01')); // start of year default
$toDate   = trim($_GET['to_date'] ?? date('Y-m-d'));

$where = [
    "s.status = 'completed'",
    "DATE(s.created_at) >= ?",
    "DATE(s.created_at) <= ?"
];
$params = [$fromDate, $toDate];

if ($branchId) {
    $where[] = "s.branch_id = ?";
    $params[] = $branchId;
}

$whereSql = implode(' AND ', $where);

$sql = "
    SELECT m.id, m.name, m.generic_name, c.name AS category_name,
           SUM(si.quantity) AS total_qty_sold,
           SUM(si.subtotal) AS total_revenue,
           AVG(si.unit_price) AS avg_unit_price
    FROM sale_items si
    JOIN sales s ON s.id = si.sale_id
    JOIN medicines m ON m.id = si.medicine_id
    LEFT JOIN categories c ON c.id = m.category_id
    WHERE $whereSql
    GROUP BY m.id, m.name, m.generic_name, c.name
    ORDER BY total_revenue DESC
    LIMIT 20
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$topMedicines = $stmt->fetchAll();

// Chart data
$chartLabels = [];
$chartRevenue = [];
foreach (array_slice($topMedicines, 0, 10) as $med) {
    $chartLabels[]  = $med['name'];
    $chartRevenue[] = (float)$med['total_revenue'];
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-trophy text-primary"></i> Top-Selling Medicines Ranking</h2>
        <p class="page-subtitle">Highest grossing pharmaceuticals and volume turnover from <strong><?= formatDate($fromDate) ?></strong> to <strong><?= formatDate($toDate) ?></strong></p>
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

<!-- Filters -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row align-items-center gap-3">
            <div class="col-md-3">
                <label class="form-label small text-muted">From Date:</label>
                <input type="date" name="from_date" class="form-control" value="<?= e($fromDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">To Date:</label>
                <input type="date" name="to_date" class="form-control" value="<?= e($toDate) ?>">
            </div>

            <?php if (isAdmin()): ?>
            <div class="col-md-3">
                <label class="form-label small text-muted">Store Location:</label>
                <select name="branch_id" class="form-select">
                    <option value="all">All Branches</option>
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= $br['id'] ?>" <?= $branchId === (int)$br['id'] ? 'selected' : '' ?>><?= e($br['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-md-2 d-flex align-items-end" style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Apply Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Horizontal Bar Chart -->
<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="card-title m-0"><i class="fas fa-chart-bar text-primary"></i> Top 10 Medicines by Turnover Revenue (PKR)</h5></div>
    <div class="card-body">
        <div style="height: 300px;">
            <canvas id="topMedsChart"></canvas>
        </div>
    </div>
</div>

<!-- Rankings Table -->
<div class="card shadow-sm">
    <div class="card-header"><h5 class="card-title m-0">Performance League Table</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 5%;">Rank</th>
                        <th>Medicine Product</th>
                        <th>Generic Formula</th>
                        <th>Category</th>
                        <th class="text-center">Units Sold</th>
                        <th class="text-end">Avg Realized Price</th>
                        <th class="text-end">Total Gross Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topMedicines)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No sales data recorded for this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($topMedicines as $idx => $m): ?>
                            <tr>
                                <td>
                                    <?php if ($idx === 0): ?>
                                        <span class="badge" style="background:#ffd700; color:#000;"><i class="fas fa-medal"></i> #1</span>
                                    <?php elseif ($idx === 1): ?>
                                        <span class="badge" style="background:#c0c0c0; color:#000;"><i class="fas fa-medal"></i> #2</span>
                                    <?php elseif ($idx === 2): ?>
                                        <span class="badge" style="background:#cd7f32; color:#fff;"><i class="fas fa-medal"></i> #3</span>
                                    <?php else: ?>
                                        <span class="text-muted fw-bold">#<?= $idx + 1 ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><a href="<?= BASE_URL ?>/modules/medicines/view.php?id=<?= $m['id'] ?>"><?= e($m['name']) ?></a></strong>
                                </td>
                                <td><?= e($m['generic_name'] ?? '—') ?></td>
                                <td><span class="badge badge-light"><?= e($m['category_name'] ?? 'General') ?></span></td>
                                <td class="text-center"><strong><?= number_format((int)$m['total_qty_sold']) ?> units</strong></td>
                                <td class="text-end"><?= formatCurrency((float)$m['avg_unit_price']) ?></td>
                                <td class="text-end text-primary fw-bold fs-6"><?= formatCurrency((float)$m['total_revenue']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('topMedsChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Gross Sales (PKR)',
                    data: <?= json_encode($chartRevenue) ?>,
                    backgroundColor: '#0d6efd',
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { callback: function(v) { return 'PKR ' + v.toLocaleString(); } }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

