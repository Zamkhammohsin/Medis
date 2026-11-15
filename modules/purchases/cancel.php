<?php
/**
 * Medis - Cancel Purchase Order
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/modules/purchases/index.php', 'Invalid request method.', 'error');
}

if (!validateCSRF($_POST['csrf_token'] ?? '')) {
    redirect(BASE_URL . '/modules/purchases/index.php', 'Security token expired. Please try again.', 'error');
}

$currentUser = getCurrentUser();
$purchaseId = (int)($_POST['purchase_id'] ?? 0);
if (!$purchaseId) {
    redirect(BASE_URL . '/modules/purchases/index.php', 'Invalid purchase ID.', 'error');
}

$db = getDB();

try {
    $pStmt = $db->prepare("SELECT * FROM purchases WHERE id = ?");
    $pStmt->execute([$purchaseId]);
    $purchase = $pStmt->fetch();

    if (!$purchase) {
        redirect(BASE_URL . '/modules/purchases/index.php', 'Purchase order not found.', 'error');
    }

    if ($purchase['status'] !== 'pending') {
        redirect(BASE_URL . "/modules/purchases/view.php?id=$purchaseId", 'Only pending purchase orders can be cancelled.', 'warning');
    }

    if (isManager() && (int)$purchase['branch_id'] !== (int)$currentUser['branch_id']) {
        redirect(BASE_URL . '/modules/purchases/index.php', 'Unauthorized to cancel orders for other branches.', 'error');
    }

    $stmt = $db->prepare("UPDATE purchases SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$purchaseId]);

    redirect(BASE_URL . "/modules/purchases/view.php?id=$purchaseId", 'Purchase order has been cancelled.', 'info');
} catch (PDOException $e) {
    error_log($e->getMessage());
    redirect(BASE_URL . "/modules/purchases/view.php?id=$purchaseId", 'Error cancelling order: ' . $e->getMessage(), 'error');
}

