<?php
/**
 * Medis - Add Branch
 */
$pageTitle = 'Add Branch';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin']);

$db = getDB();
$error = '';

$managers = $db->query("SELECT id, name, email FROM users WHERE role = 'manager' AND status = 'active' ORDER BY name ASC")->fetchAll();

$formData = [
    'name'       => '',
    'address'    => '',
    'phone'      => '',
    'manager_id' => '',
    'status'     => 'active'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $formData['name']       = trim($_POST['name'] ?? '');
        $formData['address']    = trim($_POST['address'] ?? '');
        $formData['phone']      = trim($_POST['phone'] ?? '');
        $formData['manager_id'] = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
        $formData['status']     = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (empty($formData['name'])) {
            $error = 'Branch name is required.';
        } else {
            try {
                $db->beginTransaction();

                $stmt = $db->prepare("
                    INSERT INTO branches (name, address, phone, manager_id, status)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $formData['name'],
                    $formData['address'],
                    $formData['phone'],
                    $formData['manager_id'],
                    $formData['status']
                ]);
                $branchId = (int)$db->lastInsertId();

                if ($formData['manager_id']) {
                    $uStmt = $db->prepare("UPDATE users SET branch_id = ? WHERE id = ?");
                    $uStmt->execute([$branchId, $formData['manager_id']]);
                }

                $db->commit();
                redirect(BASE_URL . '/modules/branches/index.php', 'New branch location registered successfully!', 'success');
            } catch (PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
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
        <h2 class="page-title"><i class="fas fa-plus-circle text-primary"></i> Add New Store Branch</h2>
        <p class="page-subtitle">Register a new pharmacy retail or hospital outlet.</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/modules/branches/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Branches
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= e($error) ?></div>
<?php endif; ?>

<div class="card shadow-sm" style="max-width: 750px; margin: 0 auto;">
    <div class="card-header"><h5 class="card-title m-0">Store Details</h5></div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div class="mb-3">
                <label class="form-label" for="name">Branch / Store Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" class="form-control" required
                       placeholder="e.g. Medis - Westridge Branch" value="<?= e($formData['name']) ?>">
            </div>

            <div class="row mb-3">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="phone">Phone / Landline</label>
                    <input type="text" id="phone" name="phone" class="form-control"
                           placeholder="e.g. 051-5467890" value="<?= e($formData['phone']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="manager_id">Assign Branch Manager</label>
                    <select id="manager_id" name="manager_id" class="form-select">
                        <option value="">-- No Manager Assigned Yet --</option>
                        <?php foreach ($managers as $mgr): ?>
                            <option value="<?= $mgr['id'] ?>" <?= (string)$formData['manager_id'] === (string)$mgr['id'] ? 'selected' : '' ?>>
                                <?= e($mgr['name']) ?> (<?= e($mgr['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="address">Full Physical Address</label>
                <textarea id="address" name="address" class="form-control" rows="3"
                          placeholder="Shop / Plot #, Commercial Area, City..."><?= e($formData['address']) ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label" for="status">Operating Status</label>
                <select id="status" name="status" class="form-select" style="max-width: 250px;">
                    <option value="active" <?= $formData['status'] === 'active' ? 'selected' : '' ?>>Active (Open for Sales)</option>
                    <option value="inactive" <?= $formData['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Closed / Setup)</option>
                </select>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= BASE_URL ?>/modules/branches/index.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Branch</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

