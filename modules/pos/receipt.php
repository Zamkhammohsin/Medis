<?php
/**
 * Medis - Printable POS Receipt
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();

$db = getDB();
$saleId  = (int)($_GET['id'] ?? 0);
$invoice = trim($_GET['invoice'] ?? '');

if ($saleId) {
    $stmt = $db->prepare("
        SELECT s.*, br.name AS branch_name, br.address AS branch_address, br.phone AS branch_phone,
               u.name AS cashier_name
        FROM sales s
        JOIN branches br ON br.id = s.branch_id
        LEFT JOIN users u ON u.id = s.user_id
        WHERE s.id = ?
    ");
    $stmt->execute([$saleId]);
} elseif ($invoice !== '') {
    $stmt = $db->prepare("
        SELECT s.*, br.name AS branch_name, br.address AS branch_address, br.phone AS branch_phone,
               u.name AS cashier_name
        FROM sales s
        JOIN branches br ON br.id = s.branch_id
        LEFT JOIN users u ON u.id = s.user_id
        WHERE s.invoice_number = ?
    ");
    $stmt->execute([$invoice]);
} else {
    die('Receipt not found. Missing invoice or ID parameter.');
}

$sale = $stmt->fetch();
if (!$sale) {
    die('Sales invoice not found.');
}

// Fetch sale items
$itemStmt = $db->prepare("
    SELECT si.*, m.name AS medicine_name, b.batch_number, b.expiry_date
    FROM sale_items si
    JOIN medicines m ON m.id = si.medicine_id
    LEFT JOIN batches b ON b.id = si.batch_id
    WHERE si.sale_id = ?
    ORDER BY si.id ASC
");
$itemStmt->execute([$sale['id']]);
$items = $itemStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt — <?= e($sale['invoice_number']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Courier New', Courier, monospace;
            background: #f1f5f9;
            color: #000;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .receipt-card {
            background: #ffffff;
            width: 320px;
            padding: 20px 16px;
            border-radius: 6px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .receipt-header {
            border-bottom: 1px dashed #666;
            padding-bottom: 12px;
            margin-bottom: 12px;
            text-align: center;
        }
        .pharmacy-title {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .branch-info {
            font-size: 12px;
            margin-top: 4px;
            line-height: 1.4;
        }
        .meta-info {
            font-size: 11px;
            margin-bottom: 12px;
            line-height: 1.5;
            border-bottom: 1px dashed #666;
            padding-bottom: 8px;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 12px;
        }
        .items-table th {
            border-bottom: 1px solid #000;
            padding: 4px 0;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
        }
        .items-table td {
            padding: 5px 0;
            vertical-align: top;
        }
        .calc-section {
            border-top: 1px dashed #666;
            padding-top: 8px;
            font-size: 12px;
            line-height: 1.6;
        }
        .calc-row {
            display: flex;
            justify-content: space-between;
        }
        .calc-row.grand-total {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 6px 0;
            margin: 6px 0;
            font-size: 15px;
            font-weight: bold;
        }
        .receipt-footer {
            margin-top: 16px;
            text-align: center;
            font-size: 11px;
            border-top: 1px dashed #666;
            padding-top: 12px;
            line-height: 1.4;
        }
        .action-buttons {
            margin-top: 16px;
            display: flex;
            gap: 10px;
        }
        .btn-print {
            padding: 8px 16px;
            background: #1e3a5f;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
        }
        .btn-close-window {
            padding: 8px 16px;
            background: #64748b;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .receipt-card { box-shadow: none; width: 100%; padding: 0; }
            .action-buttons { display: none !important; }
        }
    </style>
</head>
<body>

<div class="receipt-card">
    <div class="receipt-header">
        <div class="pharmacy-title"><?= APP_NAME ?></div>
        <div class="branch-info">
            <strong><?= e($sale['branch_name']) ?></strong><br>
            <?= e($sale['branch_address'] ?? '') ?><br>
            Phone: <?= e($sale['branch_phone'] ?? '—') ?>
        </div>
    </div>

    <div class="meta-info">
        <div class="meta-row">
            <span>Invoice:</span>
            <span class="bold"><?= e($sale['invoice_number']) ?></span>
        </div>
        <div class="meta-row">
            <span>Date & Time:</span>
            <span><?= date('d-M-Y H:i', strtotime($sale['created_at'])) ?></span>
        </div>
        <div class="meta-row">
            <span>Cashier:</span>
            <span><?= e($sale['cashier_name'] ?? 'Staff') ?></span>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 50%;">Item</th>
                <th class="text-center" style="width: 15%;">Qty</th>
                <th class="text-right" style="width: 15%;">Rate</th>
                <th class="text-right" style="width: 20%;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <?= e($item['medicine_name']) ?><br>
                        <small style="font-size: 9px; color:#555;">B#<?= e($item['batch_number'] ?? '—') ?></small>
                    </td>
                    <td class="text-center"><?= $item['quantity'] ?></td>
                    <td class="text-right"><?= number_format((float)$item['unit_price'], 2) ?></td>
                    <td class="text-right bold"><?= number_format((float)$item['subtotal'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="calc-section">
        <div class="calc-row">
            <span>Subtotal:</span>
            <span>PKR <?= number_format((float)$sale['subtotal'], 2) ?></span>
        </div>
        <?php if ((float)$sale['discount'] > 0): ?>
        <div class="calc-row">
            <span>Discount:</span>
            <span>-PKR <?= number_format((float)$sale['discount'], 2) ?></span>
        </div>
        <?php endif; ?>
        <div class="calc-row grand-total">
            <span>NET TOTAL:</span>
            <span>PKR <?= number_format((float)$sale['total_amount'], 2) ?></span>
        </div>
        <div class="calc-row">
            <span>Payment Mode:</span>
            <span><?= strtoupper(e($sale['payment_method'])) ?></span>
        </div>
        <div class="calc-row">
            <span>Amount Paid:</span>
            <span>PKR <?= number_format((float)$sale['amount_paid'], 2) ?></span>
        </div>
        <div class="calc-row">
            <span>Change Returned:</span>
            <span>PKR <?= number_format((float)$sale['change_amount'], 2) ?></span>
        </div>
    </div>

    <div class="receipt-footer">
        <p>*** Thank you for your visit! ***</p>
        <p>Medicines once sold cannot be returned without original receipt. Consult doctor before consumption.</p>
    </div>
</div>

<div class="action-buttons">
    <button type="button" class="btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Print Receipt
    </button>
    <button type="button" class="btn-close-window" onclick="window.close()">
        Close Window
    </button>
</div>

<script>
window.addEventListener('load', function() {
    // Optionally auto-open print dialog if opened in popup
    if (window.opener) {
        setTimeout(() => window.print(), 350);
    }
});
</script>

</body>
</html>

