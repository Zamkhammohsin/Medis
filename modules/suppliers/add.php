<?php
/**
 * Medis - Add Supplier
 */
$pageTitle = 'Add Supplier';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

$db = getDB();
$error = '';

$formData = [
    'name'           => '',
    'contact_person' => '',
    'phone'          => '',
    'email'          => '',
    'address'        => '',
    'company'        => '',
    'status'         => 'active'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $formData['name']           = trim($_POST['name'] ?? '');
        $formData['contact_person'] = trim($_POST['contact_person'] ?? '');
        $formData['phone']          = trim($_POST['phone'] ?? '');
        $formData['email']          = trim($_POST['email'] ?? '');
        $formData['address']        = trim($_POST['address'] ?? '');
        $formData['company']        = trim($_POST['company'] ?? '');
        $formData['status']         = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (empty($formData['name'])) {
            $error = 'Supplier name is required.';
        } elseif (empty($formData['phone'])) {
            $error = 'Phone number is required for contact.';
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO suppliers (name, contact_person, phone, email, address, company, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $formData['name'],
                    $formData['contact_person'],
                    $formData['phone'],
                    $formData['email'],
                    $formData['address'],
                    $formData['company'],
                    $formData['status']
                ]);

                redirect(BASE_URL . '/modules/suppliers/index.php', 'Supplier registered successfully!', 'success');
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$csrfToken = generateCSRF();
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-plus-circle text-primary"></i> Add Supplier</h2>
        <p class="page-subtitle">Register a pharmaceutical distributor or medicine vendor.</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/modules/suppliers/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Suppliers
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= e($error) ?></div>
<?php endif; ?>

<div class="card shadow-sm" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header"><h5 class="card-title m-0">Vendor Profile</h5></div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div class="row mb-3">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="name">Supplier / Business Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" required
                           placeholder="e.g. Medico Pharma Distribution" value="<?= e($formData['name']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="company">Parent Company / Brand</label>
                    <input type="text" id="company" name="company" class="form-control"
                           placeholder="e.g. GSK Distribution Network" value="<?= e($formData['company']) ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="contact_person">Representative / Contact Person</label>
                    <input type="text" id="contact_person" name="contact_person" class="form-control"
                           placeholder="e.g. Tariq Mehmood" value="<?= e($formData['contact_person']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="phone">Phone / Mobile <span class="text-danger">*</span></label>
                    <input type="text" id="phone" name="phone" class="form-control" required
                           placeholder="e.g. 0300-1234567" value="<?= e($formData['phone']) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="e.g. orders@medicopharma.com" value="<?= e($formData['email']) ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-8 mb-3">
                    <label class="form-label" for="address">Warehouse / Office Address</label>
                    <textarea id="address" name="address" class="form-control" rows="3"
                              placeholder="Complete mailing and shipping address..."><?= e($formData['address']) ?></textarea>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="status">Vendor Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="active" <?= $formData['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $formData['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= BASE_URL ?>/modules/suppliers/index.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Supplier</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

