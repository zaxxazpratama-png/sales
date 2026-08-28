<?php
/**
 * FORMGOOGLE - Formulir Pendaftaran Layanan CBN
 * PT. Talenta Integritas Nasional
 * 
 * Otomatisasi Input Form -> Simpan ke Spreadsheet -> Generate PDF Formulir Resmi CBN -> Kirim ke Email
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use App\SalesManager;
use App\SettingsManager;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$success = $_SESSION['success'] ?? null;
$errors  = $_SESSION['errors']  ?? [];
$old     = $_SESSION['old']     ?? [];
unset($_SESSION['success'], $_SESSION['errors'], $_SESSION['old']);

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 1. Cek parameter Sales dari URL
$paramSales = trim($_GET['sales'] ?? $_GET['s'] ?? $_GET['code'] ?? '');

// 2. JIKA TIDAK ADA PARAMETER SALES DI URL ATAU AKSES LANGSUNG KE /public/ -> TAMPILKAN 404
if (empty($paramSales) || $paramSales === 'index.php' || $paramSales === 'public') {
    unset($_SESSION['sales_code'], $_SESSION['active_sales']);
    require __DIR__ . '/404.php';
    exit;
}

// 3. Validasi kode sales ke sistem
$foundSales = SalesManager::findByCode($paramSales);
if (!$foundSales || ($foundSales['status'] ?? 'active') !== 'active') {
    unset($_SESSION['sales_code'], $_SESSION['active_sales']);
    require __DIR__ . '/404.php';
    exit;
}

// 4. Simpan ke Session aktif
$_SESSION['sales_code']   = $foundSales['sales_code'];
$_SESSION['active_sales'] = $foundSales;

$activeSales = $foundSales;
$salesCode   = $foundSales['sales_code'];
$salesName   = $foundSales['nama_sales'];
$tlCode      = $foundSales['tl_code'] ?: 'TL-MEDAN-01';

// Ambil paket dan pengaturan dinamis
$settings = SettingsManager::get();
$packages = $settings['packages'] ?? [];
$selectedService = $old['service'] ?? ($packages[0]['name'] ?? 'Fiber 50');
$allSales = array_values(array_filter(SalesManager::getAll(), static function (array $sales): bool {
    return ($sales['status'] ?? 'active') === 'active';
}));
$salesByLeader = [];
foreach ($allSales as $sales) {
    $leaderCode = $sales['tl_code'] ?: 'TANPA-TL';
    $salesByLeader[$leaderCode][] = [
        'code' => $sales['sales_code'],
        'name' => $sales['nama_sales'],
    ];
}

$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$cleanBase = preg_replace('#/public$#i', '', $scriptDir);
$baseUrl   = $cleanBase ?: ((strpos($_SERVER['REQUEST_URI'] ?? '', '/ALATTEMPUR/FORMGOOGLE') !== false) ? '/ALATTEMPUR/FORMGOOGLE' : '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Formulir Pendaftaran Layanan CBN - Internet Fiber Cepat & TV Berlangganan. Registrasi resmi PT. Talenta Integritas Nasional.">
    <title>Formulir Pendaftaran Layanan CBN - <?= htmlspecialchars($salesName ? $salesName . ' (' . $salesCode . ')' : 'PT. TIN') ?></title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>

<!-- ============ HEADER ============ -->
<header class="site-header">
    <div class="header-inner">
        <div class="logo-wrap">
            <div class="cbn-brand-logo">cbn<span>.</span></div>
            <div class="header-title">
                <h1><?= htmlspecialchars($settings['app_title'] ?? 'FORMULIR PENDAFTARAN LAYANAN CBN') ?></h1>
                <p><?= htmlspecialchars($settings['app_subtitle'] ?? 'CBN Service Application Form • Mitra Resmi: PT. Talenta Integritas Nasional') ?></p>
            </div>
        </div>
        <div class="header-right">
            <a href="preview_cbn.php" target="_blank" class="btn-preview-live" style="padding:7px 14px;font-size:12px;">
                Contoh Surat
            </a>
            <div class="badge-callcenter">
                <span>Call Center: <?= htmlspecialchars($settings['call_center'] ?? '1500 780') ?></span>
            </div>
        </div>
    </div>
</header>

<!-- ============ MAIN WRAPPER ============ -->
<main class="main-wrapper">

    <!-- Pemilihan Team Leader dan Sales -->
    <div style="background:rgba(17,28,56,0.9);border:1px solid rgba(0,160,223,0.35);border-radius:12px;padding:16px 20px;margin-bottom:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
            <strong style="color:#fff;font-size:14px;">Pilih Team Leader &amp; Sales</strong>
            <span style="color:#94a3b8;font-size:12px;">Link formulir mengikuti sales yang dipilih.</span>
        </div>
        <div class="sales-picker-grid" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" for="team-leader-picker">Team Leader</label>
                <select id="team-leader-picker" class="form-input form-select">
                    <?php foreach ($salesByLeader as $leaderCode => $leaderSales): ?>
                        <option value="<?= htmlspecialchars($leaderCode) ?>" <?= $leaderCode === $tlCode ? 'selected' : '' ?>><?= htmlspecialchars($leaderCode) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" for="sales-picker">Nama Sales</label>
                <select id="sales-picker" class="form-input form-select"></select>
            </div>
        </div>
    </div>

    <!-- Banner Verifikasi Sales Khusus (Persistent & Terkunci) -->
    <?php if ($activeSales): ?>
    <div style="background: linear-gradient(135deg, rgba(0, 86, 150, 0.35), rgba(0, 160, 223, 0.2)); border: 1px solid rgba(0, 160, 223, 0.45); border-radius: 12px; padding: 14px 20px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; gap: 14px; box-shadow: 0 4px 15px rgba(0, 160, 223, 0.2);">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="background: #00a0df; color: #fff; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px; flex-shrink: 0;">SLS</div>
            <div>
                <div style="font-size: 14px; font-weight: 800; color: #fff;">
                    Pendaftaran Resmi Melalui Sales: <?= htmlspecialchars($activeSales['nama_sales']) ?>
                </div>
                <div style="font-size: 12px; color: #cbd5e1;">
                    Kode Sales: <strong style="color: #67e8f9;"><?= htmlspecialchars($activeSales['sales_code']) ?></strong> 
                    <?php if (!empty($activeSales['no_wa'])): ?>
                        &bull; WhatsApp: <a href="https://wa.me/<?= preg_replace('/\D/', '', $activeSales['no_wa']) ?>" target="_blank" style="color:#67e8f9;text-decoration:underline;"><?= htmlspecialchars($activeSales['no_wa']) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <span style="background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4); color: #6ee7b7; font-size: 11px; font-weight: 800; padding: 5px 12px; border-radius: 20px; letter-spacing: 0.5px;">VERIFIED SALES</span>
    </div>
    <?php endif; ?>

    <!-- Alert Notifikasi Sukses / Error -->
    <!-- Alert & Kartu Tiket Pendaftaran Sukses -->
    <?php if ($success): ?>
    <div class="ticket-card" style="background: linear-gradient(145deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.95)); border: 1px solid rgba(16, 185, 129, 0.4); border-radius: 16px; padding: 28px; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.35); position: relative; overflow: hidden;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #10b981, #3b82f6);"></div>
        
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 20px; margin-bottom: 22px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                    <span style="background: rgba(16, 185, 129, 0.2); color: #34d399; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid rgba(16, 185, 129, 0.3);">
                        ✓ PENDAFTARAN BERHASIL
                    </span>
                    <span style="color: #64748b; font-size: 12px;"><?= htmlspecialchars(is_array($success) ? ($success['timestamp'] ?? date('d/m/Y H:i')) : date('d/m/Y H:i')) ?></span>
                </div>
                <h2 style="font-size: 22px; color: #fff; margin: 0; font-weight: 700;">
                    Tiket Pendaftaran Layanan CBN
                </h2>
                <div style="font-family: monospace; font-size: 14px; color: #38bdf8; margin-top: 4px; font-weight: bold;">
                    NO. TIKET: <?= htmlspecialchars(is_array($success) ? ($success['ticket_no'] ?? '#CBN-ORDER') : '#CBN-ORDER') ?>
                </div>
            </div>
            <div>
                <a href="preview_cbn.php" target="_blank" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;">
                    📄 Lihat Salinan PDF Formulir CBN
                </a>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 22px; font-size: 13px;">
            <div style="background: rgba(255,255,255,0.03); padding: 14px 16px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                <div style="color: #94a3b8; font-size: 11px; text-transform: uppercase; margin-bottom: 4px;">Nama Pelanggan</div>
                <div style="color: #fff; font-weight: 600; font-size: 14px;"><?= htmlspecialchars(is_array($success) ? ($success['nama'] ?? '-') : $success) ?></div>
                <div style="color: #cbd5e1; font-size: 12px; margin-top: 2px;"><?= htmlspecialchars(is_array($success) ? ($success['email'] ?? '') : '') ?></div>
            </div>

            <div style="background: rgba(255,255,255,0.03); padding: 14px 16px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                <div style="color: #94a3b8; font-size: 11px; text-transform: uppercase; margin-bottom: 4px;">Paket Terpilih & Biaya</div>
                <div style="color: #38bdf8; font-weight: 700; font-size: 14px;"><?= htmlspecialchars(is_array($success) ? ($success['paket'] ?? 'CBN Fiber') : 'CBN Fiber') ?></div>
                <div style="color: #10b981; font-weight: 700; font-size: 12px; margin-top: 2px;"><?= htmlspecialchars(is_array($success) ? ($success['total'] ?? '-') : '-') ?> /bulan</div>
            </div>

            <div style="background: rgba(255,255,255,0.03); padding: 14px 16px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                <div style="color: #94a3b8; font-size: 11px; text-transform: uppercase; margin-bottom: 4px;">Jadwal Pemasangan</div>
                <div style="color: #fff; font-weight: 600; font-size: 13.5px;"><?= htmlspecialchars(is_array($success) ? ($success['jadwal'] ?? 'Sesuai Antrean') : 'Sesuai Antrean') ?></div>
                <div style="color: #94a3b8; font-size: 11px; margin-top: 2px;">Slot Teknisi Terjadwal</div>
            </div>

            <div style="background: rgba(255,255,255,0.03); padding: 14px 16px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); grid-column: 1 / -1;">
                <div style="color: #94a3b8; font-size: 11px; text-transform: uppercase; margin-bottom: 4px;">Alamat Pemasangan Lengkap</div>
                <div style="color: #fff; font-weight: 600; font-size: 13.5px;"><?= htmlspecialchars(is_array($success) ? ($success['alamat'] ?? '-') : '-') ?></div>
            </div>

            <div style="background: rgba(255,255,255,0.03); padding: 14px 16px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                <div style="color: #94a3b8; font-size: 11px; text-transform: uppercase; margin-bottom: 4px;">Sales Consultant</div>
                <div style="color: #fff; font-weight: 600; font-size: 13.5px;"><?= htmlspecialchars(is_array($success) ? ($success['sales_name'] ?? $salesName) : $salesName) ?> (<?= htmlspecialchars($salesCode) ?>)</div>
                <div style="color: #94a3b8; font-size: 11px; margin-top: 2px;"><?= htmlspecialchars($settings['company_name'] ?? 'PT. TALENTA INTEGRITAS NASIONAL') ?></div>
            </div>
        </div>

        <div style="background: rgba(16, 185, 129, 0.08); border-left: 3px solid #10b981; padding: 12px 16px; border-radius: 6px; color: #cbd5e1; font-size: 12.5px; line-height: 1.6;">
            💡 <strong>Informasi Selanjutnya:</strong> Salinan resmi Formulir Pendaftaran PDF telah berhasil dikirimkan ke email Anda dan tercatat di sistem CBN. Tim teknisi akan menghubungi nomor telepon Anda sebelum kedatangan untuk konfirmasi kesiapan lokasi.
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error" role="alert" style="border-left: 4px solid #ef4444; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 12px; padding: 16px 20px; margin-bottom: 24px;">
        <div style="display:flex;align-items:flex-start;gap:12px;">
            <span style="font-size:22px;line-height:1;">⚠️</span>
            <div style="flex:1;">
                <strong style="font-size:14px;color:#fca5a5;display:block;margin-bottom:6px;">Mohon lengkapi dan perbaiki data formulir berikut:</strong>
                <ul style="margin:0;padding-left:20px;color:#fecaca;font-size:13px;line-height:1.7;">
                    <?php foreach ($errors as $fieldKey => $msg): ?>
                        <li><strong><?= htmlspecialchars(is_array($msg) ? implode(', ', $msg) : $msg) ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- FORM CARD -->
    <div class="form-card">

        <!-- Progress Indicator -->
        <div class="form-progress">
            <div class="progress-step active">
                <div class="progress-dot">1</div>
                <span class="progress-label">Data Pelanggan</span>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step active">
                <div class="progress-dot">2</div>
                <span class="progress-label">Alamat Pemasangan</span>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step active">
                <div class="progress-dot">3</div>
                <span class="progress-label">Paket & Add-On</span>
            </div>
            <div class="progress-line"></div>
            <div class="progress-step active">
                <div class="progress-dot">4</div>
                <span class="progress-label">Jadwal & TTD</span>
            </div>
        </div>

        <form id="cbn-form" method="POST" action="submit.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <!-- Field SO & Hidden Values -->
            <input type="hidden" name="vendor"          value="<?= htmlspecialchars($settings['company_name'] ?? 'PT. TIN') ?>">
            <input type="hidden" name="so_date"         value="<?= date('d/m/Y') ?>">
            <input type="hidden" name="tl_code"         value="<?= htmlspecialchars($tlCode) ?>">
            <input type="hidden" name="ae_name"         value="<?= htmlspecialchars($salesName ?: $salesCode) ?>">
            <input type="hidden" id="service"           name="service" value="<?= htmlspecialchars($selectedService) ?>">
            <input type="hidden" id="biaya_pasang"      name="biaya_pasang" value="Rp 0">
            <input type="hidden" id="biaya_paket"       name="biaya_paket" value="Rp 169.000">
            <input type="hidden" id="biaya_tambahan"    name="biaya_tambahan" value="Rp 5.000">
            <input type="hidden" id="biaya_addon"       name="biaya_addon" value="Rp 0">
            <input type="hidden" id="biaya_ppn"         name="biaya_ppn" value="Rp 19.140">
            <input type="hidden" id="biaya_total"       name="biaya_total" value="Rp 193.140">
            <input type="hidden" id="addon_cbn_package" name="addon_cbn_package" value="">

            <!-- ================= SEKSI 1: DATA PELANGGAN ================= -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">1</div>
                    <div>
                        <div class="section-title">1. DATA PELANGGAN / CUSTOMER DATA</div>
                        <div class="section-subtitle">Isi data pemohon sesuai kartu identitas resmi (KTP / Paspor)</div>
                    </div>
                </div>

                <div class="grid-2">
                    <!-- Sales Code (Terkunci & Persistent) -->
                    <div class="form-group">
                        <label class="form-label" for="sales_code">Sales Code (Kode Sales)</label>
                        <input type="text" id="sales_code" name="sales_code" class="form-input"
                            placeholder="Contoh: SEP-001"
                            value="<?= htmlspecialchars($salesCode) ?>"
                            <?= $activeSales ? 'readonly style="background:rgba(0,160,223,0.1);border-color:#00a0df;font-weight:bold;color:#67e8f9;"' : '' ?>>
                    </div>

                    <!-- Nama Lengkap -->
                    <div class="form-group">
                        <label class="form-label" for="nama_pelanggan">Nama Lengkap Pelanggan <span class="req">*</span></label>
                        <input type="text" id="nama_pelanggan" name="nama_pelanggan"
                            class="form-input <?= isset($errors['nama_pelanggan']) ? 'error' : '' ?>"
                            placeholder="Sesuai KTP"
                            value="<?= htmlspecialchars($old['nama_pelanggan'] ?? '') ?>"
                            required autocomplete="name">
                        <?php if (!empty($errors['nama_pelanggan'])): ?>
                            <div class="field-error-msg" style="color:#f87171;font-size:12px;font-weight:700;margin-top:5px;">⚠️ <?= htmlspecialchars($errors['nama_pelanggan']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- TTL -->
                    <div class="form-group">
                        <label class="form-label" for="ttl">Tempat / Tanggal Lahir <span class="req">*</span></label>
                        <input type="text" id="ttl" name="ttl"
                            class="form-input <?= isset($errors['ttl']) ? 'error' : '' ?>"
                            placeholder="Contoh: Jakarta, 15/08/1990"
                            value="<?= htmlspecialchars($old['ttl'] ?? '') ?>"
                            required>
                        <?php if (!empty($errors['ttl'])): ?>
                            <div class="field-error-msg" style="color:#f87171;font-size:12px;font-weight:700;margin-top:5px;">⚠️ <?= htmlspecialchars($errors['ttl']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Nomor KTP -->
                    <div class="form-group">
                        <label class="form-label" for="nomor_ktp">Nomor Identitas (KTP 16 Digit) <span class="req">*</span></label>
                        <input type="text" id="nomor_ktp" name="nomor_ktp"
                            class="form-input <?= isset($errors['nomor_ktp']) ? 'error' : '' ?>"
                            placeholder="16 digit angka KTP"
                            value="<?= htmlspecialchars($old['nomor_ktp'] ?? '') ?>"
                            maxlength="16" inputmode="numeric" required>
                        <span id="ktp-count" class="char-count">0/16</span>
                        <?php if (!empty($errors['nomor_ktp'])): ?>
                            <div class="field-error-msg" style="color:#f87171;font-size:12px;font-weight:700;margin-top:5px;">⚠️ <?= htmlspecialchars($errors['nomor_ktp']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Jenis Kelamin -->
                    <div class="form-group">
                        <label class="form-label">Jenis Kelamin (Gender) <span class="req">*</span></label>
                        <div class="radio-group">
                            <div class="radio-card">
                                <input type="radio" id="gender-pria" name="jenis_kelamin" value="PRIA"
                                    <?= ($old['jenis_kelamin'] ?? 'PRIA') === 'PRIA' ? 'checked' : '' ?> required>
                                <label class="radio-label-card" for="gender-pria">Pria (Male)</label>
                            </div>
                            <div class="radio-card">
                                <input type="radio" id="gender-wanita" name="jenis_kelamin" value="WANITA"
                                    <?= ($old['jenis_kelamin'] ?? '') === 'WANITA' ? 'checked' : '' ?>>
                                <label class="radio-label-card" for="gender-wanita">Wanita (Female)</label>
                            </div>
                        </div>
                        <?php if (!empty($errors['jenis_kelamin'])): ?>
                            <div class="field-error-msg" style="color:#f87171;font-size:12px;font-weight:700;margin-top:5px;">⚠️ <?= htmlspecialchars($errors['jenis_kelamin']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Telepon Seluler -->
                    <div class="form-group">
                        <label class="form-label" for="telp">No. Telepon Seluler / WhatsApp <span class="req">*</span></label>
                        <input type="tel" id="telp" name="telp"
                            class="form-input <?= isset($errors['telp']) ? 'error' : '' ?>"
                            placeholder="08xxxxxxxxxx"
                            value="<?= htmlspecialchars($old['telp'] ?? '') ?>"
                            required inputmode="tel">
                        <?php if (!empty($errors['telp'])): ?>
                            <div class="field-error-msg" style="color:#f87171;font-size:12px;font-weight:700;margin-top:5px;">⚠️ <?= htmlspecialchars($errors['telp']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Telepon Rumah -->
                    <div class="form-group">
                        <label class="form-label" for="telp_rumah">Telepon Rumah (Home Phone) <em style="color:#94a3b8;font-weight:normal;">(opsional)</em></label>
                        <input type="tel" id="telp_rumah" name="telp_rumah" class="form-input"
                            placeholder="Contoh: 0217654321"
                            value="<?= htmlspecialchars($old['telp_rumah'] ?? '') ?>">
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label class="form-label" for="email_pelanggan">Alamat Email Pelanggan <span class="req">*</span></label>
                        <input type="email" id="email_pelanggan" name="email_pelanggan"
                            class="form-input <?= isset($errors['email_pelanggan']) ? 'error' : '' ?>"
                            placeholder="nama@gmail.com (Salinan surat CBN akan dikirim ke sini)"
                            value="<?= htmlspecialchars($old['email_pelanggan'] ?? '') ?>"
                            required inputmode="email">
                        <?php if (!empty($errors['email_pelanggan'])): ?>
                            <div class="field-error-msg" style="color:#f87171;font-size:12px;font-weight:700;margin-top:5px;">⚠️ <?= htmlspecialchars($errors['email_pelanggan']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Home ID -->
                    <div class="form-group">
                        <label class="form-label" for="home_id">Home ID <em style="color:#94a3b8;font-weight:normal;">(opsional, jika sudah punya)</em></label>
                        <input type="text" id="home_id" name="home_id" class="form-input"
                            placeholder="Contoh: CBN-JKT-00123"
                            value="<?= htmlspecialchars($old['home_id'] ?? '') ?>">
                    </div>

                </div>
            </div>

            <!-- ================= SEKSI 2: ALAMAT PEMASANGAN ================= -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">2</div>
                    <div>
                        <div class="section-title">2. ALAMAT PEMASANGAN / INSTALLATION ADDRESS</div>
                        <div class="section-subtitle">Lokasi instalasi kabel fiber dan perangkat CBN</div>
                    </div>
                </div>

                <div class="grid-2">
                    <!-- Alamat Lengkap -->
                    <div class="form-group col-full">
                        <label class="form-label" for="alamat">Alamat Lengkap Rumah / Gedung <span class="req">*</span></label>
                        <input type="text" id="alamat" name="alamat"
                            class="form-input <?= isset($errors['alamat']) ? 'error' : '' ?>"
                            placeholder="Nama Jalan, Blok, Nomor Rumah, Patokan Lokasi"
                            value="<?= htmlspecialchars($old['alamat'] ?? '') ?>"
                            required>
                        <?php if (!empty($errors['alamat'])): ?>
                            <div class="field-error-msg" style="color:#f87171;font-size:12px;font-weight:700;margin-top:5px;">⚠️ <?= htmlspecialchars($errors['alamat']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- RT / RW -->
                    <div class="form-group">
                        <label class="form-label">RT & RW</label>
                        <div style="display:flex;gap:10px;">
                            <input type="text" name="rt" class="form-input" placeholder="RT (005)" style="flex:1;"
                                value="<?= htmlspecialchars($old['rt'] ?? '') ?>">
                            <input type="text" name="rw" class="form-input" placeholder="RW (008)" style="flex:1;"
                                value="<?= htmlspecialchars($old['rw'] ?? '') ?>">
                        </div>
                    </div>

                    <!-- Kode Pos -->
                    <div class="form-group">
                        <label class="form-label" for="kode_pos">Kode Pos (Zip Code) <span class="req">*</span></label>
                        <input type="text" id="kode_pos" name="kode_pos"
                            class="form-input <?= isset($errors['kode_pos']) ? 'error' : '' ?>"
                            placeholder="Contoh: 10510"
                            value="<?= htmlspecialchars($old['kode_pos'] ?? '') ?>"
                            maxlength="6" inputmode="numeric" required>
                        <?php if (!empty($errors['kode_pos'])): ?>
                            <div class="field-error-msg" style="color:#f87171;font-size:12px;font-weight:700;margin-top:5px;">⚠️ <?= htmlspecialchars($errors['kode_pos']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Kelurahan & Kecamatan -->
                    <div class="form-group">
                        <label class="form-label" for="kelurahan">Kelurahan / Desa</label>
                        <input type="text" id="kelurahan" name="kelurahan" class="form-input"
                            placeholder="Nama kelurahan"
                            value="<?= htmlspecialchars($old['kelurahan'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="kecamatan">Kecamatan</label>
                        <input type="text" id="kecamatan" name="kecamatan" class="form-input"
                            placeholder="Nama kecamatan"
                            value="<?= htmlspecialchars($old['kecamatan'] ?? '') ?>">
                    </div>

                    <!-- Status Kepemilikan -->
                    <div class="form-group">
                        <label class="form-label">Status Kepemilikan (Ownership Status) <span class="req">*</span></label>
                        <div class="radio-group">
                            <div class="radio-card">
                                <input type="radio" id="own-pemilik" name="status_kepemilikan" value="PEMILIK"
                                    <?= ($old['status_kepemilikan'] ?? 'PEMILIK') === 'PEMILIK' ? 'checked' : '' ?>>
                                <label class="radio-label-card" for="own-pemilik">Pemilik (Owner)</label>
                            </div>
                            <div class="radio-card">
                                <input type="radio" id="own-penyewa" name="status_kepemilikan" value="PENYEWA"
                                    <?= ($old['status_kepemilikan'] ?? '') === 'PENYEWA' ? 'checked' : '' ?>>
                                <label class="radio-label-card" for="own-penyewa">Penyewa (Renter)</label>
                            </div>
                        </div>
                    </div>

                    <!-- Titik Koordinat GPS -->
                    <div class="form-group">
                        <label class="form-label" for="tikor">Titik Koordinat GPS Lokasi Pemasangan</label>
                        <div class="input-with-btn" style="display:flex;gap:6px;flex-wrap:wrap;">
                            <input type="text" id="tikor" name="tikor" class="form-input" style="flex:1;min-width:180px;"
                                placeholder="Contoh: 3.5952, 98.6722"
                                value="<?= htmlspecialchars($old['tikor'] ?? '') ?>">
                            <button type="button" id="tikor-gps-btn" class="btn-gps" style="padding:0 14px;height:42px;display:inline-flex;align-items:center;gap:4px;">
                                📍 Deteksi GPS
                            </button>
                            <button type="button" id="tikor-map-btn" class="btn-gps" style="background:linear-gradient(135deg, #059669, #10b981);padding:0 14px;height:42px;display:inline-flex;align-items:center;gap:4px;">
                                🗺️ Pilih di Peta
                            </button>
                        </div>
                        <div id="tikor-helper-text" style="font-size:11.5px;color:#94a3b8;margin-top:5px;line-height:1.4;">
                            💡 Tekan <strong>Deteksi GPS</strong> untuk lokasi otomatis HP, atau <strong>Pilih di Peta</strong> untuk geser pin merah tepat ke atap rumah Anda.
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= SEKSI 3: PILIHAN PAKET & ADD-ON ================= -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon">3</div>
                    <div>
                        <div class="section-title">3. PILIHAN PAKET LAYANAN & ADD-ON CBN</div>
                        <div class="section-subtitle">Pilih kecepatan internet fiber dan opsi hiburan TV</div>
                    </div>
                </div>

                <!-- Paket Cards (Dinamis dari Dashboard Settings) -->
                <label class="form-label">Pilih Paket Internet Fiber <span class="req">*</span></label>
                <div class="package-grid">
                    <?php foreach ($packages as $pkg): 
                        if (empty($pkg['active'])) continue;
                        $isSelected = ($selectedService === $pkg['name']);
                    ?>
                    <div class="package-card <?= $isSelected ? 'selected' : '' ?>" data-package="<?= htmlspecialchars($pkg['name']) ?>">
                        <?php if (!empty($pkg['badge'])): ?>
                            <span class="package-badge" style="background:<?= htmlspecialchars($pkg['badge_color'] ?: '#005696') ?>;"><?= htmlspecialchars($pkg['badge']) ?></span>
                        <?php endif; ?>
                        <div class="package-name"><?= htmlspecialchars($pkg['name']) ?></div>
                        <div class="package-speed"><?= htmlspecialchars($pkg['speed']) ?></div>
                        <div class="package-price">Rp <?= number_format($pkg['price'], 0, ',', '.') ?> <span class="package-period">/ bln</span></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="grid-2" style="margin-top:22px;">
                    <!-- Add-on TV -->
                    <div class="form-group">
                        <label class="form-label">Paket Add-On TV & Hiburan <em style="color:#94a3b8;font-weight:normal;">(opsional)</em></label>
                        <div style="display:flex;flex-direction:column;gap:8px;">
                            <label class="checkbox-label-card" style="justify-content:flex-start;">
                                <input type="checkbox" name="addon_tv[]" value="Dens TV+ Apps" class="addon-tv-check">
                                Dens TV+ Apps (+Rp 30.000/bln)
                            </label>
                            <label class="checkbox-label-card" style="justify-content:flex-start;">
                                <input type="checkbox" name="addon_tv[]" value="Vision - Premium Sports" class="addon-tv-check">
                                Vision - Premium Sports (+Rp 40.000/bln)
                            </label>
                        </div>
                    </div>

                    <!-- CBN Package Auto-Info (ditentukan otomatis berdasarkan paket yang dipilih) -->
                    <div class="form-group">
                        <label class="form-label">Paket CBN yang Termasuk <em style="color:#10b981;font-weight:600;font-size:11px;">(Otomatis)</em></label>
                        <div id="cbn-package-info" style="background:rgba(0,160,223,0.08);border:1px solid rgba(0,160,223,0.25);border-radius:10px;padding:12px 14px;">
                            <div id="cbn-package-list">
                                <span style="color:#94a3b8;font-style:italic;font-size:12px;">Pilih paket internet di atas untuk melihat paket CBN yang termasuk.</span>
                            </div>
                        </div>
                        <div style="font-size:11px;color:#64748b;margin-top:5px;">📋 Teks ini akan otomatis muncul ter-ceklis di bagian Add-On TV pada Surat Formulir CBN.</div>
                    </div>

                    <!-- Perangkat Tambahan -->
                    <div class="form-group">
                        <label class="form-label">Perangkat Tambahan (Additional Devices)</label>
                        <div style="display:flex;gap:12px;">
                            <div style="flex:1;">
                                <label style="font-size:11.5px;color:#94a3b8;">Wireless Router (Unit)</label>
                                <input type="number" name="router_qty" class="form-input" value="0" min="0" max="5">
                            </div>
                            <div style="flex:1;">
                                <label style="font-size:11.5px;color:#94a3b8;">Smartbox Android TV (+Rp 35rb)</label>
                                <input type="number" id="smartbox_qty" name="smartbox_qty" class="form-input" value="0" min="0" max="5">
                            </div>
                        </div>
                    </div>

                    <!-- Aktivasi Username CBN -->
                    <div class="form-group col-full">
                        <label class="form-label" for="username_cbn">Pilihan Akun Email CBN (Service Activation)</label>
                        <div class="input-with-btn">
                            <input type="text" id="username_cbn" name="username_cbn" class="form-input"
                                placeholder="nama.pengguna"
                                value="<?= htmlspecialchars($old['username_cbn'] ?? '') ?>">
                            <span style="display:flex;align-items:center;padding:0 12px;background:rgba(255,255,255,0.08);border-radius:8px;font-weight:700;color:var(--cbn-cyan);font-size:13px;">
                                @ cbn.net.id
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Rincian Biaya Real-time -->
                <div class="pricing-summary-card">
                    <div style="font-size:13.5px;font-weight:700;color:#fff;margin-bottom:8px;border-bottom:1px solid var(--border-color);padding-bottom:6px;">
                        Estimasi Perincian Biaya / Payment Details
                    </div>
                    <div class="pricing-summary-row">
                        <span>Biaya Pemasangan / Instalasi</span>
                        <span style="color:#10b981;font-weight:700;">Rp 0 (Promo Gratis)</span>
                    </div>
                    <div class="pricing-summary-row">
                        <span>Biaya Paket Internet Bulanan</span>
                        <span id="summary-biaya-paket">Rp 169.000</span>
                    </div>
                    <div class="pricing-summary-row">
                        <span>Biaya Tambahan (Service Fee)</span>
                        <span id="summary-biaya-tambahan">Rp 5.000</span>
                    </div>
                    <div class="pricing-summary-row">
                        <span>Biaya Add-On TV / Device</span>
                        <span id="summary-biaya-addon">Rp 0</span>
                    </div>
                    <div class="pricing-summary-row">
                        <span id="summary-ppn-label">PPN <?= (float)($settings['ppn_percent'] ?? 11) ?>%</span>
                        <span id="summary-biaya-ppn">Rp 19.140</span>
                    </div>
                    <div class="pricing-summary-row total-row">
                        <span>ESTIMASI TOTAL BULAN PERTAMA</span>
                        <span id="summary-biaya-total">Rp 193.140</span>
                    </div>
                </div>
            </div>

            <!-- ================= SEKSI 4: JADWAL PEMASANGAN ================= -->

                    <!-- Catatan Tambahan -->
                    <div class="form-group col-full">
                        <label class="form-label" for="catatan">Catatan Lokasi / Permintaan Khusus</label>
                        <textarea id="catatan" name="catatan" class="form-textarea"
                            placeholder="Misal: Pagar hitam depan pos satpam, hubungi 30 menit sebelum tiba..."
                            maxlength="400"><?= htmlspecialchars($old['catatan'] ?? '') ?></textarea>
                    </div>

                </div>
            </div>

            <!-- ================= FOOTER SUBMIT ================= -->
            <div class="form-footer">
                <a href="<?= $baseUrl ?>/preview_cbn.php" target="_blank" class="btn-preview-live">
                    Pratinjau Template Surat CBN
                </a>
                <button type="submit" id="submit-btn" class="btn-submit">
                    <span class="spinner"></span>
                    <span class="btn-text">Kirim Formulir & Buat Dokumen CBN (PDF)</span>
                </button>
            </div>
        </form>
    </div>

</main>

<!-- MODAL INTERACTIVE MAP PICKER (LEAFLET) -->
<div id="map-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);backdrop-filter:blur(6px);z-index:9999;align-items:center;justify-content:center;padding:12px;">
    <div style="background:#111c38;border:1px solid rgba(0,160,223,0.35);border-radius:14px;width:100%;max-width:640px;overflow:hidden;box-shadow:0 20px 40px rgba(0,0,0,0.6);display:flex;flex-direction:column;max-height:90vh;">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:#0d162c;border-bottom:1px solid rgba(255,255,255,0.1);">
            <div style="font-weight:800;color:#fff;font-size:14px;display:flex;align-items:center;gap:6px;">
                🗺️ Pilih Titik Lokasi Pemasangan di Peta
            </div>
            <button type="button" id="close-map-btn" style="background:none;border:none;color:#94a3b8;font-size:24px;cursor:pointer;line-height:1;padding:0 4px;">&times;</button>
        </div>
        <div style="padding:9px 14px;background:rgba(0,160,223,0.12);font-size:11.5px;color:#67e8f9;line-height:1.4;">
            📌 <strong>Petunjuk:</strong> Geser pin merah atau ketuk pada peta untuk menentukan posisi rumah/gedung Anda.
        </div>
        <div id="leaflet-map-container" style="height:350px;width:100%;background:#0a1128;"></div>
        <div style="padding:12px 16px;background:#0d162c;border-top:1px solid rgba(255,255,255,0.1);display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
            <div style="font-size:12px;color:#cbd5e1;">
                Koordinat: <strong id="map-selected-coords" style="color:#38bdf8;font-family:monospace;font-size:12.5px;">-</strong>
            </div>
            <button type="button" id="use-map-coords-btn" class="btn-gps" style="background:linear-gradient(135deg, #005696, #00a0df);padding:9px 18px;font-size:13px;border-radius:8px;font-weight:700;">
                ✅ Gunakan Titik Ini
            </button>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const salesByLeader = <?= json_encode($salesByLeader, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const activeSalesCode = <?= json_encode($salesCode) ?>;
const formBaseUrl = <?= json_encode(rtrim($baseUrl, '/')) ?>;
const leaderPicker = document.getElementById('team-leader-picker');
const salesPicker = document.getElementById('sales-picker');

function populateSalesPicker(leaderCode, selectedCode) {
    salesPicker.replaceChildren();
    const list = salesByLeader[leaderCode] || [];
    list.forEach((sales) => {
        const option = new Option(`${sales.name} (${sales.code})`, sales.code);
        option.selected = sales.code === selectedCode;
        salesPicker.add(option);
    });
}

function redirectToSales(code) {
    if (!code || code === activeSalesCode) return;
    const base = formBaseUrl ? formBaseUrl : '';
    window.location.href = `${base}/${encodeURIComponent(code)}`;
}

leaderPicker.addEventListener('change', () => {
    populateSalesPicker(leaderPicker.value, '');
    if (salesPicker.value) {
        redirectToSales(salesPicker.value);
    }
});

salesPicker.addEventListener('change', () => {
    if (salesPicker.value) {
        redirectToSales(salesPicker.value);
    }
});

populateSalesPicker(leaderPicker.value, activeSalesCode);

// Package config injected from admin settings (settings.json)
// Allows main.js to use dynamic pricing and CBN package descriptions
window.CBN_PACKAGES = <?php
    $monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
    $curMonth   = $monthNames[(int)date('n')];
    $curYear    = date('Y');
    $pkgMap = [];
    foreach ($settings['packages'] as $pkg) {
        $cbnLines = $pkg['cbn_package'] ?? [];
        if (!is_array($cbnLines)) $cbnLines = [];
        // Replace {BULAN} placeholder with current month+year
        $cbnLines = array_map(fn($l) => str_replace('{BULAN}', $curMonth . ' ' . $curYear, $l), $cbnLines);
        $pkgMap[$pkg['name']] = [
            'price'          => (int)($pkg['price'] ?? 0),
            'biaya_tambahan' => (int)($pkg['biaya_tambahan'] ?? 5000),
            'cbn_package'    => array_values($cbnLines),
            'active'         => !empty($pkg['active']),
        ];
    }
    echo json_encode($pkgMap, JSON_UNESCAPED_UNICODE);
?>;
window.PPN_PERCENT = <?= json_encode((float)($settings['ppn_percent'] ?? 11)) ?>;
</script>
<script src="<?= $baseUrl ?>/assets/js/main.js"></script>
</body>
</html>
