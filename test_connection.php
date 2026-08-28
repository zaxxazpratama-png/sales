<?php
/**
 * FORMGOOGLE - Diagnostic & System Checker
 * Domain: idpanel.site / Localhost / cPanel
 */
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/vendor/autoload.php';

use App\Config;
use App\Database;
use App\AuthManager;
use App\SalesManager;
use App\SettingsManager;
use App\OrdersManager;

Config::load();

$dbStatus = Database::isConnected();
$settings = SettingsManager::get();
$salesCount = count(SalesManager::getAll());
$usersCount = count(AuthManager::getUsers());
$ordersCount = count(OrdersManager::getAll());
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Diagnostic - idpanel.site</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; padding: 24px; }
        .card { max-width: 700px; margin: 0 auto; background: #1e293b; border-radius: 12px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        h1 { font-size: 20px; margin-bottom: 20px; color: #38bdf8; border-bottom: 1px solid #334155; padding-bottom: 12px; }
        .item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #334155; font-size: 14px; }
        .badge { padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 12px; }
        .badge-success { background: #059669; color: white; }
        .badge-warning { background: #d97706; color: white; }
        .badge-danger { background: #dc2626; color: white; }
        .btn { display: inline-block; margin-top: 20px; padding: 10px 16px; background: #0284c7; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 13px; }
    </style>
</head>
<body>

<div class="card">
    <h1>🚀 System Health & Diagnostic: idpanel.site</h1>

    <div class="item">
        <span>PHP Version</span>
        <span class="badge badge-success"><?= PHP_VERSION ?></span>
    </div>

    <div class="item">
        <span>PDO MySQL Extension</span>
        <span class="badge <?= extension_loaded('pdo_mysql') ? 'badge-success' : 'badge-danger' ?>">
            <?= extension_loaded('pdo_mysql') ? 'AKTIF' : 'TIDAK AKTIF' ?>
        </span>
    </div>

    <div class="item">
        <span>Mode Penyimpanan Database</span>
        <span class="badge <?= $dbStatus ? 'badge-success' : 'badge-warning' ?>">
            <?= $dbStatus ? 'MySQL Database (cPanel / Server)' : 'JSON Fallback Mode (/tmp & /data)' ?>
        </span>
    </div>

    <div class="item">
        <span>Total Akun Pengguna</span>
        <span class="badge badge-success"><?= $usersCount ?> Akun</span>
    </div>

    <div class="item">
        <span>Total Tim Sales</span>
        <span class="badge badge-success"><?= $salesCount ?> Sales Terdaftar</span>
    </div>

    <div class="item">
        <span>Total Order Tersimpan</span>
        <span class="badge badge-success"><?= $ordersCount ?> Tiket</span>
    </div>

    <div class="item">
        <span>Nama Perusahaan</span>
        <span style="color: #cbd5e1; font-weight: 600;"><?= htmlspecialchars($settings['company_name'] ?? 'N/A') ?></span>
    </div>

    <div class="item">
        <span>Google Apps Script Sync</span>
        <span class="badge <?= !empty($settings['apps_script_url']) ? 'badge-success' : 'badge-warning' ?>">
            <?= !empty($settings['apps_script_url']) ? 'TERHUBUNG' : 'BELUM DISET' ?>
        </span>
    </div>

    <div style="margin-top: 24px; text-align: center;">
        <a href="admin" class="btn">Buka Panel Admin &rarr;</a>
        <a href="SEP-001" class="btn" style="background:#475569; margin-left: 8px;">Uji Form Sales &rarr;</a>
    </div>
</div>

</body>
</html>
