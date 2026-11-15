<?php
/**
 * Medis - Users Management
 */
$pageTitle = 'Users Management';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin']);

$currentUser = getCurrentUser();
$db = getDB();

$roleFilter   = trim($_GET['role'] ?? 'all');
$branchFilter = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? (int)$_GET['branch_id'] : null;
$search       = trim($_GET['search'] ?? '');

$branches = getAllBranches();

$where = ["1=1"];
$params = [];

if ($roleFilter !== 'all' && in_array($roleFilter, ['admin', 'manager', 'cashier'])) {
    $where[] = "u.role = ?";
    $params[] = $roleFilter;
}

if ($branchFilter) {
    $where[] = "u.branch_id = ?";
    $params[] = $branchFilter;
}

if ($search !== '') {
    $where[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
}

$whereSql = implode(' AND ', $where);

$sql = "
    SELECT u.*, b.name AS branch_name,
           (SELECT COUNT(*) FROM sales WHERE user_id = u.id) AS sales_processed
    FROM users u
    LEFT JOIN branches b ON b.id = u.branch_id
    WHERE $whereSql
    ORDER BY u.role ASC, u.name ASC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$csrfToken = generateCSRF();
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-users-cog text-primary"></i> User Accounts & Access Control</h2>
        <p class="page-subtitle">Configure system users, assign role privileges (Admin, Manager, Cashier), and set store assignments.</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/modules/users/add.php" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Add New User
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="GET" action="" class="row align-items-center gap-2">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search user name or email..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select form-select-sm">
                    <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>All Roles</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Administrator</option>
                    <option value="manager" <?= $roleFilter === 'manager' ? 'selected' : '' ?>>Branch Manager</option>
                    <option value="cashier" <?= $roleFilter === 'cashier' ? 'selected' : '' ?>>Cashier / POS Operator</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="branch_id" class="form-select form-select-sm">
                    <option value="">All Branches</option>
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= $br['id'] ?>" <?= $branchFilter === (int)$br['id'] ? 'selected' : '' ?>><?= e($br['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-secondary"><i class="fas fa-filter"></i></button>
                <a href="<?= BASE_URL ?>/modules/users/index.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title m-0">Registered User Accounts (<?= count($users) ?> accounts)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 data-table">
                <thead class="table-light">
                    <tr>
                        <th>User Name</th>
                        <th>Email Address</th>
                        <th>System Role</th>
                        <th>Assigned Branch</th>
                        <th>Sales Processed</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No users found matching the filter.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <strong><?= e($u['name']) ?></strong>
                                    <?php if ((int)$u['id'] === (int)$currentUser['id']): ?>
                                        <span class="badge badge-light text-primary">(You)</span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= e($u['email']) ?></code></td>
                                <td>
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="badge badge-purple"><i class="fas fa-shield-alt"></i> Administrator</span>
                                    <?php elseif ($u['role'] === 'manager'): ?>
                                        <span class="badge badge-info"><i class="fas fa-user-tie"></i> Manager</span>
                                    <?php else: ?>
                                        <span class="badge badge-success"><i class="fas fa-cash-register"></i> Cashier</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($u['branch_name'] ?? '— Central / All —') ?></td>
                                <td><?= $u['sales_processed'] ?> invoices</td>
                                <td>
                                    <span class="badge <?= $u['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>">
                                        <?= ucfirst(e($u['status'])) ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><?= formatDate($u['created_at']) ?></small></td>
                                <td class="text-center">
                                    <div class="btn-group gap-1">
                                        <a href="<?= BASE_URL ?>/modules/users/edit.php?id=<?= $u['id'] ?>" class="btn btn-xs btn-outline-primary" title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                                            <form method="POST" action="<?= BASE_URL ?>/modules/users/toggle_status.php" style="display:inline;" onsubmit="return confirm('Toggle status for <?= e($u['name']) ?>?')">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <button type="submit" class="btn btn-xs <?= $u['status'] === 'active' ? 'btn-outline-danger' : 'btn-outline-success' ?>" title="<?= $u['status'] === 'active' ? 'Deactivate Account' : 'Activate Account' ?>">
                                                    <i class="fas fa-<?= $u['status'] === 'active' ? 'ban' : 'check' ?>"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

