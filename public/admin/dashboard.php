<?php
/**
 * FORMGOOGLE - Admin Dashboard
 * Manajemen Tim Sales, Link Generator, dan Pengaturan Form Dinamis
 * PT. Talenta Integritas Nasional
 */
require_once dirname(__DIR__, 2) . '/src/autoload.php';

use App\Config;
use App\SalesManager;
use App\SettingsManager;
use App\AuthManager;
use App\OrdersManager;

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
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_user'], $_SESSION['admin_role'], $_SESSION['admin_tl_code']);
    header('Location: admin');
    exit;
}

Config::load();

$currentRole   = $_SESSION['admin_role'] ?? 'admin';
$currentTlCode = $_SESSION['admin_tl_code'] ?? '';
$currentUser   = $_SESSION['admin_user'] ?? 'admin';
$currentTlAccount = ($currentRole === 'tl' && $currentTlCode) ? AuthManager::getTlByCode($currentTlCode) : null;
$activeTab     = $_SESSION['active_admin_tab'] ?? 'sales-tab';

$msgSuccess = '';
$msgError   = '';

// ========================================================
// REALTIME API: FETCH ORDERS (AUTO-SYNC & POLLING)
// ========================================================
if (isset($_GET['action']) && $_GET['action'] === 'fetch_orders_realtime') {
    header('Content-Type: application/json; charset=utf-8');
    $allOrders = OrdersManager::getAll();
    if ($currentRole === 'tl') {
        $mySalesList = SalesManager::getAll();
        $mySalesCodes = array_map(fn($s) => strtoupper(trim($s['sales_code'] ?? '')), 
            array_filter($mySalesList, fn($s) => strtoupper(trim($s['tl_code'] ?? '')) === strtoupper(trim($currentTlCode)))
        );
        $allOrders = array_values(array_filter($allOrders, function($ord) use ($currentTlCode, $mySalesCodes) {
            $ordTl = strtoupper(trim($ord['tl_code'] ?? ''));
            $ordSales = strtoupper(trim($ord['sales_code'] ?? ''));
            return ($ordTl !== '' && $ordTl === strtoupper(trim($currentTlCode))) || in_array($ordSales, $mySalesCodes);
        }));
    }
    // Sort newest first
    usort($allOrders, function($a, $b) {
        return strcmp($b['submitted_at'] ?? '', $a['submitted_at'] ?? '');
    });

    $pendingCount = count(array_filter($allOrders, fn($o) => strtoupper($o['status'] ?? 'PENDING') === 'PENDING'));

    echo json_encode([
        'success'       => true,
        'orders'        => $allOrders,
        'pending_count' => $pendingCount,
        'total_count'   => count($allOrders),
        'role'          => $currentRole,
        'timestamp'     => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========================================================
// POST ACTION HANDLERS
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_team_leader' && $currentRole === 'superadmin') {
        try {
            AuthManager::addTeamLeader(
                $_POST['tl_username'] ?? '',
                $_POST['tl_password'] ?? '',
                $_POST['tl_code'] ?? '',
                $_POST['tl_admin_email'] ?? ''
            );
            $msgSuccess = 'Akun team leader berhasil dibuat.';
        } catch (\Exception $e) {
            $msgError = $e->getMessage();
        }
    }

    if ($action === 'edit_team_leader' && $currentRole === 'superadmin') {
        try {
            $tlId = $_POST['tl_id'] ?? '';
            $oldTlCode = '';
            foreach (AuthManager::getUsers() as $user) {
                if (($user['id'] ?? $user['username'] ?? '') === $tlId) {
                    $oldTlCode = $user['tl_code'] ?? '';
                    break;
                }
            }
            $newTlCode = strtoupper(trim($_POST['tl_code'] ?? ''));
            AuthManager::updateTeamLeader(
                $tlId,
                $_POST['tl_username'] ?? '',
                $_POST['tl_password'] ?? '',
                $newTlCode,
                $_POST['tl_admin_email'] ?? '',
                $_POST['tl_status'] ?? 'active'
            );
            if ($oldTlCode !== '' && $oldTlCode !== $newTlCode) {
                SalesManager::reassignTeamLeader($oldTlCode, $newTlCode);
            }
            $msgSuccess = 'Data team leader berhasil diperbarui.';
        } catch (\Exception $e) {
            $msgError = $e->getMessage();
        }
    }

    // 1. TAMBAH SALES BARU
    if ($action === 'add_sales') {
        try {
            $salesCode = strtoupper(trim($_POST['sales_code'] ?? ''));
            $namaSales = trim($_POST['nama_sales'] ?? '');
            $noWa      = trim($_POST['no_wa'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $tlCode    = $currentRole === 'tl' ? $currentTlCode : trim($_POST['tl_code'] ?? 'TL-01');

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
            $tlCode    = $currentRole === 'tl' ? $currentTlCode : trim($_POST['tl_code'] ?? 'TL-01');
            $status    = $_POST['status'] ?? 'active';

            $existingSales = SalesManager::findById($id);
            $ttdPath = $existingSales['ttd_path'] ?? '';

            if (!empty($_FILES['ttd_sales']['name']) && $_FILES['ttd_sales']['error'] === UPLOAD_ERR_OK) {
                $fileName = 'ttd_sales_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $salesCode)) . '.png';
                $target = dirname(__DIR__) . '/assets/img/' . $fileName;
                \App\ImageHelper::makeTransparentSignature($_FILES['ttd_sales']['tmp_name'], $target);
                $ttdPath = 'assets/img/' . $fileName;
            }

            SalesManager::update($id, [
                'sales_code' => $salesCode,
                'nama_sales' => $namaSales,
                'no_wa'      => $noWa,
                'email'      => $email,
                'tl_code'    => $tlCode,
                'ttd_path'   => $ttdPath,
                'status'     => $status
            ]);

            $msgSuccess = "Data sales <strong>{$namaSales}</strong> berhasil diperbarui!";
        } catch (\Exception $e) {
            $msgError = $e->getMessage();
        }
    }

    // 3. HAPUS SALES
    elseif ($action === 'delete_sales') {
        $id   = trim($_POST['sales_id'] ?? '');
        $code = trim($_POST['sales_code'] ?? '');
        if (($id !== '' && SalesManager::delete($id)) || ($code !== '' && SalesManager::delete($code))) {
            $msgSuccess = "Sales berhasil dihapus.";
        } else {
            $msgError = "Gagal menghapus sales.";
        }
    }

    // 6. TOGGLE EMAIL CUSTOMER PER SALES
    elseif ($action === 'toggle_email_customer') {
        $id      = trim($_POST['sales_id'] ?? '');
        $enabled = ($_POST['enabled'] ?? '1') === '1';
        $sales   = SalesManager::findById($id);
        if ($sales) {
            SalesManager::update($id, array_merge($sales, ['email_customer_enabled' => $enabled]));
            if (!empty($_POST['ajax']) || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'enabled' => $enabled,
                    'message' => 'Status kirim email customer untuk sales ' . ($sales['nama_sales'] ?? '') . ' diubah ke ' . ($enabled ? 'ON (Aktif)' : 'OFF (Nonaktif)')
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $msgSuccess = "Pengaturan email customer berhasil diubah.";
        } else {
            if (!empty($_POST['ajax']) || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Sales tidak ditemukan.']);
                exit;
            }
            $msgError = "Sales tidak ditemukan.";
        }
    }

    // 4. UPDATE PROFIL TEAM LEADER
    elseif ($action === 'update_tl_profile' && $currentRole === 'tl') {
        $activeTab = 'settings-tab';
        try {
            $newUsername = trim($_POST['tl_username'] ?? '');
            $newPassword = $_POST['tl_password'] ?? '';
            if ($newUsername === '') {
                throw new \InvalidArgumentException('Username Team Leader tidak boleh kosong.');
            }
            $updated = AuthManager::updateProfile($currentUser, $newUsername, $newPassword);
            $_SESSION['admin_user'] = $updated['username'];
            $currentUser = $updated['username'];
            $msgSuccess = "Profil dan password Team Leader <strong>{$currentUser}</strong> berhasil diperbarui!";
        } catch (\Exception $e) {
            $msgError = $e->getMessage();
        }
    }

    // 5. UPDATE PENGATURAN GOOGLE & PROFIL SUPERADMIN
    elseif ($action === 'update_general_settings') {
        $activeTab = ($currentRole === 'superadmin' && isset($_POST['apps_script_url'])) ? 'google-tab' : 'settings-tab';
        try {
            $settings = SettingsManager::get();

            if ($currentRole === 'superadmin') {
                if (isset($_POST['company_name'])) {
                    $settings['company_name'] = trim($_POST['company_name'] ?? $settings['company_name']);
                }
                if (isset($_POST['call_center'])) {
                    $settings['call_center'] = trim($_POST['call_center'] ?? $settings['call_center']);
                }
                if (isset($_POST['wa_helpdesk'])) {
                    $settings['wa_helpdesk'] = trim($_POST['wa_helpdesk'] ?? $settings['wa_helpdesk']);
                }
                if (isset($_POST['apps_script_url'])) {
                    $appsScriptUrl = trim($_POST['apps_script_url'] ?? '');
                    if ($appsScriptUrl !== '' && !filter_var($appsScriptUrl, FILTER_VALIDATE_URL)) {
                        throw new \InvalidArgumentException('URL Web App Google Apps Script tidak valid.');
                    }
                    $settings['apps_script_url'] = $appsScriptUrl;
                }
                if (isset($_POST['spreadsheet_id'])) {
                    $settings['spreadsheet_id'] = trim($_POST['spreadsheet_id'] ?? '');
                }
                if (isset($_POST['drive_folder_id'])) {
                    $settings['drive_folder_id'] = trim($_POST['drive_folder_id'] ?? '');
                }
                if (isset($_POST['master_email'])) {
                    $settings['master_email'] = trim($_POST['master_email'] ?? '');
                }
                if (isset($_POST['admin_email'])) {
                    $settings['admin_email'] = trim($_POST['admin_email'] ?? '');
                }
                if (isset($_POST['default_notes'])) {
                    $settings['default_notes'] = trim($_POST['default_notes'] ?? 'REGULER PROMO JULY 2026 - NAB');
                }

                // Update superadmin credentials
                $newAdminUsername = trim($_POST['admin_username'] ?? '');
                $newAdminPassword = $_POST['admin_password'] ?? '';
                if ($newAdminUsername !== '') {
                    $updated = AuthManager::updateProfile($currentUser, $newAdminUsername, $newAdminPassword);
                    $_SESSION['admin_user'] = $updated['username'];
                    $currentUser = $updated['username'];
                    $settings['admin_username'] = $newAdminUsername;
                }

                if (!empty($_FILES['ttd_spv']['name']) && $_FILES['ttd_spv']['error'] === UPLOAD_ERR_OK) {
                    $target = dirname(__DIR__) . '/assets/img/ttd_spv_master.png';
                    \App\ImageHelper::makeTransparentSignature($_FILES['ttd_spv']['tmp_name'], $target);
                    $settings['ttd_spv_path'] = 'assets/img/ttd_spv_master.png';
                }

                SettingsManager::update($settings);
                $msgSuccess = "Pengaturan profil & sistem berhasil disimpan!";
            } else {
                $newUsername   = trim($_POST['tl_username'] ?? '');
                $newPassword   = $_POST['tl_password'] ?? '';
                $newAdminEmail = trim($_POST['tl_admin_email'] ?? '');
                if ($newUsername !== '') {
                    $updated = AuthManager::updateProfile($currentUser, $newUsername, $newPassword, $newAdminEmail);
                    $_SESSION['admin_user'] = $updated['username'];
                    $currentUser = $updated['username'];
                    $msgSuccess = 'Profil Team Leader & Email Admin berhasil diperbarui!';
                }
            }
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

    // 7. UPDATE STATUS ORDER
    elseif ($action === 'update_order_status') {
        $activeTab = 'orders-tab';
        $ticketNo  = trim($_POST['ticket_no'] ?? '');
        $newStatus = strtoupper(trim($_POST['order_status'] ?? 'PENDING'));
        $allowedStatuses = ['PENDING', 'DIPROSES', 'SELESAI', 'BATAL'];
        if (!in_array($newStatus, $allowedStatuses)) $newStatus = 'PENDING';

        $targetOrder = OrdersManager::updateStatus($ticketNo, $newStatus, $currentRole === 'tl' ? $currentTlCode : null);

        // Sinkronkan status langsung ke Google Spreadsheet via Apps Script
        $gsheetSynced = false;
        $settings = SettingsManager::get();
        try {
            if (!empty($settings['apps_script_url'])) {
                $service = new \App\AppsScriptService();
                $service->updateStatus($ticketNo, $newStatus, $targetOrder ?: []);
                $gsheetSynced = true;
            }
        } catch (\Exception $syncErr) {
            error_log("GSheet Status Sync Error: " . $syncErr->getMessage());
        }

        if (!empty($_POST['ajax']) || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json; charset=utf-8');
            $allOrders = OrdersManager::getAll();
            if ($currentRole === 'tl') {
                $allOrders = array_values(array_filter($allOrders, fn($o) => ($o['tl_code'] ?? '') === $currentTlCode));
            }
            $pendingCount = count(array_filter($allOrders, fn($o) => strtoupper($o['status'] ?? 'PENDING') === 'PENDING'));
            echo json_encode([
                'success'       => true,
                'ticket_no'     => $ticketNo,
                'status'        => $newStatus,
                'status_class'  => 'status-' . strtolower($newStatus),
                'pending_count' => $pendingCount,
                'total_count'   => count($allOrders),
                'message'       => "Status order {$ticketNo} diubah ke {$newStatus}" . ($gsheetSynced ? " (Tersinkron ke Spreadsheet)" : "")
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($targetOrder === null) {
            $msgError = "Order tiket {$ticketNo} tidak ditemukan.";
        } else {
            $syncInfo = $gsheetSynced ? " (Otomatis Tersinkron ke Google Spreadsheet)" : "";
            $msgSuccess = "Status order <strong>{$ticketNo}</strong> berhasil diubah ke <strong>{$newStatus}</strong>{$syncInfo}.";
        }
    }

    // 8. HAPUS ORDER (KHUSUS SUPER ADMIN)
    elseif ($action === 'delete_order') {
        $activeTab = 'orders-tab';
        if ($currentRole !== 'superadmin') {
            if (!empty($_POST['ajax']) || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Hanya Super Admin yang berhak menghapus data order.']);
                exit;
            }
            $msgError = 'Hanya Super Admin yang berhak menghapus data order.';
        } else {
            $ticketNo = trim($_POST['ticket_no'] ?? '');
            $deletedOrder = OrdersManager::delete($ticketNo);

                // Sinkronkan ke Google Spreadsheet untuk merahkan baris yang terhapus
                $settings = SettingsManager::get();
                $gsheetSynced = false;
                try {
                    if (!empty($settings['apps_script_url'])) {
                        $service = new \App\AppsScriptService();
                        $service->deleteOrder($ticketNo, $deletedOrder ?: []);
                        $gsheetSynced = true;
                    }
                } catch (\Exception $syncErr) {
                    error_log("GSheet Delete Order Sync Error: " . $syncErr->getMessage());
                }

                $remainingOrders = OrdersManager::getAll();
                if (!empty($_POST['ajax']) || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                    header('Content-Type: application/json; charset=utf-8');
                    $pendingCount = count(array_filter($remainingOrders, fn($o) => strtoupper($o['status'] ?? 'PENDING') === 'PENDING'));
                    echo json_encode([
                        'success'       => true,
                        'ticket_no'     => $ticketNo,
                        'pending_count' => $pendingCount,
                        'total_count'   => count($remainingOrders),
                        'message'       => "Order tiket {$ticketNo} berhasil dihapus & di-merahkan di Google Spreadsheet."
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }

                if ($deletedOrder === null) {
                    if (!empty($_POST['ajax']) || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['success' => false, 'message' => 'Data order tidak ditemukan.']);
                        exit;
                    }
                    $msgError = "Data order tidak ditemukan.";
                } else {
                    $syncInfo = $gsheetSynced ? " dan baris di Google Spreadsheet telah ditandai merah (DIHAPUS)" : "";
                    $msgSuccess = "Order tiket <strong>{$ticketNo}</strong> berhasil dihapus{$syncInfo}.";
                }
        }
    }

    // 9. UPDATE TARIF PPN (KHUSUS SUPER ADMIN)
    elseif ($action === 'update_ppn_rate') {
        $activeTab = 'packages-tab';
        if ($currentRole !== 'superadmin') {
            if (!empty($_POST['ajax']) || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Hanya Super Admin yang berhak mengubah tarif PPN.']);
                exit;
            }
            $msgError = 'Hanya Super Admin yang berhak mengubah tarif PPN sistem.';
        } else {
            $ppnRate = (float)($_POST['ppn_percent'] ?? 11);
            if ($ppnRate < 0) $ppnRate = 11;
            $settings['ppn_percent'] = $ppnRate;
            SettingsManager::update($settings);

            if (!empty($_POST['ajax']) || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success'     => true,
                    'ppn_percent' => $ppnRate,
                    'message'     => "Tarif PPN berhasil diperbarui menjadi {$ppnRate}% dan otomatis berlaku untuk seluruh sistem!"
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $msgSuccess = "Tarif PPN berhasil diperbarui menjadi <strong>{$ppnRate}%</strong> dan otomatis berlaku untuk seluruh formulir & tim!";
        }
    }
}

// Data Fetching
$salesList     = SalesManager::getAll();
if ($currentRole === 'tl') {
    $salesList = array_values(array_filter($salesList, fn($sales) => ($sales['tl_code'] ?? '') === $currentTlCode));
}
$settings      = SettingsManager::get();
$teamLeaders   = array_values(array_filter(AuthManager::getUsers(), fn($user) => ($user['role'] ?? '') === 'tl'));
$currentTlAccount = ($currentRole === 'tl' && $currentTlCode) ? AuthManager::getTlByCode($currentTlCode) : null;
$packages      = $settings['packages'] ?? [];
$codeGsPath    = dirname(__DIR__, 2) . '/apps-script/Code.gs';
$codeGsContent = file_exists($codeGsPath) ? file_get_contents($codeGsPath) : '';

// Load Orders untuk Status Tracking
$ordersList = OrdersManager::getAll();
if ($currentRole === 'tl') {
    $mySalesCodes = array_map(fn($s) => strtoupper(trim($s['sales_code'] ?? '')), $salesList);
    $ordersList = array_values(array_filter($ordersList, function($order) use ($currentTlCode, $mySalesCodes) {
        $ordTl = strtoupper(trim($order['tl_code'] ?? ''));
        $ordSales = strtoupper(trim($order['sales_code'] ?? ''));
        return ($ordTl !== '' && $ordTl === strtoupper(trim($currentTlCode))) || in_array($ordSales, $mySalesCodes);
    }));
}
$ordersList = array_reverse($ordersList); // Terbaru di atas

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
$totalOrders = count($ordersList);
$pendingOrders = count(array_filter($ordersList, fn($o) => ($o['status'] ?? 'PENDING') === 'PENDING'));
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
            font-size: 11px; font-weight: 800;
            padding: 4px 12px; border-radius: 12px;
            display: inline-block;
        }
        .status-active, .status-selesai { background: #00ff00; color: #000; font-weight: 800; border: 1px solid #00cc00; }
        .status-inactive, .status-batal { background: #ff0000; color: #fff; font-weight: 800; border: 1px solid #cc0000; }
        .status-pending { background: #ff9900; color: #000; font-weight: 800; border: 1px solid #cc7a00; }
        .status-processing, .status-diproses { background: #ffff00; color: #000; font-weight: 800; border: 1px solid #cccc00; }

        .order-status-select {
            padding: 7px 12px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 12.5px;
            cursor: pointer;
            outline: none;
            transition: all 0.2s ease;
            width: 100%;
        }
        .order-status-select.status-pending { background: #ff9900; color: #000; border: 1px solid #cc7a00; }
        .order-status-select.status-diproses { background: #ffff00; color: #000; border: 1px solid #cccc00; }
        .order-status-select.status-selesai { background: #00ff00; color: #000; border: 1px solid #00cc00; }
        .order-status-select.status-batal { background: #ff0000; color: #fff; border: 1px solid #cc0000; }
        .order-status-select option { background: #111c38; color: #fff; font-weight: normal; }
        .empty-state { padding: 32px; text-align: center; color: var(--text-muted); border: 1px dashed var(--border); border-radius: var(--radius-md); }

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

        @keyframes rowNewAnim {
            0% { background: rgba(16, 185, 129, 0.35); transform: translateY(-8px); opacity: 0; }
            50% { background: rgba(16, 185, 129, 0.2); }
            100% { background: transparent; transform: translateY(0); opacity: 1; }
        }

        @keyframes savingPulse {
            0% { box-shadow: 0 0 0 0 rgba(0, 160, 223, 0.7); }
            70% { box-shadow: 0 0 0 8px rgba(0, 160, 223, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 160, 223, 0); }
        }

        .row-new {
            animation: rowNewAnim 1.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .select-saving {
            animation: savingPulse 1s infinite;
            pointer-events: none;
            opacity: 0.85;
        }

        /* LIVE PULSE DOT */
        .live-pulse-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            margin-left: 6px;
            box-shadow: 0 0 8px #10b981;
            animation: pulseDot 2s infinite;
        }
        @keyframes pulseDot {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1.15); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        /* FLOATING TOAST SYSTEM */
        #toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
            max-width: 380px;
            width: calc(100% - 48px);
        }
        .toast-item {
            pointer-events: auto;
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(15, 23, 42, 0.96);
            border: 1px solid rgba(0, 160, 223, 0.4);
            backdrop-filter: blur(10px);
            color: #fff;
            padding: 12px 18px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            animation: toastIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            transition: all 0.3s ease;
        }
        .toast-item.toast-success { border-color: rgba(16, 185, 129, 0.5); border-left: 4px solid #10b981; }
        .toast-item.toast-error { border-color: rgba(239, 68, 68, 0.5); border-left: 4px solid #ef4444; }
        .toast-item.toast-info { border-color: rgba(0, 160, 223, 0.5); border-left: 4px solid #00a0df; }
        .toast-item.toast-out { opacity: 0; transform: translateY(12px) scale(0.95); }
        @keyframes toastIn {
            from { opacity: 0; transform: translateY(16px) scale(0.9); }
            to { opacity: 1; transform: translateY(0) scale(1); }
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
            <div class="topbar-sub"><?= htmlspecialchars($settings['company_name'] ?? 'PT. TALENTA INTEGRITAS NASIONAL') ?></div>
        </div>
    </div>
    <div class="topbar-right">
        <div style="display:flex;align-items:center;gap:8px;padding:6px 14px;background:rgba(255,255,255,0.06);border:1px solid var(--border);border-radius:8px;font-size:12.5px;">
            <span style="color:#67e8f9;font-weight:800;">👤 <?= htmlspecialchars($currentUser) ?></span>
            <span style="color:var(--text-muted);font-size:11px;">(<?= $currentRole === 'superadmin' ? 'Super Admin' : 'TL: ' . htmlspecialchars($currentTlCode) ?>)</span>
        </div>
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
        <button type="button" class="tab-btn <?= $activeTab === 'sales-tab' ? 'active' : '' ?>" onclick="switchTab('sales-tab', this)">Manajemen Tim Sales & Shortlink</button>
        <button type="button" class="tab-btn <?= $activeTab === 'orders-tab' ? 'active' : '' ?>" onclick="switchTab('orders-tab', this)" id="tab-btn-orders">
            Status Order <span id="orders-tab-count">(<?= $totalOrders ?>)</span> <span class="live-pulse-dot" title="Realtime Live Sync Aktif"></span>
        </button>
        <?php if ($currentRole === 'superadmin'): ?>
            <button type="button" class="tab-btn <?= $activeTab === 'google-tab' ? 'active' : '' ?>" onclick="switchTab('google-tab', this)">Integrasi Google & Apps Script</button>
        <?php endif; ?>
        <button type="button" class="tab-btn <?= $activeTab === 'packages-tab' ? 'active' : '' ?>" onclick="switchTab('packages-tab', this)">Pengaturan Paket & Form</button>
        <button type="button" class="tab-btn <?= $activeTab === 'settings-tab' ? 'active' : '' ?>" onclick="switchTab('settings-tab', this)"><?= $currentRole === 'superadmin' ? 'Profil Perusahaan & Super Admin' : 'Profil & Keamanan Team Leader' ?></button>
    </div>

    <!-- ================= TAB: STATUS ORDER ================= -->
    <div id="orders-tab" class="tab-content <?= $activeTab === 'orders-tab' ? 'active' : '' ?>">
        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <div class="panel-title" style="display:flex;align-items:center;gap:8px;">
                        <span>Status Pendaftaran Pelanggan</span>
                        <span style="font-size:11px;background:rgba(16,185,129,0.15);color:#6ee7b7;border:1px solid rgba(16,185,129,0.3);padding:2px 8px;border-radius:10px;font-weight:700;display:inline-flex;align-items:center;gap:4px;">
                            <span class="live-pulse-dot" style="margin-left:0;"></span> REALTIME LIVE SYNC
                        </span>
                    </div>
                    <div class="panel-desc">Perubahan status dan data pendaftaran diperbarui secara otomatis dan instan secara realtime tanpa perlu me-refresh halaman.</div>
                </div>
                <span class="status-badge status-pending" id="header-pending-badge"><?= $pendingOrders ?> Pending</span>
            </div>

            <!-- SEARCH & STATUS FILTER BAR -->
            <div style="display:flex;gap:12px;margin-bottom:18px;flex-wrap:wrap;align-items:center;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:12px;padding:12px 16px;">
                <div style="flex:1;min-width:260px;position:relative;">
                    <input type="text" id="search-orders" class="form-control" 
                           placeholder="🔍 Cari kata kunci (No. Tiket, Nama Pelanggan, Email, Sales, Home ID, Tikor)..." 
                           oninput="filterOrders()" 
                           style="padding-left:14px;padding-right:38px;font-size:13px;border-radius:8px;background:#090d1a;">
                    <button type="button" id="btn-clear-search-orders" onclick="clearSearchOrders()" 
                            style="display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.1);border:none;color:#cbd5e1;font-size:13px;width:22px;height:22px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;" 
                            title="Hapus kata kunci pencarian">&times;</button>
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                    <span style="font-size:12px;color:var(--text-muted);font-weight:700;">Filter:</span>
                    <button type="button" class="btn-filter-status active" data-status="ALL" onclick="filterStatusOrders('ALL', this)" style="padding:6px 12px;font-size:11.5px;font-weight:700;border-radius:8px;cursor:pointer;background:#00a0df;color:#fff;border:1px solid #00a0df;transition:all 0.2s;">Semua</button>
                    <button type="button" class="btn-filter-status" data-status="PENDING" onclick="filterStatusOrders('PENDING', this)" style="padding:6px 12px;font-size:11.5px;font-weight:700;border-radius:8px;cursor:pointer;background:rgba(245,158,11,0.12);color:#fbbf24;border:1px solid rgba(245,158,11,0.3);transition:all 0.2s;">Pending</button>
                    <button type="button" class="btn-filter-status" data-status="DIPROSES" onclick="filterStatusOrders('DIPROSES', this)" style="padding:6px 12px;font-size:11.5px;font-weight:700;border-radius:8px;cursor:pointer;background:rgba(14,165,233,0.12);color:#38bdf8;border:1px solid rgba(14,165,233,0.3);transition:all 0.2s;">Diproses</button>
                    <button type="button" class="btn-filter-status" data-status="SELESAI" onclick="filterStatusOrders('SELESAI', this)" style="padding:6px 12px;font-size:11.5px;font-weight:700;border-radius:8px;cursor:pointer;background:rgba(16,185,129,0.12);color:#6ee7b7;border:1px solid rgba(16,185,129,0.3);transition:all 0.2s;">Selesai</button>
                    <button type="button" class="btn-filter-status" data-status="BATAL" onclick="filterStatusOrders('BATAL', this)" style="padding:6px 12px;font-size:11.5px;font-weight:700;border-radius:8px;cursor:pointer;background:rgba(239,68,68,0.12);color:#fca5a5;border:1px solid rgba(239,68,68,0.3);transition:all 0.2s;">Batal</button>
                </div>
            </div>

            <div id="orders-not-found" class="empty-state" style="display:none;padding:24px;background:rgba(255,255,255,0.02);border:1px dashed rgba(255,255,255,0.15);border-radius:10px;margin-bottom:14px;color:#94a3b8;">
                🔍 Tidak ditemukan pendaftaran yang cocok dengan kata kunci pencarian Anda.
            </div>

            <div id="orders-empty-state" class="empty-state" style="<?= empty($ordersList) ? '' : 'display:none;' ?>">
                Belum ada pendaftaran yang tercatat.
            </div>

            <div class="table-responsive" id="orders-table-wrapper" style="<?= empty($ordersList) ? 'display:none;' : '' ?>">
                <table class="admin-table" id="orders-table">
                    <thead>
                        <tr>
                            <th>Pelanggan</th>
                            <th>No. Tiket</th>
                            <th>Sales / Team Leader</th>
                            <th>Home ID</th>
                            <th>Waktu Daftar</th>
                            <th>Status Order</th>
                            <?php if ($currentRole === 'superadmin'): ?>
                                <th style="text-align:center;">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="orders-tbody">
                        <?php foreach ($ordersList as $order):
                            $orderStatus = strtoupper($order['status'] ?? 'PENDING');
                            $statusClass = match ($orderStatus) {
                                'SELESAI' => 'status-selesai',
                                'BATAL' => 'status-batal',
                                'DIPROSES' => 'status-diproses',
                                default => 'status-pending',
                            };
                            $ticketId = htmlspecialchars($order['ticket_no'] ?? '');
                        ?>
                        <tr id="order-row-<?= $ticketId ?>">
                            <td><strong><?= htmlspecialchars($order['nama'] ?? '-') ?></strong><br><small><?= htmlspecialchars($order['email'] ?? '-') ?></small></td>
                            <td><code style="font-size:11.5px;color:#38bdf8;"><?= $ticketId ?: '-' ?></code></td>
                            <td><?= htmlspecialchars($order['sales_code'] ?? '-') ?><br><small><?= htmlspecialchars($order['tl_code'] ?? '-') ?></small></td>
                            <td>
                                <?= htmlspecialchars($order['home_id'] ?? '-') ?>
                                <?php if (!empty($order['tikor'])): ?>
                                    <br><a href="https://www.google.com/maps?q=<?= urlencode($order['tikor']) ?>" target="_blank" style="font-size:11px;color:#38bdf8;text-decoration:none;display:inline-flex;align-items:center;gap:3px;margin-top:2px;" title="Buka Lokasi di Google Maps">📍 <?= htmlspecialchars($order['tikor']) ?></a>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($order['submitted_at'] ?? '-') ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:6px;min-width:140px;">
                                    <select id="select-status-<?= $ticketId ?>" class="order-status-select <?= $statusClass ?>" onchange="updateOrderStatusRealtime('<?= $ticketId ?>', this.value, this)" aria-label="Status order <?= $ticketId ?>">
                                        <option value="PENDING" <?= $orderStatus === 'PENDING' ? 'selected' : '' ?>>PENDING</option>
                                        <option value="DIPROSES" <?= $orderStatus === 'DIPROSES' ? 'selected' : '' ?>>DIPROSES</option>
                                        <option value="SELESAI" <?= $orderStatus === 'SELESAI' ? 'selected' : '' ?>>SELESAI</option>
                                        <option value="BATAL" <?= $orderStatus === 'BATAL' ? 'selected' : '' ?>>BATAL</option>
                                    </select>
                                </div>
                            </td>
                            <?php if ($currentRole === 'superadmin'): ?>
                            <td style="text-align:center;white-space:nowrap;">
                                <button type="button" class="btn-action" onclick="deleteOrderRealtime('<?= $ticketId ?>', this)" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border:none;padding:6px 12px;border-radius:6px;font-size:11.5px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px;box-shadow:0 2px 8px rgba(239,68,68,0.35);" title="Hapus order & tandai merah di Spreadsheet">
                                    🗑️ Hapus
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ================= TAB 1: MANAJEMEN SALES ================= -->
    <div id="sales-tab" class="tab-content <?= $activeTab === 'sales-tab' ? 'active' : '' ?>">

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
                            <th style="text-align:center;">Email Customer</th>
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
                            <td style="text-align:center;">
                                <?php $emailOn = $sales['email_customer_enabled'] ?? true; ?>
                                <button type="button" onclick="toggleEmailCustomerAjax('<?= htmlspecialchars($sales['id']) ?>', <?= $emailOn ? '0' : '1' ?>, this)"
                                    title="<?= $emailOn ? 'Klik untuk MATIKAN email ke customer' : 'Klik untuk AKTIFKAN email ke customer' ?>"
                                    style="background:<?= $emailOn ? 'rgba(16,185,129,0.2)' : 'rgba(239,68,68,0.15)' ?>;border:1px solid <?= $emailOn ? 'rgba(16,185,129,0.5)' : 'rgba(239,68,68,0.4)' ?>;color:<?= $emailOn ? '#6ee7b7' : '#fca5a5' ?>;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;cursor:pointer;transition:all 0.2s;white-space:nowrap;">
                                    <?= $emailOn ? '✓ ON' : '✗ OFF' ?>
                                </button>
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
                                    <button type="button" class="btn-action" data-sales='<?= htmlspecialchars(json_encode($sales), ENT_QUOTES, 'UTF-8') ?>' onclick="openEditModal(JSON.parse(this.getAttribute('data-sales')))">Edit</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus sales <?= htmlspecialchars($sales['nama_sales'], ENT_QUOTES) ?> (<?= htmlspecialchars($sales['sales_code'], ENT_QUOTES) ?>)?')">
                                        <input type="hidden" name="action" value="delete_sales">
                                        <input type="hidden" name="sales_id" value="<?= htmlspecialchars($sales['id']) ?>">
                                        <input type="hidden" name="sales_code" value="<?= htmlspecialchars($sales['sales_code']) ?>">
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

    <?php if ($currentRole === 'superadmin'): ?>
    <!-- ================= TAB 2: INTEGRASI GOOGLE & APPS SCRIPT ================= -->
    <div id="google-tab" class="tab-content <?= $activeTab === 'google-tab' ? 'active' : '' ?>">
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
                        <label>Email Master Penerima Notifikasi SO (Super Admin)</label>
                        <input type="email" name="master_email" class="form-control"
                            value="<?= htmlspecialchars($settings['master_email'] ?? 'pujapangestu02@gmail.com') ?>" required>
                        <small style="color:var(--text-muted);font-size:11px;">Menerima tembusan seluruh notifikasi pendaftaran dari semua Team Leader.</small>
                    </div>

                    <div class="form-group">
                        <label>Nama Perusahaan / Mitra</label>
                        <input type="text" name="company_name" class="form-control" 
                            value="<?= htmlspecialchars($settings['company_name'] ?? 'PT. TALENTA INTEGRITAS NASIONAL') ?>" required>
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
    <?php endif; ?>

    <!-- ================= TAB 3: PENGATURAN PAKET & FORM ================= -->
    <div id="packages-tab" class="tab-content <?= $activeTab === 'packages-tab' ? 'active' : '' ?>">

        <?php $ppnPercent = (float)($settings['ppn_percent'] ?? 11); ?>

        <!-- CARD PENGATURAN TARIF PPN SISTEM -->
        <div class="panel-card" style="margin-bottom:20px;background:linear-gradient(135deg,rgba(0,160,223,0.1),rgba(15,23,42,0.6));border:1px solid rgba(0,160,223,0.3);">
            <div class="panel-header" style="margin-bottom:12px;">
                <div>
                    <div class="panel-title" style="color:#67e8f9;font-size:16px;">⚙️ Pengaturan Tarif PPN Sistem</div>
                    <div class="panel-desc">Atur persentase tarif PPN untuk seluruh kalkulasi paket internet &amp; add-on. Perubahan oleh Super Admin otomatis berlaku secara instan ke formulir pelanggan dan seluruh akun Team Leader.</div>
                </div>
            </div>
            <form id="form-ppn" onsubmit="updatePpnRateRealtime(event)" method="POST" style="display:flex;align-items:flex-end;gap:14px;flex-wrap:wrap;">
                <input type="hidden" name="action" value="update_ppn_rate">
                <input type="hidden" name="ajax" value="1">
                <div class="form-group" style="margin-bottom:0;min-width:220px;">
                    <label style="color:#e2e8f0;font-weight:700;">Tarif PPN Saat Ini (%) *</label>
                    <div style="position:relative;display:flex;align-items:center;">
                        <input type="number" step="0.1" min="0" max="100" name="ppn_percent" id="input-ppn-percent" class="form-control" 
                               value="<?= htmlspecialchars($ppnPercent) ?>" 
                               <?= $currentRole !== 'superadmin' ? 'readonly style="background:rgba(0,0,0,0.4);color:#94a3b8;"' : 'required' ?>
                               style="padding-right:38px;font-size:15px;font-weight:800;font-family:'JetBrains Mono',monospace;">
                        <span style="position:absolute;right:14px;color:#67e8f9;font-weight:800;font-size:14px;">%</span>
                    </div>
                </div>
                <?php if ($currentRole === 'superadmin'): ?>
                <button type="submit" id="btn-save-ppn" class="btn-primary" style="padding:10px 22px;">
                    Simpan Tarif PPN
                </button>
                <?php else: ?>
                <div style="font-size:12px;color:#fbbf24;display:flex;align-items:center;gap:6px;padding-bottom:10px;">
                    ℹ️ Tarif PPN dikonfigurasi secara terpusat oleh Super Admin (Otomatis Tersinkron)
                </div>
                <?php endif; ?>
            </form>
        </div>

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
                    $estimasiPpn = round($estimasiSubtotal * ($ppnPercent / 100));
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
                                        <span>PPN <?= $ppnPercent ?>%:</span>
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

    <!-- ================= TAB 4: PROFIL PERUSAHAAN & ADMIN / TEAM LEADER ================= -->
    <div id="settings-tab" class="tab-content <?= $activeTab === 'settings-tab' ? 'active' : '' ?>">
        <?php if ($currentRole === 'superadmin'): ?>
        <!-- SUPERADMIN: KELOLA AKUN TEAM LEADER -->
        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Akun Team Leader & Email Admin Notifikasi</div>
                    <div class="panel-desc">Buat akun TL dan tentukan Email Admin khusus untuk masing-masing Team Leader penerima notifikasi order.</div>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_team_leader">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Username TL *</label>
                        <input type="text" name="tl_username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Password TL *</label>
                        <input type="password" name="tl_password" class="form-control" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Kode Team Leader *</label>
                        <input type="text" name="tl_code" class="form-control" placeholder="TIN-SUHARTA" required>
                    </div>
                    <div class="form-group">
                        <label>Email Admin Notifikasi Order TL *</label>
                        <input type="email" name="tl_admin_email" class="form-control" placeholder="admin.suharta@gmail.com" required>
                        <small style="color:var(--text-muted);font-size:11px;">Notifikasi email order dari sales di bawah TL ini akan dikirim ke email ini.</small>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Buat Akun Team Leader</button>
            </form>
            <?php if ($teamLeaders): ?>
                <div class="table-responsive" style="margin-top:20px;">
                    <table class="admin-table">
                        <thead><tr><th>Username</th><th>Kode TL</th><th>Email Admin Notifikasi</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody><?php foreach ($teamLeaders as $leader): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($leader['username']) ?></strong></td>
                                <td><span style="font-family:'JetBrains Mono',monospace;font-weight:700;color:#38bdf8;"><?= htmlspecialchars($leader['tl_code'] ?? '-') ?></span></td>
                                <td><span style="color:#6ee7b7;font-weight:600;"><?= htmlspecialchars(!empty($leader['admin_email']) ? $leader['admin_email'] : '-') ?></span></td>
                                <td><span class="status-badge <?= ($leader['status'] ?? 'active') === 'active' ? 'status-active' : 'status-inactive' ?>"><?= ($leader['status'] ?? 'active') === 'active' ? 'Aktif' : 'Non-aktif' ?></span></td>
                                <td><button type="button" class="btn-action" onclick="openEditTeamLeader(<?= htmlspecialchars(json_encode($leader)) ?>)">Edit Data</button></td>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- SUPERADMIN: PROFIL PERUSAHAAN & KEAMANAN -->
        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Pengaturan Profil Perusahaan & Keamanan Super Admin</div>
                    <div class="panel-desc">Sesuaikan profil perusahaan, kontak helpdesk, dan kredensial login akun Super Admin</div>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_general_settings">

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Nama Perusahaan / Vendor</label>
                        <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($settings['company_name'] ?? 'PT. TALENTA INTEGRITAS NASIONAL') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Nomor Call Center</label>
                        <input type="text" name="call_center" class="form-control" value="<?= htmlspecialchars($settings['call_center'] ?? '1500 780') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>WhatsApp Helpdesk</label>
                        <input type="text" name="wa_helpdesk" class="form-control" value="<?= htmlspecialchars($settings['wa_helpdesk'] ?? '081265753141') ?>">
                    </div>

                    <div class="form-group">
                        <label>Username Login Super Admin</label>
                        <input type="text" name="admin_username" class="form-control" value="<?= htmlspecialchars($currentUser) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Ganti Password Login Super Admin</label>
                        <input type="password" name="admin_password" class="form-control" placeholder="Biarkan kosong jika tidak ingin mengubah">
                    </div>

                    <div class="form-group" style="grid-column:span 2;">
                        <label style="color:#cbd5e1;font-weight:700;">📝 Catatan Promo Default (Kolom Notes Surat Formulir CBN)</label>
                        <input type="text" name="default_notes" class="form-control" 
                               value="<?= htmlspecialchars($settings['default_notes'] ?? 'REGULER PROMO JULY 2026 - NAB') ?>" 
                               placeholder="Contoh: REGULER PROMO JULY 2026 - NAB">
                        <div style="font-size:11.5px;color:var(--text-muted);margin-top:4px;">
                            💡 Teks promo / catatan ini akan dicetak otomatis di bagian bawah Surat CBN jika pemohon tidak mengisi catatan khusus.
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top:16px;background:rgba(0,160,223,0.06);border:1px solid rgba(0,160,223,0.25);border-radius:10px;padding:16px;">
                    <label style="font-size:13.5px;font-weight:700;color:#67e8f9;display:flex;align-items:center;gap:6px;">
                        ✍️ Tanda Tangan SPV Master (Disetujui Oleh)
                    </label>
                    <div style="font-size:12px;color:var(--text-muted);margin:4px 0 10px;line-height:1.5;">
                        Upload foto atau scan tanda tangan SPV (JPG atau PNG). Background kertas putih akan otomatis di-convert menjadi transparan murni untuk dicetak pada kolom <strong>Disetujui Oleh (SPV)</strong> di Formulir CBN.
                    </div>
                    <input type="file" name="ttd_spv" class="form-control" accept="image/*">
                    <?php if (!empty($settings['ttd_spv_path'])): ?>
                    <div style="margin-top:12px;display:flex;align-items:center;gap:14px;background:rgba(0,0,0,0.3);padding:12px 16px;border-radius:8px;border:1px solid var(--border);">
                        <img src="../<?= htmlspecialchars($settings['ttd_spv_path']) ?>?v=<?= time() ?>" style="max-height:60px;max-width:160px;background:#fff;padding:6px;border-radius:6px;" alt="TTD SPV">
                        <div>
                            <div style="font-size:12.5px;font-weight:700;color:#6ee7b7;">✓ Tanda Tangan SPV Aktif</div>
                            <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">File: <?= htmlspecialchars($settings['ttd_spv_path']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-primary" style="margin-top:14px;">Simpan Pengaturan Profil & TTD SPV</button>
            </form>
        </div>

        <?php else: ?>
        <!-- TEAM LEADER: PROFIL & KEAMANAN MANDIRI (BALANCE) -->
        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <div class="panel-title">Profil & Keamanan Akun Team Leader</div>
                    <div class="panel-desc">Kelola username dan password login akun Team Leader Anda secara mandiri.</div>
                </div>
                <span class="status-badge status-active">Kode TL: <?= htmlspecialchars($currentTlCode) ?></span>
            </div>

            <div style="background:rgba(0,160,223,0.1);border:1px solid rgba(0,160,223,0.3);border-radius:10px;padding:14px 18px;margin-bottom:20px;">
                <div style="font-size:13px;color:#cbd5e1;line-height:1.6;">
                    👤 Anda sedang login sebagai <strong>Team Leader</strong> dengan kode <strong><?= htmlspecialchars($currentTlCode) ?></strong>. Anda dapat mengganti username dan password akun Anda di bawah ini kapan saja.
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="update_tl_profile">

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Username Login Team Leader *</label>
                        <input type="text" name="tl_username" class="form-control" value="<?= htmlspecialchars($currentUser) ?>" required>
                        <small style="color:var(--text-muted);font-size:11px;margin-top:3px;">Username yang Anda gunakan untuk login ke panel ini.</small>
                    </div>

                    <div class="form-group">
                        <label>Ganti Password Login Team Leader</label>
                        <input type="password" name="tl_password" class="form-control" minlength="6" placeholder="Biarkan kosong jika tidak ingin mengubah">
                        <small style="color:var(--text-muted);font-size:11px;margin-top:3px;">Minimal 6 karakter jika ingin mengganti password baru.</small>
                    </div>

                    <div class="form-group">
                        <label>Email Admin Notifikasi Order Team Leader</label>
                        <input type="email" name="tl_admin_email" class="form-control" value="<?= htmlspecialchars($currentTlAccount['admin_email'] ?? '') ?>" placeholder="admin.tl@gmail.com">
                        <small style="color:var(--text-muted);font-size:11px;margin-top:3px;">Notifikasi email pesanan dari seluruh tim sales Anda (kode <?= htmlspecialchars($currentTlCode) ?>) akan dikirimkan ke email ini.</small>
                    </div>

                    <div class="form-group">
                        <label>Kode Team Leader (TL Code)</label>
                        <input type="text" readonly class="form-control" value="<?= htmlspecialchars($currentTlCode) ?>" style="background:rgba(0,0,0,0.3);color:#67e8f9;font-weight:800;font-family:'JetBrains Mono',monospace;">
                        <small style="color:var(--text-muted);font-size:11px;margin-top:3px;">Kode penugasan tim sales Anda (diatur oleh Super Admin).</small>
                    </div>

                    <div class="form-group">
                        <label>Role Akun</label>
                        <input type="text" readonly class="form-control" value="Team Leader / SPV" style="background:rgba(0,0,0,0.3);color:#86efac;font-weight:700;">
                        <small style="color:var(--text-muted);font-size:11px;margin-top:3px;">Hak akses khusus pimpinan tim sales.</small>
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="margin-top:14px;padding:12px 28px;">
                    💾 Simpan Perubahan Profil Team Leader
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

</main>

<!-- MODAL EDIT TEAM LEADER -->
<div id="modal-edit-team-leader" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Edit Data Team Leader</div>
            <button type="button" class="modal-close" onclick="closeModal('modal-edit-team-leader')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_team_leader">
            <input type="hidden" id="edit-tl-id" name="tl_id">
            <div class="form-group">
                <label>Username TL *</label>
                <input type="text" id="edit-tl-username" name="tl_username" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Password Baru</label>
                <input type="password" name="tl_password" class="form-control" minlength="6" placeholder="Kosongkan jika tidak diubah">
            </div>
            <div class="form-group">
                <label>Kode Team Leader *</label>
                <input type="text" id="edit-tl-code" name="tl_code" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Email Admin Notifikasi Order TL</label>
                <input type="email" id="edit-tl-admin-email" name="tl_admin_email" class="form-control" placeholder="admin.tl@gmail.com">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select id="edit-tl-status" name="tl_status" class="form-control form-select">
                    <option value="active">Aktif</option>
                    <option value="inactive">Non-aktif</option>
                </select>
            </div>
            <button type="submit" class="btn-primary" style="width:100%;margin-top:8px;">Simpan Perubahan TL</button>
        </form>
    </div>
</div>

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
        <form method="POST" enctype="multipart/form-data">
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
                <label>Tanda Tangan Sales (PNG/JPG)</label>
                <input type="file" name="ttd_sales" class="form-control" accept="image/*">
                <div id="edit-ttd-preview" style="margin-top:8px;display:none;">
                    <img id="img-ttd-sales" src="" style="max-height:50px;border:1px solid var(--border);border-radius:4px;padding:4px;background:white;">
                    <div style="font-size:10px;color:var(--text-muted);">Tanda tangan saat ini</div>
                </div>
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
const PPN_PERCENT = <?= (float)($settings['ppn_percent'] ?? 11) ?>;

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

function openEditTeamLeader(leader) {
    document.getElementById('edit-tl-id').value = leader.id || leader.username;
    document.getElementById('edit-tl-username').value = leader.username || '';
    document.getElementById('edit-tl-code').value = leader.tl_code || '';
    document.getElementById('edit-tl-admin-email').value = leader.admin_email || '';
    document.getElementById('edit-tl-status').value = leader.status || 'active';
    document.getElementById('modal-edit-team-leader').classList.add('active');
}

function openEditModal(sales) {
    document.getElementById('edit-id').value = sales.id;
    document.getElementById('edit-code').value = sales.sales_code;
    document.getElementById('edit-name').value = sales.nama_sales;
    document.getElementById('edit-wa').value = sales.no_wa || '';
    document.getElementById('edit-email').value = sales.email || '';
    document.getElementById('edit-tl').value = sales.tl_code || 'TL-01';
    document.getElementById('edit-status').value = sales.status || 'active';
    
    const ttdPreview = document.getElementById('edit-ttd-preview');
    if (sales.ttd_path) {
        document.getElementById('img-ttd-sales').src = '../' + sales.ttd_path;
        ttdPreview.style.display = 'block';
    } else {
        ttdPreview.style.display = 'none';
    }
    
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
    const ppn = Math.round(subtotal * (PPN_PERCENT / 100));
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

// ========================================================
// REALTIME LIVE SYSTEM (ZERO PAGE RELOAD)
// ========================================================
function showLiveToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `toast-item toast-${type}`;
    const icon = type === 'success' ? '✓' : (type === 'error' ? '⚠️' : 'ℹ️');
    toast.innerHTML = `<span style="font-size:16px;">${icon}</span><span style="flex:1;">${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('toast-out');
        setTimeout(() => toast.remove(), 320);
    }, 3800);
}

// Toggle Email Customer Realtime
async function toggleEmailCustomerAjax(salesId, nextState, btnEl) {
    const formData = new FormData();
    formData.append('action', 'toggle_email_customer');
    formData.append('sales_id', salesId);
    formData.append('enabled', String(nextState));
    formData.append('ajax', '1');
    
    btnEl.style.opacity = '0.5';
    try {
        const res = await fetch('dashboard.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await res.json();
        btnEl.style.opacity = '1';
        if (result.success) {
            const isNowOn = !!result.enabled;
            btnEl.textContent = isNowOn ? '✓ ON' : '✗ OFF';
            btnEl.style.background = isNowOn ? 'rgba(16,185,129,0.2)' : 'rgba(239,68,68,0.15)';
            btnEl.style.borderColor = isNowOn ? 'rgba(16,185,129,0.5)' : 'rgba(239,68,68,0.4)';
            btnEl.style.color = isNowOn ? '#6ee7b7' : '#fca5a5';
            btnEl.setAttribute('onclick', `toggleEmailCustomerAjax('${salesId}', ${isNowOn ? 0 : 1}, this)`);
            showLiveToast(result.message, 'success');
        } else {
            showLiveToast(result.message || 'Gagal mengubah status email', 'error');
        }
    } catch (e) {
        btnEl.style.opacity = '1';
        showLiveToast('Terjadi kesalahan: ' + e.message, 'error');
    }
}

// 1. Update Status Order Realtime (Zero Reload)
async function updateOrderStatusRealtime(ticketNo, newStatus, selectEl) {
    if (!ticketNo) return;
    
    // Ubah visual status & badge seketika
    const statusLower = newStatus.toLowerCase();
    selectEl.className = `order-status-select status-${statusLower} select-saving`;
    
    const formData = new FormData();
    formData.append('action', 'update_order_status');
    formData.append('ticket_no', ticketNo);
    formData.append('order_status', newStatus);
    formData.append('ajax', '1');
    
    try {
        const res = await fetch('dashboard.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await res.json();
        selectEl.classList.remove('select-saving');
        
        if (result.success) {
            showLiveToast(result.message || `Status order ${ticketNo} diubah ke ${newStatus}`, 'success');
            if (typeof result.pending_count !== 'undefined') {
                const headerBadge = document.getElementById('header-pending-badge');
                if (headerBadge) headerBadge.textContent = `${result.pending_count} Pending`;
            }
            if (typeof result.total_count !== 'undefined') {
                const tabCount = document.getElementById('orders-tab-count');
                if (tabCount) tabCount.textContent = `(${result.total_count})`;
            }
        } else {
            showLiveToast(result.message || 'Gagal mengubah status order', 'error');
        }
    } catch (err) {
        selectEl.classList.remove('select-saving');
        showLiveToast('Koneksi terganggu saat menyimpan status: ' + err.message, 'error');
    }
}

// 2. Hapus Order Realtime (Zero Reload - Khusus Super Admin)
async function deleteOrderRealtime(ticketNo, btnEl) {
    if (!ticketNo) return;
    if (!confirm(`Apakah Anda yakin ingin menghapus data order tiket ${ticketNo}?\n\nBaris pendaftaran di Google Spreadsheet akan otomatis di-merahkan (DIHAPUS).`)) {
        return;
    }
    
    const row = document.getElementById(`order-row-${ticketNo}`) || btnEl.closest('tr');
    if (row) {
        row.style.transition = 'all 0.35s ease';
        row.style.opacity = '0.4';
        row.style.pointerEvents = 'none';
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_order');
    formData.append('ticket_no', ticketNo);
    formData.append('ajax', '1');
    
    try {
        const res = await fetch('dashboard.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await res.json();
        
        if (result.success) {
            if (row) {
                row.style.opacity = '0';
                row.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    row.remove();
                    const tbody = document.getElementById('orders-tbody');
                    if (tbody && tbody.children.length === 0) {
                        const emptyState = document.getElementById('orders-empty-state');
                        const tableWrapper = document.getElementById('orders-table-wrapper');
                        if (emptyState) emptyState.style.display = 'block';
                        if (tableWrapper) tableWrapper.style.display = 'none';
                    }
                }, 350);
            }
            showLiveToast(result.message || `Order tiket ${ticketNo} berhasil dihapus`, 'success');
            
            if (typeof result.pending_count !== 'undefined') {
                const headerBadge = document.getElementById('header-pending-badge');
                if (headerBadge) headerBadge.textContent = `${result.pending_count} Pending`;
            }
            if (typeof result.total_count !== 'undefined') {
                const tabCount = document.getElementById('orders-tab-count');
                if (tabCount) tabCount.textContent = `(${result.total_count})`;
            }
        } else {
            if (row) {
                row.style.opacity = '1';
                row.style.pointerEvents = 'auto';
            }
            showLiveToast(result.message || 'Gagal menghapus order', 'error');
        }
    } catch (err) {
        if (row) {
            row.style.opacity = '1';
            row.style.pointerEvents = 'auto';
        }
        showLiveToast('Koneksi terganggu saat menghapus order: ' + err.message, 'error');
    }
}

// 3. Simpan Tarif PPN Realtime (Zero Reload)
async function updatePpnRateRealtime(e) {
    if (e) e.preventDefault();
    const form = document.getElementById('form-ppn');
    const input = document.getElementById('input-ppn-percent');
    const btn = document.getElementById('btn-save-ppn');
    
    if (!input) return;
    const newRate = parseFloat(input.value) || 0;
    
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Menyimpan...';
    }
    
    const formData = new FormData(form);
    formData.set('action', 'update_ppn_rate');
    formData.set('ajax', '1');
    
    try {
        const res = await fetch('dashboard.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await res.json();
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Simpan Tarif PPN';
        }
        
        if (result.success) {
            window.PPN_PERCENT = newRate;
            // Update estimasi semua paket seketika
            document.querySelectorAll('.pkg-main-card').forEach(card => {
                const pkgId = card.id.replace('pkg-card-', '');
                if (pkgId && typeof syncPkgLive === 'function') {
                    syncPkgLive(pkgId);
                }
            });
            showLiveToast(`⚙️ Tarif PPN sistem berhasil diubah ke ${newRate}% & seluruh estimasi harga paket otomatis diperbarui!`, 'success');
        } else {
            showLiveToast(result.message || 'Gagal memperbarui tarif PPN', 'error');
        }
    } catch (err) {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Simpan Tarif PPN';
        }
        showLiveToast('Terjadi kesalahan: ' + err.message, 'error');
    }
}

// 4. Background Polling & Realtime Sync (Tiap 5 Detik)
let knownTicketNumbers = new Set();
let isPollingActive = false;

function initKnownTickets() {
    document.querySelectorAll('#orders-tbody tr').forEach(tr => {
        const tId = tr.id.replace('order-row-', '');
        if (tId) knownTicketNumbers.add(tId);
    });
}

async function pollRealtimeOrders() {
    if (isPollingActive) return;
    isPollingActive = true;
    
    try {
        const res = await fetch('dashboard.php?action=fetch_orders_realtime', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        
        if (data.success && Array.isArray(data.orders)) {
            const tbody = document.getElementById('orders-tbody');
            const emptyState = document.getElementById('orders-empty-state');
            const tableWrapper = document.getElementById('orders-table-wrapper');
            const isSuperAdmin = data.role === 'superadmin';
            
            // Update counter badges
            const headerBadge = document.getElementById('header-pending-badge');
            if (headerBadge) headerBadge.textContent = `${data.pending_count} Pending`;
            const tabCount = document.getElementById('orders-tab-count');
            if (tabCount) tabCount.textContent = `(${data.total_count})`;
            
            if (data.orders.length > 0) {
                if (emptyState) emptyState.style.display = 'none';
                if (tableWrapper) tableWrapper.style.display = 'block';
            }
            
            // Periksa pendaftaran baru atau update status eksternal
            let newOrderFound = false;
            data.orders.forEach(order => {
                const tId = String(order.ticket_no || '').trim();
                if (!tId) return;
                
                const existingRow = document.getElementById(`order-row-${tId}`);
                if (!existingRow) {
                    newOrderFound = true;
                    knownTicketNumbers.add(tId);
                    
                    const orderStatus = (order.status || 'PENDING').toUpperCase();
                    const statusClass = orderStatus === 'SELESAI' ? 'status-selesai' : (orderStatus === 'BATAL' ? 'status-batal' : (orderStatus === 'DIPROSES' ? 'status-diproses' : 'status-pending'));
                    
                    const tr = document.createElement('tr');
                    tr.id = `order-row-${tId}`;
                    tr.className = 'row-new';
                    
                    const tikorHtml = order.tikor ? `<br><a href="https://www.google.com/maps?q=${encodeURIComponent(order.tikor)}" target="_blank" style="font-size:11px;color:#38bdf8;text-decoration:none;display:inline-flex;align-items:center;gap:3px;margin-top:2px;" title="Buka Lokasi di Google Maps">📍 ${escapeHtml(order.tikor)}</a>` : '';
                    const aksiHtml = isSuperAdmin ? `<td style="text-align:center;white-space:nowrap;"><button type="button" class="btn-action" onclick="deleteOrderRealtime('${tId}', this)" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border:none;padding:6px 12px;border-radius:6px;font-size:11.5px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px;box-shadow:0 2px 8px rgba(239,68,68,0.35);" title="Hapus order & tandai merah di Spreadsheet">🗑️ Hapus</button></td>` : '';
                    
                    tr.innerHTML = `
                        <td><strong>${escapeHtml(order.nama || '-')}</strong><br><small>${escapeHtml(order.email || '-')}</small></td>
                        <td><code style="font-size:11.5px;color:#38bdf8;">${escapeHtml(tId)}</code></td>
                        <td>${escapeHtml(order.sales_code || '-')}<br><small>${escapeHtml(order.tl_code || '-')}</small></td>
                        <td>${escapeHtml(order.home_id || '-')}${tikorHtml}</td>
                        <td>${escapeHtml(order.submitted_at || '-')}</td>
                        <td>
                            <div style="display:flex;align-items:center;gap:6px;min-width:140px;">
                                <select id="select-status-${tId}" class="order-status-select ${statusClass}" onchange="updateOrderStatusRealtime('${tId}', this.value, this)" aria-label="Status order ${tId}">
                                    <option value="PENDING" ${orderStatus === 'PENDING' ? 'selected' : ''}>PENDING</option>
                                    <option value="DIPROSES" ${orderStatus === 'DIPROSES' ? 'selected' : ''}>DIPROSES</option>
                                    <option value="SELESAI" ${orderStatus === 'SELESAI' ? 'selected' : ''}>SELESAI</option>
                                    <option value="BATAL" ${orderStatus === 'BATAL' ? 'selected' : ''}>BATAL</option>
                                </select>
                            </div>
                        </td>
                        ${aksiHtml}
                    `;
                    
                    if (tbody) {
                        tbody.insertBefore(tr, tbody.firstChild);
                    }
                } else {
                    const select = document.getElementById(`select-status-${tId}`);
                    if (select && !select.classList.contains('select-saving')) {
                        const currentVal = select.value;
                        const serverVal = (order.status || 'PENDING').toUpperCase();
                        if (currentVal !== serverVal) {
                            select.value = serverVal;
                            select.className = `order-status-select status-${serverVal.toLowerCase()}`;
                        }
                    }
                }
            });
            
            if (newOrderFound) {
                showLiveToast(`🔔 Pendaftaran Baru Masuk! Data otomatis diperbarui secara realtime.`, 'info');
                if (typeof filterOrders === 'function') {
                    filterOrders();
                }
            }
        }
    } catch (e) {
        // Polling skip
    } finally {
        isPollingActive = false;
    }
}

// 5. Filter & Pencarian Kata Kunci Order (Realtime Client-side Search)
let currentStatusFilter = 'ALL';

function filterStatusOrders(status, btnEl) {
    currentStatusFilter = status;
    document.querySelectorAll('.btn-filter-status').forEach(b => {
        b.classList.remove('active');
        const st = b.getAttribute('data-status');
        if (st === 'PENDING') {
            b.style.background = 'rgba(245,158,11,0.12)';
            b.style.color = '#fbbf24';
            b.style.borderColor = 'rgba(245,158,11,0.3)';
        } else if (st === 'DIPROSES') {
            b.style.background = 'rgba(14,165,233,0.12)';
            b.style.color = '#38bdf8';
            b.style.borderColor = 'rgba(14,165,233,0.3)';
        } else if (st === 'SELESAI') {
            b.style.background = 'rgba(16,185,129,0.12)';
            b.style.color = '#6ee7b7';
            b.style.borderColor = 'rgba(16,185,129,0.3)';
        } else if (st === 'BATAL') {
            b.style.background = 'rgba(239,68,68,0.12)';
            b.style.color = '#fca5a5';
            b.style.borderColor = 'rgba(239,68,68,0.3)';
        } else {
            b.style.background = 'rgba(255,255,255,0.06)';
            b.style.color = '#94a3b8';
            b.style.borderColor = 'var(--border)';
        }
    });
    btnEl.classList.add('active');
    btnEl.style.background = '#00a0df';
    btnEl.style.color = '#fff';
    btnEl.style.borderColor = '#00a0df';
    filterOrders();
}

function clearSearchOrders() {
    const input = document.getElementById('search-orders');
    if (input) {
        input.value = '';
        const clearBtn = document.getElementById('btn-clear-search-orders');
        if (clearBtn) clearBtn.style.display = 'none';
        filterOrders();
        input.focus();
    }
}

function filterOrders() {
    const input = document.getElementById('search-orders');
    const query = input ? input.value.trim().toLowerCase() : '';
    const clearBtn = document.getElementById('btn-clear-search-orders');
    if (clearBtn) {
        clearBtn.style.display = query ? 'flex' : 'none';
    }
    
    const rows = document.querySelectorAll('#orders-tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const select = row.querySelector('.order-status-select');
        const rowStatus = select ? select.value.toUpperCase() : '';
        
        const matchesQuery = !query || text.includes(query);
        const matchesStatus = currentStatusFilter === 'ALL' || rowStatus === currentStatusFilter;
        
        if (matchesQuery && matchesStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    const notFoundEl = document.getElementById('orders-not-found');
    const tableWrapper = document.getElementById('orders-table-wrapper');
    const emptyState = document.getElementById('orders-empty-state');
    
    if (rows.length === 0) {
        if (notFoundEl) notFoundEl.style.display = 'none';
        if (emptyState) emptyState.style.display = 'block';
        if (tableWrapper) tableWrapper.style.display = 'none';
    } else {
        if (emptyState) emptyState.style.display = 'none';
        if (visibleCount === 0) {
            if (notFoundEl) notFoundEl.style.display = 'block';
            if (tableWrapper) tableWrapper.style.display = 'none';
        } else {
            if (notFoundEl) notFoundEl.style.display = 'none';
            if (tableWrapper) tableWrapper.style.display = 'block';
        }
    }
}

// Inisialisasi Realtime Sync saat halaman dibuka
document.addEventListener('DOMContentLoaded', () => {
    initKnownTickets();
    setInterval(pollRealtimeOrders, 5000);
});
</script>

</body>
</html>
