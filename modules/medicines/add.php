<?php
/**
 * Medis - Add Medicine
 */
$pageTitle = 'Add Medicine';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

$db = getDB();
$error = '';
$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$formData = [
    'name'            => '',
    'generic_name'    => '',
    'category_id'     => '',
    'manufacturer'    => '',
    'barcode'         => '',
    'description'     => '',
    'purchase_price'  => '',
    'selling_price'   => '',
    'min_stock_level' => '15',
    'units_per_pack'  => '10',
    'status'          => 'active'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $formData['name']            = trim($_POST['name'] ?? '');
        $formData['generic_name']    = trim($_POST['generic_name'] ?? '');
        $formData['category_id']     = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $formData['manufacturer']    = trim($_POST['manufacturer'] ?? '');
        $formData['barcode']         = trim($_POST['barcode'] ?? '');
        $formData['description']     = trim($_POST['description'] ?? '');
        $formData['purchase_price']  = (float)($_POST['purchase_price'] ?? 0);
        $formData['selling_price']   = (float)($_POST['selling_price'] ?? 0);
        $formData['min_stock_level'] = (int)($_POST['min_stock_level'] ?? 10);
        $formData['units_per_pack']  = max(1, (int)($_POST['units_per_pack'] ?? 1));
        $formData['status']          = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        // Auto-generate barcode if blank
        if (empty($formData['barcode'])) {
            $formData['barcode'] = '890' . str_pad((string)mt_rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
        }

        // Validation
        if (empty($formData['name'])) {
            $error = 'Medicine name is required.';
        } elseif ($formData['purchase_price'] < 0 || $formData['selling_price'] <= 0) {
            $error = 'Please enter valid purchase and selling prices.';
        } else {
            // Check barcode uniqueness
            $chk = $db->prepare("SELECT id FROM medicines WHERE barcode = ? LIMIT 1");
            $chk->execute([$formData['barcode']]);
            if ($chk->fetch()) {
                $error = 'A medicine with this barcode already exists.';
            } else {
                try {
                    $insertSql = "
                        INSERT INTO medicines 
                        (name, generic_name, category_id, manufacturer, barcode, description, purchase_price, selling_price, min_stock_level, units_per_pack, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ";
                    $stmt = $db->prepare($insertSql);
                    $stmt->execute([
                        $formData['name'],
                        $formData['generic_name'],
                        $formData['category_id'],
                        $formData['manufacturer'],
                        $formData['barcode'],
                        $formData['description'],
                        $formData['purchase_price'],
                        $formData['selling_price'],
                        $formData['min_stock_level'],
                        $formData['units_per_pack'],
                        $formData['status']
                    ]);

                    redirect(BASE_URL . '/modules/medicines/index.php', 'Medicine added successfully!', 'success');
                } catch (PDOException $e) {
                    error_log($e->getMessage());
                    $error = 'Database error: ' . $e->getMessage();
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
        <h2 class="page-title"><i class="fas fa-plus-circle"></i> Add New Medicine</h2>
        <p class="page-subtitle">Register a pharmaceutical item into the centralized catalog.</p>
    </div>
    <div class="page-actions">
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
                           placeholder="e.g. Panadol Extra 500mg" value="<?= e($formData['name']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="generic_name">Generic Formula / Composition</label>
                    <input type="text" id="generic_name" name="generic_name" class="form-control"
                           placeholder="e.g. Paracetamol + Caffeine" value="<?= e($formData['generic_name']) ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="category_id">Category</label>
                    <select id="category_id" name="category_id" class="form-select">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= (string)$formData['category_id'] === (string)$cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="manufacturer">Manufacturer / Pharma Company</label>
                    <input type="text" id="manufacturer" name="manufacturer" class="form-control"
                           placeholder="e.g. GSK, Abbott, Getz" value="<?= e($formData['manufacturer']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="barcode">Barcode (EAN/UPC)</label>
                    <div class="input-group">
                        <input type="text" id="barcode" name="barcode" class="form-control"
                               placeholder="Auto-generated if left blank" value="<?= e($formData['barcode']) ?>">
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="purchase_price">Purchase Price (PKR) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" id="purchase_price" name="purchase_price" class="form-control"
                           placeholder="0.00" value="<?= e((string)$formData['purchase_price']) ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="selling_price">Selling Price (PKR) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" id="selling_price" name="selling_price" class="form-control"
                           placeholder="0.00" value="<?= e((string)$formData['selling_price']) ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="min_stock_level">Min Stock Threshold <span class="text-danger">*</span></label>
                    <input type="number" min="1" id="min_stock_level" name="min_stock_level" class="form-control"
                           value="<?= e((string)$formData['min_stock_level']) ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="units_per_pack">Units Per Pack / Strip</label>
                    <input type="number" min="1" id="units_per_pack" name="units_per_pack" class="form-control"
                           value="<?= e((string)$formData['units_per_pack']) ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-8 mb-3">
                    <label class="form-label" for="description">Dosage / Description / Side Effects</label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                              placeholder="Prescription guidelines, dosage, storage instructions..."><?= e($formData['description']) ?></textarea>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="status">Catalog Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="active" <?= $formData['status'] === 'active' ? 'selected' : '' ?>>Active (Available for sale)</option>
                        <option value="inactive" <?= $formData['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Hidden)</option>
                    </select>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= BASE_URL ?>/modules/medicines/index.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Medicine</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

