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

            $msgSuccess = "Pengaturan Google & Profil Admin berhasil disimpan!";
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
                if (isset($_POST['pkg_price_' . $pid]) || isset($_POST['pkg_name_' . $pid])) {
                    $pkg['name']          = trim($_POST['pkg_name_' . $pid] ?? $pkg['name']);
                    $pkg['price']         = (int) preg_replace('/\D/', '', $_POST['pkg_price_' . $pid] ?? (string)$pkg['price']);
                    $pkg['speed']         = trim($_POST['pkg_speed_' . $pid] ?? $pkg['speed']);
                    $pkg['active']        = isset($_POST['pkg_active_' . $pid]);
                    $pkg['biaya_tambahan']= (int) preg_replace('/\D/', '', $_POST['pkg_biaya_tambahan_' . $pid] ?? '5000');
                    $pkg['badge']         = trim($_POST['pkg_badge_' . $pid] ?? ($pkg['badge'] ?? ''));
                    $pkg['badge_color']   = trim($_POST['pkg_badge_color_' . $pid] ?? ($pkg['badge_color'] ?? '#005696'));

                    // Parse CBN package lines (baris per baris, pisah dengan newline)
                    $rawCbn = trim($_POST['pkg_cbn_package_' . $pid] ?? '');
                    if (!empty($rawCbn)) {
                        $lines = array_filter(array_map('trim', explode("\n", $rawCbn)));
                        $pkg['cbn_package'] = array_values($lines);
                    } else {
                        $pkg['cbn_package'] = [];
                    }
                }
            }
            unset($pkg);

            // Tambah paket baru jika diisi
            $newPkgName = trim($_POST['new_pkg_name'] ?? '');
            if (!empty($newPkgName)) {
                $newPkgSpeed    = trim($_POST['new_pkg_speed'] ?? 'Speed up to 50 Mbps');
                $newPkgPrice    = (int) preg_replace('/\D/', '', $_POST['new_pkg_price'] ?? '169000');
                $newPkgTambahan = (int) preg_replace('/\D/', '', $_POST['new_pkg_biaya_tambahan'] ?? '5000');
                $newPkgBadge    = trim($_POST['new_pkg_badge'] ?? '');
                $newPkgBadgeClr = trim($_POST['new_pkg_badge_color'] ?? '#005696');
                $rawNewCbn      = trim($_POST['new_pkg_cbn_package'] ?? '');
                $newCbnLines    = !empty($rawNewCbn)
                    ? array_values(array_filter(array_map('trim', explode("\n", $rawNewCbn))))
                    : [];

                $packages[] = [
                    'id'            => 'pkg_' . time(),
                    'name'          => $newPkgName,
                    'speed'         => $newPkgSpeed,
                    'price'         => $newPkgPrice,
                    'biaya_tambahan'=> $newPkgTambahan,
                    'badge'         => $newPkgBadge,
                    'badge_color'   => $newPkgBadgeClr ?: '#005696',
                    'active'        => true,
                    'cbn_package'   => $newCbnLines
                ];
            }

            // Hapus paket yang ditandai untuk dihapus
            if (!empty($_POST['delete_pkg'])) {
                $toDelete = (array)$_POST['delete_pkg'];
                $packages = array_values(array_filter($packages, fn($p) => !in_array($p['id'], $toDelete)));
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

        /* ================= PACKAGE CARDS & GRIDS ================= */
        .pkg-main-card {
            background: var(--bg-card-alt);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 22px;
            position: relative;
            transition: var(--transition);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }
        .pkg-main-card:hover {
            border-color: rgba(0, 160, 223, 0.35);
        }
        .pkg-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 16px;
            margin-bottom: 18px;
        }
        .pkg-header-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .pkg-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .pkg-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 14px;
        }
        .pkg-grid-3 {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr;
            gap: 14px;
            margin-bottom: 14px;
            align-items: end;
        }
        .pkg-preview-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 14px;
            margin-top: 12px;
        }

        .pkg-btn-edit {
            padding: 8px 16px;
            font-size: 12.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #0284c7, #0369a1);
            color: #fff;
            border: 1px solid #38bdf8;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
        }
        .pkg-btn-edit:hover {
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.4);
            transform: translateY(-1px);
        }
        .pkg-btn-preview {
            padding: 8px 16px;
            font-size: 12.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(0, 160, 223, 0.15);
            border: 1px solid rgba(0, 160, 223, 0.4);
            color: #67e8f9;
            border-radius: 8px;
            text-decoration: none;
            transition: var(--transition);
        }
        .pkg-btn-preview:hover {
            background: rgba(0, 160, 223, 0.3);
            color: #fff;
            transform: translateY(-1px);
        }
        .pkg-btn-delete {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            font-size: 12.5px;
            font-weight: 700;
            color: #fca5a5;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 8px 14px;
            border-radius: 8px;
            transition: var(--transition);
        }
        .pkg-btn-delete:hover {
            background: rgba(239, 68, 68, 0.25);
            color: #fff;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ================= RESPONSIVE BREAKPOINTS ================= */
        @media (max-width: 992px) {
            .pkg-grid-4 {
                grid-template-columns: repeat(2, 1fr);
            }
            .pkg-grid-3 {
                grid-template-columns: repeat(2, 1fr);
            }
            .pkg-preview-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            body { padding: 0; }
            .wrapper { padding: 14px 12px 60px; max-width: 100%; box-sizing: border-box; }
            
            /* Topbar */
            .topbar {
                padding: 12px 14px;
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            .brand-area { width: 100%; }
            .cbn-logo { font-size: 24px; }
            .topbar-title { font-size: 13px; }
            .topbar-sub { font-size: 10.5px; }
            .topbar-right {
                width: 100%;
                display: grid;
                grid-template-columns: 1fr auto;
                gap: 8px;
            }
            .btn-view-form {
                font-size: 11.5px;
                padding: 8px 12px;
                text-align: center;
                justify-content: center;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .btn-logout {
                font-size: 11.5px;
                padding: 8px 14px;
                text-align: center;
            }

            /* Stats */
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                margin-bottom: 16px;
            }
            .stats-grid .stat-card:last-child {
                grid-column: span 2;
            }
            .stat-card {
                padding: 12px 14px;
            }
            .stat-val {
                font-size: 22px;
            }
            .stat-lbl {
                font-size: 10.5px;
            }
            .stat-icon-wrap {
                width: 36px;
                height: 36px;
                font-size: 14px;
            }

            /* Tabs Nav */
            .tabs-nav {
                display: flex;
                gap: 6px;
                margin-bottom: 16px;
                padding-bottom: 8px;
                overflow-x: auto;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
            }
            .tabs-nav::-webkit-scrollbar { height: 3px; }
            .tabs-nav::-webkit-scrollbar-thumb { background: rgba(0, 160, 223, 0.4); border-radius: 4px; }
            .tab-btn {
                padding: 8px 14px;
                font-size: 12px;
                flex-shrink: 0;
                border-radius: 8px;
            }

            /* Cards & Panels */
            .panel-card {
                padding: 16px 14px;
                border-radius: 12px;
                margin-bottom: 16px;
                box-sizing: border-box;
                width: 100%;
                overflow: hidden;
            }
            .panel-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
                margin-bottom: 14px;
                padding-bottom: 10px;
            }
            .panel-title {
                font-size: 15px;
            }
            .panel-desc {
                font-size: 11.5px;
            }

            /* Package Card Mobile */
            .pkg-main-card {
                padding: 16px 14px;
                border-radius: 14px;
            }
            .pkg-card-header {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }
            .pkg-header-info {
                width: 100%;
            }
            .pkg-header-actions {
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .pkg-btn-edit, .pkg-btn-preview, .pkg-btn-delete {
                width: 100%;
                justify-content: center;
                text-align: center;
                box-sizing: border-box;
            }

            .pkg-grid-4 {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .pkg-grid-3 {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .pkg-preview-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .form-group {
                margin-bottom: 10px;
                width: 100%;
                box-sizing: border-box;
            }
            .form-control {
                font-size: 13px;
                padding: 9px 12px;
                width: 100%;
                box-sizing: border-box;
            }
            .btn-primary {
                width: 100%;
                text-align: center;
                justify-content: center;
                padding: 11px 16px;
                font-size: 13px;
            }

            /* Table Responsive */
            .table-responsive {
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-radius: 8px;
                border: 1px solid var(--border);
            }
            .admin-table {
                min-width: 580px;
            }
            .admin-table th, .admin-table td {
                padding: 10px 12px;
                font-size: 12px;
            }

            /* Integration Box */
            .integration-box {
                padding: 12px 14px;
                font-size: 12px;
            }

            /* Modal */
            .modal {
                padding: 12px;
            }
            .modal-content {
                padding: 18px 14px;
                width: 100%;
                max-width: 100%;
                max-height: 90vh;
                overflow-y: auto;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid .stat-card:last-child {
                grid-column: span 1;
            }
            .topbar-right {
                grid-template-columns: 1fr;
            }
            .btn-view-form, .btn-logout {
                width: 100%;
                text-align: center;
            }
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

        <!-- INFO BULAN OTOMATIS -->
        <div style="background:linear-gradient(135deg, rgba(16,185,129,0.12), rgba(0,160,223,0.12));border:1px solid rgba(16,185,129,0.3);border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <span style="font-size:26px;">📅</span>
                <div>
                    <strong style="color:#6ee7b7;font-size:13.5px;">Bulan Otomatis &amp; Sinkronisasi Formulir CBN</strong>
                    <p style="font-size:12px;color:#cbd5e1;margin-top:2px;line-height:1.5;">
                        Gunakan placeholder <code style="background:rgba(0,0,0,0.4);color:#67e8f9;padding:2px 6px;border-radius:4px;font-weight:700;">{BULAN}</code> pada Deskripsi Paket CBN agar nama bulan selalu otomatis terisi sesuai bulan berjalan.<br>
                        Bulan saat ini: <strong style="color:#34d399;font-size:13px;"><?= date('F Y') ?></strong>
                    </p>
                </div>
            </div>
            <div style="display:flex;gap:10px;">
                <a href="#tambah-paket-card" class="btn-primary" style="padding:8px 16px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                    ➕ Tambah Paket Baru
                </a>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <div class="panel-title" style="font-size:17px;font-weight:800;color:#fff;">Daftar Paket Internet CBN — Kelola Harga &amp; CBN Package</div>
                    <div class="panel-desc">Setiap paket dapat diedit secara langsung (Nama, Kecepatan, Harga, Biaya Tambahan, Badge, dan teks CBN Package Auto-Claim) serta dapat di-preview langsung ke Surat Formulir CBN.</div>
                </div>
                <button type="submit" form="form-packages" class="btn-primary" style="padding:9px 18px;font-size:13px;box-shadow:0 4px 15px rgba(0,160,223,0.35);">
                    💾 Simpan Semua Perubahan
                </button>
            </div>

            <form method="POST" id="form-packages">
                <input type="hidden" name="action" value="update_packages">

                <?php
                $monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
                $currentMonth = $monthNames[(int)date('n')];
                $currentYear  = date('Y');
                ?>

                <?php foreach ($packages as $idx => $pkg):
                    $cbnLines = $pkg['cbn_package'] ?? [];
                    if (!is_array($cbnLines)) $cbnLines = !empty($cbnLines) ? [$cbnLines] : [];
                    $cbnLinesDisplay = array_map(fn($l) => str_replace('{BULAN}', $currentMonth . ' ' . $currentYear, $l), $cbnLines);
                    $cbnRaw = implode("\n", $cbnLines);
                    $biayaTambahan = (int)($pkg['biaya_tambahan'] ?? 5000);
                    $price = (int)($pkg['price'] ?? 0);
                    $badgeColor = $pkg['badge_color'] ?? '#005696';
                    $badgeText = $pkg['badge'] ?? '';
                    $isActive = !empty($pkg['active']);
                    $pkgId = $pkg['id'];

                    $estimasiSubtotal = $price + $biayaTambahan;
                    $estimasiPpn = round($estimasiSubtotal * 0.11);
                    $estimasiTotal = $estimasiSubtotal + $estimasiPpn;
                ?>
                <div class="pkg-main-card" id="pkg-card-<?= $pkgId ?>">

                    <!-- Header Row / Summary Bar -->
                    <div class="pkg-card-header">
                        <div class="pkg-header-info">
                            <span style="font-size:18px;font-weight:900;color:#fff;" id="title-name-<?= $pkgId ?>">🌐 <?= htmlspecialchars($pkg['name']) ?></span>
                            
                            <span id="badge-preview-<?= $pkgId ?>" style="background:<?= htmlspecialchars($badgeColor) ?>;color:#fff;font-size:10.5px;font-weight:800;padding:3px 10px;border-radius:12px;<?= empty($badgeText) ? 'display:none;' : '' ?>">
                                <?= htmlspecialchars($badgeText) ?>
                            </span>

                            <span id="status-pill-<?= $pkgId ?>" style="background:<?= $isActive ? 'rgba(16,185,129,0.2)' : 'rgba(239,68,68,0.2)' ?>;color:<?= $isActive ? '#6ee7b7' : '#fca5a5' ?>;border:1px solid <?= $isActive ? 'rgba(16,185,129,0.4)' : 'rgba(239,68,68,0.4)' ?>;font-size:10.5px;font-weight:800;padding:2px 10px;border-radius:12px;">
                                <?= $isActive ? '● AKTIF' : '○ NON-AKTIF' ?>
                            </span>

                            <span style="font-size:12.5px;color:var(--text-muted);background:rgba(255,255,255,0.05);padding:3px 10px;border-radius:8px;">
                                🚀 <span id="summary-speed-<?= $pkgId ?>"><?= htmlspecialchars($pkg['speed']) ?></span> • <strong style="color:#67e8f9;" id="summary-price-<?= $pkgId ?>">Rp <?= number_format($price, 0, ',', '.') ?>/bln</strong>
                            </span>
                        </div>

                        <!-- Action Buttons -->
                        <div class="pkg-header-actions">
                            <button type="button" class="pkg-btn-edit" onclick="toggleEditPackage('<?= $pkgId ?>')">
                                ✏️ Edit Paket
                            </button>

                            <a href="../preview_cbn.php?pkg_id=<?= urlencode($pkgId) ?>" target="_blank" class="pkg-btn-preview" title="Lihat tampilan paket ini pada Formulir Resmi CBN">
                                🔍 Preview di Formulir CBN
                            </a>

                            <label class="pkg-btn-delete" title="Centang untuk menghapus paket ini saat klik simpan">
                                <input type="checkbox" name="delete_pkg[]" value="<?= htmlspecialchars($pkgId) ?>"
                                    onchange="document.getElementById('pkg-card-<?= $pkgId ?>').style.opacity=this.checked?'0.35':'1'">
                                <span>🗑 Hapus</span>
                            </label>
                        </div>
                    </div>

                    <!-- Collapsible / Editable Form Content -->
                    <div id="edit-panel-<?= $pkgId ?>" class="pkg-edit-panel" style="display:block;">
                        
                        <!-- Row 1: Nama Paket + Speed + Price + Biaya Tambahan -->
                        <div class="pkg-grid-4">
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;font-weight:700;color:#cbd5e1;">Nama Paket *</label>
                                <input type="text" name="pkg_name_<?= $pkgId ?>" id="pkg-name-<?= $pkgId ?>" class="form-control" value="<?= htmlspecialchars($pkg['name']) ?>" style="padding:9px 12px;font-weight:700;" oninput="syncPkgLive('<?= $pkgId ?>')" required>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;font-weight:700;color:#cbd5e1;">Deskripsi Kecepatan *</label>
                                <input type="text" name="pkg_speed_<?= $pkgId ?>" id="pkg-speed-<?= $pkgId ?>" class="form-control" value="<?= htmlspecialchars($pkg['speed']) ?>" style="padding:9px 12px;" oninput="syncPkgLive('<?= $pkgId ?>')" required>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;font-weight:700;color:#cbd5e1;">Harga Paket Bulanan (Rp) *</label>
                                <input type="text" name="pkg_price_<?= $pkgId ?>" id="pkg-price-<?= $pkgId ?>" class="form-control" value="<?= number_format($price, 0, ',', '.') ?>" style="padding:9px 12px;font-weight:700;color:#67e8f9;" oninput="syncPkgLive('<?= $pkgId ?>')" required>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;font-weight:700;color:#cbd5e1;">Biaya Tambahan (Rp)</label>
                                <input type="text" name="pkg_biaya_tambahan_<?= $pkgId ?>" id="pkg-tambahan-<?= $pkgId ?>" class="form-control" value="<?= number_format($biayaTambahan, 0, ',', '.') ?>" style="padding:9px 12px;" oninput="syncPkgLive('<?= $pkgId ?>')" placeholder="5000">
                            </div>
                        </div>

                        <!-- Row 2: Badge Label + Badge Color + Status Aktif -->
                        <div class="pkg-grid-3">
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;font-weight:700;color:#cbd5e1;">Badge Label (opsional)</label>
                                <input type="text" name="pkg_badge_<?= $pkgId ?>" id="pkg-badge-<?= $pkgId ?>" class="form-control" value="<?= htmlspecialchars($badgeText) ?>" style="padding:9px 12px;" placeholder="Contoh: POPULAR / BEST VALUE" oninput="syncPkgLive('<?= $pkgId ?>')">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;font-weight:700;color:#cbd5e1;">Warna Badge</label>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <input type="color" name="pkg_badge_color_<?= $pkgId ?>" id="pkg-badge-color-<?= $pkgId ?>" class="form-control" value="<?= htmlspecialchars($badgeColor) ?>" style="height:40px;width:55px;padding:2px 4px;cursor:pointer;" onchange="syncPkgLive('<?= $pkgId ?>')">
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($badgeColor) ?>" id="pkg-badge-color-text-<?= $pkgId ?>" style="padding:9px 10px;font-family:monospace;font-size:12px;" oninput="document.getElementById('pkg-badge-color-<?= $pkgId ?>').value=this.value; syncPkgLive('<?= $pkgId ?>');">
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;font-weight:700;color:#cbd5e1;">Status Tampil di Form</label>
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;height:40px;background:rgba(255,255,255,0.04);border:1px solid var(--border);border-radius:8px;padding:0 14px;">
                                    <input type="checkbox" name="pkg_active_<?= $pkgId ?>" id="pkg-active-<?= $pkgId ?>" value="1" <?= $isActive ? 'checked' : '' ?> onchange="syncPkgLive('<?= $pkgId ?>')">
                                    <span style="font-size:13px;font-weight:700;color:#6ee7b7;">Aktifkan Paket Ini</span>
                                </label>
                            </div>
                        </div>

                        <!-- Row 3: CBN Package descriptions (Auto Claim) -->
                        <div class="form-group" style="margin-bottom:14px;">
                            <label style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;flex-wrap:wrap;gap:4px;">
                                <span style="font-size:12.5px;font-weight:700;color:#cbd5e1;">🏷 Deskripsi Paket CBN yang Termasuk (Auto-Claim di Bagian Add-On TV Surat)</span>
                                <span style="font-weight:400;font-size:11px;color:#67e8f9;">💡 Satu baris = satu item ter-ceklis. Ketik <code>{BULAN}</code> untuk nama bulan dinamis.</span>
                            </label>
                            <textarea name="pkg_cbn_package_<?= $pkgId ?>" id="pkg-cbn-<?= $pkgId ?>" class="form-control"
                                rows="<?= max(2, count($cbnLines)) ?>"
                                placeholder="Contoh:&#10;CBN Fiber {BULAN} Package 2 (100, 150 &amp; 200 Mbps) [1]&#10;Trend Micro Maximum Security 1 Months - 1 Device (Free) [1]"
                                style="font-size:12.5px;line-height:1.6;resize:vertical;"
                                oninput="syncPkgLive('<?= $pkgId ?>')"><?= htmlspecialchars($cbnRaw) ?></textarea>
                        </div>

                        <!-- Row 4: LIVE DYNAMIC PREVIEWS -->
                        <div class="pkg-preview-grid">
                            
                            <!-- Box 1: Live CBN Package Checkmarks Preview -->
                            <div style="padding:14px;background:rgba(0,0,0,0.3);border:1px solid rgba(0,160,223,0.25);border-radius:10px;">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:4px;">
                                    <span style="font-size:11px;color:#67e8f9;font-weight:800;letter-spacing:0.5px;">📋 LIVE PREVIEW TEKS CEKLIS DI SURAT:</span>
                                    <span style="font-size:10px;color:#94a3b8;">Bulan: <?= $currentMonth . ' ' . $currentYear ?></span>
                                </div>
                                <div id="live-cbn-preview-<?= $pkgId ?>" style="min-height:48px;display:flex;flex-direction:column;gap:6px;">
                                    <?php if (!empty($cbnLinesDisplay)): ?>
                                        <?php foreach ($cbnLinesDisplay as $line): ?>
                                            <div style="font-size:12px;color:#86efac;display:flex;align-items:flex-start;gap:6px;line-height:1.4;">
                                                <span style="color:#10b981;font-weight:900;">✓</span>
                                                <span><?= htmlspecialchars($line) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="font-size:12px;color:#64748b;font-style:italic;">(Tidak ada item CBN Package untuk paket ini)</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Box 2: Live Real-time Perincian Biaya Preview -->
                            <div style="padding:14px;background:linear-gradient(135deg, rgba(0,86,150,0.18), rgba(0,160,223,0.1));border:1px solid rgba(0,160,223,0.3);border-radius:10px;">
                                <div style="font-size:11px;color:#67e8f9;font-weight:800;letter-spacing:0.5px;margin-bottom:8px;">💰 LIVE ESTIMASI PERINCIAN BIAYA FORMULIR:</div>
                                <div style="display:flex;flex-direction:column;gap:5px;font-size:12px;color:#cbd5e1;">
                                    <div style="display:flex;justify-content:space-between;">
                                        <span>Biaya Paket:</span>
                                        <strong style="color:#fff;" id="live-calc-paket-<?= $pkgId ?>">Rp <?= number_format($price, 0, ',', '.') ?></strong>
                                    </div>
                                    <div style="display:flex;justify-content:space-between;">
                                        <span>Biaya Tambahan:</span>
                                        <strong style="color:#fff;" id="live-calc-tambahan-<?= $pkgId ?>">Rp <?= number_format($biayaTambahan, 0, ',', '.') ?></strong>
                                    </div>
                                    <div style="display:flex;justify-content:space-between;">
                                        <span>PPN 11%:</span>
                                        <strong style="color:#fff;" id="live-calc-ppn-<?= $pkgId ?>">Rp <?= number_format($estimasiPpn, 0, ',', '.') ?></strong>
                                    </div>
                                    <div style="display:flex;justify-content:space-between;border-top:1px solid rgba(255,255,255,0.12);padding-top:5px;margin-top:2px;">
                                        <span style="font-weight:700;color:#fff;">TOTAL:</span>
                                        <strong style="color:#67e8f9;font-size:13.5px;" id="live-calc-total-<?= $pkgId ?>">Rp <?= number_format($estimasiTotal, 0, ',', '.') ?></strong>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>
                <?php endforeach; ?>

                <div style="position:sticky;bottom:15px;z-index:90;background:rgba(10,17,40,0.95);backdrop-filter:blur(15px);padding:14px 20px;border-radius:12px;border:1px solid rgba(0,160,223,0.3);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-top:15px;">
                    <span style="font-size:13px;color:#cbd5e1;">Pastikan semua perubahan sudah sesuai sebelum menyimpan.</span>
                    <button type="submit" class="btn-primary" style="padding:12px 28px;font-size:14px;box-shadow:0 4px 20px rgba(0,160,223,0.4);">
                        💾 Simpan Semua Perubahan Paket
                    </button>
                </div>
            </form>
        </div>

        <!-- TAMBAH PAKET BARU -->
        <div class="panel-card" id="tambah-paket-card">
            <div class="panel-header">
                <div>
                    <div class="panel-title" style="font-size:16px;font-weight:800;color:#fff;">➕ Tambah Paket CBN Baru</div>
                    <div class="panel-desc">Isi kolom di bawah untuk menambahkan paket internet baru ke dalam sistem dan formulir pendaftaran</div>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="update_packages">

                <div class="pkg-grid-4">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Nama Paket *</label>
                        <input type="text" name="new_pkg_name" class="form-control" placeholder="Misal: Fiber 200" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Deskripsi Kecepatan *</label>
                        <input type="text" name="new_pkg_speed" class="form-control" placeholder="Speed up to 200 Mbps" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Harga Bulanan (Rp) *</label>
                        <input type="text" name="new_pkg_price" class="form-control" placeholder="349000" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Biaya Tambahan (Rp)</label>
                        <input type="text" name="new_pkg_biaya_tambahan" class="form-control" placeholder="5000" value="5000">
                    </div>
                </div>

                <div class="pkg-grid-4" style="margin-top:14px;">
                    <div class="form-group" style="margin-bottom:0;grid-column:span 2;">
                        <label>Badge Label (opsional)</label>
                        <input type="text" name="new_pkg_badge" class="form-control" placeholder="ULTRA SPEED / BEST VALUE">
                    </div>
                    <div class="form-group" style="margin-bottom:0;grid-column:span 2;">
                        <label>Warna Badge</label>
                        <input type="color" name="new_pkg_badge_color" class="form-control" value="#005696" style="height:40px;width:100%;cursor:pointer;padding:2px 4px;">
                    </div>
                </div>

                <div class="form-group" style="margin-top:14px;margin-bottom:14px;">
                    <label style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:4px;">
                        <span>🏷 Deskripsi Paket CBN (satu baris = satu item yang ter-ceklis di surat formulir)</span>
                        <span style="font-size:11px;color:#64748b;">Gunakan <code>{BULAN}</code> untuk nama bulan otomatis (misal: <?= $currentMonth . ' ' . $currentYear ?>)</span>
                    </label>
                    <textarea name="new_pkg_cbn_package" class="form-control" rows="3"
                        placeholder="CBN Fiber {BULAN} Package 2 (100, 150 &amp; 200 Mbps) [1]&#10;Trend Micro Maximum Security 1 Months - 1 Device (Free) [1]"
                        style="font-size:12.5px;line-height:1.6;resize:vertical;"></textarea>
                </div>

                <button type="submit" class="btn-primary" style="padding:11px 24px;font-size:13px;">
                    ➕ Tambah Paket Baru Sekarang
                </button>
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
function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    const content = document.getElementById(tabId);
    if (content) content.classList.add('active');
    
    if (btn) {
        btn.classList.add('active');
    } else if (typeof event !== 'undefined' && event && event.target && event.target.classList) {
        event.target.classList.add('active');
    } else {
        const matchingBtn = document.querySelector(`.tab-btn[onclick*="${tabId}"]`);
        if (matchingBtn) matchingBtn.classList.add('active');
    }
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

// === FITUR EDIT & LIVE PREVIEW PAKET CBN ===
const CURRENT_MONTH_YEAR = '<?= $currentMonth . " " . $currentYear ?>';

function toggleEditPackage(pkgId) {
    const panel = document.getElementById('edit-panel-' + pkgId);
    if (!panel) return;
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        const firstInput = document.getElementById('pkg-name-' + pkgId);
        if (firstInput) firstInput.focus();
    } else {
        panel.style.display = 'none';
    }
}

function formatRupiahJs(num) {
    return 'Rp ' + Number(num).toLocaleString('id-ID');
}

function syncPkgLive(pkgId) {
    const nameEl = document.getElementById('pkg-name-' + pkgId);
    const speedEl = document.getElementById('pkg-speed-' + pkgId);
    const priceEl = document.getElementById('pkg-price-' + pkgId);
    const tambahanEl = document.getElementById('pkg-tambahan-' + pkgId);
    const badgeEl = document.getElementById('pkg-badge-' + pkgId);
    const badgeColorEl = document.getElementById('pkg-badge-color-' + pkgId);
    const badgeColorTextEl = document.getElementById('pkg-badge-color-text-' + pkgId);
    const activeEl = document.getElementById('pkg-active-' + pkgId);
    const cbnEl = document.getElementById('pkg-cbn-' + pkgId);

    // 1. Update Title & Header Summaries
    if (nameEl) {
        const titleName = document.getElementById('title-name-' + pkgId);
        if (titleName) titleName.textContent = '🌐 ' + (nameEl.value.trim() || 'Paket');
    }
    if (speedEl) {
        const summarySpeed = document.getElementById('summary-speed-' + pkgId);
        if (summarySpeed) summarySpeed.textContent = speedEl.value.trim();
    }

    // 2. Parse numbers & calculate pricing
    const rawPrice = (priceEl ? priceEl.value : '0').replace(/\D/g, '');
    const price = parseInt(rawPrice, 10) || 0;

    const rawTambahan = (tambahanEl ? tambahanEl.value : '0').replace(/\D/g, '');
    const tambahan = parseInt(rawTambahan, 10) || 0;

    const subtotal = price + tambahan;
    const ppn = Math.round(subtotal * 0.11);
    const total = subtotal + ppn;

    // Update Header Price Summary
    const summaryPrice = document.getElementById('summary-price-' + pkgId);
    if (summaryPrice) summaryPrice.textContent = formatRupiahJs(price) + '/bln';

    // Update Live Calculation Box
    const calcPaket = document.getElementById('live-calc-paket-' + pkgId);
    const calcTambahan = document.getElementById('live-calc-tambahan-' + pkgId);
    const calcPpn = document.getElementById('live-calc-ppn-' + pkgId);
    const calcTotal = document.getElementById('live-calc-total-' + pkgId);

    if (calcPaket) calcPaket.textContent = formatRupiahJs(price);
    if (calcTambahan) calcTambahan.textContent = formatRupiahJs(tambahan);
    if (calcPpn) calcPpn.textContent = formatRupiahJs(ppn);
    if (calcTotal) calcTotal.textContent = formatRupiahJs(total);

    // 3. Update Badge & Color
    const badgePreview = document.getElementById('badge-preview-' + pkgId);
    if (badgePreview && badgeEl) {
        const bText = badgeEl.value.trim();
        const bColor = badgeColorEl ? badgeColorEl.value : '#005696';
        if (badgeColorTextEl && badgeColorEl) badgeColorTextEl.value = bColor;
        badgePreview.style.backgroundColor = bColor;
        if (bText) {
            badgePreview.textContent = bText;
            badgePreview.style.display = 'inline-block';
        } else {
            badgePreview.style.display = 'none';
        }
    }

    // 4. Update Active Status Pill
    const statusPill = document.getElementById('status-pill-' + pkgId);
    if (statusPill && activeEl) {
        if (activeEl.checked) {
            statusPill.textContent = '● AKTIF';
            statusPill.style.background = 'rgba(16,185,129,0.2)';
            statusPill.style.color = '#6ee7b7';
            statusPill.style.borderColor = 'rgba(16,185,129,0.4)';
        } else {
            statusPill.textContent = '○ NON-AKTIF';
            statusPill.style.background = 'rgba(239,68,68,0.2)';
            statusPill.style.color = '#fca5a5';
            statusPill.style.borderColor = 'rgba(239,68,68,0.4)';
        }
    }

    // 5. Update CBN Package Text Live Preview
    const cbnPreviewBox = document.getElementById('live-cbn-preview-' + pkgId);
    if (cbnPreviewBox && cbnEl) {
        const lines = cbnEl.value.split('\n').map(l => l.trim()).filter(Boolean);
        if (lines.length > 0) {
            cbnPreviewBox.innerHTML = lines.map(line => {
                const formatted = line.replace(/\{BULAN\}/gi, CURRENT_MONTH_YEAR);
                return `
                    <div style="font-size:12px;color:#86efac;display:flex;align-items:flex-start;gap:6px;line-height:1.4;">
                        <span style="color:#10b981;font-weight:900;">✓</span>
                        <span>${escapeHtml(formatted)}</span>
                    </div>
                `;
            }).join('');
        } else {
            cbnPreviewBox.innerHTML = '<span style="font-size:12px;color:#64748b;font-style:italic;">(Tidak ada item CBN Package untuk paket ini)</span>';
        }
    }
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}
</script>

</body>
</html>
