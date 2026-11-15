<?php
/**
 * Medis - Toggle User Account Status
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/modules/users/index.php', 'Invalid request method.', 'error');
}

if (!validateCSRF($_POST['csrf_token'] ?? '')) {
    redirect(BASE_URL . '/modules/users/index.php', 'Security token expired. Please try again.', 'error');
}

$currentUser = getCurrentUser();
$userId = (int)($_POST['user_id'] ?? 0);

if (!$userId) {
    redirect(BASE_URL . '/modules/users/index.php', 'Invalid user ID.', 'error');
}

if ($userId === (int)$currentUser['id']) {
    redirect(BASE_URL . '/modules/users/index.php', 'You cannot deactivate your own active session account!', 'warning');
}

$db = getDB();

try {
    $stmt = $db->prepare("SELECT status, name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        redirect(BASE_URL . '/modules/users/index.php', 'User not found.', 'error');
    }

    $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';

    $upd = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
    $upd->execute([$newStatus, $userId]);

    $msg = "Account for {$user['name']} has been set to " . strtoupper($newStatus) . '.';
    redirect(BASE_URL . '/modules/users/index.php', $msg, 'info');

} catch (PDOException $e) {
    error_log($e->getMessage());
    redirect(BASE_URL . '/modules/users/index.php', 'Failed to update account: ' . $e->getMessage(), 'error');
}

