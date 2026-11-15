<?php
/**
 * Medis - Page Header
 * Usage: require_once __DIR__ . '/../includes/header.php';
 * Set $pageTitle before including.
 */
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/database.php';
}
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$pageTitle  = $pageTitle ?? 'Dashboard';
$flash      = getFlash();
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
          integrity="sha512-Avb2QiuDEEvB4bZJYdft2mNjVShBftLdPG8FJ0V7irTLQ8Uo0qcPxh4Plq7G5tGm0rU+1SPhVotteLpBERwTkw=="
          crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- App CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="wrapper">

    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <div class="main-content" id="mainContent">
        <!-- Top Navbar -->
        <nav class="topbar">
            <div class="topbar-left">
                <button class="btn-icon sidebar-mobile-toggle" id="mobileSidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="breadcrumb-nav">
                    <span class="breadcrumb-item"><i class="fas fa-home"></i></span>
                    <span class="breadcrumb-sep">/</span>
                    <span class="breadcrumb-item active"><?= e($pageTitle) ?></span>
                </div>
            </div>

            <div class="topbar-right">
                <!-- Search -->
                <div class="topbar-search">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="globalSearch" placeholder="Search medicines..." class="search-input">
                </div>

                <!-- Notifications -->
                <div class="topbar-notifications dropdown">
                    <?php
                    $lowStock = getLowStockCount($currentUser['branch_id']);
                    $expiring = getExpiringCount(90, $currentUser['branch_id']);
                    $totalAlerts = $lowStock + $expiring;
                    ?>
                    <button class="btn-icon dropdown-toggle" id="notificationsBtn" title="Alerts">
                        <i class="fas fa-bell"></i>
                        <?php if ($totalAlerts > 0): ?>
                        <span class="notification-badge"><?= $totalAlerts ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right" id="notificationsMenu">
                        <div class="dropdown-header">
                            <span>Alerts (<?= $totalAlerts ?>)</span>
                        </div>
                        <?php if ($lowStock > 0): ?>
                        <a href="<?= BASE_URL ?>/modules/inventory/index.php?filter=low_stock" class="dropdown-item">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                            <?= $lowStock ?> medicines low in stock
                        </a>
                        <?php endif; ?>
                        <?php if ($expiring > 0): ?>
                        <a href="<?= BASE_URL ?>/modules/inventory/index.php?filter=expiring" class="dropdown-item">
                            <i class="fas fa-calendar-times text-danger"></i>
                            <?= $expiring ?> batches expiring soon
                        </a>
                        <?php endif; ?>
                        <?php if ($totalAlerts === 0): ?>
                        <div class="dropdown-item text-muted">No alerts</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- User Profile -->
                <div class="topbar-user dropdown">
                    <button class="btn-user dropdown-toggle" id="userBtn">
                        <div class="user-avatar-sm">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="user-info-sm">
                            <span class="user-name-sm"><?= e($currentUser['name'] ?? '') ?></span>
                            <span class="user-role-sm"><?= ucfirst(e($currentUser['role'] ?? '')) ?></span>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right" id="userMenu">
                        <div class="dropdown-header">
                            <strong><?= e($currentUser['name'] ?? '') ?></strong>
                            <small><?= e($currentUser['email'] ?? '') ?></small>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="<?= BASE_URL ?>/logout.php" class="dropdown-item text-danger"
                           onclick="return confirm('Logout from Medis?')">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Flash Message -->
        <?php if (!empty($flash['message'])): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible" id="flashMessage">
            <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= e($flash['message']) ?>
            <button class="alert-close" onclick="this.parentElement.remove()">×</button>
        </div>
        <?php endif; ?>

        <!-- Page Content starts here -->
        <div class="page-content">
