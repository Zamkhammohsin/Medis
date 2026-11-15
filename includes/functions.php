<?php
/**
 * Medis - Helper Functions
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Format currency in PKR
 */
function formatCurrency($amount): string {
    return 'PKR ' . number_format((float)($amount ?? 0), 2);
}

/**
 * Format date to readable format
 */
function formatDate(?string $date, string $format = 'd M Y'): string {
    return ($date && $date !== '0000-00-00') ? date($format, strtotime($date)) : '—';
}

/**
 * Format datetime
 */
function formatDateTime(?string $dt): string {
    return ($dt && $dt !== '0000-00-00 00:00:00') ? date('d M Y, h:i A', strtotime($dt)) : '—';
}

/**
 * Sanitize output
 */
function e($str): string {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a unique invoice number
 */
function generateInvoiceNumber(string $prefix = 'INV'): string {
    return $prefix . '-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

/**
 * Get today's total sales for a branch (or all branches for admin)
 */
function getTodaySales(?int $branchId = null): float {
    $db = getDB();
    try {
        if ($branchId) {
            $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE DATE(created_at) = CURDATE() AND branch_id = ? AND status = 'completed'");
            $stmt->execute([$branchId]);
        } else {
            $stmt = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE DATE(created_at) = CURDATE() AND status = 'completed'");
        }
        return (float)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return 0.0;
    }
}

/**
 * Get this month's total sales
 */
function getMonthSales(?int $branchId = null): float {
    $db = getDB();
    try {
        if ($branchId) {
            $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE YEAR(created_at)=YEAR(NOW()) AND MONTH(created_at)=MONTH(NOW()) AND branch_id=? AND status='completed'");
            $stmt->execute([$branchId]);
        } else {
            $stmt = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE YEAR(created_at)=YEAR(NOW()) AND MONTH(created_at)=MONTH(NOW()) AND status='completed'");
        }
        return (float)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return 0.0;
    }
}

/**
 * Count active medicines
 */
function getMedicineCount(): int {
    $db = getDB();
    try {
        return (int)$db->query("SELECT COUNT(*) FROM medicines WHERE status='active'")->fetchColumn();
    } catch (PDOException $e) { return 0; }
}

/**
 * Get medicines with low stock (sum of batch quantities < min_stock_level)
 */
function getLowStockMedicines(?int $branchId = null): array {
    $db = getDB();
    try {
        $branchClause = $branchId ? "AND b.branch_id = $branchId" : '';
        $sql = "SELECT m.id, m.name, m.min_stock_level,
                       COALESCE(SUM(b.quantity),0) AS total_stock,
                       c.name AS category
                FROM medicines m
                LEFT JOIN batches b ON b.medicine_id = m.id $branchClause
                LEFT JOIN categories c ON c.id = m.category_id
                WHERE m.status = 'active'
                GROUP BY m.id, m.name, m.min_stock_level, c.name
                HAVING total_stock < m.min_stock_level
                ORDER BY total_stock ASC
                LIMIT 20";
        return $db->query($sql)->fetchAll();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

/**
 * Count low stock medicines
 */
function getLowStockCount(?int $branchId = null): int {
    return count(getLowStockMedicines($branchId));
}

/**
 * Get medicines expiring within N days
 */
function getExpiringMedicines(int $days = 90, ?int $branchId = null): array {
    $db = getDB();
    try {
        $branchClause = $branchId ? "AND b.branch_id = $branchId" : '';
        $sql = "SELECT m.name, b.batch_number, b.expiry_date, b.quantity,
                       br.name AS branch_name,
                       DATEDIFF(b.expiry_date, CURDATE()) AS days_left
                FROM batches b
                JOIN medicines m ON m.id = b.medicine_id
                JOIN branches br ON br.id = b.branch_id
                WHERE b.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $days DAY)
                AND b.quantity > 0
                $branchClause
                ORDER BY b.expiry_date ASC
                LIMIT 20";
        return $db->query($sql)->fetchAll();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

/**
 * Count expiring medicines
 */
function getExpiringCount(int $days = 90, ?int $branchId = null): int {
    return count(getExpiringMedicines($days, $branchId));
}

/**
 * Count expired batches (still in stock)
 */
function getExpiredCount(?int $branchId = null): int {
    $db = getDB();
    try {
        $branchClause = $branchId ? "AND branch_id = $branchId" : '';
        return (int)$db->query("SELECT COUNT(*) FROM batches WHERE expiry_date < CURDATE() AND quantity > 0 $branchClause")->fetchColumn();
    } catch (PDOException $e) { return 0; }
}

/**
 * Get recent sales transactions
 */
function getRecentSales(int $limit = 10, ?int $branchId = null): array {
    $db = getDB();
    try {
        $branchClause = $branchId ? "AND s.branch_id = $branchId" : '';
        $sql = "SELECT s.invoice_number, s.total_amount, s.payment_method, s.status, s.created_at,
                       u.name AS cashier, br.name AS branch_name
                FROM sales s
                LEFT JOIN users u ON u.id = s.user_id
                LEFT JOIN branches br ON br.id = s.branch_id
                WHERE 1=1 $branchClause
                ORDER BY s.created_at DESC
                LIMIT $limit";
        return $db->query($sql)->fetchAll();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return [];
    }
}

/**
 * Count active suppliers
 */
function getSupplierCount(): int {
    $db = getDB();
    try {
        return (int)$db->query("SELECT COUNT(*) FROM suppliers WHERE status='active'")->fetchColumn();
    } catch (PDOException $e) { return 0; }
}

/**
 * Count active branches
 */
function getBranchCount(): int {
    $db = getDB();
    try {
        return (int)$db->query("SELECT COUNT(*) FROM branches WHERE status='active'")->fetchColumn();
    } catch (PDOException $e) { return 0; }
}

/**
 * Get sales chart data (last 7 days)
 */
function getSalesChartData(?int $branchId = null): array {
    $db = getDB();
    $labels = [];
    $data   = [];
    try {
        $branchClause = $branchId ? "AND branch_id = $branchId" : '';
        $sql = "SELECT DATE(created_at) AS sale_date, COALESCE(SUM(total_amount),0) AS total
                FROM sales
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                AND status = 'completed'
                $branchClause
                GROUP BY DATE(created_at)
                ORDER BY sale_date ASC";
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);

        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('d M', strtotime($date));
            $data[]   = (float)($rows[$date] ?? 0);
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
    return ['labels' => $labels, 'data' => $data];
}

/**
 * Get all branches
 */
function getAllBranches(): array {
    $db = getDB();
    try {
        return $db->query("SELECT * FROM branches WHERE status='active' ORDER BY name")->fetchAll();
    } catch (PDOException $e) { return []; }
}

/**
 * Get total sales count today
 */
function getTodaySalesCount(?int $branchId = null): int {
    $db = getDB();
    try {
        if ($branchId) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM sales WHERE DATE(created_at)=CURDATE() AND branch_id=? AND status='completed'");
            $stmt->execute([$branchId]);
        } else {
            $stmt = $db->query("SELECT COUNT(*) FROM sales WHERE DATE(created_at)=CURDATE() AND status='completed'");
        }
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) { return 0; }
}

/**
 * Redirect with message
 */
function redirect(string $url, string $msg = '', string $type = 'success'): void {
    if ($msg) {
        $_SESSION['flash_message'] = $msg;
        $_SESSION['flash_type']    = $type;
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Get and clear flash message
 */
function getFlash(): array {
    $msg  = $_SESSION['flash_message'] ?? '';
    $type = $_SESSION['flash_type']    ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    return ['message' => $msg, 'type' => $type];
}
