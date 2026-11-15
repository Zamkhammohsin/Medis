<?php
/**
 * Medis - Edit Medicine
 */
$pageTitle = 'Edit Medicine';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    redirect(BASE_URL . '/modules/medicines/index.php', 'Invalid medicine ID', 'error');
}

$stmt = $db->prepare("SELECT * FROM medicines WHERE id = ?");
$stmt->execute([$id]);
$medicine = $stmt->fetch();
if (!$medicine) {
    redirect(BASE_URL . '/modules/medicines/index.php', 'Medicine not found', 'error');
}

$error = '';
$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $name            = trim($_POST['name'] ?? '');
        $generic_name    = trim($_POST['generic_name'] ?? '');
        $category_id     = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $manufacturer    = trim($_POST['manufacturer'] ?? '');
        $barcode         = trim($_POST['barcode'] ?? '');
        $description     = trim($_POST['description'] ?? '');
        $purchase_price  = (float)($_POST['purchase_price'] ?? 0);
        $selling_price   = (float)($_POST['selling_price'] ?? 0);
        $min_stock_level = (int)($_POST['min_stock_level'] ?? 10);
        $units_per_pack  = max(1, (int)($_POST['units_per_pack'] ?? 1));
        $status          = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (empty($name)) {
            $error = 'Medicine name is required.';
        } elseif ($purchase_price < 0 || $selling_price <= 0) {
            $error = 'Please enter valid purchase and selling prices.';
        } else {
            // Check barcode uniqueness
            if (!empty($barcode)) {
                $chk = $db->prepare("SELECT id FROM medicines WHERE barcode = ? AND id != ? LIMIT 1");
                $chk->execute([$barcode, $id]);
                if ($chk->fetch()) {
                    $error = 'A medicine with this barcode already exists.';
                }
            }

            if (empty($error)) {
                try {
                    $updateSql = "
                        UPDATE medicines SET
                            name = ?, generic_name = ?, category_id = ?, manufacturer = ?, barcode = ?,
                            description = ?, purchase_price = ?, selling_price = ?, min_stock_level = ?,
                            units_per_pack = ?, status = ?
                        WHERE id = ?
                    ";
                    $stmt = $db->prepare($updateSql);
                    $stmt->execute([
                        $name, $generic_name, $category_id, $manufacturer, $barcode,
                        $description, $purchase_price, $selling_price, $min_stock_level,
                        $units_per_pack, $status, $id
                    ]);

                    redirect(BASE_URL . '/modules/medicines/index.php', 'Medicine updated successfully!', 'success');
                } catch (PDOException $e) {
                    error_log($e->getMessage());
                    $error = 'Database update error: ' . $e->getMessage();
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
        <h2 class="page-title"><i class="fas fa-edit"></i> Edit Medicine</h2>
        <p class="page-subtitle">Update product information and stock thresholds for <strong><?= e($medicine['name']) ?></strong>.</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/modules/medicines/view.php?id=<?= $medicine['id'] ?>" class="btn btn-outline-info">
            <i class="fas fa-eye"></i> View Batches
        </a>
        <a href="<?= BASE_URL ?>/modules/medicines/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Catalog
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="card-title m-0">Medicine Specifications</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div class="row mb-3">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="name">Medicine Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" required
                           value="<?= e($medicine['name']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="generic_name">Generic Formula / Composition</label>
                    <input type="text" id="generic_name" name="generic_name" class="form-control"
                           value="<?= e($medicine['generic_name'] ?? '') ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="category_id">Category</label>
                    <select id="category_id" name="category_id" class="form-select">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= (int)$medicine['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="manufacturer">Manufacturer / Pharma Company</label>
                    <input type="text" id="manufacturer" name="manufacturer" class="form-control"
                           value="<?= e($medicine['manufacturer'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="barcode">Barcode (EAN/UPC)</label>
                    <input type="text" id="barcode" name="barcode" class="form-control"
                           value="<?= e($medicine['barcode'] ?? '') ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="purchase_price">Purchase Price (PKR) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" id="purchase_price" name="purchase_price" class="form-control"
                           value="<?= e((string)$medicine['purchase_price']) ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="selling_price">Selling Price (PKR) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" id="selling_price" name="selling_price" class="form-control"
                           value="<?= e((string)$medicine['selling_price']) ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="min_stock_level">Min Stock Threshold <span class="text-danger">*</span></label>
                    <input type="number" min="1" id="min_stock_level" name="min_stock_level" class="form-control"
                           value="<?= e((string)$medicine['min_stock_level']) ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="units_per_pack">Units Per Pack / Strip</label>
                    <input type="number" min="1" id="units_per_pack" name="units_per_pack" class="form-control"
                           value="<?= e((string)$medicine['units_per_pack']) ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-8 mb-3">
                    <label class="form-label" for="description">Dosage / Description / Side Effects</label>
                    <textarea id="description" name="description" class="form-control" rows="3"><?= e($medicine['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="status">Catalog Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="active" <?= $medicine['status'] === 'active' ? 'selected' : '' ?>>Active (Available for sale)</option>
                        <option value="inactive" <?= $medicine['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Hidden)</option>
                    </select>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= BASE_URL ?>/modules/medicines/index.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Medicine</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

