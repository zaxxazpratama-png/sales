<?php
/**
 * FORMGOOGLE - Admin Panel Login
 * PT. Sinergi Emas Perdana
 */
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\SettingsManager;

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
$defaultUser = $settings['admin_username'] ?? 'admin';
$defaultPass = $settings['admin_password'] ?? 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($user === $defaultUser && $pass === $defaultPass) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user']      = $user;
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
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: radial-gradient(ellipse 70% 50% at 50% 20%, rgba(0, 160, 223, 0.15) 0%, transparent 60%),
                        radial-gradient(ellipse 60% 40% at 80% 80%, rgba(0, 86, 150, 0.2) 0%, transparent 60%);
            pointer-events: none;
        }
        .login-card {
            position: relative;
            background: #111c38;
            border: 1px solid rgba(255, 255, 255, 0.12);
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
        .brand-logo {
            font-size: 34px;
            font-weight: 900;
            color: #ffffff;
            font-family: 'Arial Black', sans-serif;
            margin-bottom: 4px;
            letter-spacing: -1.5px;
        }
        .brand-logo span { color: #00a0df; }
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
            font-size: 12px;
            font-weight: 700;
            color: #cbd5e1;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 7px;
        }
        input {
            width: 100%;
            padding: 12px 14px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            color: #ffffff;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: all 0.2s;
        }
        input:focus {
            border-color: #00a0df;
            box-shadow: 0 0 0 3px rgba(0, 160, 223, 0.2);
            background: rgba(255, 255, 255, 0.09);
        }
        .btn-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #005696, #00a0df);
            border: none;
            border-radius: 8px;
            color: white;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 8px;
            box-shadow: 0 6px 18px rgba(0, 160, 223, 0.3);
        }
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(0, 160, 223, 0.45);
        }
        .btn-back-form {
            display: block;
            text-align: center;
            color: #94a3b8;
            text-decoration: none;
            font-size: 12.5px;
            margin-top: 20px;
            transition: color 0.2s;
        }
        .btn-back-form:hover { color: #00a0df; }

        @media (max-width: 480px) {
            body { padding: 14px; }
            .login-card { padding: 26px 18px; border-radius: 12px; }
            .brand-logo { font-size: 28px; }
            .brand-header h1 { font-size: 15px; }
            .brand-header p { font-size: 11px; }
            .btn-login { padding: 11px; font-size: 13.5px; }
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="brand-header">
        <div class="brand-logo">cbn<span>.</span></div>
        <h1>DASHBOARD ADMIN</h1>
        <p>Kelola Tim Sales & Form CBN &bull; PT. SEP</p>
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
</div>

</body>
</html>
