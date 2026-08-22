<?php
/**
 * FORMGOOGLE - preview_cbn.php
 * Halaman Preview & Cetak Surat / PDF Formulir CBN
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

use App\CbnDocumentTemplate;

// Ambil data dari session atau GET/POST dummy jika dibuka langsung
$data = $_SESSION['cbn_last_submission'] ?? [
    'sales_code'        => $_SESSION['sales_code'] ?? 'SEP-001',
    'nama_pelanggan'    => 'PRAMUDYA ADI KUSUMA',
    'ttl'               => 'JAKARTA, 15/08/1990',
    'nomor_ktp'         => '3171021508900001',
    'jenis_kelamin'     => 'PRIA',
    'telp_rumah'        => '0217654321',
    'telp'              => '081234567890',
    'alamat'            => 'JL. CEMPAKA PUTIH TENGAH NO. 45',
    'rt'                => '005',
    'rw'                => '008',
    'kode_pos'          => '10510',
    'status_kepemilikan'=> 'PEMILIK',
    'email_pelanggan'   => 'pramudya.kusuma@gmail.com',
    'service'           => 'Fiber 100',
    'addon_tv'          => ['Dens TV+ Apps'],
    'addon_device'      => ['Wireless Router', 'Smartbox'],
    'router_qty'        => '1',
    'smartbox_qty'      => '1',
    'username_cbn'      => 'pramudya.adi',
    'jadwal_tanggal'    => date('d/m/Y', strtotime('+2 days')),
    'jadwal_waktu'      => '09.00-11.00',
    'catatan'           => 'Dekat pos satpam blok B, rumah pagar hitam.',
    'biaya_pasang'      => 'Rp 0 (Promo Gratis)',
    'biayaPaket'        => 'Rp 399.000',
    'biayaAddon'        => 'Rp 50.000',
    'biayaPpn'          => 'Rp 49.390',
    'biayaTotal'        => 'Rp 498.390',
    'so_date'           => date('d/m/Y'),
    'signature_data'    => '',
];

echo CbnDocumentTemplate::render($data);
