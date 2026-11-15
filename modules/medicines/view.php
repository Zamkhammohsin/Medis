<?php
/**
 * Medis - Medicine Detail View
 */
$pageTitle = 'Medicine Details';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin', 'manager']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    redirect(BASE_URL . '/modules/medicines/index.php', 'Invalid medicine ID', 'error');
}

$db = getDB();

$stmt = $db->prepare("
    SELECT m.*, c.name AS category_name
    FROM medicines m
    LEFT JOIN categories c ON c.id = m.category_id
    WHERE m.id = ?
");
$stmt->execute([$id]);
$medicine = $stmt->fetch();
if (!$medicine) {
    redirect(BASE_URL . '/modules/medicines/index.php', 'Medicine not found', 'error');
}

// Fetch Batches across branches
$bStmt = $db->prepare("
    SELECT b.*, br.name AS branch_name,
           DATEDIFF(b.expiry_date, CURDATE()) AS days_left
    FROM batches b
    JOIN branches br ON br.id = b.branch_id
    WHERE b.medicine_id = ?
    ORDER BY b.expiry_date ASC
");
$bStmt->execute([$id]);
$batches = $bStmt->fetchAll();

// Calculate total stock
$totalActiveStock = 0;
$totalExpiredStock = 0;
foreach ($batches as $b) {
    if ($b['days_left'] >= 0) {
        $totalActiveStock += (int)$b['quantity'];
    } else {
        $totalExpiredStock += (int)$b['quantity'];
    }
}

// Recent Sales for this medicine
$salesStmt = $db->prepare("
    SELECT si.quantity, si.unit_price, si.subtotal, si.created_at,
           s.invoice_number, br.name AS branch_name
    FROM sale_items si
    JOIN sales s ON s.id = si.sale_id
    JOIN branches br ON br.id = s.branch_id
    WHERE si.medicine_id = ?
    ORDER BY si.created_at DESC
    LIMIT 5
");
$salesStmt->execute([$id]);
$recentSales = $salesStmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-pills"></i> <?= e($medicine['name']) ?></h2>
        <p class="page-subtitle"><?= e($medicine['generic_name'] ?? 'No generic formula listed') ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>/modules/medicines/edit.php?id=<?= $medicine['id'] ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Edit Medicine
        </a>
        <a href="<?= BASE_URL ?>/modules/inventory/adjust.php?medicine_id=<?= $medicine['id'] ?>" class="btn btn-warning">
            <i class="fas fa-sliders-h"></i> Adjust Stock
        </a>
        <a href="<?= BASE_URL ?>/modules/medicines/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Catalog
        </a>
    </div>
</div>

<div class="row mb-4">
    <!-- Specifications Card -->
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <h5 class="card-title m-0"><i class="fas fa-info-circle text-primary"></i> Product Specifications</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tbody>
                        <tr>
                            <th class="text-muted" style="width: 40%;">Category</th>
                            <td><span class="badge badge-light"><?= e($medicine['category_name'] ?? 'Uncategorized') ?></span></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Manufacturer</th>
                            <td><?= e($medicine['manufacturer'] ?? '—') ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Barcode</th>
                            <td><code><?= e($medicine['barcode'] ?? '—') ?></code></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Purchase Price</th>
                            <td><?= formatCurrency((float)$medicine['purchase_price']) ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Selling Price</th>
                            <td><strong class="text-success"><?= formatCurrency((float)$medicine['selling_price']) ?></strong></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Min Stock Threshold</th>
                            <td><?= $medicine['min_stock_level'] ?> units</td>
                        </tr>
                        <tr>
                            <th class="text-muted">Units Per Pack</th>
                            <td><?= $medicine['units_per_pack'] ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted">Status</th>
                            <td>
                                <span class="badge <?= $medicine['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?>">
                                    <?= ucfirst(e($medicine['status'])) ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-muted">Description</th>
                            <td><small><?= nl2br(e($medicine['description'] ?? 'No special dosage or storage notes.')) ?></small></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Stock Summary Cards -->
    <div class="col-lg-7 mb-4">
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon bg-success-light text-success"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-details">
                        <span class="stat-label">Usable Available Stock</span>
                        <h3 class="stat-value text-success"><?= number_format($totalActiveStock) ?> units</h3>
                        <span class="stat-subtext">Across non-expired batches</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-icon <?= $totalExpiredStock > 0 ? 'bg-danger-light text-danger' : 'bg-secondary-light text-secondary' ?>"><i class="fas fa-ban"></i></div>
                    <div class="stat-details">
                        <span class="stat-label">Expired Stock (Blocked)</span>
                        <h3 class="stat-value text-danger"><?= number_format($totalExpiredStock) ?> units</h3>
                        <span class="stat-subtext">Cannot be sold</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Batches List -->
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title m-0"><i class="fas fa-layer-group text-primary"></i> Inventory Batches</h5>
                <span class="badge badge-light"><?= count($batches) ?> batch records</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Batch #</th>
                                <th>Branch</th>
                                <th>Quantity</th>
                                <th>Cost Price</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($batches)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No stock batches found for this product.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($batches as $batch): ?>
                                    <tr>
                                        <td><strong><?= e($batch['batch_number']) ?></strong></td>
                                        <td><?= e($batch['branch_name']) ?></td>
                                        <td><strong><?= $batch['quantity'] ?></strong></td>
                                        <td><?= formatCurrency((float)$batch['purchase_price']) ?></td>
                                        <td><?= formatDate($batch['expiry_date']) ?></td>
                                        <td>
                                            <?php if ($batch['days_left'] < 0): ?>
                                                <span class="badge badge-danger">Expired (<?= abs($batch['days_left']) ?>d ago)</span>
                                            <?php elseif ($batch['days_left'] <= 30): ?>
                                                <span class="badge badge-danger"><?= $batch['days_left'] ?> days left</span>
                                            <?php elseif ($batch['days_left'] <= 90): ?>
                                                <span class="badge badge-warning"><?= $batch['days_left'] ?> days left</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Valid (<?= $batch['days_left'] ?>d)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Sales Activity -->
<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="card-title m-0"><i class="fas fa-history text-muted"></i> Recent Sales Transactions</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice</th>
                        <th>Branch</th>
                        <th>Units Sold</th>
                        <th>Unit Price</th>
                        <th>Subtotal</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentSales)): ?>
                        <tr><td colspan="6" class="text-center py-3 text-muted">No sales recorded yet for this medicine.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td><strong><?= e($sale['invoice_number']) ?></strong></td>
                                <td><?= e($sale['branch_name']) ?></td>
                                <td><?= $sale['quantity'] ?></td>
                                <td><?= formatCurrency((float)$sale['unit_price']) ?></td>
                                <td><?= formatCurrency((float)$sale['subtotal']) ?></td>
                                <td><?= formatDateTime($sale['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

