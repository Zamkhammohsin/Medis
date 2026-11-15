<?php
/**
 * Medis - Delete / Deactivate Supplier
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/modules/suppliers/index.php', 'Invalid request method.', 'error');
}

if (!validateCSRF($_POST['csrf_token'] ?? '')) {
    redirect(BASE_URL . '/modules/suppliers/index.php', 'Security token expired. Please try again.', 'error');
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    redirect(BASE_URL . '/modules/suppliers/index.php', 'Invalid supplier ID.', 'error');
}

$db = getDB();

try {
    // Check if supplier has purchases
    $chk = $db->prepare("SELECT COUNT(*) FROM purchases WHERE supplier_id = ?");
    $chk->execute([$id]);
    $purchaseCount = (int)$chk->fetchColumn();

    if ($purchaseCount > 0) {
        // Soft deactivate to maintain purchase history
        $stmt = $db->prepare("UPDATE suppliers SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$id]);
        redirect(BASE_URL . '/modules/suppliers/index.php', 'Supplier has purchase order history and has been deactivated.', 'warning');
    } else {
        // Hard delete
        $stmt = $db->prepare("DELETE FROM suppliers WHERE id = ?");
        $stmt->execute([$id]);
        redirect(BASE_URL . '/modules/suppliers/index.php', 'Supplier deleted successfully.', 'success');
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    redirect(BASE_URL . '/modules/suppliers/index.php', 'Failed to remove supplier: ' . $e->getMessage(), 'error');
}

