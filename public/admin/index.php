<?php
/**
 * FORMGOOGLE - Admin Panel Login
 * PT. Talenta Integritas Nasional
 */
require_once dirname(__DIR__, 2) . '/src/autoload.php';

use App\SettingsManager;
use App\AuthManager;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika sudah login, langsung ke dashboard
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$settings = SettingsManager::get();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    $account = AuthManager::authenticate($user, $pass);

    if ($account) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user']      = $account['username'];
        $_SESSION['admin_role']      = $account['role'];
        $_SESSION['admin_tl_code']   = $account['tl_code'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Username atau password salah. Silakan periksa kembali.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Dashboard Tim Sales CBN</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0a1128;
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
        }
            padding: 20px;
        }
        .login-card {
            background: rgba(17, 28, 56, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 160, 223, 0.25);
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.45);
        }
        .brand-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .brand-logo-img {
            max-width: 140px;
            max-height: 70px;
            object-fit: contain;
            display: block;
            margin: 0 auto 16px;
            background: rgba(255, 255, 255, 0.95);
            padding: 8px 16px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(56, 189, 248, 0.3);
        }
        .brand-header h1 {
            font-size: 17px;
            font-weight: 700;
            color: #f8fafc;
            margin-top: 4px;
        }
        .brand-header p {
            color: #94a3b8;
            font-size: 12px;
            margin-top: 2px;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #fca5a5;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            line-height: 1.4;
        }
        .form-group { margin-bottom: 18px; }
        label {
            display: block;
            color: #cbd5e1;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            color: #ffffff;
            font-size: 14px;
            outline: none;
            transition: all 0.3s ease;
        }
        input[type="text"]:focus, input[type="password"]:focus {
            border-color: #00a0df;
            background: rgba(0, 160, 223, 0.1);
            box-shadow: 0 0 10px rgba(0, 160, 223, 0.3);
        }
        .btn-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #00a0df 0%, #005696 100%);
            border: none;
            border-radius: 8px;
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 160, 223, 0.4);
            margin-top: 10px;
        }
        .btn-login:hover {
            opacity: 0.92;
            transform: translateY(-1px);
        }
        .footer-note {
            text-align: center;
            margin-top: 24px;
            font-size: 11.5px;
            color: #64748b;
        }

        @media (max-width: 480px) {
            body { padding: 14px; }
            .login-card { padding: 26px 18px; border-radius: 12px; }
            .brand-logo-img { max-width: 120px; }
            .brand-header h1 { font-size: 15px; }
            .brand-header p { font-size: 11px; }
            .btn-login { padding: 11px; font-size: 13.5px; }
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="brand-header">
        <img src="../assets/img/logo-tin.png" alt="PT. TALENTA INTEGRITAS NASIONAL" class="brand-logo-img">
        <h1>DASHBOARD ADMIN</h1>
        <p>Kelola Tim Sales &amp; Form CBN &bull; PT. TALENTA INTEGRITAS NASIONAL</p>
    </div>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" placeholder="Masukkan username" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Masukkan password" required>
        </div>

        <button type="submit" class="btn-login">Masuk ke Dashboard</button>
    </form>

    <a href="../index.php" class="btn-back-form">Kembali ke Formulir Pendaftaran</a>
    <script src="../assets/js/cookie_consent.js?v=<?= time() ?>"></script>
</body>
</html>
