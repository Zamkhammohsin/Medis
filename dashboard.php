<?php
/**
 * Medis - Dashboard
 */
$pageTitle = 'Dashboard';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();
$db = getDB();

// Determine active branch filter
$selectedBranchId = null;
if (isAdmin()) {
    if (isset($_GET['branch_id']) && $_GET['branch_id'] !== '' && $_GET['branch_id'] !== 'all') {
        $selectedBranchId = (int)$_GET['branch_id'];
    }
} elseif (isManager() || isCashier()) {
    $selectedBranchId = $currentUser['branch_id'];
}

// Fetch branches for admin filter dropdown
$branches = getAllBranches();

// Dashboard Statistics (Dynamic from MySQL)
$todaySales      = getTodaySales($selectedBranchId);
$monthSales      = getMonthSales($selectedBranchId);
$todaySalesCount = getTodaySalesCount($selectedBranchId);
$medicineCount   = getMedicineCount();
$lowStockCount   = getLowStockCount($selectedBranchId);
$expiringCount   = getExpiringCount(90, $selectedBranchId);
$expiredCount    = getExpiredCount($selectedBranchId);
$supplierCount   = getSupplierCount();
$branchCount     = getBranchCount();

// Charts Data
$salesChart = getSalesChartData($selectedBranchId);

// Branch-wise sales comparison data (for bar chart)
$branchSalesLabels = [];
$branchSalesData   = [];
try {
    $stmt = $db->query("
        SELECT b.name, COALESCE(SUM(s.total_amount), 0) AS total
        FROM branches b
        LEFT JOIN sales s ON s.branch_id = b.id AND s.status = 'completed'
        WHERE b.status = 'active'
        GROUP BY b.id, b.name
        ORDER BY b.name ASC
    ");
    while ($row = $stmt->fetch()) {
        $branchSalesLabels[] = $row['name'];
        $branchSalesData[]   = (float)$row['total'];
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
}

// Recent Transactions
$recentSales = getRecentSales(8, $selectedBranchId);

// Low Stock Items (top 5 for dashboard)
$lowStockItems = array_slice(getLowStockMedicines($selectedBranchId), 0, 5);

// Expiring Items (top 5 for dashboard)
$expiringItems = array_slice(getExpiringMedicines(90, $selectedBranchId), 0, 5);

require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-header-bar mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h2 class="page-title"><i class="fas fa-tachometer-alt text-primary"></i> Dashboard Overview</h2>
        <p class="text-muted">Welcome back, <strong><?= e($currentUser['name'] ?? 'User') ?></strong>! Here is the latest pharmacy operational summary.</p>
    </div>

    <?php if (isAdmin()): ?>
    <div class="branch-filter-box d-flex align-items-center gap-2">
        <label for="branchFilter" class="form-label mb-0"><i class="fas fa-filter text-muted"></i> Branch:</label>
        <form method="GET" action="" id="branchFilterForm" class="m-0">
            <select name="branch_id" id="branchFilter" class="form-select form-select-sm" onchange="document.getElementById('branchFilterForm').submit()">
                <option value="all" <?= $selectedBranchId === null ? 'selected' : '' ?>>All Branches (Consolidated)</option>
                <?php foreach ($branches as $br): ?>
                    <option value="<?= $br['id'] ?>" <?= $selectedBranchId === (int)$br['id'] ? 'selected' : '' ?>>
                        <?= e($br['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php elseif ($currentUser['branch_id']): ?>
    <div class="branch-badge-box">
        <span class="badge badge-info py-2 px-3">
            <i class="fas fa-store"></i> Branch: <?= e($_SESSION['user_branch_name'] ?? 'Assigned Branch') ?>
        </span>
    </div>
    <?php endif; ?>
</div>

<!-- Stats Grid -->
<div class="stats-grid mb-4">
    <!-- Today's Sales -->
    <div class="stat-card">
        <div class="stat-icon bg-success-light text-success">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-details">
            <span class="stat-label">Today's Sales</span>
            <h3 class="stat-value"><?= formatCurrency($todaySales) ?></h3>
            <span class="stat-subtext text-muted"><?= $todaySalesCount ?> transactions completed</span>
        </div>
    </div>

    <!-- Monthly Sales -->
    <div class="stat-card">
        <div class="stat-icon bg-primary-light text-primary">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-details">
            <span class="stat-label">This Month's Sales</span>
            <h3 class="stat-value"><?= formatCurrency($monthSales) ?></h3>
            <span class="stat-subtext text-success"><i class="fas fa-calendar-check"></i> Current billing cycle</span>
        </div>
    </div>

    <!-- Total Medicines -->
    <div class="stat-card">
        <div class="stat-icon bg-info-light text-info">
            <i class="fas fa-pills"></i>
        </div>
        <div class="stat-details">
            <span class="stat-label">Total Medicines</span>
            <h3 class="stat-value"><?= number_format($medicineCount) ?></h3>
            <span class="stat-subtext"><a href="<?= BASE_URL ?>/modules/medicines/index.php" class="text-info">View Catalog &rarr;</a></span>
        </div>
    </div>

    <!-- Low Stock -->
    <div class="stat-card">
        <div class="stat-icon <?= $lowStockCount > 0 ? 'bg-warning-light text-warning' : 'bg-secondary-light text-secondary' ?>">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="stat-details">
            <span class="stat-label">Low Stock Alerts</span>
            <h3 class="stat-value text-warning"><?= number_format($lowStockCount) ?></h3>
            <span class="stat-subtext"><a href="<?= BASE_URL ?>/modules/inventory/index.php?filter=low_stock" class="text-warning">Needs Restocking &rarr;</a></span>
        </div>
    </div>

    <!-- Expiring Soon -->
    <div class="stat-card">
        <div class="stat-icon <?= $expiringCount > 0 ? 'bg-danger-light text-danger' : 'bg-secondary-light text-secondary' ?>">
            <i class="fas fa-calendar-times"></i>
        </div>
        <div class="stat-details">
            <span class="stat-label">Expiring (&le; 90 Days)</span>
            <h3 class="stat-value text-danger"><?= number_format($expiringCount) ?></h3>
            <span class="stat-subtext"><?= $expiredCount ?> already expired</span>
        </div>
    </div>

    <!-- Total Suppliers -->
    <div class="stat-card">
        <div class="stat-icon bg-purple-light text-purple">
            <i class="fas fa-truck-loading"></i>
        </div>
        <div class="stat-details">
            <span class="stat-label">Active Suppliers</span>
            <h3 class="stat-value"><?= number_format($supplierCount) ?></h3>
            <span class="stat-subtext"><a href="<?= BASE_URL ?>/modules/suppliers/index.php" class="text-purple">Manage Vendors &rarr;</a></span>
        </div>
    </div>

    <!-- Branches -->
    <div class="stat-card">
        <div class="stat-icon bg-navy-light text-navy">
            <i class="fas fa-store-alt"></i>
        </div>
        <div class="stat-details">
            <span class="stat-label">Active Branches</span>
            <h3 class="stat-value"><?= number_format($branchCount) ?></h3>
            <span class="stat-subtext"><?= isAdmin() ? '<a href="' . BASE_URL . '/modules/branches/index.php">Manage Branches &rarr;</a>' : 'Network Stores' ?></span>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row mb-4">
    <div class="col-lg-7 mb-4">
        <div class="card h-100 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title m-0"><i class="fas fa-chart-area text-primary"></i> Last 7 Days Sales Trend</h5>
                <span class="badge badge-light">PKR</span>
            </div>
            <div class="card-body">
                <canvas id="salesTrendsChart" height="150"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-5 mb-4">
        <div class="card h-100 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title m-0"><i class="fas fa-chart-bar text-success"></i> Branch Sales Distribution</h5>
                <span class="badge badge-light">All-Time</span>
            </div>
            <div class="card-body">
                <canvas id="branchComparisonChart" height="150"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Section: Recent Sales & Alerts -->
<div class="row">
    <!-- Recent Transactions -->
    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title m-0"><i class="fas fa-receipt text-info"></i> Recent Sales Transactions</h5>
                <a href="<?= BASE_URL ?>/modules/sales/index.php" class="btn btn-sm btn-outline-primary">View All Sales</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice</th>
                                <th>Date & Time</th>
                                <th>Branch</th>
                                <th>Cashier</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentSales)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No sales transactions recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentSales as $sale): ?>
                                    <tr>
                                        <td><strong><?= e($sale['invoice_number']) ?></strong></td>
                                        <td><?= formatDateTime($sale['created_at']) ?></td>
                                        <td><span class="badge badge-light"><?= e($sale['branch_name'] ?? '—') ?></span></td>
                                        <td><?= e($sale['cashier'] ?? 'System') ?></td>
                                        <td><strong><?= formatCurrency((float)$sale['total_amount']) ?></strong></td>
                                        <td><span class="badge badge-outline-secondary"><?= ucfirst(e($sale['payment_method'])) ?></span></td>
                                        <td>
                                            <a href="<?= BASE_URL ?>/modules/sales/view.php?invoice=<?= urlencode($sale['invoice_number']) ?>" class="btn btn-xs btn-outline-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?= BASE_URL ?>/modules/pos/receipt.php?invoice=<?= urlencode($sale['invoice_number']) ?>" target="_blank" class="btn btn-xs btn-outline-secondary" title="Print Receipt">
                                                <i class="fas fa-print"></i>
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
    </div>

    <!-- Stock & Expiry Alerts -->
    <div class="col-lg-5 mb-4">
        <!-- Low Stock Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title m-0 text-warning"><i class="fas fa-exclamation-triangle"></i> Low Stock Alerts</h5>
                <a href="<?= BASE_URL ?>/modules/inventory/index.php?filter=low_stock" class="btn btn-xs btn-outline-warning">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($lowStockItems)): ?>
                    <div class="p-3 text-center text-muted"><i class="fas fa-check-circle text-success"></i> All stocks are above minimum threshold.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($lowStockItems as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                <div>
                                    <strong><?= e($item['name']) ?></strong>
                                    <div class="text-muted small"><?= e($item['category'] ?? 'General') ?> &bull; Min: <?= $item['min_stock_level'] ?> units</div>
                                </div>
                                <span class="badge badge-danger"><?= $item['total_stock'] ?> in stock</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Expiry Alerts Card -->
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title m-0 text-danger"><i class="fas fa-calendar-times"></i> Near Expiry Batches</h5>
                <a href="<?= BASE_URL ?>/modules/inventory/index.php?filter=expiring" class="btn btn-xs btn-outline-danger">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($expiringItems)): ?>
                    <div class="p-3 text-center text-muted"><i class="fas fa-check-circle text-success"></i> No batches expiring within 90 days.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($expiringItems as $exp): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                <div>
                                    <strong><?= e($exp['name']) ?></strong>
                                    <div class="text-muted small">Batch #<?= e($exp['batch_number']) ?> &bull; <?= e($exp['branch_name']) ?></div>
                                </div>
                                <div class="text-end">
                                    <span class="badge <?= $exp['days_left'] < 30 ? 'badge-danger' : 'badge-warning' ?>">
                                        <?= $exp['days_left'] ?> days left
                                    </span>
                                    <div class="small text-muted"><?= formatDate($exp['expiry_date']) ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Sales Trend Chart (Line Chart)
    const trendCtx = document.getElementById('salesTrendsChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($salesChart['labels']) ?>,
                datasets: [{
                    label: 'Daily Sales (PKR)',
                    data: <?= json_encode($salesChart['data']) ?>,
                    borderColor: '#1e3a5f',
                    backgroundColor: 'rgba(30, 58, 95, 0.08)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#0d6efd',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return 'Sales: PKR ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { return 'PKR ' + value.toLocaleString(); }
                        },
                        grid: { color: '#f0f2f5' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // Branch Comparison Chart (Bar Chart)
    const branchCtx = document.getElementById('branchComparisonChart');
    if (branchCtx) {
        new Chart(branchCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($branchSalesLabels) ?>,
                datasets: [{
                    label: 'Total Revenue (PKR)',
                    data: <?= json_encode($branchSalesData) ?>,
                    backgroundColor: [
                        '#1e3a5f',
                        '#2d6a4f',
                        '#0d6efd',
                        '#e76f51',
                        '#6f42c1'
                    ],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return 'Revenue: PKR ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { return 'PKR ' + value.toLocaleString(); }
                        },
                        grid: { color: '#f0f2f5' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

