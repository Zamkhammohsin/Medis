<?php
/**
 * Medis - Medicines Management
 */
$pageTitle = 'Medicines Catalog';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

$currentUser = getCurrentUser();
$db = getDB();

// Handle search & filters
$search     = trim($_GET['search'] ?? '');
$categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;
$status     = trim($_GET['status'] ?? 'active');

// Pagination
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Fetch categories for dropdown
$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Build Query
$where = ["1=1"];
$params = [];

if ($status !== 'all') {
    $where[] = "m.status = ?";
    $params[] = $status;
}

if ($categoryId) {
    $where[] = "m.category_id = ?";
    $params[] = $categoryId;
}

if ($search !== '') {
    $where[] = "(m.name LIKE ? OR m.generic_name LIKE ? OR m.barcode LIKE ? OR m.manufacturer LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$whereSql = implode(' AND ', $where);

// Count total
$countStmt = $db->prepare("
    SELECT COUNT(*) 
    FROM medicines m 
    LEFT JOIN categories c ON c.id = m.category_id 
    WHERE $whereSql
");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages   = max(1, ceil($totalRecords / $limit));

// Fetch records with available stock
$branchId = $currentUser['branch_id'];
$branchJoin = $branchId ? "AND b.branch_id = $branchId" : "";

$querySql = "
    SELECT m.*, c.name AS category_name,
           COALESCE(SUM(CASE WHEN b.expiry_date > CURDATE() THEN b.quantity ELSE 0 END), 0) AS total_stock
    FROM medicines m
    LEFT JOIN categories c ON c.id = m.category_id
    LEFT JOIN batches b ON b.medicine_id = m.id $branchJoin
    WHERE $whereSql
    GROUP BY m.id
    ORDER BY m.name ASC
    LIMIT $limit OFFSET $offset
";
$stmt = $db->prepare($querySql);
$stmt->execute($params);
$medicines = $stmt->fetchAll();

// Statistics
$totalMedsActive   = (int)$db->query("SELECT COUNT(*) FROM medicines WHERE status = 'active'")->fetchColumn();
$totalMedsInactive = (int)$db->query("SELECT COUNT(*) FROM medicines WHERE status = 'inactive'")->fetchColumn();
$lowStockTotal     = getLowStockCount($branchId);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-capsules"></i> Medicine Management</h2>
        <p class="page-subtitle">Manage pharmaceutical products, generic formulas, pricing, and stock limits.</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/modules/medicines/add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Medicine
        </a>
    </div>
</div>

<!-- Quick Stats -->
<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-icon bg-primary-light text-primary"><i class="fas fa-pills"></i></div>
        <div class="stat-details">
            <span class="stat-label">Active Medicines</span>
            <h3 class="stat-value"><?= number_format($totalMedsActive) ?></h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-warning-light text-warning"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-details">
            <span class="stat-label">Low Stock Items</span>
            <h3 class="stat-value text-warning"><?= number_format($lowStockTotal) ?></h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-secondary-light text-secondary"><i class="fas fa-archive"></i></div>
        <div class="stat-details">
            <span class="stat-label">Inactive Medicines</span>
            <h3 class="stat-value"><?= number_format($totalMedsInactive) ?></h3>
        </div>
    </div>
</div>

<!-- Filter & Search Toolbar -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row align-items-center gap-3">
            <div class="col-md-4">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, generic, barcode..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="category_id" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filter</button>
                <a href="<?= BASE_URL ?>/modules/medicines/index.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Medicines Data Table -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title m-0">Medicines (<?= $totalRecords ?> found)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 data-table">
                <thead>
                    <tr>
                        <th>Barcode</th>
                        <th>Medicine Name</th>
                        <th>Generic Formula</th>
                        <th>Category</th>
                        <th>Manufacturer</th>
                        <th>Purchase</th>
                        <th>Selling</th>
                        <th>Current Stock</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($medicines)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">No medicines match the selected filter criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($medicines as $m): ?>
                            <tr>
                                <td><code><?= e($m['barcode'] ?? '—') ?></code></td>
                                <td>
                                    <strong><a href="<?= BASE_URL ?>/modules/medicines/view.php?id=<?= $m['id'] ?>"><?= e($m['name']) ?></a></strong>
                                </td>
                                <td><?= e($m['generic_name'] ?? '—') ?></td>
                                <td><span class="badge badge-light"><?= e($m['category_name'] ?? 'Uncategorized') ?></span></td>
                                <td><?= e($m['manufacturer'] ?? '—') ?></td>
                                <td><?= formatCurrency((float)$m['purchase_price']) ?></td>
                                <td><strong><?= formatCurrency((float)$m['selling_price']) ?></strong></td>
                                <td>
                                    <?php if ($m['total_stock'] <= 0): ?>
                                        <span class="badge badge-danger">Out of Stock</span>
                                    <?php elseif ($m['total_stock'] < $m['min_stock_level']): ?>
                                        <span class="badge badge-warning"><?= $m['total_stock'] ?> (Low)</span>
                                    <?php else: ?>
                                        <span class="badge badge-success"><?= $m['total_stock'] ?> units</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $m['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>">
                                        <?= ucfirst(e($m['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group gap-1">
                                        <a href="<?= BASE_URL ?>/modules/medicines/view.php?id=<?= $m['id'] ?>" class="btn btn-xs btn-outline-info" title="View details and batches">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?= BASE_URL ?>/modules/medicines/edit.php?id=<?= $m['id'] ?>" class="btn btn-xs btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" action="<?= BASE_URL ?>/modules/medicines/delete.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to deactivate/delete this medicine?')">
                                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                                            <button type="submit" class="btn btn-xs btn-outline-danger" title="Deactivate">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
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
        <span class="text-muted">Showing page <?= $page ?> of <?= $totalPages ?> (Total: <?= $totalRecords ?> items)</span>
        <div class="pagination d-flex gap-1">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category_id=<?= $categoryId ?>&status=<?= $status ?>" class="btn btn-xs btn-outline-secondary">&laquo; Previous</a>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php if ($p == 1 || $p == $totalPages || abs($p - $page) <= 2): ?>
                    <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&category_id=<?= $categoryId ?>&status=<?= $status ?>" class="btn btn-xs <?= $p === $page ? 'btn-primary' : 'btn-outline-secondary' ?>">
                        <?= $p ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category_id=<?= $categoryId ?>&status=<?= $status ?>" class="btn btn-xs btn-outline-secondary">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

