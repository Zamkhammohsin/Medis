<?php
/**
 * Medis - Stock Adjustment
 */
$pageTitle = 'Stock Adjustment';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

$currentUser = getCurrentUser();
$db = getDB();
$error = '';

$preMedicineId = (int)($_GET['medicine_id'] ?? 0);
$preBranchId   = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : ($currentUser['branch_id'] ?? 0);
$preBatchId    = (int)($_GET['batch_id'] ?? 0);

if (!isAdmin() && $preBranchId && $preBranchId !== (int)$currentUser['branch_id']) {
    $preBranchId = (int)$currentUser['branch_id'];
}

$branches  = getAllBranches();
$medicines = $db->query("SELECT id, name, generic_name FROM medicines WHERE status = 'active' ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $branchId   = isAdmin() ? (int)($_POST['branch_id'] ?? 0) : (int)$currentUser['branch_id'];
        $medicineId = (int)($_POST['medicine_id'] ?? 0);
        $batchId    = (int)($_POST['batch_id'] ?? 0);
        $adjType    = $_POST['adj_type'] ?? '';
        $qty        = (int)($_POST['quantity'] ?? 0);
        $notes      = trim($_POST['notes'] ?? '');

        if (!$branchId || !$medicineId || !$batchId) {
            $error = 'Branch, medicine, and batch selection are required.';
        } elseif (!in_array($adjType, ['increase', 'decrease'])) {
            $error = 'Invalid adjustment type.';
        } elseif ($qty <= 0) {
            $error = 'Quantity must be greater than zero.';
        } elseif (empty($notes)) {
            $error = 'Please provide an adjustment reason / note for auditing purposes.';
        } else {
            try {
                $db->beginTransaction();

                // Fetch batch with lock
                $stmt = $db->prepare("SELECT * FROM batches WHERE id = ? AND medicine_id = ? AND branch_id = ? FOR UPDATE");
                $stmt->execute([$batchId, $medicineId, $branchId]);
                $batch = $stmt->fetch();

                if (!$batch) {
                    $db->rollBack();
                    $error = 'Selected batch could not be found for the specified branch and medicine.';
                } else {
                    $oldQty = (int)$batch['quantity'];
                    $movementQty = 0;
                    $newQty = 0;

                    if ($adjType === 'increase') {
                        $movementQty = $qty;
                        $newQty = $oldQty + $qty;
                    } else {
                        if ($qty > $oldQty) {
                            $db->rollBack();
                            $error = "Cannot decrease by $qty units. Batch currently only has $oldQty units in stock.";
                        } else {
                            $movementQty = -$qty;
                            $newQty = $oldQty - $qty;
                        }
                    }

                    if (empty($error)) {
                        // Update batch
                        $updStmt = $db->prepare("UPDATE batches SET quantity = ?, updated_at = NOW() WHERE id = ?");
                        $updStmt->execute([$newQty, $batchId]);

                        // Record movement
                        $movStmt = $db->prepare("
                            INSERT INTO stock_movements 
                            (medicine_id, branch_id, batch_id, type, quantity, reference_id, reference_type, notes)
                            VALUES (?, ?, ?, 'adjustment', ?, ?, 'adjustment', ?)
                        ");
                        $movStmt->execute([
                            $medicineId,
                            $branchId,
                            $batchId,
                            $movementQty,
                            $currentUser['id'],
                            $notes . " (By " . $currentUser['name'] . ")"
                        ]);

                        $db->commit();
                        redirect(BASE_URL . '/modules/inventory/movements.php', "Stock adjusted successfully! Batch {$batch['batch_number']} updated from $oldQty to $newQty.", 'success');
                    }
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log($e->getMessage());
                $error = 'Adjustment error: ' . $e->getMessage();
            }
        }
    }
}

$csrfToken = generateCSRF();
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-sliders-h text-warning"></i> Inventory Stock Adjustment</h2>
        <p class="page-subtitle">Perform audit corrections, record damaged/expired write-offs, or reconcile discrepancies.</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/modules/inventory/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Inventory
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= e($error) ?></div>
<?php endif; ?>

<div class="card shadow-sm" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h5 class="card-title m-0">Adjustment Details</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div class="row mb-3">
                <!-- Branch Selector -->
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="branch_id">Target Branch <span class="text-danger">*</span></label>
                    <?php if (isAdmin()): ?>
                        <select name="branch_id" id="branch_id" class="form-select" required onchange="loadBatches()">
                            <option value="">-- Select Store Branch --</option>
                            <?php foreach ($branches as $br): ?>
                                <option value="<?= $br['id'] ?>" <?= $preBranchId === (int)$br['id'] ? 'selected' : '' ?>>
                                    <?= e($br['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" class="form-control" value="<?= e($_SESSION['user_branch_name'] ?? 'Assigned Branch') ?>" readonly>
                        <input type="hidden" name="branch_id" id="branch_id" value="<?= $currentUser['branch_id'] ?>">
                    <?php endif; ?>
                </div>

                <!-- Medicine Selector -->
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="medicine_id">Medicine Item <span class="text-danger">*</span></label>
                    <select name="medicine_id" id="medicine_id" class="form-select" required onchange="loadBatches()">
                        <option value="">-- Select Medicine --</option>
                        <?php foreach ($medicines as $med): ?>
                            <option value="<?= $med['id'] ?>" <?= $preMedicineId === (int)$med['id'] ? 'selected' : '' ?>>
                                <?= e($med['name']) ?> (<?= e($med['generic_name'] ?? '—') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <!-- Batch Selector -->
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="batch_id">Batch Number <span class="text-danger">*</span></label>
                    <select name="batch_id" id="batch_id" class="form-select" required>
                        <option value="">-- Select Medicine & Branch first --</option>
                    </select>
                    <small class="text-muted" id="batchStockInfo"></small>
                </div>

                <!-- Adjustment Type -->
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="adj_type">Action <span class="text-danger">*</span></label>
                    <select name="adj_type" id="adj_type" class="form-select" required>
                        <option value="decrease">Decrease (Loss/Damage/Expiry)</option>
                        <option value="increase">Increase (Found/Audit)</option>
                    </select>
                </div>

                <!-- Quantity -->
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="quantity">Quantity (Units) <span class="text-danger">*</span></label>
                    <input type="number" min="1" id="quantity" name="quantity" class="form-control" required placeholder="Units">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="notes">Audit Reason / Justification <span class="text-danger">*</span></label>
                <textarea id="notes" name="notes" class="form-control" rows="3" required
                          placeholder="e.g. Expired batch disposed, Broken vial during handling, Physical inventory count adjustment..."></textarea>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= BASE_URL ?>/modules/inventory/index.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-warning" onclick="return confirm('Confirm stock adjustment? This action is permanently logged.')">
                    <i class="fas fa-check"></i> Apply Stock Adjustment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function loadBatches() {
    const branchEl = document.getElementById('branch_id');
    const medEl = document.getElementById('medicine_id');
    const batchSelect = document.getElementById('batch_id');
    const stockInfo = document.getElementById('batchStockInfo');

    const branchId = branchEl ? branchEl.value : '';
    const medId = medEl ? medEl.value : '';

    if (!branchId || !medId) {
        batchSelect.innerHTML = '<option value="">-- Select Medicine & Branch first --</option>';
        if (stockInfo) stockInfo.innerText = '';
        return;
    }

    batchSelect.innerHTML = '<option value="">Loading batches...</option>';

    fetch(`<?= BASE_URL ?>/modules/inventory/api.php?medicine_id=${medId}&branch_id=${branchId}`)
        .then(res => res.json())
        .then(data => {
            if (!Array.isArray(data) || data.length === 0) {
                batchSelect.innerHTML = '<option value="">No batches found for this store</option>';
                if (stockInfo) stockInfo.innerText = '';
                return;
            }

            let html = '<option value="">-- Choose Batch --</option>';
            data.forEach(b => {
                const isSelected = <?= $preBatchId ?> === parseInt(b.id) ? 'selected' : '';
                html += `<option value="${b.id}" data-qty="${b.quantity}" ${isSelected}>
                    Batch ${b.batch_number} (Qty: ${b.quantity}, Exp: ${b.expiry_date})
                </option>`;
            });
            batchSelect.innerHTML = html;
            updateStockInfo();
        })
        .catch(err => {
            console.error(err);
            batchSelect.innerHTML = '<option value="">Error loading batches</option>';
        });
}

function updateStockInfo() {
    const batchSelect = document.getElementById('batch_id');
    const stockInfo = document.getElementById('batchStockInfo');
    const opt = batchSelect.options[batchSelect.selectedIndex];
    if (opt && opt.dataset.qty !== undefined) {
        stockInfo.innerText = `Current Batch Stock: ${opt.dataset.qty} units`;
    } else {
        stockInfo.innerText = '';
    }
}

document.getElementById('batch_id').addEventListener('change', updateStockInfo);

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('medicine_id').value && document.getElementById('branch_id').value) {
        loadBatches();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

