<?php
/**
 * Medis - Branch-wise Sales Comparison Report
 */
$pageTitle = 'Branch Sales Comparison';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

$db = getDB();

$fromDate = trim($_GET['from_date'] ?? date('Y-m-01')); // start of month default
$toDate   = trim($_GET['to_date'] ?? date('Y-m-d'));

$sql = "
    SELECT b.id, b.name, b.address, u.name AS manager_name,
           COUNT(s.id) AS tx_count,
           COALESCE(SUM(s.subtotal), 0) AS subtotal,
           COALESCE(SUM(s.discount), 0) AS discount,
           COALESCE(SUM(s.total_amount), 0) AS net_revenue
    FROM branches b
    LEFT JOIN users u ON u.id = b.manager_id
    LEFT JOIN sales s ON s.branch_id = b.id
                     AND s.status = 'completed'
                     AND DATE(s.created_at) >= ?
                     AND DATE(s.created_at) <= ?
    WHERE b.status = 'active'
    GROUP BY b.id, b.name, b.address, u.name
    ORDER BY net_revenue DESC
";
$stmt = $db->prepare($sql);
$stmt->execute([$fromDate, $toDate]);
$rows = $stmt->fetchAll();

$totalAllBranches = 0.0;
$totalTxAll       = 0;
foreach ($rows as $r) {
    $totalAllBranches += (float)$r['net_revenue'];
    $totalTxAll       += (int)$r['tx_count'];
}

$chartLabels = [];
$chartData   = [];
$colors = ['#1e3a5f', '#2d6a4f', '#0d6efd', '#e76f51', '#6f42c1', '#f4a261'];

foreach ($rows as $idx => $r) {
    $chartLabels[] = $r['name'];
    $chartData[]   = (float)$r['net_revenue'];
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-store text-primary"></i> Multi-Branch Performance Comparison</h2>
        <p class="page-subtitle">Cross-store revenue comparison from <strong><?= formatDate($fromDate) ?></strong> to <strong><?= formatDate($toDate) ?></strong></p>
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

<!-- Date Filter -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row align-items-center gap-3">
            <div class="col-md-4">
                <label class="form-label small text-muted">From Date:</label>
                <input type="date" name="from_date" class="form-control" value="<?= e($fromDate) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted">To Date:</label>
                <input type="date" name="to_date" class="form-control" value="<?= e($toDate) ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end" style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Apply Date Range</button>
            </div>
        </form>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <!-- Bar Chart -->
    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h5 class="card-title m-0"><i class="fas fa-chart-bar text-primary"></i> Branch Revenue Benchmarking (PKR)</h5></div>
            <div class="card-body">
                <div style="height: 260px;">
                    <canvas id="branchBarChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Doughnut Chart -->
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h5 class="card-title m-0"><i class="fas fa-chart-pie text-success"></i> Market Share Distribution</h5></div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <div style="height: 240px; width: 100%;">
                    <canvas id="branchDoughnutChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Comparison Table -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title m-0">Store Breakdown</h5>
        <span class="badge badge-light">Consolidated Total: <?= formatCurrency($totalAllBranches) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Branch</th>
                        <th>Location</th>
                        <th>Manager</th>
                        <th class="text-center">Invoices</th>
                        <th class="text-end">Gross Sales</th>
                        <th class="text-end">Discounts</th>
                        <th class="text-end">Net Revenue</th>
                        <th class="text-end">Share %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $net = (float)$r['net_revenue'];
                        $share = $totalAllBranches > 0 ? (($net / $totalAllBranches) * 100) : 0;
                        ?>
                        <tr>
                            <td><strong><?= e($r['name']) ?></strong></td>
                            <td><?= e($r['address'] ?? '—') ?></td>
                            <td><?= e($r['manager_name'] ?? '—') ?></td>
                            <td class="text-center"><?= $r['tx_count'] ?></td>
                            <td class="text-end"><?= formatCurrency((float)$r['subtotal']) ?></td>
                            <td class="text-end"><?= (float)$r['discount'] > 0 ? formatCurrency((float)$r['discount']) : '—' ?></td>
                            <td class="text-end fw-bold text-primary"><?= formatCurrency($net) ?></td>
                            <td class="text-end">
                                <span class="badge badge-light fw-bold"><?= number_format($share, 1) ?>%</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="3">Consolidated Totals:</th>
                        <th class="text-center"><?= $totalTxAll ?></th>
                        <th colspan="2"></th>
                        <th class="text-end text-primary fs-5"><?= formatCurrency($totalAllBranches) ?></th>
                        <th class="text-end">100.0%</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const labels = <?= json_encode($chartLabels) ?>;
    const dataVals = <?= json_encode($chartData) ?>;
    const colors = <?= json_encode($colors) ?>;

    // Bar Chart
    const barCtx = document.getElementById('branchBarChart');
    if (barCtx) {
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Net Revenue (PKR)',
                    data: dataVals,
                    backgroundColor: colors,
                    borderRadius: 6
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

    // Doughnut Chart
    const donutCtx = document.getElementById('branchDoughnutChart');
    if (donutCtx) {
        new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: dataVals,
                    backgroundColor: colors,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

