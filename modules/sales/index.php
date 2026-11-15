<?php
/**
 * Medis - Sales History
 */
$pageTitle = 'Sales History';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();
$db = getDB();

// Determine scoping
$branchId = null;
$cashierId = null;

if (isAdmin()) {
    if (isset($_GET['branch_id']) && $_GET['branch_id'] !== '' && $_GET['branch_id'] !== 'all') {
        $branchId = (int)$_GET['branch_id'];
    }
} elseif (isManager()) {
    $branchId = (int)$currentUser['branch_id'];
} else {
    // Cashier sees only their own sales
    $branchId = (int)$currentUser['branch_id'];
    $cashierId = (int)$currentUser['id'];
}

$branches = getAllBranches();

// Filters
$search   = trim($_GET['search'] ?? '');
$fromDate = trim($_GET['from_date'] ?? '');
$toDate   = trim($_GET['to_date'] ?? '');
$payMode  = trim($_GET['payment_method'] ?? '');

// Pagination
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;

$where = ["1=1"];
$params = [];

if ($branchId) {
    $where[] = "s.branch_id = ?";
    $params[] = $branchId;
}

if ($cashierId) {
    $where[] = "s.user_id = ?";
    $params[] = $cashierId;
}

if ($search !== '') {
    $where[] = "(s.invoice_number LIKE ? OR u.name LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
}

if ($payMode !== '' && in_array($payMode, ['cash', 'card', 'online'])) {
    $where[] = "s.payment_method = ?";
    $params[] = $payMode;
}

if ($fromDate !== '') {
    $where[] = "DATE(s.created_at) >= ?";
    $params[] = $fromDate;
}

if ($toDate !== '') {
    $where[] = "DATE(s.created_at) <= ?";
    $params[] = $toDate;
}

$whereSql = implode(' AND ', $where);

// Count
$countStmt = $db->prepare("
    SELECT COUNT(*) 
    FROM sales s 
    LEFT JOIN users u ON u.id = s.user_id 
    WHERE $whereSql
");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages   = max(1, ceil($totalRecords / $limit));

// Fetch Records
$sql = "
    SELECT s.*, br.name AS branch_name, u.name AS cashier_name,
           (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) AS item_count
    FROM sales s
    JOIN branches br ON br.id = s.branch_id
    LEFT JOIN users u ON u.id = s.user_id
    WHERE $whereSql
    ORDER BY s.created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Total sales value for current filter
$sumStmt = $db->prepare("SELECT COALESCE(SUM(s.total_amount),0) FROM sales s LEFT JOIN users u ON u.id = s.user_id WHERE $whereSql");
$sumStmt->execute($params);
$filteredTotalRevenue = (float)$sumStmt->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-receipt text-primary"></i> Sales Transactions History</h2>
        <p class="page-subtitle">Search invoices, print receipts, and track branch-wise POS revenues.</p>
    </div>
    <div class="page-actions d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/pos/index.php" class="btn btn-primary">
            <i class="fas fa-cash-register"></i> Open POS Terminal
        </a>
    </div>
</div>

<!-- Revenue Stat Box -->
<div class="card mb-4 shadow-sm bg-primary-light border-0">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3 py-3">
        <div>
            <span class="text-muted small text-uppercase fw-bold">Filtered Revenue</span>
            <h3 class="text-primary mb-0"><?= formatCurrency($filteredTotalRevenue) ?></h3>
        </div>
        <div>
            <span class="badge badge-light fs-6"><?= $totalRecords ?> Total Transactions Found</span>
        </div>
    </div>
</div>

<!-- Filter Toolbar -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row align-items-center gap-2">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Invoice # or Cashier..." value="<?= e($search) ?>">
            </div>

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

            <div class="col-md-2">
                <select name="payment_method" class="form-select form-select-sm">
                    <option value="">All Payment Modes</option>
                    <option value="cash" <?= $payMode === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="card" <?= $payMode === 'card' ? 'selected' : '' ?>>Card</option>
                    <option value="online" <?= $payMode === 'online' ? 'selected' : '' ?>>Online / UPI</option>
                </select>
            </div>

            <div class="col-md-2">
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= e($fromDate) ?>" title="From Date">
            </div>

            <div class="col-md-2">
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= e($toDate) ?>" title="To Date">
            </div>

            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="fas fa-filter"></i></button>
                <a href="<?= BASE_URL ?>/modules/sales/index.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Sales Data Table -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title m-0">Invoices</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 data-table">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Date & Time</th>
                        <th>Branch</th>
                        <th>Cashier</th>
                        <th>Items Count</th>
                        <th>Subtotal</th>
                        <th>Discount</th>
                        <th>Total Amount</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales)): ?>
                        <tr><td colspan="11" class="text-center py-4 text-muted">No sales invoices found matching the criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td><strong><code><?= e($sale['invoice_number']) ?></code></strong></td>
                                <td><?= formatDateTime($sale['created_at']) ?></td>
                                <td><span class="badge badge-light"><?= e($sale['branch_name']) ?></span></td>
                                <td><?= e($sale['cashier_name'] ?? 'Staff') ?></td>
                                <td><?= $sale['item_count'] ?> items</td>
                                <td><?= formatCurrency((float)$sale['subtotal']) ?></td>
                                <td>
                                    <?php if ((float)$sale['discount'] > 0): ?>
                                        <span class="text-success">-<?= formatCurrency((float)$sale['discount']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= formatCurrency((float)$sale['total_amount']) ?></strong></td>
                                <td>
                                    <span class="badge badge-outline-secondary">
                                        <i class="fas fa-<?= $sale['payment_method'] === 'cash' ? 'money-bill' : ($sale['payment_method'] === 'card' ? 'credit-card' : 'mobile') ?>"></i>
                                        <?= ucfirst(e($sale['payment_method'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-success"><?= ucfirst(e($sale['status'])) ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group gap-1">
                                        <a href="<?= BASE_URL ?>/modules/sales/view.php?id=<?= $sale['id'] ?>" class="btn btn-xs btn-outline-info" title="View Sale Details">
                                            <i class="fas fa-eye"></i> Details
                                        </a>
                                        <a href="<?= BASE_URL ?>/modules/pos/receipt.php?id=<?= $sale['id'] ?>" target="_blank" class="btn btn-xs btn-outline-secondary" title="Print Receipt">
                                            <i class="fas fa-print"></i> Receipt
                                        </a>
                                    </div>
                                </td>
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
        <span class="text-muted">Showing page <?= $page ?> of <?= $totalPages ?></span>
        <div class="pagination d-flex gap-1">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&branch_id=<?= $branchId ?>&payment_method=<?= $payMode ?>&from_date=<?= $fromDate ?>&to_date=<?= $toDate ?>" class="btn btn-xs btn-outline-secondary">&laquo; Prev</a>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php if ($p == 1 || $p == $totalPages || abs($p - $page) <= 2): ?>
                    <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&branch_id=<?= $branchId ?>&payment_method=<?= $payMode ?>&from_date=<?= $fromDate ?>&to_date=<?= $toDate ?>" class="btn btn-xs <?= $p === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&branch_id=<?= $branchId ?>&payment_method=<?= $payMode ?>&from_date=<?= $fromDate ?>&to_date=<?= $toDate ?>" class="btn btn-xs btn-outline-secondary">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

