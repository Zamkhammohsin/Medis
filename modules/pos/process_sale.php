<?php
/**
 * Medis - POS Backend Sale Processor
 * Handles atomic inventory deduction and sale finalization with InnoDB transactions
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User session expired. Please log in again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload received.']);
    exit;
}

// CSRF check
$csrf = $data['csrf_token'] ?? '';
if (!validateCSRF($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF security token invalid or expired.']);
    exit;
}

$currentUser = getCurrentUser();
$branchId = null;

if (isAdmin()) {
    $branchId = !empty($data['branch_id']) ? (int)$data['branch_id'] : $currentUser['branch_id'];
} else {
    $branchId = (int)$currentUser['branch_id'];
}

if (!$branchId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid branch must be assigned for POS billing.']);
    exit;
}

$cart = $data['cart'] ?? [];
if (empty($cart) || !is_array($cart)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cart is empty. Please add items before completing the sale.']);
    exit;
}

$db = getDB();

try {
    $db->beginTransaction();

    $subtotal = 0.0;
    $validatedItems = [];

    // 1. Validate each cart item against database and lock batches
    foreach ($cart as $item) {
        $medId = (int)($item['medicine_id'] ?? 0);
        $qtyNeeded = (int)($item['qty'] ?? 0);

        if (!$medId || $qtyNeeded <= 0) {
            continue;
        }

        // Fetch official medicine details from DB
        $medStmt = $db->prepare("SELECT id, name, selling_price, status FROM medicines WHERE id = ?");
        $medStmt->execute([$medId]);
        $medicine = $medStmt->fetch();

        if (!$medicine || $medicine['status'] !== 'active') {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => "Medicine not found or inactive: ID #$medId"]);
            exit;
        }

        $unitPrice = (float)$medicine['selling_price'];
        $itemSubtotal = $unitPrice * $qtyNeeded;
        $subtotal += $itemSubtotal;

        // Fetch available non-expired batches for this medicine in this branch (FIFO: earliest expiry first)
        $batchStmt = $db->prepare("
            SELECT id, batch_number, quantity, purchase_price, expiry_date
            FROM batches
            WHERE medicine_id = ?
              AND branch_id = ?
              AND quantity > 0
              AND expiry_date > CURDATE()
            ORDER BY expiry_date ASC
            FOR UPDATE
        ");
        $batchStmt->execute([$medId, $branchId]);
        $batches = $batchStmt->fetchAll();

        $totalAvailable = 0;
        foreach ($batches as $b) {
            $totalAvailable += (int)$b['quantity'];
        }

        if ($totalAvailable < $qtyNeeded) {
            $db->rollBack();
            echo json_encode([
                'success' => false,
                'message' => "Insufficient stock for {$medicine['name']}. Requested: $qtyNeeded, Available non-expired: $totalAvailable"
            ]);
            exit;
        }

        // Deduct from batches FIFO
        $remaining = $qtyNeeded;
        $deductions = [];
        foreach ($batches as $batch) {
            if ($remaining <= 0) break;
            $batchQty = (int)$batch['quantity'];
            $deduct = min($remaining, $batchQty);
            $deductions[] = [
                'batch_id'       => (int)$batch['id'],
                'batch_number'   => $batch['batch_number'],
                'qty'            => $deduct,
                'purchase_price' => (float)$batch['purchase_price']
            ];
            $remaining -= $deduct;
        }

        $validatedItems[] = [
            'medicine_id'   => $medId,
            'medicine_name' => $medicine['name'],
            'unit_price'    => $unitPrice,
            'qty'           => $qtyNeeded,
            'subtotal'      => $itemSubtotal,
            'deductions'    => $deductions
        ];
    }

    if (empty($validatedItems)) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'No valid items in the cart to process.']);
        exit;
    }

    // 2. Calculate Final Financials
    $discount = max(0.0, (float)($data['discount'] ?? 0));
    if ($discount > $subtotal) {
        $discount = $subtotal;
    }
    $totalAmount = round($subtotal - $discount, 2);

    $paymentMethod = $data['payment_method'] ?? 'cash';
    if (!in_array($paymentMethod, ['cash', 'card', 'online'])) {
        $paymentMethod = 'cash';
    }

    $amountPaid = (float)($data['amount_paid'] ?? $totalAmount);
    if ($paymentMethod === 'cash') {
        if ($amountPaid < $totalAmount) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Paid amount is less than total bill amount.']);
            exit;
        }
        $changeAmount = round($amountPaid - $totalAmount, 2);
    } else {
        $amountPaid = $totalAmount;
        $changeAmount = 0.0;
    }

    // 3. Generate unique invoice number
    $invoiceNumber = generateInvoiceNumber('INV');

    // 4. Insert into `sales` table
    $saleStmt = $db->prepare("
        INSERT INTO sales 
        (branch_id, user_id, invoice_number, subtotal, discount, total_amount, payment_method, amount_paid, change_amount, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)
    ");
    $saleStmt->execute([
        $branchId,
        $currentUser['id'],
        $invoiceNumber,
        $subtotal,
        $discount,
        $totalAmount,
        $paymentMethod,
        $amountPaid,
        $changeAmount,
        $data['notes'] ?? 'POS Sale'
    ]);
    $saleId = (int)$db->lastInsertId();

    // 5. Insert `sale_items`, update `batches`, and log `stock_movements`
    $itemInsertStmt = $db->prepare("
        INSERT INTO sale_items (sale_id, medicine_id, batch_id, quantity, unit_price, subtotal)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $batchUpdateStmt = $db->prepare("
        UPDATE batches SET quantity = quantity - ?, updated_at = NOW() WHERE id = ?
    ");
    $movStmt = $db->prepare("
        INSERT INTO stock_movements (medicine_id, branch_id, batch_id, type, quantity, reference_id, reference_type, notes)
        VALUES (?, ?, ?, 'sale', ?, ?, 'sale', ?)
    ");

    foreach ($validatedItems as $item) {
        foreach ($item['deductions'] as $ded) {
            $dedSubtotal = round($item['unit_price'] * $ded['qty'], 2);

            // Record sale item line for each batch portion
            $itemInsertStmt->execute([
                $saleId,
                $item['medicine_id'],
                $ded['batch_id'],
                $ded['qty'],
                $item['unit_price'],
                $dedSubtotal
            ]);

            // Deduct stock from the batch
            $batchUpdateStmt->execute([
                $ded['qty'],
                $ded['batch_id']
            ]);

            // Create stock movement audit record (negative qty for sale)
            $movStmt->execute([
                $item['medicine_id'],
                $branchId,
                $ded['batch_id'],
                -$ded['qty'],
                $saleId,
                "POS Sale Invoice: $invoiceNumber (Batch {$ded['batch_number']})"
            ]);
        }
    }

    // 6. Record Payment
    $payStmt = $db->prepare("
        INSERT INTO payments (sale_id, amount, payment_method)
        VALUES (?, ?, ?)
    ");
    $payStmt->execute([$saleId, $amountPaid, $paymentMethod]);

    // 7. Commit Transaction
    $db->commit();

    echo json_encode([
        'success'        => true,
        'message'        => 'Sale completed successfully!',
        'sale_id'        => $saleId,
        'invoice_number' => $invoiceNumber,
        'total_amount'   => $totalAmount,
        'amount_paid'    => $amountPaid,
        'change_amount'  => $changeAmount
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Sale processing error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Sale transaction error: ' . $e->getMessage()]);
}

