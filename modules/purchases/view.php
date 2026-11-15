<?php
/**
 * Medis - Purchase Order Detail View
 */
$pageTitle = 'Purchase Order Details';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

$currentUser = getCurrentUser();
$db = getDB();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    redirect(BASE_URL . '/modules/purchases/index.php', 'Invalid purchase order ID', 'error');
}

$stmt = $db->prepare("
    SELECT p.*, s.name AS supplier_name, s.phone AS supplier_phone, s.company AS supplier_company,
           br.name AS branch_name, br.phone AS branch_phone, br.address AS branch_address,
           u.name AS user_name
    FROM purchases p
    LEFT JOIN suppliers s ON s.id = p.supplier_id
    JOIN branches br ON br.id = p.branch_id
    LEFT JOIN users u ON u.id = p.user_id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$purchase = $stmt->fetch();

if (!$purchase) {
    redirect(BASE_URL . '/modules/purchases/index.php', 'Purchase order not found', 'error');
}

// Scoping: manager can only see their branch
if (isManager() && (int)$purchase['branch_id'] !== (int)$currentUser['branch_id']) {
    redirect(BASE_URL . '/modules/purchases/index.php', 'Unauthorized to view purchases for other branches', 'error');
}

// Fetch items
$itemStmt = $db->prepare("
    SELECT pi.*, m.name AS medicine_name, m.generic_name
    FROM purchase_items pi
    JOIN medicines m ON m.id = pi.medicine_id
    WHERE pi.purchase_id = ?
    ORDER BY pi.id ASC
");
$itemStmt->execute([$id]);
$items = $itemStmt->fetchAll();

$csrfToken = generateCSRF();
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-file-invoice text-primary"></i> Order: <?= e($purchase['invoice_number']) ?></h2>
        <p class="page-subtitle">Created on <?= formatDate($purchase['created_at']) ?> for <strong><?= e($purchase['branch_name']) ?></strong></p>
    </div>
    <div class="page-actions d-flex gap-2">
        <?php if ($purchase['status'] === 'pending'): ?>
            <form method="POST" action="<?= BASE_URL ?>/modules/purchases/receive.php" onsubmit="return confirm('Mark this purchase as RECEIVED? Stock will be immediately credited to branch inventory.')">
                <input type="hidden" name="purchase_id" value="<?= $purchase['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-box-open"></i> Receive & Credit Inventory
                </button>
            </form>

            <form method="POST" action="<?= BASE_URL ?>/modules/purchases/cancel.php" onsubmit="return confirm('Cancel this purchase order? No inventory will be added.')">
                <input type="hidden" name="purchase_id" value="<?= $purchase['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <button type="submit" class="btn btn-outline-danger">
                    <i class="fas fa-ban"></i> Cancel Order
                </button>
            </form>
        <?php endif; ?>

        <button type="button" class="btn btn-secondary" onclick="window.print()">
            <i class="fas fa-print"></i> Print
        </button>

        <a href="<?= BASE_URL ?>/modules/purchases/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Purchases
        </a>
    </div>
</div>

<div class="row mb-4">
    <!-- Order Info Card -->
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h5 class="card-title m-0">Purchase Order Metadata</h5></div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr><th class="text-muted" style="width: 35%;">Order Number</th><td><strong><code><?= e($purchase['invoice_number']) ?></code></strong></td></tr>
                    <tr><th class="text-muted">Distributor</th><td><strong><?= e($purchase['supplier_name'] ?? 'Direct Supplier') ?></strong></td></tr>
                    <tr><th class="text-muted">Destination Store</th><td><?= e($purchase['branch_name']) ?></td></tr>
                    <tr><th class="text-muted">Recorded By</th><td><?= e($purchase['user_name'] ?? 'Staff') ?></td></tr>
                    <tr><th class="text-muted">Date Ordered</th><td><?= formatDateTime($purchase['created_at']) ?></td></tr>
                    <tr><th class="text-muted">Notes / Challan</th><td><?= e($purchase['notes'] ?? '—') ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Status & Total Card -->
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h5 class="card-title m-0">Order Status & Financials</h5></div>
            <div class="card-body">
                <div class="mb-4">
                    <span class="text-muted d-block small text-uppercase fw-bold">Current Delivery Status</span>
                    <?php if ($purchase['status'] === 'received'): ?>
                        <div class="alert alert-success mt-2 mb-0">
                            <i class="fas fa-check-circle"></i> <strong>RECEIVED & INVENTORIED</strong><br>
                            All item quantities have been added to store batches and logged to stock movements.
                        </div>
                    <?php elseif ($purchase['status'] === 'pending'): ?>
                        <div class="alert alert-warning mt-2 mb-0">
                            <i class="fas fa-clock"></i> <strong>PENDING RECEIPT</strong><br>
                            Shipment is awaiting physical arrival and verification at the branch.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger mt-2 mb-0">
                            <i class="fas fa-ban"></i> <strong>ORDER CANCELLED</strong>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <span class="fs-6 text-muted">Total Purchase Valuation:</span>
                    <strong class="text-primary fs-4"><?= formatCurrency((float)$purchase['total_amount']) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Items Table -->
<div class="card shadow-sm">
    <div class="card-header"><h5 class="card-title m-0">Line Items</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Medicine</th>
                        <th>Allocated Batch #</th>
                        <th>Expiry Date</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-end">Cost Price</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $idx => $it): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td>
                                <strong><?= e($it['medicine_name']) ?></strong>
                                <div class="text-muted small"><?= e($it['generic_name'] ?? '') ?></div>
                            </td>
                            <td><code><?= e($it['batch_number'] ?? '—') ?></code></td>
                            <td><?= formatDate($it['expiry_date']) ?></td>
                            <td class="text-center"><strong><?= $it['quantity'] ?> units</strong></td>
                            <td class="text-end"><?= formatCurrency((float)$it['purchase_price']) ?></td>
                            <td class="text-end fw-bold"><?= formatCurrency((float)($it['quantity'] * $it['purchase_price'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

