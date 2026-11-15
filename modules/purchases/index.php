<?php
/**
 * Medis - Purchases Management
 */
$pageTitle = 'Purchases';
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

$branches  = getAllBranches();
$suppliers = $db->query("SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$search     = trim($_GET['search'] ?? '');
$status     = trim($_GET['status'] ?? 'all');
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$fromDate   = trim($_GET['from_date'] ?? '');
$toDate     = trim($_GET['to_date'] ?? '');

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;

$where = ["1=1"];
$params = [];

if ($branchId) {
    $where[] = "p.branch_id = ?";
    $params[] = $branchId;
}

if ($supplierId) {
    $where[] = "p.supplier_id = ?";
    $params[] = $supplierId;
}

if ($status !== 'all' && in_array($status, ['pending', 'received', 'cancelled'])) {
    $where[] = "p.status = ?";
    $params[] = $status;
}

if ($search !== '') {
    $where[] = "(p.invoice_number LIKE ? OR s.name LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
}

if ($fromDate !== '') {
    $where[] = "DATE(p.created_at) >= ?";
    $params[] = $fromDate;
}

if ($toDate !== '') {
    $where[] = "DATE(p.created_at) <= ?";
    $params[] = $toDate;
}

$whereSql = implode(' AND ', $where);

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM purchases p LEFT JOIN suppliers s ON s.id = p.supplier_id WHERE $whereSql");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages   = max(1, ceil($totalRecords / $limit));

// Fetch Records
$sql = "
    SELECT p.*, s.name AS supplier_name, br.name AS branch_name, u.name AS user_name,
           (SELECT COUNT(*) FROM purchase_items WHERE purchase_id = p.id) AS item_count
    FROM purchases p
    LEFT JOIN suppliers s ON s.id = p.supplier_id
    JOIN branches br ON br.id = p.branch_id
    LEFT JOIN users u ON u.id = p.user_id
    WHERE $whereSql
    ORDER BY p.created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$purchases = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-shopping-cart text-primary"></i> Purchase Orders & Stock Inflow</h2>
        <p class="page-subtitle">Procure medicine inventory from distributors and receive stock into store batches.</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/modules/purchases/add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create Purchase Order
        </a>
    </div>
</div>

<!-- Filters Toolbar -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row align-items-center gap-2">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Invoice # or Vendor..." value="<?= e($search) ?>">
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
                <select name="supplier_id" class="form-select form-select-sm">
                    <option value="">All Suppliers</option>
                    <?php foreach ($suppliers as $sup): ?>
                        <option value="<?= $sup['id'] ?>" <?= $supplierId === (int)$sup['id'] ? 'selected' : '' ?>><?= e($sup['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending (Draft)</option>
                    <option value="received" <?= $status === 'received' ? 'selected' : '' ?>>Received (In Stock)</option>
                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="fas fa-filter"></i> Filter</button>
                <a href="<?= BASE_URL ?>/modules/purchases/index.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Purchases Table -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title m-0">Purchase Orders (<?= $totalRecords ?> found)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 data-table">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Supplier / Vendor</th>
                        <th>Destination Branch</th>
                        <th>Items</th>
                        <th>Total Amount</th>
                        <th>Created By</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchases)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No purchase orders found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($purchases as $p): ?>
                            <tr>
                                <td><strong><code><?= e($p['invoice_number']) ?></code></strong></td>
                                <td><?= formatDate($p['created_at']) ?></td>
                                <td><strong><?= e($p['supplier_name'] ?? 'Direct Manufacturer') ?></strong></td>
                                <td><span class="badge badge-light"><?= e($p['branch_name']) ?></span></td>
                                <td><?= $p['item_count'] ?> products</td>
                                <td><strong><?= formatCurrency((float)$p['total_amount']) ?></strong></td>
                                <td><small class="text-muted"><?= e($p['user_name'] ?? 'Staff') ?></small></td>
                                <td>
                                    <?php if ($p['status'] === 'received'): ?>
                                        <span class="badge badge-success"><i class="fas fa-check"></i> Received</span>
                                    <?php elseif ($p['status'] === 'pending'): ?>
                                        <span class="badge badge-warning"><i class="fas fa-clock"></i> Pending Delivery</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Cancelled</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group gap-1">
                                        <a href="<?= BASE_URL ?>/modules/purchases/view.php?id=<?= $p['id'] ?>" class="btn btn-xs btn-outline-info" title="View details">
                                            <i class="fas fa-eye"></i> View
                                        </a>

                                        <?php if ($p['status'] === 'pending'): ?>
                                            <form method="POST" action="<?= BASE_URL ?>/modules/purchases/receive.php" style="display:inline;" onsubmit="return confirm('Mark this purchase as RECEIVED? Stock will be added to branch inventory.')">
                                                <input type="hidden" name="purchase_id" value="<?= $p['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                                                <button type="submit" class="btn btn-xs btn-success" title="Receive into stock">
                                                    <i class="fas fa-box-open"></i> Receive
                                                </button>
                                            </form>
                                        <?php endif; ?>
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
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&branch_id=<?= $branchId ?>&supplier_id=<?= $supplierId ?>&status=<?= $status ?>" class="btn btn-xs btn-outline-secondary">&laquo; Prev</a>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php if ($p == 1 || $p == $totalPages || abs($p - $page) <= 2): ?>
                    <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&branch_id=<?= $branchId ?>&supplier_id=<?= $supplierId ?>&status=<?= $status ?>" class="btn btn-xs <?= $p === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&branch_id=<?= $branchId ?>&supplier_id=<?= $supplierId ?>&status=<?= $status ?>" class="btn btn-xs btn-outline-secondary">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

