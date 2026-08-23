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
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    die('<b>Error:</b> File autoload composer tidak ditemukan.');
}
require_once $autoload;

use App\Config;
use App\Validator;
use App\AppsScriptService;
use App\TelegramService;

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
$salesCode = $data['sales_code'] ?? ($salesCode ?? 'SEP-001');
$salesData = \App\SalesManager::findByCode($salesCode);
$data['sales_name'] = $salesData['nama_sales'] ?? 'FIRMAN';

// Simpan data terakhir di session untuk preview/download PDF
$_SESSION['cbn_last_submission'] = $data;

// ===== PROSES & KIRIM KE APPS SCRIPT =====
$uploadPath = '';

try {

    // ---- Siapkan info file KTP (jika ada) ----
    $fileInfo = [];
    if (!empty($data['upload_file'])) {
        $file    = $data['upload_file'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mimeMap = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'pdf'  => 'application/pdf',
        ];

        // Nama file unik
        $safeKtp  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $data['nomor_ktp']);
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $data['nama_pelanggan']);
        $fileName = "KTP_" . date('Ymd_His') . "_{$safeKtp}_{$safeName}.{$ext}";

        // Simpan sementara
        $uploadDir = dirname(__DIR__) . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $uploadPath = $uploadDir . $fileName;
        move_uploaded_file($file['tmp_name'], $uploadPath);

        $fileInfo = [
            'tmp_path' => $uploadPath,
            'name'     => $fileName,
            'mime'     => $mimeMap[$ext] ?? 'application/octet-stream',
        ];
    }

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
    $cleanSalesCode = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $data['sales_code'] ?? 'SEP001'));
    $ticketNumber = 'CBN-' . $cleanSalesCode . '-' . date('ymd') . '-' . rand(1000, 9999);

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

    $redirectTarget = !empty($data['sales_code']) ? $data['sales_code'] : $salesCode;
    header('Location: ' . $redirectTarget);
    exit;

} catch (\Exception $e) {

    // Hapus file temp jika error
    if ($uploadPath && file_exists($uploadPath)) {
        unlink($uploadPath);
    }

    // Log error
    $logDir = dirname(__DIR__) . '/logs/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logMsg = "[" . date('Y-m-d H:i:s') . "] " . $e->getMessage() . "\n";
    file_put_contents($logDir . 'error.log', $logMsg, FILE_APPEND);

    $errMsg = 'Gagal memproses pendaftaran ke Google Apps Script: ' . $e->getMessage() . '. Silakan periksa kembali konfigurasi atau coba beberapa saat lagi.';

    $_SESSION['errors']['general'] = $errMsg;
    $_SESSION['old'] = $_POST;

    $redirectTarget = !empty($data['sales_code']) ? $data['sales_code'] : $salesCode;
    header('Location: ' . $redirectTarget);
    exit;
}
