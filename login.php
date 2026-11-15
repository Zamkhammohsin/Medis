<?php
/**
 * Medis - Login Page
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRF($token)) {
        $error = 'Security check failed. Please refresh the page and try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT u.*, b.name AS branch_name FROM users u LEFT JOIN branches b ON b.id = u.branch_id WHERE u.email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status'] !== 'active') {
                        $error = 'Your account has been deactivated. Please contact an administrator.';
                    } else {
                        // Regenerate session ID for security against session fixation
                        session_regenerate_id(true);

                        $_SESSION['user_id']        = (int)$user['id'];
                        $_SESSION['user_name']      = $user['name'];
                        $_SESSION['user_email']     = $user['email'];
                        $_SESSION['user_role']      = $user['role'];
                        $_SESSION['user_branch_id'] = $user['branch_id'] ? (int)$user['branch_id'] : null;
                        $_SESSION['user_branch_name'] = $user['branch_name'] ?? null;

                        header('Location: ' . BASE_URL . '/dashboard.php');
                        exit;
                    }
                } else {
                    $error = 'Invalid email address or password.';
                }
            } catch (Exception $e) {
                error_log($e->getMessage());
                $error = 'A system error occurred. Please try again later.';
            }
        }
    }
}
$csrfToken = generateCSRF();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f1e36 0%, #1e3a5f 50%, #2b5876 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .login-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 440px;
            overflow: hidden;
            animation: fadeIn 0.4s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-header {
            background: #1e3a5f;
            color: #ffffff;
            padding: 32px 24px;
            text-align: center;
        }
        .login-header .logo-icon {
            font-size: 42px;
            color: #4da6ff;
            margin-bottom: 8px;
            display: inline-block;
        }
        .login-header h1 {
            font-size: 24px;
            margin: 0;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .login-header p {
            margin: 6px 0 0;
            color: #b0c4de;
            font-size: 14px;
        }
        .login-body {
            padding: 32px 28px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
        }
        .input-group {
            position: relative;
        }
        .input-group i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 15px;
        }
        .form-control {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        .form-control:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
        }
        .btn-login {
            width: 100%;
            padding: 13px;
            background: #1e3a5f;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.1s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-login:hover {
            background: #162b47;
        }
        .btn-login:active {
            transform: scale(0.99);
        }
        .login-error {
            background: #fee2e2;
            border: 1px solid #f87171;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .login-success {
            background: #dcfce7;
            border: 1px solid #86efac;
            color: #166534;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .demo-credentials {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .demo-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 10px;
            text-align: center;
        }
        .demo-pills {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        .demo-pill {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 6px;
            text-align: center;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.15s ease;
            color: #1e293b;
            font-weight: 500;
        }
        .demo-pill:hover {
            background: #e2e8f0;
            border-color: #94a3b8;
        }
        .demo-pill strong {
            display: block;
            color: #1e3a5f;
            font-size: 11px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <i class="fas fa-pills logo-icon"></i>
        <h1><?= APP_NAME ?></h1>
        <p>Pharmacy Management System with POS</p>
    </div>

    <div class="login-body">
        <?php if (!empty($error)): ?>
            <div class="login-error">
                <i class="fas fa-exclamation-triangle"></i>
                <div><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['logged_out'])): ?>
            <div class="login-success">
                <i class="fas fa-check-circle"></i>
                <div>You have been logged out successfully.</div>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="name@medis.pk" value="<?= e($email) ?>" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Sign In to Dashboard
            </button>
        </form>

        <div class="demo-credentials">
            <div class="demo-title"><i class="fas fa-key"></i> Quick Demo Logins</div>
            <div class="demo-pills">
                <button type="button" class="demo-pill" onclick="fillCreds('admin@medis.pk', 'Admin@123')">
                    <strong>Admin</strong>
                    All Branches
                </button>
                <button type="button" class="demo-pill" onclick="fillCreds('manager.lahore@medis.pk', 'Manager@123')">
                    <strong>Manager</strong>
                    Main Branch
                </button>
                <button type="button" class="demo-pill" onclick="fillCreds('cashier1@medis.pk', 'Cashier@123')">
                    <strong>Cashier</strong>
                    POS Access
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function fillCreds(email, password) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = password;
}
</script>

</body>
</html>

