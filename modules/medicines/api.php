<?php
/**
 * Medis - Medicines JSON API
 * Used by POS & autocomplete searches
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$currentUser = getCurrentUser();
$db = getDB();

$search   = trim($_GET['search'] ?? '');
$barcode  = trim($_GET['barcode'] ?? '');
$branchId = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? (int)$_GET['branch_id'] : $currentUser['branch_id'];

try {
    $whereConditions = ["m.status = 'active'"];
    $params = [];

    if ($barcode !== '') {
        $whereConditions[] = "m.barcode = ?";
        $params[] = $barcode;
    } elseif ($search !== '') {
        $whereConditions[] = "(m.name LIKE ? OR m.generic_name LIKE ? OR m.barcode LIKE ? OR c.name LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $whereSql = implode(' AND ', $whereConditions);

    $sql = "
        SELECT m.id, m.name, m.generic_name, m.manufacturer, m.barcode,
               m.purchase_price, m.selling_price, m.min_stock_level, m.units_per_pack,
               c.name AS category_name
        FROM medicines m
        LEFT JOIN categories c ON c.id = m.category_id
        WHERE $whereSql
        ORDER BY m.name ASC
        LIMIT 30
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $medicines = $stmt->fetchAll();

    $results = [];
    foreach ($medicines as $med) {
        $medId = (int)$med['id'];

        // Get available non-expired batches for branch (FIFO by expiry)
        $batchParams = [$medId];
        $batchBranchClause = '';
        if ($branchId) {
            $batchBranchClause = 'AND b.branch_id = ?';
            $batchParams[] = $branchId;
        }

        $batchSql = "
            SELECT b.id, b.batch_number, b.quantity, b.purchase_price, b.expiry_date,
                   br.name AS branch_name
            FROM batches b
            JOIN branches br ON br.id = b.branch_id
            WHERE b.medicine_id = ?
              AND b.quantity > 0
              AND b.expiry_date > CURDATE()
              $batchBranchClause
            ORDER BY b.expiry_date ASC
        ";
        $bStmt = $db->prepare($batchSql);
        $bStmt->execute($batchParams);
        $batches = $bStmt->fetchAll();

        $availableStock = 0;
        foreach ($batches as $b) {
            $availableStock += (int)$b['quantity'];
        }

        $med['available_stock'] = $availableStock;
        $med['batches']         = $batches;
        $results[]              = $med;
    }

    echo json_encode($results);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed: ' . $e->getMessage()]);
}

