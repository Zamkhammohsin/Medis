<?php
/**
 * Medis - Daily Sales Report
 */
$pageTitle = 'Daily Sales Report';
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

$reportDate = trim($_GET['date'] ?? date('Y-m-d'));

// Summary Metrics Query
$where = ["DATE(s.created_at) = ?", "s.status = 'completed'"];
$params = [$reportDate];

if ($branchId) {
    $where[] = "s.branch_id = ?";
    $params[] = $branchId;
}

$whereSql = implode(' AND ', $where);

$sumStmt = $db->prepare("
    SELECT COUNT(*) AS total_txns,
           COALESCE(SUM(s.subtotal), 0) AS total_subtotal,
           COALESCE(SUM(s.discount), 0) AS total_discount,
           COALESCE(SUM(s.total_amount), 0) AS total_net
    FROM sales s
    WHERE $whereSql
");
$sumStmt->execute($params);
$summary = $sumStmt->fetch();

$totalTxns     = (int)$summary['total_txns'];
$totalRevenue  = (float)$summary['total_net'];
$totalDiscount = (float)$summary['total_discount'];
$avgTicket     = $totalTxns > 0 ? ($totalRevenue / $totalTxns) : 0.0;

// Hourly breakdown for Chart.js
$hourlySql = "
    SELECT HOUR(s.created_at) AS hr, COALESCE(SUM(s.total_amount), 0) AS hr_total
    FROM sales s
    WHERE $whereSql
    GROUP BY HOUR(s.created_at)
    ORDER BY hr ASC
";
$hourlyStmt = $db->prepare($hourlySql);
$hourlyStmt->execute($params);
$hourlyRows = $hourlyStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$hourLabels = [];
$hourData   = [];
for ($h = 8; $h <= 23; $h++) {
    $timeLabel = date('h A', strtotime("$h:00"));
    $hourLabels[] = $timeLabel;
    $hourData[]   = (float)($hourlyRows[$h] ?? 0);
}

// Sales Transactions List for that day
$txStmt = $db->prepare("
    SELECT s.*, br.name AS branch_name, u.name AS cashier_name,
           (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) AS item_count
    FROM sales s
    JOIN branches br ON br.id = s.branch_id
    LEFT JOIN users u ON u.id = s.user_id
    WHERE $whereSql
    ORDER BY s.created_at ASC
");
$txStmt->execute($params);
$transactions = $txStmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-calendar-day text-primary"></i> Daily Sales Report</h2>
        <p class="page-subtitle">Hourly revenue curve and transaction log for <strong><?= formatDate($reportDate) ?></strong></p>
    </div>
    <div class="page-actions d-flex gap-2">
        <button type="button" class="btn btn-secondary" onclick="window.print()">
            <i class="fas fa-print"></i> Print Report
        </button>
        <a href="<?= BASE_URL ?>/modules/reports/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Reports Hub
        </a>
    </div>
</div>

<!-- Date & Branch Filter Form -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row align-items-center gap-3">
            <div class="col-md-4">
                <label class="form-label small text-muted">Reporting Date:</label>
                <input type="date" name="date" class="form-control" value="<?= e($reportDate) ?>">
            </div>

            <?php if (isAdmin()): ?>
            <div class="col-md-4">
                <label class="form-label small text-muted">Filter Store Branch:</label>
                <select name="branch_id" class="form-select">
                    <option value="all">All Branches (Consolidated)</option>
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= $br['id'] ?>" <?= $branchId === (int)$br['id'] ? 'selected' : '' ?>><?= e($br['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-md-3 d-flex align-items-end gap-2" style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Generate Report</button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-icon bg-success-light text-success"><i class="fas fa-money-bill-wave"></i></div>
        <div class="stat-details">
            <span class="stat-label">Daily Revenue</span>
            <h3 class="stat-value text-success"><?= formatCurrency($totalRevenue) ?></h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-primary-light text-primary"><i class="fas fa-receipt"></i></div>
        <div class="stat-details">
            <span class="stat-label">Invoices Completed</span>
            <h3 class="stat-value"><?= number_format($totalTxns) ?></h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-info-light text-info"><i class="fas fa-chart-pie"></i></div>
        <div class="stat-details">
            <span class="stat-label">Average Order Size</span>
            <h3 class="stat-value"><?= formatCurrency($avgTicket) ?></h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-warning-light text-warning"><i class="fas fa-tags"></i></div>
        <div class="stat-details">
            <span class="stat-label">Discounts Conceded</span>
            <h3 class="stat-value text-warning"><?= formatCurrency($totalDiscount) ?></h3>
        </div>
    </div>
</div>

<!-- Hourly Bar Chart -->
<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="card-title m-0"><i class="fas fa-chart-bar text-primary"></i> Hourly Sales Distribution (8:00 AM – 11:00 PM)</h5></div>
    <div class="card-body">
        <div style="height: 250px;">
            <canvas id="hourlySalesChart"></canvas>
        </div>
    </div>
</div>

<!-- Transactions Table -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title m-0">Invoices for <?= formatDate($reportDate) ?></h5>
        <span class="badge badge-light"><?= count($transactions) ?> sales</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Time</th>
                        <th>Invoice #</th>
                        <th>Branch</th>
                        <th>Cashier</th>
                        <th>Items</th>
                        <th>Subtotal</th>
                        <th>Discount</th>
                        <th>Net Total</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No sales recorded on this date.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td><?= date('h:i A', strtotime($t['created_at'])) ?></td>
                                <td><strong><a href="<?= BASE_URL ?>/modules/sales/view.php?id=<?= $t['id'] ?>"><?= e($t['invoice_number']) ?></a></strong></td>
                                <td><?= e($t['branch_name']) ?></td>
                                <td><?= e($t['cashier_name'] ?? 'Staff') ?></td>
                                <td><?= $t['item_count'] ?> items</td>
                                <td><?= formatCurrency((float)$t['subtotal']) ?></td>
                                <td><?= (float)$t['discount'] > 0 ? formatCurrency((float)$t['discount']) : '—' ?></td>
                                <td><strong><?= formatCurrency((float)$t['total_amount']) ?></strong></td>
                                <td><span class="badge badge-outline-secondary"><?= strtoupper(e($t['payment_method'])) ?></span></td>
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
    const ctx = document.getElementById('hourlySalesChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($hourLabels) ?>,
                datasets: [{
                    label: 'Hourly Sales (PKR)',
                    data: <?= json_encode($hourData) ?>,
                    backgroundColor: '#2563eb',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(v) { return 'PKR ' + v.toLocaleString(); }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

