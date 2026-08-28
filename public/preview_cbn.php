<?php
/**
 * FORMGOOGLE - preview_cbn.php
 * Halaman Preview & Cetak Surat / PDF Formulir CBN
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/src/autoload.php';

use App\CbnDocumentTemplate;
use App\SettingsManager;
use App\SalesManager;

// Ambil data dasar
$data = $_SESSION['cbn_last_submission'] ?? [];

// Parameter Sales
$requestedSalesCode = $_GET['sales_code'] ?? ($_GET['sales'] ?? ($_SESSION['sales_code'] ?? 'SEP-001'));
$salesData = SalesManager::findByCode($requestedSalesCode);
$currentSalesName = $salesData['nama_sales'] ?? 'FIRMAN';

// Jika ada parameter pkg_id atau service dari dashboard admin
$requestedPkgId = $_GET['pkg_id'] ?? null;
$requestedService = $_GET['service'] ?? null;

$settings = SettingsManager::get();
$packages = $settings['packages'] ?? [];

$targetPkg = null;
if ($requestedPkgId) {
    foreach ($packages as $p) {
        if ($p['id'] === $requestedPkgId) {
            $targetPkg = $p;
            break;
        }
    }
} elseif ($requestedService) {
    foreach ($packages as $p) {
        if (strcasecmp($p['name'], $requestedService) === 0) {
            $targetPkg = $p;
            break;
        }
    }
}

$ppnPercent   = (float)($settings['ppn_percent'] ?? 11);
$monthNames   = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
$currentMonth = $monthNames[(int)date('n')];
$currentYear  = date('Y');

if ($targetPkg) {
    $price = (int)($targetPkg['price'] ?? 169000);
    $biayaTambahan = (int)($targetPkg['biaya_tambahan'] ?? 5000);
    $subtotal = $price + $biayaTambahan;
    $ppn = (int)round($subtotal * ($ppnPercent / 100));
    $total = $subtotal + $ppn;

    $cbnLines = $targetPkg['cbn_package'] ?? [];
    if (!is_array($cbnLines)) $cbnLines = !empty($cbnLines) ? [$cbnLines] : [];
    $cbnLinesFormatted = array_map(fn($l) => str_replace('{BULAN}', $currentMonth . ' ' . $currentYear, $l), $cbnLines);

    $data = array_merge([
        'sales_code'        => $requestedSalesCode,
        'sales_name'        => $currentSalesName,
        'nama_pelanggan'    => 'CONTOH PELANGGAN CBN',
        'ttl'               => 'MEDAN, 15/08/1995',
        'nomor_ktp'         => '1271184887725666',
        'jenis_kelamin'     => 'PRIA',
        'telp_rumah'        => '0217654321',
        'telp'              => '081265753141',
        'alamat'            => 'JL. KL. YOS SUDARSO NO. 88',
        'kelurahan'         => 'KOTA MEDAN',
        'kecamatan'         => 'MEDAN BARAT',
        'rt'                => '005',
        'rw'                => '012',
        'kode_pos'          => '20158',
        'status_kepemilikan'=> 'PEMILIK',
        'email_pelanggan'   => 'pelanggan@cbn.net.id',
        'router_qty'        => '1',
        'smartbox_qty'      => '0',
        'username_cbn'      => 'pelanggan.cbn',
        'jadwal_tanggal'    => date('d/m/Y', strtotime('+2 days')),
        'jadwal_waktu'      => '09.00-11.00',
        'catatan'           => 'PREVIEW FORMULIR PAKET CBN',
        'so_date'           => date('d/m/Y'),
        'signature_data'    => '',
    ], $data);

    $data['service'] = $targetPkg['name'];
    $data['biaya_pasang'] = 'Rp0';
    $data['biaya_paket'] = 'Rp' . number_format($price, 0, ',', '.');
    $data['biaya_tambahan'] = 'Rp' . number_format($biayaTambahan, 0, ',', '.');
    $data['biaya_ppn'] = 'Rp' . number_format($ppn, 0, ',', '.');
    $data['biaya_total'] = 'Rp' . number_format($total, 0, ',', '.');
    $data['addon_cbn_package'] = json_encode($cbnLinesFormatted);
} elseif (empty($data)) {
    // Default fallback
    $defaultPkg = $packages[0] ?? ['name' => 'Fiber 100', 'price' => 199000, 'biaya_tambahan' => 5000];
    $price = (int)($defaultPkg['price'] ?? 199000);
    $biayaTambahan = (int)($defaultPkg['biaya_tambahan'] ?? 5000);
    $subtotal = $price + $biayaTambahan;
    $ppn = (int)round($subtotal * ($ppnPercent / 100));
    $total = $subtotal + $ppn;

    $cbnLines = $defaultPkg['cbn_package'] ?? [];
    if (!is_array($cbnLines)) $cbnLines = !empty($cbnLines) ? [$cbnLines] : [];
    $cbnLinesFormatted = array_map(fn($l) => str_replace('{BULAN}', $currentMonth . ' ' . $currentYear, $l), $cbnLines);

    $data = [
        'sales_code'        => $requestedSalesCode,
        'sales_name'        => $currentSalesName,
        'nama_pelanggan'    => 'PRAMUDYA ADI KUSUMA',
        'ttl'               => 'JAKARTA, 15/08/1990',
        'nomor_ktp'         => '3171021508900001',
        'jenis_kelamin'     => 'PRIA',
        'telp_rumah'        => '0217654321',
        'telp'              => '081234567890',
        'alamat'            => 'JL. CEMPAKA PUTIH TENGAH NO. 45',
        'kelurahan'         => 'CEMPAKA PUTIH TIMUR',
        'kecamatan'         => 'CEMPAKA PUTIH',
        'rt'                => '005',
        'rw'                => '008',
        'kode_pos'          => '10510',
        'status_kepemilikan'=> 'PEMILIK',
        'email_pelanggan'   => 'pramudya.kusuma@gmail.com',
        'service'           => $defaultPkg['name'],
        'addon_tv'          => ['Dens TV+ Apps'],
        'addon_device'      => ['Wireless Router'],
        'router_qty'        => '1',
        'smartbox_qty'      => '0',
        'username_cbn'      => 'pramudya.adi',
        'jadwal_tanggal'    => date('d/m/Y', strtotime('+2 days')),
        'jadwal_waktu'      => '09.00-11.00',
        'catatan'           => 'Dekat pos satpam blok B, rumah pagar hitam.',
        'biaya_pasang'      => 'Rp0',
        'biaya_paket'       => 'Rp' . number_format($price, 0, ',', '.'),
        'biaya_tambahan'    => 'Rp' . number_format($biayaTambahan, 0, ',', '.'),
        'biaya_ppn'         => 'Rp' . number_format($ppn, 0, ',', '.'),
        'biaya_total'       => 'Rp' . number_format($total, 0, ',', '.'),
        'addon_cbn_package' => json_encode($cbnLinesFormatted),
        'so_date'           => date('d/m/Y'),
        'signature_data'    => '',
    ];
}

echo CbnDocumentTemplate::render($data);

