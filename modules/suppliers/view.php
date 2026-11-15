<?php
/**
 * Medis - Supplier Detail & Order History
 */
$pageTitle = 'Supplier Profile';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    redirect(BASE_URL . '/modules/suppliers/index.php', 'Invalid supplier ID', 'error');
}

$stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->execute([$id]);
$supplier = $stmt->fetch();
if (!$supplier) {
    redirect(BASE_URL . '/modules/suppliers/index.php', 'Supplier not found', 'error');
}

// Fetch purchase history for this supplier
$purchasesStmt = $db->prepare("
    SELECT p.*, br.name AS branch_name, u.name AS created_by_name,
           (SELECT COUNT(*) FROM purchase_items WHERE purchase_id = p.id) AS item_count
    FROM purchases p
    JOIN branches br ON br.id = p.branch_id
    LEFT JOIN users u ON u.id = p.user_id
    WHERE p.supplier_id = ?
    ORDER BY p.created_at DESC
");
$purchasesStmt->execute([$id]);
$purchases = $purchasesStmt->fetchAll();

$totalSpend = 0.0;
foreach ($purchases as $p) {
    if ($p['status'] === 'received') {
        $totalSpend += (float)$p['total_amount'];
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-truck text-primary"></i> <?= e($supplier['name']) ?></h2>
        <p class="page-subtitle"><?= e($supplier['company'] ?? 'Pharmaceutical Distributor') ?></p>
    </div>
    <div class="page-actions d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/purchases/add.php?supplier_id=<?= $supplier['id'] ?>" class="btn btn-primary">
            <i class="fas fa-cart-plus"></i> New Purchase Order
        </a>
        <a href="<?= BASE_URL ?>/modules/suppliers/edit.php?id=<?= $supplier['id'] ?>" class="btn btn-outline-primary">
            <i class="fas fa-edit"></i> Edit
        </a>
        <a href="<?= BASE_URL ?>/modules/suppliers/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="row mb-4">
    <!-- Supplier Info Card -->
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h5 class="card-title m-0">Vendor Information</h5></div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr><th class="text-muted" style="width: 40%;">Contact Person</th><td><?= e($supplier['contact_person'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Phone Number</th><td><?= e($supplier['phone'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Email Address</th><td><?= e($supplier['email'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Company</th><td><?= e($supplier['company'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted">Status</th><td><span class="badge <?= $supplier['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>"><?= ucfirst(e($supplier['status'])) ?></span></td></tr>
                    <tr><th class="text-muted">Mailing Address</th><td><small><?= nl2br(e($supplier['address'] ?? '—')) ?></small></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="col-lg-7 mb-4">
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon bg-success-light text-success"><i class="fas fa-receipt"></i></div>
                    <div class="stat-details">
                        <span class="stat-label">Total Completed Purchases</span>
                        <h3 class="stat-value text-success"><?= formatCurrency($totalSpend) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon bg-primary-light text-primary"><i class="fas fa-boxes"></i></div>
                    <div class="stat-details">
                        <span class="stat-label">Purchase Orders</span>
                        <h3 class="stat-value"><?= count($purchases) ?> Invoices</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="card-title m-0">Order History</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Branch</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($purchases)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No purchase orders found for this vendor.</td></tr>
                            <?php else: ?>
                                <?php foreach ($purchases as $p): ?>
                                    <tr>
                                        <td><strong><?= e($p['invoice_number']) ?></strong></td>
                                        <td><?= e($p['branch_name']) ?></td>
                                        <td><?= formatDate($p['created_at']) ?></td>
                                        <td><?= formatCurrency((float)$p['total_amount']) ?></td>
                                        <td>
                                            <?php if ($p['status'] === 'received'): ?>
                                                <span class="badge badge-success">Received</span>
                                            <?php elseif ($p['status'] === 'pending'): ?>
                                                <span class="badge badge-warning">Pending</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Cancelled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?= BASE_URL ?>/modules/purchases/view.php?id=<?= $p['id'] ?>" class="btn btn-xs btn-outline-info">
                                                <i class="fas fa-eye"></i> View
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
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

