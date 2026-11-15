<?php
/**
 * Medis - Point of Sale (POS) Interface
 */
$pageTitle = 'Point of Sale (POS)';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager', 'cashier']);

$currentUser = getCurrentUser();
$db = getDB();

// Determine default or selected branch
$branches = getAllBranches();
$activeBranchId = $currentUser['branch_id'];

if (isAdmin()) {
    $activeBranchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : ($branches[0]['id'] ?? 1);
}

// Get branch info
$brStmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
$brStmt->execute([$activeBranchId]);
$activeBranch = $brStmt->fetch();

$csrfToken = generateCSRF();
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* POS Dedicated Layout Styling */
.pos-wrapper {
    display: flex;
    gap: 20px;
    align-items: flex-start;
}
.pos-left {
    flex: 1 1 65%;
    min-width: 0;
}
.pos-right {
    flex: 0 0 35%;
    max-width: 440px;
}
@media (max-width: 1024px) {
    .pos-wrapper { flex-direction: column; }
    .pos-right { max-width: 100%; width: 100%; }
}
.pos-search-box {
    position: relative;
}
.pos-search-input {
    width: 100%;
    padding: 14px 16px 14px 46px;
    font-size: 1.05rem;
    border: 2px solid #cbd5e1;
    border-radius: 10px;
    outline: none;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.pos-search-input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
}
.pos-search-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1.15rem;
    color: #64748b;
}
.search-results-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    max-height: 380px;
    overflow-y: auto;
    z-index: 1050;
    display: none;
}
.search-result-item {
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: background 0.15s ease;
}
.search-result-item:hover {
    background: #f8fafc;
}
.search-result-item:last-child {
    border-bottom: none;
}
.pos-cart-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    overflow: hidden;
}
.pos-cart-table {
    width: 100%;
    border-collapse: collapse;
}
.pos-cart-table th {
    background: #f8fafc;
    padding: 12px 16px;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #64748b;
    border-bottom: 2px solid #e2e8f0;
}
.pos-cart-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.pos-qty-controls {
    display: inline-flex;
    align-items: center;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    overflow: hidden;
    background: #fff;
}
.btn-qty {
    background: #f1f5f9;
    border: none;
    width: 28px;
    height: 28px;
    cursor: pointer;
    font-weight: 700;
    font-size: 1rem;
    color: #334155;
    transition: background 0.15s;
}
.btn-qty:hover {
    background: #e2e8f0;
}
.pos-qty-input {
    width: 44px;
    height: 28px;
    border: none;
    text-align: center;
    font-weight: 600;
    font-size: 0.9rem;
    outline: none;
}
.btn-remove-item {
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    font-size: 1rem;
    padding: 4px 8px;
    border-radius: 4px;
    transition: all 0.15s;
}
.btn-remove-item:hover {
    background: #fee2e2;
}
.payment-tabs {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 16px;
}
.payment-btn {
    padding: 10px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #f8fafc;
    cursor: pointer;
    text-align: center;
    font-weight: 600;
    font-size: 0.85rem;
    color: #475569;
    transition: all 0.15s ease;
}
.payment-btn.active {
    background: #1e3a5f;
    border-color: #1e3a5f;
    color: #ffffff;
}
.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    font-size: 0.9rem;
    color: #475569;
}
.summary-row.total-row {
    border-top: 2px dashed #cbd5e1;
    margin-top: 8px;
    padding-top: 14px;
    font-size: 1.35rem;
    font-weight: 700;
    color: #1e293b;
}
.btn-complete-sale {
    width: 100%;
    padding: 16px;
    background: #16a34a;
    color: #ffffff;
    border: none;
    border-radius: 10px;
    font-size: 1.15rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25);
}
.btn-complete-sale:hover {
    background: #15803d;
}
.btn-complete-sale:active {
    transform: scale(0.99);
}
</style>

<div class="page-header mb-3">
    <div>
        <h2 class="page-title"><i class="fas fa-cash-register text-success"></i> Point of Sale (POS)</h2>
        <p class="page-subtitle">Fast barcode checkout, automated FIFO inventory batches, and instant receipt generation.</p>
    </div>
    <div class="page-actions d-flex align-items-center gap-3">
        <?php if (isAdmin()): ?>
            <div class="d-flex align-items-center gap-2">
                <label for="posBranchSelect" class="form-label mb-0 small text-muted">Store:</label>
                <select id="posBranchSelect" class="form-select form-select-sm" onchange="window.location.href='?branch_id=' + this.value">
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= $br['id'] ?>" <?= $activeBranchId === (int)$br['id'] ? 'selected' : '' ?>>
                            <?= e($br['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <input type="hidden" id="posBranchId" value="<?= $currentUser['branch_id'] ?>">
            <span class="badge badge-info py-2 px-3">
                <i class="fas fa-store"></i> <?= e($activeBranch['name'] ?? 'Branch') ?>
            </span>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/modules/sales/index.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-receipt"></i> Sales History
        </a>
    </div>
</div>

<div class="pos-wrapper">
    <!-- LEFT PANEL: Search & Cart -->
    <div class="pos-left">
        <!-- Dual Search Bar: Medicine Name & Barcode -->
        <div class="row mb-3">
            <div class="col-md-8 mb-2">
                <div class="pos-search-box">
                    <i class="fas fa-search pos-search-icon"></i>
                    <input type="text" id="medSearchInput" class="pos-search-input"
                           placeholder="Search medicine by name or generic formula... (Press F2)" autofocus autocomplete="off">
                    <div id="searchResults" class="search-results-dropdown"></div>
                </div>
            </div>
            <div class="col-md-4 mb-2">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-barcode text-primary"></i></span>
                    <input type="text" id="barcodeInput" class="form-control" style="height: 52px; font-size: 0.95rem;"
                           placeholder="Scan Barcode (Enter)" autocomplete="off">
                </div>
            </div>
        </div>

        <!-- Cart Table -->
        <div class="pos-cart-card">
            <div class="card-header d-flex justify-content-between align-items-center py-2 px-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-shopping-basket text-primary"></i>
                    <strong>Customer Cart</strong>
                    <span id="cartItemCount" class="badge badge-light">0 items</span>
                </div>
                <button type="button" class="btn btn-xs btn-outline-danger" onclick="clearCart()">
                    <i class="fas fa-trash"></i> Clear Cart
                </button>
            </div>

            <div style="min-height: 340px; max-height: 480px; overflow-y: auto;">
                <table class="pos-cart-table">
                    <thead>
                        <tr>
                            <th>Product Description</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="cartTableBody"></tbody>
                </table>

                <div id="cartEmptyState" class="empty-state py-5 text-center">
                    <i class="fas fa-shopping-cart fa-3x text-muted mb-3" style="opacity: 0.25;"></i>
                    <h5 class="text-muted">Cart is Empty</h5>
                    <p class="text-muted small">Scan product barcode or search by medicine name to begin billing.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL: Billing & Payment -->
    <div class="pos-right">
        <div class="card shadow-sm">
            <div class="card-header py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong class="d-block text-dark"><i class="fas fa-cash-register"></i> Checkout Summary</strong>
                        <small class="text-muted">Cashier: <?= e($currentUser['name']) ?></small>
                    </div>
                    <span class="badge badge-success"><i class="fas fa-circle" style="font-size: 8px;"></i> Online</span>
                </div>
            </div>

            <div class="card-body">
                <!-- Payment Method Tabs -->
                <label class="form-label small text-muted text-uppercase fw-bold mb-2">Payment Method</label>
                <div class="payment-tabs">
                    <button type="button" class="payment-btn active" data-method="cash">
                        <i class="fas fa-money-bill-wave d-block mb-1"></i> Cash
                    </button>
                    <button type="button" class="payment-btn" data-method="card">
                        <i class="fas fa-credit-card d-block mb-1"></i> Card
                    </button>
                    <button type="button" class="payment-btn" data-method="online">
                        <i class="fas fa-mobile-alt d-block mb-1"></i> Online / UPI
                    </button>
                </div>

                <!-- Financial Breakdown -->
                <div class="summary-box mb-3">
                    <div class="summary-row">
                        <span>Items Subtotal:</span>
                        <strong id="posSubtotal">PKR 0.00</strong>
                    </div>

                    <div class="summary-row">
                        <span>Discount (PKR or %):</span>
                        <div style="width: 120px;">
                            <input type="text" id="discountInput" class="form-control form-control-sm text-end" value="0" placeholder="0 or 10%">
                        </div>
                    </div>

                    <div class="summary-row">
                        <span>Discount Applied:</span>
                        <span id="posDiscountDisplay" class="text-success">-PKR 0.00</span>
                    </div>

                    <div class="summary-row total-row">
                        <span>Total Payable:</span>
                        <span id="posGrandTotal" class="text-primary">PKR 0.00</span>
                    </div>
                </div>

                <!-- Cash Payment Fields -->
                <div id="cashPaymentFields" class="mb-3 p-3 bg-light rounded">
                    <div class="mb-2">
                        <label class="form-label small text-muted" for="amountPaidInput">Amount Tendered / Paid (PKR):</label>
                        <input type="number" step="0.01" min="0" id="amountPaidInput" class="form-control" placeholder="0.00">
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="small fw-bold">Change Due:</span>
                        <strong id="posChangeDisplay" class="fs-5 text-success">PKR 0.00</strong>
                    </div>
                </div>

                <div class="mb-3">
                    <input type="text" id="posNotes" class="form-control form-control-sm" placeholder="Optional sale notes / customer phone...">
                </div>

                <!-- Complete Sale Button -->
                <button type="button" id="btnCompleteSale" class="btn-complete-sale" onclick="processSale()">
                    <i class="fas fa-check-circle"></i> Complete Sale (<span id="btnPayAmount">PKR 0.00</span>)
                </button>

                <div class="text-center mt-2">
                    <small class="text-muted"><kbd>F4</kbd> to Complete Sale &bull; <kbd>F2</kbd> to Focus Search</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.BASE_URL = '<?= BASE_URL ?>';
window.CSRF_TOKEN = '<?= $csrfToken ?>';
</script>
<script src="<?= BASE_URL ?>/assets/js/pos.js"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

