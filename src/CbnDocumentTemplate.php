<?php
namespace App;

class CbnDocumentTemplate
{
    /**
     * Render string char boxes
     */
    public static function renderBoxes(string $text, int $totalBoxes = 30): string
    {
        $text = strtoupper($text);
        $chars = mb_str_split($text);
        $html = '<div class="cbn-char-row">';
        for ($i = 0; $i < $totalBoxes; $i++) {
            $char = isset($chars[$i]) ? htmlspecialchars($chars[$i]) : '&nbsp;';
            $html .= '<div class="cbn-char-box">' . $char . '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Render Formulir CBN HTML Lengkap
     */
    public static function render(array $data): string
    {
        $nama        = strtoupper($data['nama_pelanggan'] ?? '');
        $salesCode   = strtoupper($data['sales_code'] ?? 'SEP-001');
        $ttl         = strtoupper($data['ttl'] ?? '');
        $ktp         = $data['nomor_ktp'] ?? '';
        $gender      = strtoupper($data['jenis_kelamin'] ?? 'PRIA');
        $telpRumah   = $data['telp_rumah'] ?? '';
        $telpSelular = $data['telp'] ?? '';
        
        $alamat      = strtoupper($data['alamat'] ?? '');
        $rt          = $data['rt'] ?? '';
        $rw          = $data['rw'] ?? '';
        $kodePos     = $data['kode_pos'] ?? '';
        $kepemilikan = strtoupper($data['status_kepemilikan'] ?? 'PEMILIK');
        $email       = strtolower($data['email_pelanggan'] ?? '');
        
        $service     = $data['service'] ?? 'Fiber 50';
        $addonTv     = $data['addon_tv'] ?? [];
        if (!is_array($addonTv)) {
            $addonTv = !empty($addonTv) ? explode(',', $addonTv) : [];
        }
        
        $addonDevice = $data['addon_device'] ?? [];
        if (!is_array($addonDevice)) {
            $addonDevice = !empty($addonDevice) ? explode(',', $addonDevice) : [];
        }
        
        $routerQty   = $data['router_qty'] ?? '1';
        $smartboxQty = $data['smartbox_qty'] ?? '0';
        
        $usernameCbn = $data['username_cbn'] ?? strtolower(explode(' ', trim($nama))[0] ?? 'user');
        
        $tglPasang   = $data['jadwal_tanggal'] ?? date('d/m/Y', strtotime('+2 days'));
        $waktuPasang = $data['jadwal_waktu'] ?? '09.00-11.00';
        $catatan     = $data['catatan'] ?? '';
        
        $biayaPasang = $data['biaya_pasang'] ?? 'Rp 0 (Promo Gratis Pasang)';
        $biayaPaket  = $data['biaya_paket'] ?? 'Rp 299.000';
        $biayaAddon  = $data['biaya_addon'] ?? 'Rp 0';
        $biayaPpn    = $data['biaya_ppn'] ?? 'Rp 32.890';
        $biayaTotal  = $data['biaya_total'] ?? 'Rp 331.890';
        
        $tglTtd      = $data['so_date'] ?? date('d / m / Y');
        $signatureImg= $data['signature_data'] ?? '';

        // Checkbox helper
        $isPria      = ($gender === 'PRIA' || $gender === 'MALE');
        $isWanita    = ($gender === 'WANITA' || $gender === 'FEMALE');
        $isPemilik   = ($kepemilikan === 'PEMILIK' || $kepemilikan === 'OWNER');
        $isPenyewa   = ($kepemilikan === 'PENYEWA' || $kepemilikan === 'RENTER');

        // Fiber Packages
        $isF50       = ($service === 'Fiber 50' || $service === '50 Mbps');
        $isF100      = ($service === 'Fiber 100' || $service === '100 Mbps');
        $isF200      = ($service === 'Fiber 200' || $service === '200 Mbps');
        $isF250      = ($service === 'Fiber 250' || $service === '250 Mbps');
        $isF1G       = ($service === 'Fiber 1Gbps' || $service === '1 Gbps');
        $isPro100    = ($service === 'Fiber PRO 100' || $service === 'Pro 100');
        $isPro200    = ($service === 'Fiber PRO 200' || $service === 'Pro 200');

        // TV Addons
        $hasDensTv   = in_array('Dens TV+ Apps', $addonTv);
        $hasVision   = in_array('Vision - Premium Sports', $addonTv);

        // Devices
        $hasRouter   = in_array('Wireless Router', $addonDevice) || !empty($routerQty);
        $hasSmartbox = in_array('Smartbox', $addonDevice) || (!empty($smartboxQty) && $smartboxQty !== '0');

        // Split characters for box rendering
        $nameRow1 = substr($nama, 0, 28);
        $nameRow2 = substr($nama, 28, 28);
        
        $alamatRow1 = substr($alamat, 0, 32);
        $alamatRow2 = substr($alamat, 32, 32);

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Formulir Pendaftaran Layanan CBN - <?= htmlspecialchars($nama) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 8.5pt;
    color: #111;
    background: #e2e8f0;
    line-height: 1.2;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .cbn-page {
    width: 210mm;
    min-height: 297mm;
    margin: 20px auto;
    padding: 10mm 12mm 8mm 12mm;
    background: #fff;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    position: relative;
  }
  @media print {
    body { background: #fff; }
    .cbn-page { width: 100%; min-height: 100%; padding: 6mm 8mm; margin: 0; box-shadow: none; }
    .no-print { display: none !important; }
  }

  /* Print Controls */
  .cbn-toolbar {
    max-width: 210mm;
    margin: 15px auto 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #1e293b;
    padding: 10px 16px;
    border-radius: 8px;
    color: white;
  }
  .cbn-btn {
    background: #0088cc;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
  }
  .cbn-btn:hover { background: #006699; }
  .cbn-btn-back { background: #475569; }
  .cbn-btn-back:hover { background: #334155; }

  /* Header */
  .cbn-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 2px solid #005696;
    padding-bottom: 6px;
    margin-bottom: 6px;
  }
  .cbn-logo-area { display: flex; align-items: center; gap: 8px; }
  .cbn-logo-icon {
    font-size: 26pt;
    font-weight: 900;
    color: #005696;
    letter-spacing: -2px;
    font-family: 'Arial Black', sans-serif;
  }
  .cbn-logo-icon span { color: #00a0df; }
  .cbn-title-block h1 {
    font-size: 11pt;
    color: #005696;
    font-weight: 800;
    text-transform: uppercase;
  }
  .cbn-title-block p {
    font-size: 8.5pt;
    font-style: italic;
    color: #333;
  }
  .cbn-header-right {
    text-align: right;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 3px;
  }
  .cbn-sales-code-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 8pt;
    font-weight: bold;
  }
  .cbn-contact-pills {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 7pt;
    color: #333;
  }
  .cbn-callcenter-badge {
    background: #e30613;
    color: #fff;
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: bold;
    font-size: 8pt;
    display: inline-block;
  }

  /* Section Bars */
  .cbn-section-bar {
    background: #005696;
    color: #fff;
    font-weight: 700;
    font-size: 8.5pt;
    padding: 3px 6px;
    letter-spacing: 0.5px;
    margin-top: 6px;
    margin-bottom: 4px;
    text-transform: uppercase;
  }

  /* Grid Layouts */
  .cbn-row { display: flex; margin-bottom: 3px; align-items: center; }
  .cbn-label {
    width: 145px;
    flex-shrink: 0;
    font-size: 7.5pt;
    font-weight: 700;
    color: #222;
  }
  .cbn-label small {
    display: block;
    font-size: 6.5pt;
    font-weight: 400;
    font-style: italic;
    color: #555;
  }
  .cbn-content { flex: 1; display: flex; align-items: center; flex-wrap: wrap; }

  /* Character Boxes */
  .cbn-char-row { display: flex; gap: 1px; }
  .cbn-char-box {
    width: 14px;
    height: 16px;
    border: 1px solid #777;
    text-align: center;
    font-size: 8.5pt;
    font-weight: bold;
    line-height: 15px;
    background: #fff;
  }

  /* Checkbox styling */
  .cbn-check {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-right: 14px;
    font-size: 8pt;
  }
  .cbn-sq {
    width: 12px;
    height: 12px;
    border: 1.5px solid #222;
    display: inline-block;
    text-align: center;
    line-height: 10px;
    font-size: 9pt;
    font-weight: 900;
  }

  /* 2 Columns Section */
  .cbn-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
  }
  .cbn-box-card {
    border: 1px solid #ccc;
    padding: 5px 6px;
    height: 100%;
    font-size: 7.5pt;
  }
  .cbn-box-card h4 {
    font-size: 8pt;
    font-weight: 700;
    color: #005696;
    margin-bottom: 4px;
    border-bottom: 1px dotted #ccc;
    padding-bottom: 2px;
  }

  /* Table styling */
  .cbn-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 7.5pt;
  }
  .cbn-table th, .cbn-table td {
    border: 1px solid #999;
    padding: 2.5px 5px;
  }
  .cbn-table th {
    background: #f0f4f8;
    text-align: left;
    font-size: 7.5pt;
  }

  /* Signatures */
  .cbn-sign-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 8px;
    margin-top: 6px;
    text-align: center;
  }
  .cbn-sign-box {
    border: 1px solid #aaa;
    height: 70px;
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 3px;
  }
  .cbn-sign-img {
    max-height: 45px;
    max-width: 130px;
    margin: 0 auto;
    position: absolute;
    top: 4px;
    left: 0;
    right: 0;
  }
  .cbn-sign-title {
    font-size: 7pt;
    font-weight: bold;
    border-top: 1px solid #333;
    padding-top: 2px;
    margin-top: auto;
  }

  /* Footer note */
  .cbn-footer-meta {
    margin-top: 6px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 6.5pt;
    color: #666;
    border-top: 1px solid #ddd;
    padding-top: 3px;
  }
</style>
</head>
<body>

<div class="cbn-toolbar no-print">
  <div>
    <strong>Formulir Pendaftaran Layanan CBN</strong> &mdash; <?= htmlspecialchars($nama) ?>
  </div>
  <div style="display:flex;gap:8px;">
    <button type="button" class="cbn-btn" onclick="window.print()">Cetak / Simpan PDF</button>
    <a href="javascript:window.history.back();" class="cbn-btn cbn-btn-back">Kembali ke Form</a>
  </div>
</div>

<div class="cbn-page">

  <!-- ================= HEADER ================= -->
  <div class="cbn-header">
    <div class="cbn-logo-area">
      <div class="cbn-logo-icon">cbn<span>.</span></div>
      <div class="cbn-title-block">
        <h1>FORMULIR PENDAFTARAN LAYANAN CBN</h1>
        <p>CBN service application form</p>
      </div>
    </div>
    <div class="cbn-header-right">
      <div class="cbn-sales-code-wrap">
        <span>Sales code</span>
        <?= self::renderBoxes($salesCode, 8) ?>
      </div>
      <div class="cbn-contact-pills">
        <span>@www.cbn.id</span>
        <span>f / di_CBN</span>
        <span class="cbn-callcenter-badge">1500 780</span>
      </div>
    </div>
  </div>

  <!-- ================= 1. DATA PELANGGAN ================= -->
  <div class="cbn-section-bar">DATA PELANGGAN / CUSTOMER DATA</div>

  <!-- Nama -->
  <div class="cbn-row">
    <div class="cbn-label">NAMA PELANGGAN<small>Full Name</small></div>
    <div class="cbn-content">
      <?= self::renderBoxes($nameRow1, 28) ?>
    </div>
  </div>
  <?php if (!empty($nameRow2)): ?>
  <div class="cbn-row" style="margin-top:-2px;">
    <div class="cbn-label"></div>
    <div class="cbn-content">
      <?= self::renderBoxes($nameRow2, 28) ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- TTL & Gender -->
  <div class="cbn-row">
    <div class="cbn-label">TEMPAT/TANGGAL LAHIR<small>Place/Date of birth</small></div>
    <div class="cbn-content" style="gap: 10px;">
      <?= self::renderBoxes($ttl, 18) ?>
      <div class="cbn-check" style="margin-left: 10px;">
        <span class="cbn-sq"><?= $isPria ? 'V' : '' ?></span> Pria - Male
      </div>
      <div class="cbn-check">
        <span class="cbn-sq"><?= $isWanita ? 'V' : '' ?></span> Wanita - Wanita
      </div>
    </div>
  </div>

  <!-- KTP -->
  <div class="cbn-row">
    <div class="cbn-label">NOMOR IDENTITAS<small>Nomor KTP / Paspor</small></div>
    <div class="cbn-content">
      <?= self::renderBoxes($ktp, 20) ?>
    </div>
  </div>

  <!-- Telepon -->
  <div class="cbn-row">
    <div class="cbn-label">TELEPON RUMAH / SELULAR<small>Home / Mobile Phone</small></div>
    <div class="cbn-content" style="gap: 15px;">
      <div style="display:flex;align-items:center;gap:4px;">
        <span style="font-size:7pt;color:#555;">Rumah:</span>
        <?= self::renderBoxes($telpRumah, 12) ?>
      </div>
      <div style="display:flex;align-items:center;gap:4px;">
        <span style="font-size:7pt;color:#555;">Selular/WA:</span>
        <?= self::renderBoxes($telpSelular, 14) ?>
      </div>
    </div>
  </div>

  <!-- ================= 2. ALAMAT PEMASANGAN ================= -->
  <div class="cbn-section-bar">ALAMAT PEMASANGAN / INSTALLATION ADDRESS</div>

  <div class="cbn-row">
    <div class="cbn-label">ALAMAT PEMASANGAN<small>Installation Address</small></div>
    <div class="cbn-content">
      <?= self::renderBoxes($alamatRow1, 28) ?>
    </div>
  </div>
  <div class="cbn-row" style="margin-top:-2px;">
    <div class="cbn-label"></div>
    <div class="cbn-content" style="gap: 8px;">
      <?= self::renderBoxes($alamatRow2, 16) ?>
      <span style="font-size:7pt;">RT</span> <?= self::renderBoxes($rt, 3) ?>
      <span style="font-size:7pt;">RW</span> <?= self::renderBoxes($rw, 3) ?>
      <span style="font-size:7pt;">KODE POS</span> <?= self::renderBoxes($kodePos, 5) ?>
    </div>
  </div>

  <div class="cbn-row">
    <div class="cbn-label">STATUS KEPEMILIKAN<small>Ownership Status</small></div>
    <div class="cbn-content">
      <div class="cbn-check">
        <span class="cbn-sq"><?= $isPemilik ? 'V' : '' ?></span> Pemilik - Owner
      </div>
      <div class="cbn-check">
        <span class="cbn-sq"><?= $isPenyewa ? 'V' : '' ?></span> Penyewa - Renter
      </div>
    </div>
  </div>

  <div class="cbn-row">
    <div class="cbn-label">ALAMAT EMAIL<small>Email Address</small></div>
    <div class="cbn-content">
      <?= self::renderBoxes($email, 28) ?>
    </div>
  </div>

  <!-- ================= 3. PILIHAN PAKET & LAYANAN TAMBAHAN ================= -->
  <div class="cbn-grid-2" style="margin-top: 4px;">
    <!-- Kiri: Paket Layanan -->
    <div class="cbn-box-card">
      <h4>PILIHAN PAKET LAYANAN / SERVICE PACKAGE OPTIONS</h4>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px;">
        <div>
          <strong>FIBER</strong><br>
          <div class="cbn-check" style="margin:2px 0;"><span class="cbn-sq"><?= $isF50 ? 'V' : '' ?></span> Fiber 50</div><br>
          <div class="cbn-check" style="margin:2px 0;"><span class="cbn-sq"><?= $isF100 ? 'V' : '' ?></span> Fiber 100</div><br>
          <div class="cbn-check" style="margin:2px 0;"><span class="cbn-sq"><?= $isF200 ? 'V' : '' ?></span> Fiber 200</div><br>
          <div class="cbn-check" style="margin:2px 0;"><span class="cbn-sq"><?= $isF250 ? 'V' : '' ?></span> Fiber 250</div><br>
          <div class="cbn-check" style="margin:2px 0;"><span class="cbn-sq"><?= $isF1G ? 'V' : '' ?></span> Fiber 1Gbps</div>
        </div>
        <div>
          <strong>FIBER PRO</strong><br>
          <div class="cbn-check" style="margin:2px 0;"><span class="cbn-sq"><?= $isPro100 ? 'V' : '' ?></span> Fiber PRO 100</div><br>
          <div class="cbn-check" style="margin:2px 0;"><span class="cbn-sq"><?= $isPro200 ? 'V' : '' ?></span> Fiber PRO 200</div>
        </div>
      </div>
    </div>

    <!-- Kanan: Layanan Tambahan -->
    <div class="cbn-box-card">
      <h4>PILIHAN LAYANAN TAMBAHAN / ADDITIONAL OPTIONS</h4>
      <div style="margin-bottom: 4px;">
        <strong>PERANGKAT TAMBAHAN</strong>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:2px;">
          <div class="cbn-check"><span class="cbn-sq"><?= $hasRouter ? 'V' : '' ?></span> Wireless Router</div>
          <span style="font-size:7pt;">[ <?= htmlspecialchars($routerQty) ?> Unit ]</span>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:2px;">
          <div class="cbn-check"><span class="cbn-sq"><?= $hasSmartbox ? 'V' : '' ?></span> Smartbox</div>
          <span style="font-size:7pt;">[ <?= htmlspecialchars($smartboxQty) ?> Unit ]</span>
        </div>
      </div>
      <div>
        <strong>PAKET ADD-ON TV</strong>
        <div class="cbn-check" style="margin-top:2px;"><span class="cbn-sq"><?= $hasDensTv ? 'V' : '' ?></span> Dens TV+ Apps</div>
        <div class="cbn-check" style="margin-top:2px;"><span class="cbn-sq"><?= $hasVision ? 'V' : '' ?></span> Vision - Premium Sports</div>
      </div>
    </div>
  </div>

  <!-- ================= 4. PEMBAYARAN & RINCIAN BIAYA ================= -->
  <div class="cbn-grid-2" style="margin-top: 4px;">
    <!-- Kiri: Info Pembayaran -->
    <div class="cbn-box-card">
      <h4>PEMBAYARAN / PAYMENT METHOD</h4>
      <p style="font-size:7pt;margin-bottom:4px;">Metode: Transfer Virtual Account / CC / Auto-Debit</p>
      <table style="width:100%;font-size:7pt;border-collapse:collapse;">
        <tr><td style="width:90px;color:#555;">Nama Akun:</td><td><?= htmlspecialchars($nama) ?></td></tr>
        <tr><td style="color:#555;">No. Referensi:</td><td><?= htmlspecialchars($salesCode) ?>-<?= date('Ymd') ?></td></tr>
        <tr><td style="color:#555;">Status:</td><td><strong>Menunggu Aktivasi</strong></td></tr>
      </table>
    </div>

    <!-- Kanan: Rincian Biaya -->
    <div class="cbn-box-card">
      <h4>PERINCIAN BIAYA / PAYMENT DETAILS</h4>
      <table class="cbn-table">
        <tr><td>Biaya Pemasangan</td><td style="text-align:right;"><?= $biayaPasang ?></td></tr>
        <tr><td>Biaya Paket (Monthly)</td><td style="text-align:right;"><?= $biayaPaket ?></td></tr>
        <tr><td>Biaya Tambahan / Add-on</td><td style="text-align:right;"><?= $biayaAddon ?></td></tr>
        <tr><td>PPN 11%</td><td style="text-align:right;"><?= $biayaPpn ?></td></tr>
        <tr style="font-weight:bold;background:#e2f1ff;"><td>TOTAL</td><td style="text-align:right;"><?= $biayaTotal ?></td></tr>
      </table>
    </div>
  </div>

  <!-- ================= 5. AKTIVASI & JADWAL PEMASANGAN ================= -->
  <div class="cbn-grid-2" style="margin-top: 4px;">
    <!-- Kiri: Aktivasi Layanan -->
    <div class="cbn-box-card">
      <h4>AKTIVASI LAYANAN / SERVICE ACTIVATION</h4>
      <div style="margin-top:4px;">
        <span style="font-size:7pt;color:#333;">Pilihan Username Email CBN:</span><br>
        <div style="display:flex;align-items:center;gap:4px;margin-top:2px;">
          <?= self::renderBoxes($usernameCbn, 14) ?>
          <strong style="font-size:8pt;color:#005696;">@ cbn.net.id</strong>
        </div>
      </div>
    </div>

    <!-- Kanan: Jadwal Pemasangan -->
    <div class="cbn-box-card">
      <h4>JADWAL PEMASANGAN / INSTALLATION SCHEDULE</h4>
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;">
        <span style="font-size:7pt;">Tanggal:</span>
        <?= self::renderBoxes($tglPasang, 10) ?>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:4px;font-size:7pt;">
        <span class="cbn-check"><span class="cbn-sq"><?= $waktuPasang === '09.00-11.00' ? 'V' : '' ?></span> 09.00-11.00</span>
        <span class="cbn-check"><span class="cbn-sq"><?= $waktuPasang === '11.00-13.00' ? 'V' : '' ?></span> 11.00-13.00</span>
        <span class="cbn-check"><span class="cbn-sq"><?= $waktuPasang === '13.00-15.00' ? 'V' : '' ?></span> 13.00-15.00</span>
        <span class="cbn-check"><span class="cbn-sq"><?= $waktuPasang === '15.00-17.00' ? 'V' : '' ?></span> 15.00-17.00</span>
      </div>
      <?php if (!empty($catatan)): ?>
      <div style="font-size:6.5pt;color:#666;margin-top:2px;">Notes: <?= htmlspecialchars($catatan) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ================= 6. TANDA TANGAN & PERSETUJUAN ================= -->
  <div style="margin-top: 6px; font-size: 6.5pt; color: #555; line-height: 1.2;">
    Dengan menandatangani formulir ini, pelanggan menyetujui syarat & ketentuan layanan CBN yang berlaku.
  </div>

  <div class="cbn-sign-row">
    <!-- Pelanggan -->
    <div class="cbn-sign-box">
      <?php if (!empty($signatureImg)): ?>
        <img class="cbn-sign-img" src="<?= htmlspecialchars($signatureImg) ?>" alt="Tanda Tangan Pelanggan">
      <?php endif; ?>
      <div class="cbn-sign-title">
        Tanda tangan pelanggan<br>
        <span style="font-weight:normal;font-size:6pt;">Tanggal: <?= htmlspecialchars($tglTtd) ?></span>
      </div>
    </div>

    <!-- Sales -->
    <div class="cbn-sign-box">
      <div class="cbn-sign-title">
        Tanda tangan sales<br>
        <span style="font-weight:normal;font-size:6pt;">Sales: <?= htmlspecialchars($salesCode) ?></span>
      </div>
    </div>

    <!-- SPV -->
    <div class="cbn-sign-box">
      <div class="cbn-sign-title">
        Tanda tangan sales SPV<br>
        <span style="font-weight:normal;font-size:6pt;">PT. Sinergi Emas Perdana</span>
      </div>
    </div>
  </div>

  <!-- Meta footer -->
  <div class="cbn-footer-meta">
    <span>Dokumen Resmi Pendaftaran Layanan CBN Internet & TV</span>
    <span>CA-JKT-REL-FRM-00002023-1.0</span>
  </div>

</div>

</body>
</html>
        <?php
        return ob_get_clean();
    }
}
