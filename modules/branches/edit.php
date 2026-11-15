<?php
/**
 * Medis - Edit Branch
 */
$pageTitle = 'Edit Branch';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin']);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    redirect(BASE_URL . '/modules/branches/index.php', 'Invalid branch ID', 'error');
}

$stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
$stmt->execute([$id]);
$branch = $stmt->fetch();
if (!$branch) {
    redirect(BASE_URL . '/modules/branches/index.php', 'Branch not found', 'error');
}

$error = '';
$managers = $db->query("SELECT id, name, email FROM users WHERE role = 'manager' AND status = 'active' ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $name       = trim($_POST['name'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $manager_id = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
        $status     = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (empty($name)) {
            $error = 'Branch name is required.';
        } else {
            try {
                $db->beginTransaction();

                $updStmt = $db->prepare("
                    UPDATE branches SET name = ?, address = ?, phone = ?, manager_id = ?, status = ?
                    WHERE id = ?
                ");
                $updStmt->execute([$name, $address, $phone, $manager_id, $status, $id]);

                // Update manager's branch assignment
                if ($manager_id) {
                    $uStmt = $db->prepare("UPDATE users SET branch_id = ? WHERE id = ?");
                    $uStmt->execute([$id, $manager_id]);
                }

                $db->commit();
                redirect(BASE_URL . '/modules/branches/index.php', 'Branch updated successfully!', 'success');
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
        <h2 class="page-title"><i class="fas fa-edit text-primary"></i> Edit Branch</h2>
        <p class="page-subtitle">Update store details and manager assignment for <strong><?= e($branch['name']) ?></strong>.</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/modules/branches/view.php?id=<?= $branch['id'] ?>" class="btn btn-outline-info">
            <i class="fas fa-eye"></i> View Branch Hub
        </a>
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
                       value="<?= e($branch['name']) ?>">
            </div>

            <div class="row mb-3">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="phone">Phone / Landline</label>
                    <input type="text" id="phone" name="phone" class="form-control"
                           value="<?= e($branch['phone'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="manager_id">Assign Branch Manager</label>
                    <select id="manager_id" name="manager_id" class="form-select">
                        <option value="">-- No Manager Assigned --</option>
                        <?php foreach ($managers as $mgr): ?>
                            <option value="<?= $mgr['id'] ?>" <?= (int)$branch['manager_id'] === (int)$mgr['id'] ? 'selected' : '' ?>>
                                <?= e($mgr['name']) ?> (<?= e($mgr['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="address">Full Physical Address</label>
                <textarea id="address" name="address" class="form-control" rows="3"><?= e($branch['address'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label" for="status">Operating Status</label>
                <select id="status" name="status" class="form-select" style="max-width: 250px;">
                    <option value="active" <?= $branch['status'] === 'active' ? 'selected' : '' ?>>Active (Open for Sales)</option>
                    <option value="inactive" <?= $branch['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Closed / Setup)</option>
                </select>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= BASE_URL ?>/modules/branches/index.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Branch</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

