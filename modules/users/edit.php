<?php
/**
 * Medis - Edit User
 */
$pageTitle = 'Edit User';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin']);

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    redirect(BASE_URL . '/modules/users/index.php', 'Invalid user ID', 'error');
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) {
    redirect(BASE_URL . '/modules/users/index.php', 'User not found', 'error');
}

$error = '';
$branches = getAllBranches();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $name      = trim($_POST['name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $role      = in_array($_POST['role'] ?? '', ['admin', 'manager', 'cashier']) ? $_POST['role'] : 'cashier';
        $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
        $status    = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
        $newPass   = $_POST['new_password'] ?? '';

        if (empty($name)) {
            $error = 'Full name is required.';
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (in_array($role, ['manager', 'cashier']) && empty($branch_id)) {
            $error = 'Managers and cashiers must be assigned to a branch.';
        } else {
            // Check email uniqueness
            $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $chk->execute([$email, $id]);
            if ($chk->fetch()) {
                $error = 'Another account already uses this email address.';
            } else {
                try {
                    if (!empty($newPass)) {
                        if (strlen($newPass) < 6) {
                            $error = 'New password must be at least 6 characters.';
                        } else {
                            $hash = password_hash($newPass, PASSWORD_BCRYPT);
                            $upd = $db->prepare("
                                UPDATE users SET name = ?, email = ?, password = ?, role = ?, branch_id = ?, status = ?
                                WHERE id = ?
                            ");
                            $upd->execute([$name, $email, $hash, $role, $branch_id, $status, $id]);
                        }
                    } else {
                        $upd = $db->prepare("
                            UPDATE users SET name = ?, email = ?, role = ?, branch_id = ?, status = ?
                            WHERE id = ?
                        ");
                        $upd->execute([$name, $email, $role, $branch_id, $status, $id]);
                    }

                    if (empty($error)) {
                        redirect(BASE_URL . '/modules/users/index.php', 'User account updated successfully!', 'success');
                    }
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
        <h2 class="page-title"><i class="fas fa-user-edit text-primary"></i> Edit User Account</h2>
        <p class="page-subtitle">Update permissions and profile for <strong><?= e($user['name']) ?></strong>.</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/modules/users/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Users
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= e($error) ?></div>
<?php endif; ?>

<div class="card shadow-sm" style="max-width: 750px; margin: 0 auto;">
    <div class="card-header"><h5 class="card-title m-0">User Account Specifications</h5></div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div class="row mb-3">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="name">Full Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" required
                           value="<?= e($user['name']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="email">Email Address <span class="text-danger">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" required
                           value="<?= e($user['email']) ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="role">User Role <span class="text-danger">*</span></label>
                    <select id="role" name="role" class="form-select" required>
                        <option value="cashier" <?= $user['role'] === 'cashier' ? 'selected' : '' ?>>Cashier (POS & Sales)</option>
                        <option value="manager" <?= $user['role'] === 'manager' ? 'selected' : '' ?>>Branch Manager</option>
                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrator (Full Access)</option>
                    </select>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label" for="branch_id">Store Branch</label>
                    <select id="branch_id" name="branch_id" class="form-select">
                        <option value="">-- No Branch (Central Admin) --</option>
                        <?php foreach ($branches as $br): ?>
                            <option value="<?= $br['id'] ?>" <?= (int)$user['branch_id'] === (int)$br['id'] ? 'selected' : '' ?>>
                                <?= e($br['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label" for="status">Account Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active (Can Login)</option>
                        <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Suspended)</option>
                    </select>
                </div>
            </div>

            <div class="mb-3 p-3 bg-light rounded">
                <label class="form-label fw-bold" for="new_password">Change Password (Leave blank to keep current)</label>
                <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Enter new password if changing...">
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= BASE_URL ?>/modules/users/index.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update User</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

