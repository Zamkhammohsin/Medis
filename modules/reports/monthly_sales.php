<?php
/**
 * Medis - Monthly Sales Report
 */
$pageTitle = 'Monthly Sales Report';
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

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd   = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

$where = ["s.created_at >= ?", "s.created_at <= ?", "s.status = 'completed'"];
$params = [$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59'];

if ($branchId) {
    $where[] = "s.branch_id = ?";
    $params[] = $branchId;
}

$whereSql = implode(' AND ', $where);

// Daily aggregation query
$sql = "
    SELECT DATE(s.created_at) AS sale_date,
           COUNT(*) AS tx_count,
           SUM(s.subtotal) AS gross,
           SUM(s.discount) AS disc,
           SUM(s.total_amount) AS net
    FROM sales s
    WHERE $whereSql
    GROUP BY DATE(s.created_at)
    ORDER BY sale_date ASC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Map by date
$mapped = [];
$totalGross = 0.0;
$totalDisc  = 0.0;
$totalNet   = 0.0;
$totalTx    = 0;
$bestDay    = ['date' => '—', 'amount' => 0.0];

foreach ($rows as $r) {
    $d = $r['sale_date'];
    $mapped[$d] = $r;
    $totalGross += (float)$r['gross'];
    $totalDisc  += (float)$r['disc'];
    $totalNet   += (float)$r['net'];
    $totalTx    += (int)$r['tx_count'];

    if ((float)$r['net'] > $bestDay['amount']) {
        $bestDay = ['date' => $d, 'amount' => (float)$r['net']];
    }
}

// Prepare arrays for Chart.js
$labels = [];
$dataPoints = [];
for ($day = 1; $day <= $daysInMonth; $day++) {
    $curDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $labels[] = sprintf('%02d %s', $day, date('M', strtotime($curDate)));
    $dataPoints[] = isset($mapped[$curDate]) ? (float)$mapped[$curDate]['net'] : 0.0;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-calendar-alt text-primary"></i> Monthly Sales Analytics</h2>
        <p class="page-subtitle">Monthly billing trends and day-by-day revenue breakdown for <strong><?= date('F Y', strtotime($monthStart)) ?></strong></p>
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

<!-- Selector Form -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row align-items-center gap-3">
            <div class="col-md-3">
                <label class="form-label small text-muted">Select Month:</label>
                <select name="month" class="form-select">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $month === $m ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small text-muted">Year:</label>
                <select name="year" class="form-select">
                    <?php for ($y = 2024; $y <= 2028; $y++): ?>
                        <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <?php if (isAdmin()): ?>
            <div class="col-md-3">
                <label class="form-label small text-muted">Branch Store:</label>
                <select name="branch_id" class="form-select">
                    <option value="all">All Branches</option>
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= $br['id'] ?>" <?= $branchId === (int)$br['id'] ? 'selected' : '' ?>><?= e($br['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-md-3 d-flex align-items-end" style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Update Report</button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Metrics -->
<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-icon bg-success-light text-success"><i class="fas fa-money-bill-wave"></i></div>
        <div class="stat-details">
            <span class="stat-label">Net Monthly Revenue</span>
            <h3 class="stat-value text-success"><?= formatCurrency($totalNet) ?></h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-primary-light text-primary"><i class="fas fa-receipt"></i></div>
        <div class="stat-details">
            <span class="stat-label">Total Invoices</span>
            <h3 class="stat-value"><?= number_format($totalTx) ?></h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-purple-light text-purple"><i class="fas fa-crown"></i></div>
        <div class="stat-details">
            <span class="stat-label">Peak Turnover Day</span>
            <h3 class="stat-value fs-5"><?= $bestDay['date'] !== '—' ? formatDate($bestDay['date']) : '—' ?></h3>
            <span class="stat-subtext text-purple"><?= formatCurrency($bestDay['amount']) ?></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-warning-light text-warning"><i class="fas fa-tags"></i></div>
        <div class="stat-details">
            <span class="stat-label">Total Discounts Given</span>
            <h3 class="stat-value text-warning"><?= formatCurrency($totalDisc) ?></h3>
        </div>
    </div>
</div>

<!-- Monthly Trend Line Chart -->
<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="card-title m-0"><i class="fas fa-chart-line text-primary"></i> Daily Sales Progression (PKR)</h5></div>
    <div class="card-body">
        <div style="height: 280px;">
            <canvas id="monthlyTrendChart"></canvas>
        </div>
    </div>
</div>

<!-- Day-by-Day Table -->
<div class="card shadow-sm">
    <div class="card-header"><h5 class="card-title m-0">Daily Revenue Summary</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Day</th>
                        <th class="text-center">Invoices</th>
                        <th class="text-end">Gross Sales</th>
                        <th class="text-end">Discounts</th>
                        <th class="text-end">Net Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                        <?php
                        $curDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $hasData = isset($mapped[$curDate]);
                        $txCount = $hasData ? $mapped[$curDate]['tx_count'] : 0;
                        $gross   = $hasData ? (float)$mapped[$curDate]['gross'] : 0.0;
                        $disc    = $hasData ? (float)$mapped[$curDate]['disc'] : 0.0;
                        $net     = $hasData ? (float)$mapped[$curDate]['net'] : 0.0;
                        ?>
                        <tr class="<?= $hasData ? '' : 'text-muted' ?>">
                            <td><strong><?= formatDate($curDate) ?></strong></td>
                            <td><?= date('l', strtotime($curDate)) ?></td>
                            <td class="text-center"><?= $txCount ?></td>
                            <td class="text-end"><?= formatCurrency($gross) ?></td>
                            <td class="text-end"><?= $disc > 0 ? formatCurrency($disc) : '—' ?></td>
                            <td class="text-end fw-bold <?= $net > 0 ? 'text-primary' : '' ?>"><?= formatCurrency($net) ?></td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('monthlyTrendChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [{
                    label: 'Net Daily Revenue (PKR)',
                    data: <?= json_encode($dataPoints) ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    fill: true,
                    tension: 0.3,
                    borderWidth: 2.5,
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

