<?php
/**
 * FORMGOOGLE - submit.php
 * Handler POST Formulir Pendaftaran Layanan CBN
 * Mengirim data ke Google Apps Script (Sheets + Drive PDF CBN + Email Attachment)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$salesCode = $_SESSION['sales_code'] ?? 'SEP-001';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $salesCode);
    exit;
}

// ===== AUTOLOAD =====
require_once dirname(__DIR__) . '/src/autoload.php';

use App\Config;
use App\Validator;
use App\AppsScriptService;
use App\TelegramService;
use App\OrdersManager;

Config::load();

// ===== CSRF CHECK =====
$submittedToken = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)) {
    $_SESSION['errors']['general'] = 'Sesi formulir telah berakhir. Silakan muat ulang halaman dan coba lagi.';
    $_SESSION['old'] = $_POST;
    header('Location: ' . $salesCode);
    exit;
}

// ===== VALIDASI INPUT =====
$validator = new Validator();
$valid     = $validator->validate($_POST, $_FILES);

if (!$valid) {
    $_SESSION['errors'] = $validator->getErrors();
    $_SESSION['old']    = $_POST;
    header('Location: ' . $salesCode);
    exit;
}

$data = $validator->getData();
$salesCode = strtoupper(trim($data['sales_code'] ?? ($salesCode ?? 'SEP-001')));
$salesData = \App\SalesManager::findByCode($salesCode);

// Pastikan Data Perusahaan, Vendor, SO Date, Team Leader & Nama Sales terisi akurat
$settings = \App\SettingsManager::get();
$vendorName = !empty($settings['company_name']) ? $settings['company_name'] : 'PT. TALENTA INTEGRITAS NASIONAL';
$tlCode = !empty($salesData['tl_code']) ? strtoupper(trim($salesData['tl_code'])) : (!empty($data['tl_code']) ? strtoupper(trim($data['tl_code'])) : 'TL-01');
$aeName = $salesData['nama_sales'] ?? ($data['sales_name'] ?? ($salesData['sales_code'] ?? 'FIRMAN'));

$data['sales_code']  = $salesCode;
$data['vendor']      = $vendorName;
$data['so_date']     = date('d/m/Y');
$data['tl_code']     = $tlCode;
$data['team_leader'] = $tlCode;
$data['ae_name']     = $aeName;
$data['sales_name']  = $aeName;

// Buat Nomor Tiket Resmi menggunakan Kode Team Leader (contoh: CBN-TIN-SUHARTA-260826-1234)
$cleanTlCode  = strtoupper(trim(preg_replace('/[^a-zA-Z0-9\-]/', '', $tlCode)));
$ticketNumber = 'CBN-' . $cleanTlCode . '-' . date('ymd') . '-' . rand(1000, 9999);
$data['ticket_no'] = $ticketNumber;

// Simpan data terakhir di session untuk preview/download PDF
$_SESSION['cbn_last_submission'] = $data;

// ===== PROSES & KIRIM KE APPS SCRIPT =====
$uploadPath = '';

try {

    // Tidak ada upload KTP atau tanda tangan customer pada form.
    $fileInfo = [];

    // ---- 1. Kirim ke Google Apps Script (Spreadsheet, Drive PDF, & Email) ----
    $service  = new AppsScriptService();
    $response = $service->send($data, $fileInfo);

    // ---- 2. Kirim Notifikasi Lengkap & Foto KTP ke Telegram Admin/Bot ----
    TelegramService::sendRegistration($data, $fileInfo);

    // ---- Hapus file temp ----
    if ($uploadPath && file_exists($uploadPath)) {
        unlink($uploadPath);
    }

    // ---- Regenerate CSRF ----
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // ---- Pesan Sukses & Data Tiket Pendaftaran ----
    $_SESSION['success'] = [
        'ticket_no'  => $ticketNumber,
        'nama'       => $data['nama_pelanggan'],
        'email'      => $data['email_pelanggan'],
        'telp'       => $data['telp'] ?? '',
        'alamat'     => $data['alamat'] . (!empty($data['kelurahan']) ? ', Kel. ' . $data['kelurahan'] : '') . (!empty($data['kecamatan']) ? ', Kec. ' . $data['kecamatan'] : ''),
        'paket'      => $data['service'],
        'jadwal'     => ($data['jadwal_tanggal'] ?? '') . (!empty($data['jadwal_waktu']) ? ' (' . $data['jadwal_waktu'] . ')' : ''),
        'total'      => $data['biaya_total'] ?? 'Rp 193.140',
        'sales_name' => $data['sales_name'] ?? ($salesData['nama_sales'] ?? 'FIRMAN'),
        'sales_code' => $data['sales_code'] ?? $salesCode,
        'timestamp'  => date('d/m/Y H:i:s'),
    ];

    // ---- Simpan Order ke JSON Lokal untuk Status Tracking ----
    OrdersManager::add([
        'ticket_no'    => $ticketNumber,
        'nama'         => $data['nama_pelanggan'],
        'nomor_ktp'    => $data['nomor_ktp'] ?? '',
        'telp'         => $data['telp'] ?? '',
        'email'        => $data['email_pelanggan'] ?? '',
        'alamat'       => $data['alamat'] . (!empty($data['kelurahan']) ? ', Kel. ' . $data['kelurahan'] : '') . (!empty($data['kecamatan']) ? ', Kec. ' . $data['kecamatan'] : ''),
        'home_id'      => $data['home_id'] ?? '',
        'tikor'        => $data['tikor'] ?? '',
        'paket'        => $data['service'] ?? '',
        'total'        => $data['biaya_total'] ?? '',
        'sales_code'   => $data['sales_code'] ?? $salesCode,
        'tl_code'      => $data['tl_code'] ?? '',
        'jadwal'       => ($data['jadwal_tanggal'] ?? '') . (!empty($data['jadwal_waktu']) ? ' (' . $data['jadwal_waktu'] . ')' : ''),
        'status'       => 'PENDING',
        'submitted_at' => date('Y-m-d H:i:s'),
    ]);

    $redirectTarget = !empty($data['sales_code']) ? $data['sales_code'] : ($salesCode ?? 'SEP-001');
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    $cleanBase = preg_replace('#/public$#i', '', $scriptDir);
    $redirectUrl = ($cleanBase ? $cleanBase : '') . '/' . ltrim($redirectTarget, '/');
    header('Location: ' . $redirectUrl);
    exit;

} catch (\Exception $e) {

    // Hapus file temp jika error
    if ($uploadPath && file_exists($uploadPath)) {
        unlink($uploadPath);
    }

    // Log error ke /tmp (kompatibel Vercel serverless)
    $logDir = sys_get_temp_dir() . '/formgoogle_logs/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logMsg = '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage() . "\n";
    file_put_contents($logDir . 'error.log', $logMsg, FILE_APPEND);

    $errMsg = 'Gagal memproses pendaftaran ke Google Apps Script: ' . $e->getMessage() . '. Silakan periksa kembali konfigurasi atau coba beberapa saat lagi.';

    $_SESSION['errors']['general'] = $errMsg;
    $_SESSION['old'] = $_POST;

    $redirectTarget = !empty($data['sales_code']) ? $data['sales_code'] : $salesCode;
    header('Location: ' . $redirectTarget);
    exit;
}
