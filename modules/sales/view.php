<?php
/**
 * Medis - Sale Transaction Details
 */
$pageTitle = 'Invoice Details';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();

$currentUser = getCurrentUser();
$db = getDB();

$id      = (int)($_GET['id'] ?? 0);
$invoice = trim($_GET['invoice'] ?? '');

if ($id) {
    $stmt = $db->prepare("
        SELECT s.*, br.name AS branch_name, br.phone AS branch_phone, br.address AS branch_address,
               u.name AS cashier_name, u.email AS cashier_email
        FROM sales s
        JOIN branches br ON br.id = s.branch_id
        LEFT JOIN users u ON u.id = s.user_id
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
} elseif ($invoice !== '') {
    $stmt = $db->prepare("
        SELECT s.*, br.name AS branch_name, br.phone AS branch_phone, br.address AS branch_address,
               u.name AS cashier_name, u.email AS cashier_email
        FROM sales s
        JOIN branches br ON br.id = s.branch_id
        LEFT JOIN users u ON u.id = s.user_id
        WHERE s.invoice_number = ?
    ");
    $stmt->execute([$invoice]);
} else {
    redirect(BASE_URL . '/modules/sales/index.php', 'Invalid sale parameter', 'error');
}

$sale = $stmt->fetch();
if (!$sale) {
    redirect(BASE_URL . '/modules/sales/index.php', 'Invoice not found', 'error');
}

// Scoping checks
if (isManager() && (int)$sale['branch_id'] !== (int)$currentUser['branch_id']) {
    redirect(BASE_URL . '/modules/sales/index.php', 'Unauthorized to view sales from other branches', 'error');
}
if (isCashier() && (int)$sale['user_id'] !== (int)$currentUser['id']) {
    redirect(BASE_URL . '/modules/sales/index.php', 'Unauthorized to view this invoice', 'error');
}

// Fetch items
$itemStmt = $db->prepare("
    SELECT si.*, m.name AS medicine_name, m.generic_name, m.barcode,
           b.batch_number, b.expiry_date
    FROM sale_items si
    JOIN medicines m ON m.id = si.medicine_id
    LEFT JOIN batches b ON b.id = si.batch_id
    WHERE si.sale_id = ?
    ORDER BY si.id ASC
");
$itemStmt->execute([$sale['id']]);
$items = $itemStmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-file-invoice-dollar text-primary"></i> Invoice: <?= e($sale['invoice_number']) ?></h2>
        <p class="page-subtitle">Processed on <?= formatDateTime($sale['created_at']) ?> at <?= e($sale['branch_name']) ?></p>
    </div>
    <div class="page-actions d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/pos/receipt.php?id=<?= $sale['id'] ?>" target="_blank" class="btn btn-primary">
            <i class="fas fa-print"></i> Print Receipt
        </a>
        <a href="<?= BASE_URL ?>/modules/sales/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Sales
        </a>
    </div>
</div>

<div class="row mb-4">
    <!-- Meta Info Card -->
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h5 class="card-title m-0">Transaction Metadata</h5></div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr><th class="text-muted" style="width: 35%;">Invoice Number</th><td><strong><code><?= e($sale['invoice_number']) ?></code></strong></td></tr>
                    <tr><th class="text-muted">Branch Store</th><td><?= e($sale['branch_name']) ?> (<?= e($sale['branch_phone'] ?? '—') ?>)</td></tr>
                    <tr><th class="text-muted">Cashier Staff</th><td><?= e($sale['cashier_name'] ?? 'Staff') ?> (<?= e($sale['cashier_email'] ?? '—') ?>)</td></tr>
                    <tr><th class="text-muted">Timestamp</th><td><?= formatDateTime($sale['created_at']) ?></td></tr>
                    <tr><th class="text-muted">Status</th><td><span class="badge badge-success"><?= ucfirst(e($sale['status'])) ?></span></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Payment Summary Card -->
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm h-100">
            <div class="card-header"><h5 class="card-title m-0">Payment Summary</h5></div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr><th class="text-muted" style="width: 35%;">Subtotal</th><td><?= formatCurrency((float)$sale['subtotal']) ?></td></tr>
                    <tr><th class="text-muted">Discount Given</th><td class="text-success">-<?= formatCurrency((float)$sale['discount']) ?></td></tr>
                    <tr><th class="text-muted fs-6">Net Total</th><td><strong class="text-primary fs-5"><?= formatCurrency((float)$sale['total_amount']) ?></strong></td></tr>
                    <tr><th class="text-muted">Payment Mode</th><td><span class="badge badge-outline-secondary"><?= strtoupper(e($sale['payment_method'])) ?></span></td></tr>
                    <tr><th class="text-muted">Amount Paid</th><td><?= formatCurrency((float)$sale['amount_paid']) ?></td></tr>
                    <tr><th class="text-muted">Change Returned</th><td><?= formatCurrency((float)$sale['change_amount']) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Item Breakdown Table -->
<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="card-title m-0">Dispatched Items</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Medicine Product</th>
                        <th>Batch Number</th>
                        <th>Batch Expiry</th>
                        <th class="text-center">Units Sold</th>
                        <th class="text-end">Unit Price</th>
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
                            <td class="text-center"><strong><?= $it['quantity'] ?></strong></td>
                            <td class="text-end"><?= formatCurrency((float)$it['unit_price']) ?></td>
                            <td class="text-end fw-bold"><?= formatCurrency((float)$it['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

