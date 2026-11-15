<?php
/**
 * Medis - Delete / Deactivate Medicine
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/modules/medicines/index.php', 'Invalid request method.', 'error');
}

if (!validateCSRF($_POST['csrf_token'] ?? '')) {
    redirect(BASE_URL . '/modules/medicines/index.php', 'Security token expired. Please try again.', 'error');
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    redirect(BASE_URL . '/modules/medicines/index.php', 'Invalid medicine ID.', 'error');
}

$db = getDB();

try {
    // Check if there are sales records referencing this medicine
    $salesChk = $db->prepare("SELECT COUNT(*) FROM sale_items WHERE medicine_id = ?");
    $salesChk->execute([$id]);
    $salesCount = (int)$salesChk->fetchColumn();

    // Check if there are purchases records
    $purchasesChk = $db->prepare("SELECT COUNT(*) FROM purchase_items WHERE medicine_id = ?");
    $purchasesChk->execute([$id]);
    $purchasesCount = (int)$purchasesChk->fetchColumn();

    if ($salesCount > 0 || $purchasesCount > 0) {
        // Soft delete: Set status to inactive to preserve financial records
        $stmt = $db->prepare("UPDATE medicines SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$id]);
        redirect(BASE_URL . '/modules/medicines/index.php', 'Medicine has transaction history and has been set to INACTIVE.', 'warning');
    } else {
        // Hard delete: No transaction history exists
        $stmt = $db->prepare("DELETE FROM medicines WHERE id = ?");
        $stmt->execute([$id]);
        redirect(BASE_URL . '/modules/medicines/index.php', 'Medicine deleted permanently.', 'success');
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    redirect(BASE_URL . '/modules/medicines/index.php', 'Failed to remove medicine: ' . $e->getMessage(), 'error');
}

