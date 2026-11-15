<?php
/**
 * Medis - Reports Hub
 */
$pageTitle = 'Reports & Analytics';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

$currentUser = getCurrentUser();
$branchId = $currentUser['branch_id'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-chart-bar text-primary"></i> Pharmacy Reports & Business Intelligence</h2>
        <p class="page-subtitle">Interactive visual analytics, revenue tracking, inventory audit reports, and procurement insights.</p>
    </div>
</div>

<div class="row">
    <!-- 1. Daily Sales Report -->
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm hover-shadow">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="stat-icon bg-primary-light text-primary"><i class="fas fa-calendar-day"></i></div>
                    <div>
                        <h5 class="mb-0">Daily Sales Report</h5>
                        <small class="text-muted">Hourly revenue & transactions</small>
                    </div>
                </div>
                <p class="text-muted small flex-grow-1">Detailed intraday sales audit, hourly volume breakdown, payment method distribution, and cashier performance.</p>
                <a href="<?= BASE_URL ?>/modules/reports/daily_sales.php" class="btn btn-outline-primary btn-sm mt-3">
                    Launch Report &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- 2. Monthly Sales Report -->
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm hover-shadow">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="stat-icon bg-success-light text-success"><i class="fas fa-calendar-alt"></i></div>
                    <div>
                        <h5 class="mb-0">Monthly Sales Report</h5>
                        <small class="text-muted">Day-by-day revenue trends</small>
                    </div>
                </div>
                <p class="text-muted small flex-grow-1">Month-over-month financial progress, daily trend line charts, average transaction value, and discounts summary.</p>
                <a href="<?= BASE_URL ?>/modules/reports/monthly_sales.php" class="btn btn-outline-success btn-sm mt-3">
                    Launch Report &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- 3. Branch Sales Comparison -->
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm hover-shadow">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="stat-icon bg-purple-light text-purple"><i class="fas fa-store"></i></div>
                    <div>
                        <h5 class="mb-0">Branch Sales Comparison</h5>
                        <small class="text-muted">Multi-branch benchmarking</small>
                    </div>
                </div>
                <p class="text-muted small flex-grow-1">Comparative revenue performance across all regional store branches with market share donut charts and volume comparisons.</p>
                <a href="<?= BASE_URL ?>/modules/reports/branch_sales.php" class="btn btn-outline-primary btn-sm mt-3">
                    Launch Report &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- 4. Top Selling Medicines -->
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm hover-shadow">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="stat-icon bg-info-light text-info"><i class="fas fa-trophy"></i></div>
                    <div>
                        <h5 class="mb-0">Top-Selling Medicines</h5>
                        <small class="text-muted">Best performers & formulas</small>
                    </div>
                </div>
                <p class="text-muted small flex-grow-1">Rankings of highest turnover medications by quantity and total gross revenue generated, helping forecast reorders.</p>
                <a href="<?= BASE_URL ?>/modules/reports/top_medicines.php" class="btn btn-outline-info btn-sm mt-3">
                    Launch Report &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- 5. Low Stock Report -->
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm hover-shadow">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="stat-icon bg-warning-light text-warning"><i class="fas fa-exclamation-triangle"></i></div>
                    <div>
                        <h5 class="mb-0">Low Stock Report</h5>
                        <small class="text-muted">Shortage alerts & safety levels</small>
                    </div>
                </div>
                <p class="text-muted small flex-grow-1">Stock shortage deficit reports showing items below minimum threshold with recommended reorder quantities.</p>
                <a href="<?= BASE_URL ?>/modules/reports/low_stock.php" class="btn btn-outline-warning btn-sm mt-3">
                    Launch Report &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- 6. Expiry Report -->
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm hover-shadow">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="stat-icon bg-danger-light text-danger"><i class="fas fa-hourglass-end"></i></div>
                    <div>
                        <h5 class="mb-0">Batch Expiry Report</h5>
                        <small class="text-muted">Shelf-life & write-off audit</small>
                    </div>
                </div>
                <p class="text-muted small flex-grow-1">Auditing tool for batches expired or expiring in 7, 30, and 90 days. Identifies items requiring urgent return or disposal.</p>
                <a href="<?= BASE_URL ?>/modules/reports/expiry_report.php" class="btn btn-outline-danger btn-sm mt-3">
                    Launch Report &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- 7. Purchase Report -->
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm hover-shadow">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="stat-icon bg-navy-light text-navy"><i class="fas fa-truck-loading"></i></div>
                    <div>
                        <h5 class="mb-0">Procurement Report</h5>
                        <small class="text-muted">Vendor spending & invoices</small>
                    </div>
                </div>
                <p class="text-muted small flex-grow-1">Analyze distributor purchase volumes, supplier order history, pending deliveries, and overall capital expenditure.</p>
                <a href="<?= BASE_URL ?>/modules/reports/purchase_report.php" class="btn btn-outline-secondary btn-sm mt-3">
                    Launch Report &rarr;
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

