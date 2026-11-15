<?php
/**
 * Medis - Branches Management
 */
$pageTitle = 'Branches';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin']);

$db = getDB();

$sql = "
    SELECT b.*, u.name AS manager_name, u.email AS manager_email,
           (SELECT COUNT(*) FROM users WHERE branch_id = b.id) AS staff_count,
           (SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE branch_id = b.id AND status = 'completed') AS total_sales,
           (SELECT COALESCE(SUM(quantity),0) FROM batches WHERE branch_id = b.id AND expiry_date > CURDATE()) AS active_stock
    FROM branches b
    LEFT JOIN users u ON u.id = b.manager_id
    ORDER BY b.name ASC
";
$branches = $db->query($sql)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-store-alt text-primary"></i> Multi-Branch Management</h2>
        <p class="page-subtitle">Configure pharmacy retail locations, store managers, and regional operations.</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/modules/branches/add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Branch
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title m-0">Store Branches (<?= count($branches) ?> active locations)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Branch Name</th>
                        <th>Address / Location</th>
                        <th>Phone</th>
                        <th>Branch Manager</th>
                        <th>Staff Members</th>
                        <th>Active Stock Units</th>
                        <th>Total Revenue</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($branches)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No branches found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($branches as $br): ?>
                            <tr>
                                <td>
                                    <strong><a href="<?= BASE_URL ?>/modules/branches/view.php?id=<?= $br['id'] ?>"><?= e($br['name']) ?></a></strong>
                                </td>
                                <td><?= e($br['address'] ?? '—') ?></td>
                                <td><?= e($br['phone'] ?? '—') ?></td>
                                <td>
                                    <?php if ($br['manager_name']): ?>
                                        <i class="fas fa-user-tie text-muted"></i> <?= e($br['manager_name']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-light"><?= $br['staff_count'] ?> staff</span></td>
                                <td><?= number_format((int)$br['active_stock']) ?> units</td>
                                <td><strong><?= formatCurrency((float)$br['total_sales']) ?></strong></td>
                                <td>
                                    <span class="badge <?= $br['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>">
                                        <?= ucfirst(e($br['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group gap-1">
                                        <a href="<?= BASE_URL ?>/modules/branches/view.php?id=<?= $br['id'] ?>" class="btn btn-xs btn-outline-info" title="View Store Analytics">
                                            <i class="fas fa-eye"></i> Details
                                        </a>
                                        <a href="<?= BASE_URL ?>/modules/branches/edit.php?id=<?= $br['id'] ?>" class="btn btn-xs btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="<?= BASE_URL ?>/modules/inventory/index.php?branch_id=<?= $br['id'] ?>" class="btn btn-xs btn-outline-secondary" title="View Store Inventory">
                                            <i class="fas fa-boxes"></i> Stock
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
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

