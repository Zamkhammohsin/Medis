<?php
/**
 * Medis - Create Purchase Order
 */
$pageTitle = 'New Purchase Order';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

$currentUser = getCurrentUser();
$db = getDB();
$error = '';

$branches  = getAllBranches();
$suppliers = $db->query("SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$medicines = $db->query("SELECT id, name, generic_name, purchase_price FROM medicines WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$defaultBranchId = isAdmin() ? ($branches[0]['id'] ?? 1) : $currentUser['branch_id'];
$autoInvoice = generateInvoiceNumber('PO');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $branchId   = isAdmin() ? (int)($_POST['branch_id'] ?? 0) : (int)$currentUser['branch_id'];
        $invoiceNum = trim($_POST['invoice_number'] ?? '');
        $notes      = trim($_POST['notes'] ?? '');
        $itemsData  = $_POST['items'] ?? [];

        if (empty($invoiceNum)) {
            $invoiceNum = generateInvoiceNumber('PO');
        }

        if (!$branchId) {
            $error = 'Please select a destination store branch.';
        } elseif (empty($itemsData) || !is_array($itemsData)) {
            $error = 'Please add at least one medicine item to the purchase order.';
        } else {
            // Check if invoice number is duplicate
            $chk = $db->prepare("SELECT id FROM purchases WHERE invoice_number = ?");
            $chk->execute([$invoiceNum]);
            if ($chk->fetch()) {
                $error = 'An order with this invoice number already exists.';
            } else {
                try {
                    $db->beginTransaction();

                    $totalAmount = 0.0;
                    $validItems = [];

                    foreach ($itemsData as $row) {
                        $medId    = (int)($row['medicine_id'] ?? 0);
                        $batchNo  = trim($row['batch_number'] ?? '');
                        $expDate  = trim($row['expiry_date'] ?? '');
                        $qty      = (int)($row['quantity'] ?? 0);
                        $price    = (float)($row['purchase_price'] ?? 0);

                        if (!$medId || $qty <= 0 || $price < 0) {
                            continue;
                        }

                        if (empty($batchNo)) {
                            $batchNo = 'B-' . strtoupper(substr(uniqid(), -6));
                        }
                        if (empty($expDate)) {
                            $expDate = date('Y-m-d', strtotime('+1 year'));
                        }

                        $subtotal = round($qty * $price, 2);
                        $totalAmount += $subtotal;

                        $validItems[] = [
                            'medicine_id'    => $medId,
                            'batch_number'   => $batchNo,
                            'quantity'       => $qty,
                            'purchase_price' => $price,
                            'expiry_date'    => $expDate,
                            'subtotal'       => $subtotal
                        ];
                    }

                    if (empty($validItems)) {
                        $db->rollBack();
                        $error = 'No valid medicine lines found in the order.';
                    } else {
                        // Insert purchase
                        $pStmt = $db->prepare("
                            INSERT INTO purchases (supplier_id, branch_id, user_id, invoice_number, total_amount, status, notes)
                            VALUES (?, ?, ?, ?, ?, 'pending', ?)
                        ");
                        $pStmt->execute([
                            $supplierId,
                            $branchId,
                            $currentUser['id'],
                            $invoiceNum,
                            $totalAmount,
                            $notes
                        ]);
                        $purchaseId = (int)$db->lastInsertId();

                        // Insert purchase items
                        $itemStmt = $db->prepare("
                            INSERT INTO purchase_items (purchase_id, medicine_id, batch_number, quantity, purchase_price, expiry_date)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");

                        foreach ($validItems as $v) {
                            $itemStmt->execute([
                                $purchaseId,
                                $v['medicine_id'],
                                $v['batch_number'],
                                $v['quantity'],
                                $v['purchase_price'],
                                $v['expiry_date']
                            ]);
                        }

                        $db->commit();
                        redirect(BASE_URL . "/modules/purchases/view.php?id=$purchaseId", 'Purchase order created successfully! Mark as received when stock arrives.', 'success');
                    }
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    error_log($e->getMessage());
                    $error = 'Purchase order error: ' . $e->getMessage();
                }
            }
        }
    }
}

$csrfToken = generateCSRF();
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-cart-plus text-primary"></i> Create Purchase Order</h2>
        <p class="page-subtitle">Enter supplier purchase order with medicine batch allocations and pricing.</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/modules/purchases/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Purchases
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= e($error) ?></div>
<?php endif; ?>

<form method="POST" action="" id="purchaseOrderForm">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <!-- Order Header Details -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header"><h5 class="card-title m-0">Purchase Order Header</h5></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="supplier_id">Distributor / Supplier</label>
                    <select name="supplier_id" id="supplier_id" class="form-select">
                        <option value="">-- Direct / Unknown Supplier --</option>
                        <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['id'] ?>" <?= isset($_GET['supplier_id']) && (int)$_GET['supplier_id'] === (int)$sup['id'] ? 'selected' : '' ?>>
                                <?= e($sup['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3 mb-3">
                    <label class="form-label" for="branch_id">Destination Branch <span class="text-danger">*</span></label>
                    <?php if (isAdmin()): ?>
                        <select name="branch_id" id="branch_id" class="form-select" required>
                            <?php foreach ($branches as $br): ?>
                                <option value="<?= $br['id'] ?>" <?= $defaultBranchId === (int)$br['id'] ? 'selected' : '' ?>>
                                    <?= e($br['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" class="form-control" value="<?= e($_SESSION['user_branch_name'] ?? 'Branch') ?>" readonly>
                        <input type="hidden" name="branch_id" value="<?= $currentUser['branch_id'] ?>">
                    <?php endif; ?>
                </div>

                <div class="col-md-3 mb-3">
                    <label class="form-label" for="invoice_number">Invoice / PO # <span class="text-danger">*</span></label>
                    <input type="text" id="invoice_number" name="invoice_number" class="form-control" required
                           value="<?= e($autoInvoice) ?>">
                </div>

                <div class="col-md-3 mb-3">
                    <label class="form-label" for="notes">Notes / Bill Reference</label>
                    <input type="text" id="notes" name="notes" class="form-control" placeholder="e.g. Delivery challan #998">
                </div>
            </div>
        </div>
    </div>

    <!-- Dynamic Items Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title m-0"><i class="fas fa-list text-primary"></i> Order Items</h5>
            <button type="button" class="btn btn-sm btn-primary" onclick="addPurchaseRow()">
                <i class="fas fa-plus"></i> Add Item Row
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="purchaseItemsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 30%;">Medicine Item</th>
                            <th style="width: 15%;">Batch Number</th>
                            <th style="width: 15%;">Expiry Date</th>
                            <th style="width: 12%;">Qty</th>
                            <th style="width: 13%;">Cost (PKR)</th>
                            <th style="width: 10%;" class="text-end">Subtotal</th>
                            <th style="width: 5%;" class="text-center">Del</th>
                        </tr>
                    </thead>
                    <tbody id="purchaseRowsBody">
                        <!-- Populated by JavaScript -->
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="5" class="text-end">Total Order Valuation:</th>
                            <th class="text-end text-primary fs-5" id="grandTotalDisplay">PKR 0.00</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="<?= BASE_URL ?>/modules/purchases/index.php" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-success btn-lg">
            <i class="fas fa-check-circle"></i> Save Purchase Order
        </button>
    </div>
</form>

<script>
const medicinesData = <?= json_encode($medicines) ?>;
let rowIndex = 0;

function addPurchaseRow() {
    const tbody = document.getElementById('purchaseRowsBody');
    const tr = document.createElement('tr');
    tr.id = `row_${rowIndex}`;

    let medOptions = '<option value="">-- Select Medicine --</option>';
    medicinesData.forEach(m => {
        medOptions += `<option value="${m.id}" data-cost="${m.purchase_price}">${escapeHtml(m.name)}</option>`;
    });

    const defaultExp = new Date();
    defaultExp.setFullYear(defaultExp.getFullYear() + 2);
    const expDateStr = defaultExp.toISOString().split('T')[0];

    tr.innerHTML = `
        <td>
            <select name="items[${rowIndex}][medicine_id]" class="form-select form-select-sm med-select" required onchange="onMedicineChange(${rowIndex}, this)">
                ${medOptions}
            </select>
        </td>
        <td>
            <input type="text" name="items[${rowIndex}][batch_number]" class="form-control form-control-sm" placeholder="e.g. B-1092" required>
        </td>
        <td>
            <input type="date" name="items[${rowIndex}][expiry_date]" class="form-control form-control-sm" value="${expDateStr}" required>
        </td>
        <td>
            <input type="number" min="1" name="items[${rowIndex}][quantity]" class="form-control form-control-sm qty-input" value="10" required oninput="calcRow(${rowIndex})">
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="items[${rowIndex}][purchase_price]" class="form-control form-control-sm price-input" value="0.00" required oninput="calcRow(${rowIndex})">
        </td>
        <td class="text-end fw-bold row-subtotal" id="subtotal_${rowIndex}">
            PKR 0.00
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-xs btn-outline-danger" onclick="removeRow(${rowIndex})">&times;</button>
        </td>
    `;

    tbody.appendChild(tr);
    rowIndex++;
    calcAll();
}

function onMedicineChange(idx, selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    if (opt && opt.dataset.cost) {
        const tr = document.getElementById(`row_${idx}`);
        const priceInput = tr.querySelector('.price-input');
        if (priceInput) priceInput.value = parseFloat(opt.dataset.cost).toFixed(2);
    }
    calcRow(idx);
}

function calcRow(idx) {
    const tr = document.getElementById(`row_${idx}`);
    if (!tr) return;
    const qty = parseFloat(tr.querySelector('.qty-input').value) || 0;
    const price = parseFloat(tr.querySelector('.price-input').value) || 0;
    const sub = qty * price;
    document.getElementById(`subtotal_${idx}`).innerText = 'PKR ' + sub.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    calcAll();
}

function removeRow(idx) {
    const tr = document.getElementById(`row_${idx}`);
    if (tr) tr.remove();
    calcAll();
}

function calcAll() {
    let grand = 0;
    const rows = document.querySelectorAll('#purchaseRowsBody tr');
    rows.forEach(r => {
        const qty = parseFloat(r.querySelector('.qty-input').value) || 0;
        const price = parseFloat(r.querySelector('.price-input').value) || 0;
        grand += (qty * price);
    });
    document.getElementById('grandTotalDisplay').innerText = 'PKR ' + grand.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

document.addEventListener('DOMContentLoaded', function() {
    // Start with 2 empty rows
    addPurchaseRow();
    addPurchaseRow();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

