<?php
/**
 * Medis - Inventory JSON API
 * Returns batches for a medicine and branch
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

$medicineId = (int)($_GET['medicine_id'] ?? 0);
$branchId   = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? (int)$_GET['branch_id'] : $currentUser['branch_id'];
$activeOnly = isset($_GET['active_only']) && $_GET['active_only'] === '1';

if (!$medicineId) {
    http_response_code(400);
    echo json_encode(['error' => 'Medicine ID required']);
    exit;
}

try {
    $where = ["b.medicine_id = ?"];
    $params = [$medicineId];

    if ($branchId) {
        $where[] = "b.branch_id = ?";
        $params[] = $branchId;
    }

    if ($activeOnly) {
        $where[] = "b.quantity > 0 AND b.expiry_date > CURDATE()";
    }

    $whereSql = implode(' AND ', $where);

    $sql = "
        SELECT b.id, b.batch_number, b.quantity, b.purchase_price, b.expiry_date,
               br.name AS branch_name,
               DATEDIFF(b.expiry_date, CURDATE()) AS days_left
        FROM batches b
        JOIN branches br ON br.id = b.branch_id
        WHERE $whereSql
        ORDER BY b.expiry_date ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $batches = $stmt->fetchAll();

    echo json_encode($batches);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query error: ' . $e->getMessage()]);
}

