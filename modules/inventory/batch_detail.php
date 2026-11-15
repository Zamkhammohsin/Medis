<?php
/**
 * Medis - Batch Detail View
 */
$pageTitle = 'Batch Breakdown';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

$currentUser = getCurrentUser();
$db = getDB();

$medicineId = (int)($_GET['medicine_id'] ?? 0);
$branchId   = (int)($_GET['branch_id'] ?? 0);

if (!$medicineId || !$branchId) {
    redirect(BASE_URL . '/modules/inventory/index.php', 'Invalid parameters', 'error');
}

// Security: If manager, verify branch
if (isManager() && $branchId !== (int)$currentUser['branch_id']) {
    redirect(BASE_URL . '/modules/inventory/index.php', 'Unauthorized to view other branch batches', 'error');
}

$medStmt = $db->prepare("SELECT m.*, c.name AS category_name FROM medicines m LEFT JOIN categories c ON c.id = m.category_id WHERE m.id = ?");
$medStmt->execute([$medicineId]);
$medicine = $medStmt->fetch();

$brStmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
$brStmt->execute([$branchId]);
$branch = $brStmt->fetch();

if (!$medicine || !$branch) {
    redirect(BASE_URL . '/modules/inventory/index.php', 'Record not found', 'error');
}

// Fetch all batches
$stmt = $db->prepare("
    SELECT b.*, DATEDIFF(b.expiry_date, CURDATE()) AS days_left
    FROM batches b
    WHERE b.medicine_id = ? AND b.branch_id = ?
    ORDER BY b.expiry_date ASC
");
$stmt->execute([$medicineId, $branchId]);
$batches = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-layer-group"></i> Batches: <?= e($medicine['name']) ?></h2>
        <p class="page-subtitle">Store: <strong><?= e($branch['name']) ?></strong> &bull; Category: <?= e($medicine['category_name'] ?? 'General') ?></p>
    </div>
    <div class="page-actions d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/inventory/adjust.php?medicine_id=<?= $medicineId ?>&branch_id=<?= $branchId ?>" class="btn btn-warning">
            <i class="fas fa-sliders-h"></i> Adjust Stock
        </a>
        <a href="<?= BASE_URL ?>/modules/inventory/index.php?branch_id=<?= $branchId ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Inventory
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title m-0">Active & Past Batches (FIFO Priority)</h5>
        <span class="badge badge-light"><?= count($batches) ?> Records</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Batch Number</th>
                        <th>Current Quantity</th>
                        <th>Cost Price</th>
                        <th>Stock Valuation</th>
                        <th>Expiry Date</th>
                        <th>Days Remaining</th>
                        <th>Sales Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($batches)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No batch records registered for this store.</td></tr>
                    <?php else: ?>
                        <?php foreach ($batches as $b): ?>
                            <tr>
                                <td><code><strong><?= e($b['batch_number']) ?></strong></code></td>
                                <td><strong><?= $b['quantity'] ?> units</strong></td>
                                <td><?= formatCurrency((float)$b['purchase_price']) ?></td>
                                <td><?= formatCurrency((float)($b['quantity'] * $b['purchase_price'])) ?></td>
                                <td><?= formatDate($b['expiry_date']) ?></td>
                                <td>
                                    <?php if ($b['days_left'] < 0): ?>
                                        <span class="text-danger fw-bold">Expired <?= abs($b['days_left']) ?> days ago</span>
                                    <?php else: ?>
                                        <span class="<?= $b['days_left'] <= 30 ? 'text-danger fw-bold' : ($b['days_left'] <= 90 ? 'text-warning fw-bold' : 'text-success') ?>">
                                            <?= $b['days_left'] ?> days left
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($b['quantity'] <= 0): ?>
                                        <span class="badge badge-secondary">Depleted</span>
                                    <?php elseif ($b['days_left'] < 0): ?>
                                        <span class="badge badge-danger">EXPIRED (Blocked from POS)</span>
                                    <?php elseif ($b['days_left'] <= 30): ?>
                                        <span class="badge badge-danger">Critical Expiry (Next in Line)</span>
                                    <?php elseif ($b['days_left'] <= 90): ?>
                                        <span class="badge badge-warning">Priority Dispense</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Good Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/modules/inventory/adjust.php?batch_id=<?= $b['id'] ?>&medicine_id=<?= $medicineId ?>&branch_id=<?= $branchId ?>" class="btn btn-xs btn-outline-warning">
                                        <i class="fas fa-sliders-h"></i> Adjust
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

