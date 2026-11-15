<?php
/**
 * Medis - Receive Purchase Order
 * Credits items to branch batches and logs stock movements
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
    redirect(BASE_URL . '/modules/purchases/index.php', 'Security check failed. Please try again.', 'error');
}

$currentUser = getCurrentUser();
$purchaseId = (int)($_POST['purchase_id'] ?? 0);
if (!$purchaseId) {
    redirect(BASE_URL . '/modules/purchases/index.php', 'Invalid purchase ID.', 'error');
}

$db = getDB();

try {
    $db->beginTransaction();

    // 1. Fetch purchase with FOR UPDATE lock
    $pStmt = $db->prepare("SELECT * FROM purchases WHERE id = ? FOR UPDATE");
    $pStmt->execute([$purchaseId]);
    $purchase = $pStmt->fetch();

    if (!$purchase) {
        $db->rollBack();
        redirect(BASE_URL . '/modules/purchases/index.php', 'Purchase order not found.', 'error');
    }

    if ($purchase['status'] !== 'pending') {
        $db->rollBack();
        redirect(BASE_URL . "/modules/purchases/view.php?id=$purchaseId", 'This order has already been processed or cancelled.', 'warning');
    }

    // Branch authorization check
    if (isManager() && (int)$purchase['branch_id'] !== (int)$currentUser['branch_id']) {
        $db->rollBack();
        redirect(BASE_URL . '/modules/purchases/index.php', 'Unauthorized to receive shipments for another branch.', 'error');
    }

    // 2. Fetch purchase items
    $itemStmt = $db->prepare("SELECT * FROM purchase_items WHERE purchase_id = ?");
    $itemStmt->execute([$purchaseId]);
    $items = $itemStmt->fetchAll();

    if (empty($items)) {
        $db->rollBack();
        redirect(BASE_URL . "/modules/purchases/view.php?id=$purchaseId", 'No items found in this purchase order.', 'error');
    }

    // 3. Update status to received
    $updPurchase = $db->prepare("UPDATE purchases SET status = 'received', updated_at = NOW() WHERE id = ?");
    $updPurchase->execute([$purchaseId]);

    // Prepared statements for batch upsert & movement
    $findBatchStmt = $db->prepare("
        SELECT id, quantity FROM batches 
        WHERE medicine_id = ? AND branch_id = ? AND batch_number = ?
        LIMIT 1
    ");
    $updBatchStmt = $db->prepare("UPDATE batches SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?");
    $insBatchStmt = $db->prepare("
        INSERT INTO batches (medicine_id, branch_id, batch_number, quantity, purchase_price, expiry_date)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $movStmt = $db->prepare("
        INSERT INTO stock_movements (medicine_id, branch_id, batch_id, type, quantity, reference_id, reference_type, notes)
        VALUES (?, ?, ?, 'purchase', ?, ?, 'purchase', ?)
    ");

    // 4. Process each purchase item
    foreach ($items as $item) {
        $medId    = (int)$item['medicine_id'];
        $branchId = (int)$purchase['branch_id'];
        $batchNo  = trim($item['batch_number']);
        $qty      = (int)$item['quantity'];
        $cost     = (float)$item['purchase_price'];
        $expiry   = $item['expiry_date'] ?: date('Y-m-d', strtotime('+2 years'));

        // Check if batch exists
        $findBatchStmt->execute([$medId, $branchId, $batchNo]);
        $existingBatch = $findBatchStmt->fetch();

        $batchId = null;
        if ($existingBatch) {
            $batchId = (int)$existingBatch['id'];
            $updBatchStmt->execute([$qty, $batchId]);
        } else {
            $insBatchStmt->execute([$medId, $branchId, $batchNo, $qty, $cost, $expiry]);
            $batchId = (int)$db->lastInsertId();
        }

        // Record stock movement (positive quantity)
        $movStmt->execute([
            $medId,
            $branchId,
            $batchId,
            $qty,
            $purchaseId,
            "Purchase Received: {$purchase['invoice_number']} (Batch: $batchNo)"
        ]);
    }

    $db->commit();
    redirect(BASE_URL . "/modules/purchases/view.php?id=$purchaseId", 'Shipment successfully received! Inventory credited to store.', 'success');

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Receive error: ' . $e->getMessage());
    redirect(BASE_URL . "/modules/purchases/view.php?id=$purchaseId", 'Failed to receive order: ' . $e->getMessage(), 'error');
}

