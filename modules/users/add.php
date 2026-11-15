<?php
/**
 * Medis - Add User
 */
$pageTitle = 'Add User';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin']);

$db = getDB();
$error = '';
$branches = getAllBranches();

$formData = [
    'name'      => '',
    'email'     => '',
    'role'      => 'cashier',
    'branch_id' => isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : '',
    'status'    => 'active'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $formData['name']      = trim($_POST['name'] ?? '');
        $formData['email']     = trim($_POST['email'] ?? '');
        $password              = $_POST['password'] ?? '';
        $confirmPassword       = $_POST['confirm_password'] ?? '';
        $formData['role']      = in_array($_POST['role'] ?? '', ['admin', 'manager', 'cashier']) ? $_POST['role'] : 'cashier';
        $formData['branch_id'] = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
        $formData['status']    = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (empty($formData['name'])) {
            $error = 'Full name is required.';
        } elseif (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Password confirmation does not match.';
        } elseif (in_array($formData['role'], ['manager', 'cashier']) && empty($formData['branch_id'])) {
            $error = 'Branch managers and cashiers must be assigned to a specific store branch.';
        } else {
            // Check email uniqueness
            $chk = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $chk->execute([$formData['email']]);
            if ($chk->fetch()) {
                $error = 'An account with this email address already exists.';
            } else {
                try {
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $db->prepare("
                        INSERT INTO users (name, email, password, role, branch_id, status)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $formData['name'],
                        $formData['email'],
                        $passwordHash,
                        $formData['role'],
                        $formData['branch_id'],
                        $formData['status']
                    ]);

                    redirect(BASE_URL . '/modules/users/index.php', 'User account created successfully!', 'success');
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
        <h2 class="page-title"><i class="fas fa-user-plus text-primary"></i> Create User Account</h2>
        <p class="page-subtitle">Add a staff member or manager with specific system permissions.</p>
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
                           placeholder="e.g. Farhan Ali" value="<?= e($formData['name']) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="email">Email Address <span class="text-danger">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" required
                           placeholder="e.g. farhan@medis.pk" value="<?= e($formData['email']) ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="password">Password <span class="text-danger">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="Minimum 6 characters">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="confirm_password">Confirm Password <span class="text-danger">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="Re-type password">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="role">User Role <span class="text-danger">*</span></label>
                    <select id="role" name="role" class="form-select" required onchange="toggleBranchRequirement()">
                        <option value="cashier" <?= $formData['role'] === 'cashier' ? 'selected' : '' ?>>Cashier (POS & Sales)</option>
                        <option value="manager" <?= $formData['role'] === 'manager' ? 'selected' : '' ?>>Branch Manager</option>
                        <option value="admin" <?= $formData['role'] === 'admin' ? 'selected' : '' ?>>Administrator (Full Access)</option>
                    </select>
                </div>

                <div class="col-md-4 mb-3" id="branchSelectGroup">
                    <label class="form-label" for="branch_id">Store Branch <span class="text-danger" id="branchStar">*</span></label>
                    <select id="branch_id" name="branch_id" class="form-select">
                        <option value="">-- Select Store Branch --</option>
                        <?php foreach ($branches as $br): ?>
                            <option value="<?= $br['id'] ?>" <?= (string)$formData['branch_id'] === (string)$br['id'] ? 'selected' : '' ?>>
                                <?= e($br['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label" for="status">Account Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="active" <?= $formData['status'] === 'active' ? 'selected' : '' ?>>Active (Can Login)</option>
                        <option value="inactive" <?= $formData['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Suspended)</option>
                    </select>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="<?= BASE_URL ?>/modules/users/index.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Account</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleBranchRequirement() {
    const role = document.getElementById('role').value;
    const branchStar = document.getElementById('branchStar');
    if (role === 'admin') {
        if (branchStar) branchStar.style.display = 'none';
    } else {
        if (branchStar) branchStar.style.display = 'inline';
    }
}
document.addEventListener('DOMContentLoaded', toggleBranchRequirement);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

