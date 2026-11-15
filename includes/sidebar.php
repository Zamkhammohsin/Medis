<?php
/**
 * Medis - Sidebar Navigation
 */
$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="<?= BASE_URL ?>/dashboard.php" class="sidebar-brand">
            <i class="fas fa-pills"></i>
            <span class="brand-text">Medis</span>
        </a>
        <button class="sidebar-toggle-btn" id="sidebarToggle" title="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">
            <i class="fas fa-user-circle"></i>
        </div>
        <div class="user-info">
            <span class="user-name"><?= e($currentUser['name'] ?? 'User') ?></span>
            <span class="user-role badge-role-<?= e($currentUser['role'] ?? '') ?>">
                <?= ucfirst(e($currentUser['role'] ?? '')) ?>
            </span>
        </div>
    </div>

    <ul class="sidebar-nav">
        <li class="nav-section">Main</li>

        <li class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt nav-icon"></i>
                <span class="nav-text">Dashboard</span>
            </a>
        </li>

        <?php if (!isCashier()): ?>
        <li class="nav-section">Inventory</li>
        <?php endif; ?>

        <li class="nav-item <?= in_array($currentPage, ['medicines','add_medicine','edit_medicine']) ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/medicines/index.php" class="nav-link">
                <i class="fas fa-capsules nav-icon"></i>
                <span class="nav-text">Medicines</span>
            </a>
        </li>

        <li class="nav-item <?= in_array($currentPage, ['inventory','stock']) ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/inventory/index.php" class="nav-link">
                <i class="fas fa-boxes nav-icon"></i>
                <span class="nav-text">Inventory</span>
                <?php
                $lowStockCount = getLowStockCount($currentUser['branch_id']);
                if ($lowStockCount > 0):
                ?>
                <span class="nav-badge badge-danger"><?= $lowStockCount ?></span>
                <?php endif; ?>
            </a>
        </li>

        <li class="nav-section">Sales</li>

        <li class="nav-item <?= in_array($currentPage, ['pos','point_of_sale']) ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/pos/index.php" class="nav-link">
                <i class="fas fa-cash-register nav-icon"></i>
                <span class="nav-text">Point of Sale</span>
            </a>
        </li>

        <li class="nav-item <?= in_array($currentPage, ['sales','sale_detail']) ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/sales/index.php" class="nav-link">
                <i class="fas fa-receipt nav-icon"></i>
                <span class="nav-text">Sales History</span>
            </a>
        </li>

        <?php if (!isCashier()): ?>
        <li class="nav-section">Procurement</li>

        <li class="nav-item <?= in_array($currentPage, ['purchases','add_purchase']) ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/purchases/index.php" class="nav-link">
                <i class="fas fa-shopping-cart nav-icon"></i>
                <span class="nav-text">Purchases</span>
            </a>
        </li>

        <li class="nav-item <?= in_array($currentPage, ['suppliers','add_supplier']) ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/suppliers/index.php" class="nav-link">
                <i class="fas fa-truck nav-icon"></i>
                <span class="nav-text">Suppliers</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (isAdmin()): ?>
        <li class="nav-section">Administration</li>

        <li class="nav-item <?= in_array($currentPage, ['branches','add_branch']) ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/branches/index.php" class="nav-link">
                <i class="fas fa-store nav-icon"></i>
                <span class="nav-text">Branches</span>
            </a>
        </li>

        <li class="nav-item <?= in_array($currentPage, ['users','add_user']) ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/users/index.php" class="nav-link">
                <i class="fas fa-users nav-icon"></i>
                <span class="nav-text">Users</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (isAdmin() || isManager()): ?>
        <li class="nav-item <?= in_array($currentPage, ['reports','report','daily_sales','monthly_sales','branch_sales','top_medicines','low_stock','expiry_report','purchase_report']) ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>/modules/reports/index.php" class="nav-link">
                <i class="fas fa-chart-bar nav-icon"></i>
                <span class="nav-text">Reports</span>
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-section">Account</li>

        <li class="nav-item">
            <a href="<?= BASE_URL ?>/logout.php" class="nav-link text-danger-nav"
               onclick="return confirm('Are you sure you want to logout?')">
                <i class="fas fa-sign-out-alt nav-icon"></i>
                <span class="nav-text">Logout</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <span><?= APP_NAME ?> v<?= APP_VERSION ?></span>
    </div>
</nav>
