<?php
/**
 * Medis - Authentication Helpers
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireRole($roles): void {
    requireLogin();
    if (!in_array($_SESSION['user_role'] ?? '', (array)$roles)) {
        header('Location: ' . BASE_URL . '/dashboard.php?error=unauthorized');
        exit;
    }
}

function getCurrentUser(): array {
    if (isset($_SESSION['user_id']) && empty($_SESSION['user_name'])) {
        try {
            if (function_exists('getDB')) {
                $db = getDB();
                $stmt = $db->prepare("SELECT u.*, b.name AS branch_name FROM users u LEFT JOIN branches b ON b.id = u.branch_id WHERE u.id = ?");
                $stmt->execute([(int)$_SESSION['user_id']]);
                $u = $stmt->fetch();
                if ($u) {
                    $_SESSION['user_name']        = $u['name'];
                    $_SESSION['user_email']       = $u['email'];
                    $_SESSION['user_role']        = $u['role'];
                    $_SESSION['user_branch_id']   = $u['branch_id'] ? (int)$u['branch_id'] : null;
                    $_SESSION['user_branch_name'] = $u['branch_name'] ?? null;
                } else {
                    // Stale or conflicting session from another project on localhost
                    session_unset();
                    session_destroy();
                    header('Location: ' . BASE_URL . '/login.php');
                    exit;
                }
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    return [
        'id'          => $_SESSION['user_id']        ?? null,
        'name'        => $_SESSION['user_name']       ?? 'User',
        'email'       => $_SESSION['user_email']      ?? '',
        'role'        => $_SESSION['user_role']       ?? 'cashier',
        'branch_id'   => $_SESSION['user_branch_id']  ?? null,
        'branch_name' => $_SESSION['user_branch_name'] ?? null,
    ];
}

function isAdmin():   bool { return ($_SESSION['user_role'] ?? '') === 'admin'; }
function isManager(): bool { return ($_SESSION['user_role'] ?? '') === 'manager'; }
function isCashier(): bool { return ($_SESSION['user_role'] ?? '') === 'cashier'; }

function generateCSRF(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRF($token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
