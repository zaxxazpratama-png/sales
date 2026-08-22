<?php
/**
 * FORMGOOGLE - Admin Dashboard
 * Manajemen Tim Sales, Link Generator, dan Pengaturan Form Dinamis
 * PT. Sinergi Emas Perdana
 */
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Config;
use App\SalesManager;
use App\SettingsManager;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek autentikasi login
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: admin');
    exit;
}

// Handle Logout
if (isset($_GET['logout'])) {
    $_SESSION['admin_logged_in'] = false;
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_user']);
    header('Location: admin');
    exit;
}

Config::load();

$msgSuccess = '';
$msgError   = '';

// ========================================================
// POST ACTION HANDLERS
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. TAMBAH SALES BARU
    if ($action === 'add_sales') {
        try {
            $salesCode = strtoupper(trim($_POST['sales_code'] ?? ''));
            $namaSales = trim($_POST['nama_sales'] ?? '');
            $noWa      = trim($_POST['no_wa'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $tlCode    = trim($_POST['tl_code'] ?? 'TL-01');

            if (empty($salesCode) || empty($namaSales)) {
                throw new \Exception('Kode Sales dan Nama Sales wajib diisi.');
            }

            SalesManager::add([
                'sales_code' => $salesCode,
                'nama_sales' => $namaSales,
                'no_wa'      => $noWa,
                'email'      => $email,
                'tl_code'    => $tlCode,
                'status'     => 'active'
            ]);

            $msgSuccess = "Sales <strong>{$namaSales}</strong> ({$salesCode}) berhasil ditambahkan!";
        } catch (\Exception $e) {
            $msgError = $e->getMessage();
        }
    }

    // 2. EDIT SALES
    elseif ($action === 'edit_sales') {
        try {
            $id        = trim($_POST['sales_id'] ?? '');
            $salesCode = strtoupper(trim($_POST['sales_code'] ?? ''));
            $namaSales = trim($_POST['nama_sales'] ?? '');
            $noWa      = trim($_POST['no_wa'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $tlCode    = trim($_POST['tl_code'] ?? 'TL-01');
            $status    = $_POST['status'] ?? 'active';

            SalesManager::update($id, [
                'sales_code' => $salesCode,
                'nama_sales' => $namaSales,
                'no_wa'      => $noWa,
                'email'      => $email,
                'tl_code'    => $tlCode,
                'status'     => $status
            ]);

            $msgSuccess = "Data sales <strong>{$namaSales}</strong> berhasil diperbarui!";
        } catch (\Exception $e) {
            $msgError = $e->getMessage();
        }
    }

    // 3. HAPUS SALES
    elseif ($action === 'delete_sales') {
        $id = trim($_POST['sales_id'] ?? '');
        if (SalesManager::delete($id)) {
            $msgSuccess = "Sales berhasil dihapus.";
        } else {
            $msgError = "Gagal menghapus sales.";
        }
    }

    // 4. UPDATE PENGATURAN GOOGLE & SISTEM
    elseif ($action === 'update_general_settings') {
        try {
            $settings = SettingsManager::get();
            $settings['company_name']    = trim($_POST['company_name'] ?? $settings['company_name']);
            $settings['call_center']     = trim($_POST['call_center'] ?? $settings['call_center']);
            $settings['wa_helpdesk']     = trim($_POST['wa_helpdesk'] ?? $settings['wa_helpdesk']);
            $settings['admin_email']     = trim($_POST['admin_email'] ?? $settings['admin_email']);
            $settings['apps_script_url'] = trim($_POST['apps_script_url'] ?? ($settings['apps_script_url'] ?? ''));
            $settings['spreadsheet_id']  = trim($_POST['spreadsheet_id'] ?? ($settings['spreadsheet_id'] ?? ''));
            $settings['drive_folder_id'] = trim($_POST['drive_folder_id'] ?? ($settings['drive_folder_id'] ?? ''));
            
            if (!empty($_POST['admin_username'])) {
                $settings['admin_username'] = trim($_POST['admin_username']);
            }
            if (!empty($_POST['admin_password'])) {
                $settings['admin_password'] = trim($_POST['admin_password']);
            }

            SettingsManager::update($settings);

            // Update file .env secara otomatis agar selalu sinkron
            $envPath = dirname(__DIR__, 2) . '/.env';
            if (file_exists($envPath)) {
                $envContent = file_get_contents($envPath);
                $envContent = preg_replace('/APPS_SCRIPT_URL=.*/', 'APPS_SCRIPT_URL=' . $settings['apps_script_url'], $envContent);
                file_put_contents($envPath, $envContent);
            }

            $msgSuccess = "Pengaturan Google Apps Script & Profil Admin berhasil disimpan!";
        } catch (\Exception $e) {
            $msgError = $e->getMessage();
        }
    }

    // 5. UPDATE PAKET LAYANAN
    elseif ($action === 'update_packages') {
        try {
            $settings = SettingsManager::get();
            $packages = $settings['packages'] ?? [];

            foreach ($packages as $idx => &$pkg) {
                $pid = $pkg['id'];
                if (isset($_POST['pkg_price_' . $pid])) {
                    $pkg['price']  = (int) preg_replace('/\D/', '', $_POST['pkg_price_' . $pid]);
                    $pkg['speed']  = trim($_POST['pkg_speed_' . $pid] ?? $pkg['speed']);
                    $pkg['active'] = isset($_POST['pkg_active_' . $pid]);
                }
            }

            // Tambah paket baru jika diisi
            $newPkgName = trim($_POST['new_pkg_name'] ?? '');
            if (!empty($newPkgName)) {
                $newPkgSpeed = trim($_POST['new_pkg_speed'] ?? 'Speed up to 50 Mbps');
                $newPkgPrice = (int) preg_replace('/\D/', '', $_POST['new_pkg_price'] ?? '299000');
                $packages[] = [
                    'id'          => 'pkg_' . time(),
                    'name'        => $newPkgName,
                    'speed'       => $newPkgSpeed,
                    'price'       => $newPkgPrice,
                    'badge'       => trim($_POST['new_pkg_badge'] ?? ''),
                    'badge_color' => '#005696',
                    'active'      => true
                ];
            }

            $settings['packages'] = $packages;
            SettingsManager::update($settings);
            $msgSuccess = "Daftar paket layanan berhasil diperbarui!";
        } catch (\Exception $e) {
            $msgError = $e->getMessage();
        }
    }
}

// Data Fetching
$salesList     = SalesManager::getAll();
$settings      = SettingsManager::get();
$packages      = $settings['packages'] ?? [];
$codeGsPath    = dirname(__DIR__, 2) . '/apps-script/Code.gs';
$codeGsContent = file_exists($codeGsPath) ? file_get_contents($codeGsPath) : '';

// Hitung Base URL untuk link sales tanpa /public/
$protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host       = $_SERVER['HTTP_HOST'];
$rootAppDir = dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))); // /ALATTEMPUR/FORMGOOGLE
if ($rootAppDir === '/' || $rootAppDir === '\\') {
    $rootAppDir = '';
}
$baseUrl    = rtrim($protocol . $host . $rootAppDir, '/') . '/';

$totalSales  = count($salesList);
$activeSales = count(array_filter($salesList, fn($s) => ($s['status'] ?? 'active') === 'active'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Tim Sales & Form CBN</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --cbn-blue: #005696;
            --cbn-cyan: #00a0df;
            --bg-dark: #0a1128;
            --bg-card: #111c38;
            --bg-card-alt: #17264c;
            --bg-input: rgba(255, 255, 255, 0.06);
            --border: rgba(255, 255, 255, 0.12);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-sub: #cbd5e1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --radius-lg: 14px;
            --radius-md: 8px;
            --shadow: 0 20px 45px rgba(0, 0, 0, 0.4);
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            line-height: 1.5;
            position: relative;
        }

        /* Topbar */
        .topbar {
            position: sticky; top: 0; z-index: 100;
            background: rgba(10, 17, 40, 0.9);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            padding: 14px 28px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .brand-area { display: flex; align-items: center; gap: 12px; }
        .cbn-logo { font-size: 26px; font-weight: 900; color: #fff; font-family: 'Arial Black', sans-serif; }
        .cbn-logo span { color: var(--cbn-cyan); }
        .topbar-title { font-size: 15px; font-weight: 700; color: #fff; }
        .topbar-sub { font-size: 11px; color: var(--text-muted); }
        .topbar-right { display: flex; align-items: center; gap: 12px; }
        
        .btn-view-form {
            background: rgba(0, 160, 223, 0.15);
            border: 1px solid rgba(0, 160, 223, 0.4);
            color: #67e8f9;
            padding: 7px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }
        .btn-view-form:hover { background: rgba(0, 160, 223, 0.3); color: #fff; }
        
        .btn-logout {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            padding: 7px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }
        .btn-logout:hover { background: rgba(239, 68, 68, 0.3); color: #fff; }

        /* Layout */
        .wrapper { max-width: 1200px; margin: 0 auto; padding: 28px 24px 60px; }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .stat-val { font-size: 28px; font-weight: 800; color: #fff; line-height: 1; margin-bottom: 4px; }
        .stat-lbl { font-size: 12px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; }
        .stat-icon-wrap {
            width: 44px; height: 44px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; font-weight: bold;
        }
        .stat-icon-blue { background: rgba(0, 86, 150, 0.25); color: #60a5fa; border: 1px solid rgba(0, 86, 150, 0.4); }
        .stat-icon-green { background: rgba(16, 185, 129, 0.2); color: #6ee7b7; border: 1px solid rgba(16, 185, 129, 0.4); }
        .stat-icon-purple { background: rgba(139, 92, 246, 0.2); color: #c4b5fd; border: 1px solid rgba(139, 92, 246, 0.4); }

        /* Tabs */
        .tabs-nav {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 12px;
            overflow-x: auto;
        }
        .tab-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            color: var(--text-sub);
            padding: 10px 20px;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }
        .tab-btn:hover { background: rgba(255, 255, 255, 0.1); color: #fff; }
        .tab-btn.active {
            background: linear-gradient(135deg, var(--cbn-blue), var(--cbn-cyan));
            border-color: var(--cbn-cyan);
            color: #fff;
            box-shadow: 0 4px 15px rgba(0, 160, 223, 0.35);
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease; }

        /* Card Panels */
        .panel-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow);
            margin-bottom: 24px;
        }
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }
        .panel-title { font-size: 16px; font-weight: 800; color: #fff; }
        .panel-desc { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

        /* Alerts */
        .alert-box {
            padding: 14px 18px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            font-size: 13.5px;
        }
        .alert-box.success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #6ee7b7; }
        .alert-box.error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; }

        /* Table */
        .table-responsive { overflow-x: auto; }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .admin-table th {
            background: rgba(0, 0, 0, 0.3);
            color: var(--text-muted);
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .admin-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        .admin-table tr:hover td { background: rgba(255, 255, 255, 0.02); }

        .sales-code-badge {
            font-family: 'JetBrains Mono', monospace;
            background: rgba(0, 160, 223, 0.15);
            border: 1px solid rgba(0, 160, 223, 0.4);
            color: #67e8f9;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 12px;
            display: inline-block;
        }

        .status-badge {
            font-size: 11px; font-weight: 700;
            padding: 3px 10px; border-radius: 12px;
            display: inline-block;
        }
        .status-active { background: rgba(16, 185, 129, 0.2); color: #6ee7b7; border: 1px solid rgba(16, 185, 129, 0.4); }
        .status-inactive { background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.4); }

        /* Action Buttons */
        .btn-action {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--border);
            color: var(--text-sub);
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
        }
        .btn-action:hover { background: rgba(255, 255, 255, 0.15); color: #fff; }
        .btn-action-copy { color: #67e8f9; border-color: rgba(0, 160, 223, 0.4); background: rgba(0, 160, 223, 0.1); }
        .btn-action-copy:hover { background: rgba(0, 160, 223, 0.25); color: #fff; }
        .btn-action-wa { color: #86efac; border-color: rgba(34, 197, 94, 0.4); background: rgba(34, 197, 94, 0.1); }
        .btn-action-wa:hover { background: rgba(34, 197, 94, 0.25); color: #fff; }
        .btn-action-qr { color: #fde047; border-color: rgba(234, 179, 8, 0.4); background: rgba(234, 179, 8, 0.1); }
        .btn-action-qr:hover { background: rgba(234, 179, 8, 0.25); color: #fff; }
        .btn-action-danger { color: #fca5a5; border-color: rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.1); }
        .btn-action-danger:hover { background: rgba(239, 68, 68, 0.25); color: #fff; }

        /* Form Inputs */
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
        .form-group label { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
        .form-control {
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 10px 14px;
            font-size: 13.5px;
            color: #fff;
            font-family: inherit;
            outline: none;
            transition: var(--transition);
        }
        .form-control:focus {
            border-color: var(--cbn-cyan);
            background: rgba(255, 255, 255, 0.09);
            box-shadow: 0 0 0 3px rgba(0, 160, 223, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--cbn-blue), var(--cbn-cyan));
            color: #fff;
            border: none;
            padding: 11px 24px;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(0, 160, 223, 0.35);
        }

        /* Integration Card */
        .integration-box {
            background: rgba(0, 86, 150, 0.15);
            border: 1px solid rgba(0, 160, 223, 0.3);
            border-radius: var(--radius-md);
            padding: 16px;
            margin-bottom: 20px;
        }

        /* Toast Alert */
        .toast {
            position: fixed; bottom: 24px; right: 24px; z-index: 999;
            background: #1e293b; border: 1px solid rgba(0, 160, 223, 0.4);
            border-radius: 10px; padding: 12px 20px; color: #fff;
            font-size: 13.5px; font-weight: 600;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.5);
            display: none; align-items: center; gap: 8px;
            animation: fadeIn 0.3s ease;
        }

        /* Modal */
        .modal {
            position: fixed; inset: 0; z-index: 500;
            background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(8px);
            display: none; align-items: center; justify-content: center;
            padding: 20px;
        }
        .modal.active { display: flex; animation: fadeIn 0.2s ease; }
        .modal-content {
            background: #111c38;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            width: 100%; max-width: 480px;
            padding: 24px; box-shadow: var(--shadow);
            position: relative;
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border);
        }
        .modal-title { font-size: 16px; font-weight: 800; color: #fff; }
        .modal-close {
            background: none; border: none; color: var(--text-muted);
            font-size: 20px; cursor: pointer; line-height: 1;
        }
        .modal-close:hover { color: #fff; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar">
    <div class="brand-area">
        <div class="cbn-logo">cbn<span>.</span></div>
        <div>
            <div class="topbar-title">PANEL PENGELOLAAN SALES & FORM</div>
            <div class="topbar-sub">PT. Sinergi Emas Perdana</div>
        </div>
    </div>
    <div class="topbar-right">
        <?php if (!empty($salesList[0])): ?>
            <a href="<?= htmlspecialchars($baseUrl . $salesList[0]['sales_code']) ?>" target="_blank" class="btn-view-form">Lihat Form Sales (<?= htmlspecialchars($salesList[0]['sales_code']) ?>)</a>
        <?php endif; ?>
        <a href="?logout=1" class="btn-logout">Logout</a>
    </div>
</header>

<main class="wrapper">

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div>
                <div class="stat-val"><?= $totalSales ?></div>
                <div class="stat-lbl">Total Tim Sales</div>
            </div>
            <div class="stat-icon-wrap stat-icon-blue">SLS</div>
        </div>
        <div class="stat-card">
            <div>
                <div class="stat-val"><?= $activeSales ?></div>
                <div class="stat-lbl">Sales Aktif</div>
            </div>
            <div class="stat-icon-wrap stat-icon-green">ON</div>
        </div>
        <div class="stat-card">
            <div>
                <div class="stat-val"><?= count($packages) ?></div>
                <div class="stat-lbl">Paket Layanan</div>
            </div>
            <div class="stat-icon-wrap stat-icon-purple">PKG</div>
        </div>
    </div>

    <!-- NOTIFIKASI -->
    <?php if ($msgSuccess): ?>
        <div class="alert-box success"><?= $msgSuccess ?></div>
    <?php endif; ?>
    <?php if ($msgError): ?>
        <div class="alert-box error"><?= htmlspecialchars($msgError) ?></div>
    <?php endif; ?>

    <!-- TABS NAV -->
    <div class="tabs-nav">
        <button type="button" class="tab-btn active" onclick="switchTab('sales-tab')">Manajemen Tim Sales & Shortlink</button>
        <button type="button" class="tab-btn" onclick="switchTab('google-tab')">Integrasi Google & Apps Script</button>
        <button type="button" class="tab-btn" onclick="switchTab('packages-tab')">Pengaturan Paket & Form</button>
        <button type="button" class="tab-btn" onclick="switchTab('settings-tab')">Profil Perusahaan & Admin</button>
    </div>

    <!-- ================= TAB 1: MANAJEMEN SALES ================= -->
    <div id="sales-tab" class="tab-content active">

        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Daftar Link Khusus Tim Sales</div>
                    <div class="panel-desc">Setiap sales memiliki folder link khusus (contoh: <code>/SEP-001</code>). Form hanya bisa dibuka melalui link sales resmi.</div>
                </div>
                <button type="button" class="btn-primary" onclick="openAddModal()">+ Tambah Sales Baru</button>
            </div>

            <div style="margin-bottom:16px;">
                <input type="text" id="search-sales" class="form-control" placeholder="Cari nama sales atau kode sales..." oninput="filterSales()">
            </div>

            <div class="table-responsive">
                <table class="admin-table" id="sales-table">
                    <thead>
                        <tr>
                            <th>Kode Sales</th>
                            <th>Nama Sales</th>
                            <th>WhatsApp / Telp</th>
                            <th>Team Leader</th>
                            <th>Status</th>
                            <th>Link Form Khusus Sales</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($salesList as $sales): 
                            $salesUrl = $baseUrl . urlencode($sales['sales_code']);
                        ?>
                        <tr class="sales-row" data-name="<?= strtolower($sales['nama_sales']) ?>" data-code="<?= strtolower($sales['sales_code']) ?>">
                            <td>
                                <span class="sales-code-badge"><?= htmlspecialchars($sales['sales_code']) ?></span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($sales['nama_sales']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($sales['no_wa'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($sales['tl_code'] ?: '-') ?></td>
                            <td>
                                <span class="status-badge <?= $sales['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                    <?= $sales['status'] === 'active' ? 'Aktif' : 'Non-aktif' ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <input type="text" readonly class="form-control" value="<?= htmlspecialchars($salesUrl) ?>" style="font-size:11.5px;padding:5px 8px;width:210px;" id="link-<?= htmlspecialchars($sales['sales_code']) ?>">
                                    <button type="button" class="btn-action btn-action-copy" onclick="copyLink('<?= htmlspecialchars($salesUrl) ?>')">Salin Link</button>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:4px;">
                                    <button type="button" class="btn-action btn-action-wa" onclick="shareWa('<?= htmlspecialchars($sales['nama_sales']) ?>', '<?= htmlspecialchars($salesUrl) ?>', '<?= htmlspecialchars($sales['no_wa']) ?>')">Share WA</button>
                                    <button type="button" class="btn-action btn-action-qr" onclick="showQr('<?= htmlspecialchars($sales['nama_sales']) ?>', '<?= htmlspecialchars($sales['sales_code']) ?>', '<?= htmlspecialchars($salesUrl) ?>')">QR Code</button>
                                    <button type="button" class="btn-action" onclick="openEditModal(<?= htmlspecialchars(json_encode($sales)) ?>)">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus sales ini?')">
                                        <input type="hidden" name="action" value="delete_sales">
                                        <input type="hidden" name="sales_id" value="<?= htmlspecialchars($sales['id']) ?>">
                                        <button type="submit" class="btn-action btn-action-danger">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ================= TAB 2: INTEGRASI GOOGLE & APPS SCRIPT ================= -->
    <div id="google-tab" class="tab-content">
        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Pengaturan Integrasi Google Apps Script & Spreadsheet</div>
                    <div class="panel-desc">Ubah URL Google Apps Script dan master email kapan saja dari sini tanpa perlu mengedit file script lagi.</div>
                </div>
            </div>

            <div class="integration-box">
                <strong style="color:#67e8f9;display:block;margin-bottom:6px;">Panduan Ganti URL Google Apps Script:</strong>
                <p style="font-size:12.5px;color:#cbd5e1;line-height:1.6;margin:0;">
                    Jika suatu saat Anda mengganti akun Google / Gmail atau melakukan deploy ulang Google Apps Script, cukup copy Web App URL baru yang berakhiran <code>/exec</code> lalu paste ke kolom di bawah ini dan klik <strong>Simpan Pengaturan Google</strong>. Sistem akan langsung terhubung ke Google Apps Script yang baru secara otomatis.
                </p>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="update_general_settings">

                <div class="form-group">
                    <label>URL Web App Google Apps Script (APPS_SCRIPT_URL) *</label>
                    <input type="url" name="apps_script_url" class="form-control" 
                        value="<?= htmlspecialchars($settings['apps_script_url'] ?? '') ?>" 
                        placeholder="https://script.google.com/macros/s/.../exec" required style="font-family:'JetBrains Mono',monospace;font-size:12px;">
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>ID Google Spreadsheet</label>
                        <input type="text" name="spreadsheet_id" class="form-control" 
                            value="<?= htmlspecialchars($settings['spreadsheet_id'] ?? '1cXeq5CkL4QqhsOnAg7bvV7JQvz5gxXnXE1H1JwF9PmQ') ?>" 
                            placeholder="ID Spreadsheet dari URL" style="font-family:'JetBrains Mono',monospace;font-size:12px;">
                    </div>

                    <div class="form-group">
                        <label>ID Folder Google Drive</label>
                        <input type="text" name="drive_folder_id" class="form-control" 
                            value="<?= htmlspecialchars($settings['drive_folder_id'] ?? '12q5pLGP9og9rcfVs_CKwKhTxfufvsN1A') ?>" 
                            placeholder="ID Folder Drive dari URL" style="font-family:'JetBrains Mono',monospace;font-size:12px;">
                    </div>

                    <div class="form-group">
                        <label>Email Master Penerima Notifikasi & SO</label>
                        <input type="email" name="admin_email" class="form-control" 
                            value="<?= htmlspecialchars($settings['admin_email'] ?? 'pujapangestu02@gmail.com') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Nama Perusahaan / Mitra</label>
                        <input type="text" name="company_name" class="form-control" 
                            value="<?= htmlspecialchars($settings['company_name'] ?? 'PT. SINERGI EMAS PERDANA') ?>" required>
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="margin-top:12px;">Simpan Pengaturan Google & Sistem</button>
            </form>

            <!-- KOTAK COPY SOURCE CODE CODE.GS -->
            <div style="margin-top: 32px; border-top: 1px solid var(--border); padding-top: 24px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                    <div>
                        <div style="font-size:15px; font-weight:800; color:#fff;">Source Code Google Apps Script (Code.gs)</div>
                        <div style="font-size:12px; color:var(--text-muted);">Salin kode ini dan paste langsung ke editor di script.google.com tanpa perlu membuka file Code.gs lagi.</div>
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <button type="button" class="btn-primary" onclick="copyCodeGs()" style="background:linear-gradient(135deg, #10b981, #059669);box-shadow:0 4px 15px rgba(16,185,129,0.35);">
                            Salin Seluruh Kode Apps Script (Code.gs)
                        </button>
                        <a href="https://script.google.com" target="_blank" class="btn-view-form" style="display:inline-flex;align-items:center;padding:9px 16px;border-radius:8px;">
                            Buka script.google.com &nearr;
                        </a>
                    </div>
                </div>

                <textarea id="code-gs-content" readonly class="form-control" style="font-family:'JetBrains Mono',monospace; font-size:11.5px; height:260px; width:100%; background:#090d1a; color:#a5f3fc; line-height:1.5; resize:vertical; padding:14px; border-radius:10px; border:1px solid rgba(0,160,223,0.3);"><?= htmlspecialchars($codeGsContent) ?></textarea>
            </div>
        </div>
    </div>

    <!-- ================= TAB 3: PENGATURAN PAKET & FORM ================= -->
    <div id="packages-tab" class="tab-content">
        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Daftar Paket Internet Fiber CBN</div>
                    <div class="panel-desc">Kelola nama paket, kecepatan (speed), harga bulanan, dan status tampil di form</div>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="update_packages">

                <div class="table-responsive" style="margin-bottom:24px;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Nama Paket</th>
                                <th>Deskripsi Kecepatan</th>
                                <th>Harga Bulanan (Rp)</th>
                                <th>Status Aktif</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($packages as $pkg): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($pkg['name']) ?></strong></td>
                                <td>
                                    <input type="text" name="pkg_speed_<?= $pkg['id'] ?>" class="form-control" value="<?= htmlspecialchars($pkg['speed']) ?>" style="padding:6px 10px;">
                                </td>
                                <td>
                                    <input type="text" name="pkg_price_<?= $pkg['id'] ?>" class="form-control" value="<?= number_format($pkg['price'], 0, ',', '.') ?>" style="padding:6px 10px;width:160px;">
                                </td>
                                <td>
                                    <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
                                        <input type="checkbox" name="pkg_active_<?= $pkg['id'] ?>" value="1" <?= !empty($pkg['active']) ? 'checked' : '' ?>>
                                        <span>Aktif</span>
                                    </label>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="border-top:1px solid var(--border);padding-top:20px;margin-top:20px;">
                    <div style="font-size:14px;font-weight:700;margin-bottom:12px;color:#fff;">+ Tambah Paket Baru</div>
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label>Nama Paket</label>
                            <input type="text" name="new_pkg_name" class="form-control" placeholder="Misal: Fiber 500">
                        </div>
                        <div class="form-group">
                            <label>Kecepatan</label>
                            <input type="text" name="new_pkg_speed" class="form-control" placeholder="Misal: Speed up to 500 Mbps">
                        </div>
                        <div class="form-group">
                            <label>Harga Bulanan (Rp)</label>
                            <input type="text" name="new_pkg_price" class="form-control" placeholder="Misal: 1199000">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="margin-top:12px;">Simpan Perubahan Paket</button>
            </form>
        </div>
    </div>

    <!-- ================= TAB 4: PROFIL PERUSAHAAN & ADMIN ================= -->
    <div id="settings-tab" class="tab-content">
        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Pengaturan Kontak & Keamanan Admin</div>
                    <div class="panel-desc">Sesuaikan nomor call center, kontak helpdesk, dan kredensial login admin</div>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="update_general_settings">

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Nomor Call Center</label>
                        <input type="text" name="call_center" class="form-control" value="<?= htmlspecialchars($settings['call_center'] ?? '1500 780') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>WhatsApp Helpdesk</label>
                        <input type="text" name="wa_helpdesk" class="form-control" value="<?= htmlspecialchars($settings['wa_helpdesk'] ?? '081265753141') ?>">
                    </div>

                    <div class="form-group">
                        <label>Username Login Admin</label>
                        <input type="text" name="admin_username" class="form-control" value="<?= htmlspecialchars($settings['admin_username'] ?? 'admin') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Ganti Password Login Admin</label>
                        <input type="password" name="admin_password" class="form-control" placeholder="Biarkan kosong jika tidak ingin mengubah">
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="margin-top:12px;">Simpan Pengaturan Profil</button>
            </form>
        </div>
    </div>

</main>

<!-- MODAL TAMBAH SALES -->
<div id="modal-add-sales" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Tambah Sales Baru</div>
            <button type="button" class="modal-close" onclick="closeModal('modal-add-sales')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_sales">

            <div class="form-group">
                <label>Kode Sales (Unik) *</label>
                <input type="text" name="sales_code" class="form-control" placeholder="Contoh: SEP-011" required>
            </div>

            <div class="form-group">
                <label>Nama Lengkap Sales *</label>
                <input type="text" name="nama_sales" class="form-control" placeholder="Contoh: Ahmad Fauzi" required>
            </div>

            <div class="form-group">
                <label>Nomor WhatsApp Sales</label>
                <input type="text" name="no_wa" class="form-control" placeholder="08xxxxxxxxxx">
            </div>

            <div class="form-group">
                <label>Email Sales (Opsional)</label>
                <input type="email" name="email" class="form-control" placeholder="sales@gmail.com">
            </div>

            <div class="form-group">
                <label>Kode Team Leader (TL)</label>
                <input type="text" name="tl_code" class="form-control" placeholder="TL-MEDAN-01" value="TL-MEDAN-01">
            </div>

            <button type="submit" class="btn-primary" style="width:100%;margin-top:8px;">Simpan Sales Baru</button>
        </form>
    </div>
</div>

<!-- MODAL EDIT SALES -->
<div id="modal-edit-sales" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Edit Data Sales</div>
            <button type="button" class="modal-close" onclick="closeModal('modal-edit-sales')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_sales">
            <input type="hidden" id="edit-id" name="sales_id">

            <div class="form-group">
                <label>Kode Sales *</label>
                <input type="text" id="edit-code" name="sales_code" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Nama Lengkap Sales *</label>
                <input type="text" id="edit-name" name="nama_sales" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Nomor WhatsApp</label>
                <input type="text" id="edit-wa" name="no_wa" class="form-control">
            </div>

            <div class="form-group">
                <label>Email Sales</label>
                <input type="email" id="edit-email" name="email" class="form-control">
            </div>

            <div class="form-group">
                <label>Team Leader</label>
                <input type="text" id="edit-tl" name="tl_code" class="form-control">
            </div>

            <div class="form-group">
                <label>Status</label>
                <select id="edit-status" name="status" class="form-control">
                    <option value="active">Aktif</option>
                    <option value="inactive">Non-aktif</option>
                </select>
            </div>

            <button type="submit" class="btn-primary" style="width:100%;margin-top:8px;">Simpan Perubahan</button>
        </form>
    </div>
</div>

<!-- MODAL QR CODE -->
<div id="modal-qr" class="modal">
    <div class="modal-content" style="text-align:center;">
        <div class="modal-header">
            <div class="modal-title" id="qr-title">QR Code Form Sales</div>
            <button type="button" class="modal-close" onclick="closeModal('modal-qr')">&times;</button>
        </div>
        <div style="background:#fff;padding:20px;border-radius:10px;display:inline-block;margin:10px 0;" id="qrcode-container"></div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:8px;">Pelanggan dapat scan QR code ini untuk langsung membuka form pendaftaran khusus sales ini.</div>
    </div>
</div>

<!-- TOAST ALERT -->
<div id="toast" class="toast">Link berhasil disalin ke clipboard!</div>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    event.target.classList.add('active');
}

function openAddModal() {
    document.getElementById('modal-add-sales').classList.add('active');
}

function openEditModal(sales) {
    document.getElementById('edit-id').value = sales.id;
    document.getElementById('edit-code').value = sales.sales_code;
    document.getElementById('edit-name').value = sales.nama_sales;
    document.getElementById('edit-wa').value = sales.no_wa || '';
    document.getElementById('edit-email').value = sales.email || '';
    document.getElementById('edit-tl').value = sales.tl_code || 'TL-01';
    document.getElementById('edit-status').value = sales.status || 'active';
    document.getElementById('modal-edit-sales').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function copyLink(url) {
    navigator.clipboard.writeText(url).then(() => {
        const toast = document.getElementById('toast');
        toast.textContent = 'Link berhasil disalin ke clipboard!';
        toast.style.display = 'flex';
        setTimeout(() => { toast.style.display = 'none'; }, 2500);
    });
}

function copyCodeGs() {
    const codeEl = document.getElementById('code-gs-content');
    navigator.clipboard.writeText(codeEl.value).then(() => {
        const toast = document.getElementById('toast');
        toast.textContent = 'Kode Google Apps Script (Code.gs) berhasil disalin! Silakan paste ke script.google.com';
        toast.style.display = 'flex';
        setTimeout(() => { toast.style.display = 'none'; }, 3500);
    });
}

function shareWa(nama, url, noWa) {
    const text = encodeURIComponent(
        `Halo! Mau pasang internet fiber cepat & TV berlangganan CBN? \n\nDaftar mudah dan cepat secara online lewat link resmi saya di sini:\n${url}\n\nGratis biaya pasang promo bulan ini! Hubungi saya jika ada pertanyaan.`
    );
    window.open(`https://wa.me/?text=${text}`, '_blank');
}

function showQr(nama, code, url) {
    document.getElementById('qr-title').textContent = `QR Code: ${nama} (${code})`;
    const container = document.getElementById('qrcode-container');
    container.innerHTML = '';
    new QRCode(container, {
        text: url,
        width: 180,
        height: 180,
        colorDark : "#002b4d",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });
    document.getElementById('modal-qr').classList.add('active');
}

function filterSales() {
    const query = document.getElementById('search-sales').value.toLowerCase();
    document.querySelectorAll('.sales-row').forEach(row => {
        const name = row.getAttribute('data-name');
        const code = row.getAttribute('data-code');
        if (name.includes(query) || code.includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>

</body>
</html>
