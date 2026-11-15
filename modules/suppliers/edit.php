<?php
/**
 * Medis - Edit Supplier
 */
$pageTitle = 'Edit Supplier';
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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $name           = trim($_POST['name'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $phone          = trim($_POST['phone'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $address        = trim($_POST['address'] ?? '');
        $company        = trim($_POST['company'] ?? '');
        $status         = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (empty($name)) {
            $error = 'Supplier name is required.';
        } elseif (empty($phone)) {
            $error = 'Phone number is required.';
        } else {
            try {
                $updStmt = $db->prepare("
                    UPDATE suppliers SET
                        name = ?, contact_person = ?, phone = ?, email = ?,
                        address = ?, company = ?, status = ?
                    WHERE id = ?
                ");
                $updStmt->execute([$name, $contact_person, $phone, $email, $address, $company, $status, $id]);

                redirect(BASE_URL . '/modules/suppliers/index.php', 'Supplier updated successfully!', 'success');
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
        <h2 class="page-title"><i class="fas fa-edit text-primary"></i> Edit Supplier</h2>
        <p class="page-subtitle">Update vendor contact details for <strong><?= e($supplier['name']) ?></strong>.</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/modules/suppliers/view.php?id=<?= $supplier['id'] ?>" class="btn btn-outline-info">
            <i class="fas fa-eye"></i> View Order History
        </a>
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
                           value="<?= e($supplier['name']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="company">Parent Company / Brand</label>
                    <input type="text" id="company" name="company" class="form-control"
                           value="<?= e($supplier['company'] ?? '') ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="contact_person">Representative / Contact Person</label>
                    <input type="text" id="contact_person" name="contact_person" class="form-control"
                           value="<?= e($supplier['contact_person'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="phone">Phone / Mobile <span class="text-danger">*</span></label>
                    <input type="text" id="phone" name="phone" class="form-control" required
                           value="<?= e($supplier['phone'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control"
                           value="<?= e($supplier['email'] ?? '') ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-8 mb-3">
                    <label class="form-label" for="address">Warehouse / Office Address</label>
                    <textarea id="address" name="address" class="form-control" rows="3"><?= e($supplier['address'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="status">Vendor Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="active" <?= $supplier['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $supplier['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= BASE_URL ?>/modules/suppliers/index.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Supplier</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

