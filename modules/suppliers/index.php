<?php
/**
 * Medis - Suppliers Management
 */
$pageTitle = 'Suppliers';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

$db = getDB();

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? 'all');

$where = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(name LIKE ? OR contact_person LIKE ? OR phone LIKE ? OR email LIKE ? OR company LIKE ?)";
    $term = "%$search%";
    $params = array_fill(0, 5, $term);
}

if ($status !== 'all') {
    $where[] = "status = ?";
    $params[] = $status;
}

$whereSql = implode(' AND ', $where);

$sql = "
    SELECT s.*,
           (SELECT COUNT(*) FROM purchases WHERE supplier_id = s.id) AS total_orders,
           (SELECT COALESCE(SUM(total_amount),0) FROM purchases WHERE supplier_id = s.id AND status = 'received') AS total_spent
    FROM suppliers s
    WHERE $whereSql
    ORDER BY s.name ASC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-truck text-primary"></i> Suppliers & Vendors</h2>
        <p class="page-subtitle">Manage pharmaceutical distributors, contacts, and purchase orders.</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/modules/suppliers/add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Supplier
        </a>
    </div>
</div>

<!-- Search & Filters -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row align-items-center gap-2">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search supplier name, company, phone, email..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active Vendors</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filter</button>
                <a href="<?= BASE_URL ?>/modules/suppliers/index.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Suppliers Table -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title m-0">Suppliers List (<?= count($suppliers) ?> registered)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 data-table">
                <thead class="table-light">
                    <tr>
                        <th>Supplier / Company</th>
                        <th>Contact Person</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Total Orders</th>
                        <th>Total Purchases</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No suppliers found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($suppliers as $sup): ?>
                            <tr>
                                <td>
                                    <strong><a href="<?= BASE_URL ?>/modules/suppliers/view.php?id=<?= $sup['id'] ?>"><?= e($sup['name']) ?></a></strong>
                                    <?php if (!empty($sup['company'])): ?>
                                        <div class="text-muted small"><?= e($sup['company']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($sup['contact_person'] ?? '—') ?></td>
                                <td><?= e($sup['phone'] ?? '—') ?></td>
                                <td><?= e($sup['email'] ?? '—') ?></td>
                                <td><span class="badge badge-light"><?= $sup['total_orders'] ?> orders</span></td>
                                <td><strong><?= formatCurrency((float)$sup['total_spent']) ?></strong></td>
                                <td>
                                    <span class="badge <?= $sup['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>">
                                        <?= ucfirst(e($sup['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group gap-1">
                                        <a href="<?= BASE_URL ?>/modules/suppliers/view.php?id=<?= $sup['id'] ?>" class="btn btn-xs btn-outline-info" title="View details and order history">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?= BASE_URL ?>/modules/suppliers/edit.php?id=<?= $sup['id'] ?>" class="btn btn-xs btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" action="<?= BASE_URL ?>/modules/suppliers/delete.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to deactivate or remove this supplier?')">
                                            <input type="hidden" name="id" value="<?= $sup['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                                            <button type="submit" class="btn btn-xs btn-outline-danger" title="Delete / Deactivate">
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
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

