<?php
namespace App;

/**
 * CbnDocumentTemplate
 * Template Formulir Resmi Pendaftaran Layanan CBN (100% Identik dengan asli.pdf)
 */
class CbnDocumentTemplate
{
    /**
     * Render string kotak-kotak karakter per huruf
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
     * Render Formulir CBN HTML Lengkap (Identik 100% dengan asli.pdf)
     */
    public static function render(array $data): string
    {
        $nama        = strtoupper($data['nama_pelanggan'] ?? '');
        $salesCode   = strtoupper($data['sales_code'] ?? 'SEP-001');
        $salesName   = strtoupper($data['sales_name'] ?? 'PUJA PANGESTU');
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
        $biayaTotal  = $data['biaya_total'] ?? 'Rp 331.890';
        
        $tglTtd      = $data['so_date'] ?? date('d / m / Y');
        $signatureImg= $data['signature_data'] ?? '';

        // Checkbox status
        $isPria      = ($gender === 'PRIA' || $gender === 'MALE');
        $isWanita    = ($gender === 'WANITA' || $gender === 'FEMALE');
        $isPemilik   = ($kepemilikan === 'PEMILIK' || $kepemilikan === 'OWNER');
        $isPenyewa   = ($kepemilikan === 'PENYEWA' || $kepemilikan === 'RENTER');

        // Fiber Packages
        $isFiberSafe = (stripos($service, 'Safe') !== false);
        $isFiberPro  = (stripos($service, 'Pro') !== false);
        $isFiberStd  = (!$isFiberSafe && !$isFiberPro);

        // TV Addons
        $hasDensTv   = false;
        $hasVision   = false;
        foreach ($addonTv as $tv) {
            if (stripos($tv, 'Dens') !== false) $hasDensTv = true;
            if (stripos($tv, 'Vision') !== false) $hasVision = true;
        }

        // Devices
        $hasRouter   = false;
        $hasSmartbox = false;
        foreach ($addonDevice as $dev) {
            if (stripos($dev, 'Router') !== false) $hasRouter = true;
            if (stripos($dev, 'Smartbox') !== false) $hasSmartbox = true;
        }
        if (!empty($routerQty) && $routerQty !== '0') $hasRouter = true;
        if (!empty($smartboxQty) && $smartboxQty !== '0') $hasSmartbox = true;

        // Pisahkan TTL menjadi Kota dan Tanggal (DD/MM/YYYY)
        $ttlKota = '';
        $ttlDay = ''; $ttlMonth = ''; $ttlYear = '';
        if (!empty($ttl)) {
            $ttlParts = explode(',', $ttl);
            $ttlKota = trim($ttlParts[0] ?? '');
            if (isset($ttlParts[1])) {
                $dateStr = trim($ttlParts[1]);
                $dParts = preg_split('/[\/\-\s]+/', $dateStr);
                if (count($dParts) >= 3) {
                    $ttlDay = str_pad($dParts[0], 2, '0', STR_PAD_LEFT);
                    $ttlMonth = str_pad($dParts[1], 2, '0', STR_PAD_LEFT);
                    $ttlYear = $dParts[2];
                }
            }
        }

        // Pisahkan Alamat menjadi baris
        $alamatRow1 = substr($alamat, 0, 30);
        $alamatRow2 = substr($alamat, 30, 30);
        $alamatRow3 = substr($alamat, 60, 13);

        // Jadwal Pasang
        $jadwalDay = ''; $jadwalMonth = ''; $jadwalYear = '';
        if (!empty($tglPasang)) {
            $jParts = preg_split('/[\/\-\s]+/', $tglPasang);
            if (count($jParts) >= 3) {
                $jadwalDay = str_pad($jParts[0], 2, '0', STR_PAD_LEFT);
                $jadwalMonth = str_pad($jParts[1], 2, '0', STR_PAD_LEFT);
                $jadwalYear = $jParts[2];
            }
        }
        $hariPasang = 'SENIN';
        if (!empty($tglPasang)) {
            $tStamp = strtotime(str_replace('/', '-', $tglPasang));
            if ($tStamp) {
                $hariMap = ['Sunday'=>'MINGGU','Monday'=>'SENIN','Tuesday'=>'SELASA','Wednesday'=>'RABU','Thursday'=>'KAMIS','Friday'=>'JUMAT','Saturday'=>'SABTU'];
                $hariPasang = $hariMap[date('l', $tStamp)] ?? 'SENIN';
            }
        }

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
    font-size: 7.2pt;
    color: #111;
    background: #e2e8f0;
    line-height: 1.15;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .cbn-page {
    width: 210mm;
    min-height: 297mm;
    margin: 15px auto;
    padding: 8mm 10mm 6mm 10mm;
    background: #fff;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    position: relative;
  }
  @media print {
    body { background: #fff; }
    .cbn-page { width: 100%; min-height: 100%; padding: 5mm 6mm; margin: 0; box-shadow: none; }
    .no-print { display: none !important; }
  }

  /* Print Controls */
  .cbn-toolbar {
    max-width: 210mm;
    margin: 10px auto 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #1e293b;
    padding: 8px 16px;
    border-radius: 8px;
    color: white;
  }
  .cbn-btn {
    background: #0088cc;
    color: white;
    border: none;
    padding: 6px 14px;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
  }
  .cbn-btn:hover { background: #006699; }
  .cbn-btn-back { background: #475569; }
  .cbn-btn-back:hover { background: #334155; }

  /* Header */
  .cbn-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 4px;
    margin-bottom: 2px;
  }
  .cbn-logo-area { display: flex; flex-direction: column; }
  .cbn-brand {
    font-size: 26pt;
    font-weight: 900;
    color: #005696;
    letter-spacing: -2px;
    font-family: 'Arial Black', sans-serif;
    line-height: 1;
    display: flex;
    align-items: center;
    gap: 4px;
  }
  .cbn-dot {
    display: inline-block;
    width: 14px;
    height: 14px;
    background: #00a0df;
    border-radius: 50%;
    margin-right: 2px;
  }
  .cbn-title-h1 {
    font-size: 9.5pt;
    color: #0066a1;
    font-weight: 800;
    text-transform: uppercase;
    margin-top: 4px;
  }
  .cbn-title-sub {
    font-size: 7.5pt;
    font-style: italic;
    color: #0066a1;
  }
  .cbn-header-right {
    text-align: right;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
  }
  .cbn-sales-box {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 7.5pt;
    font-weight: bold;
    color: #222;
  }
  .cbn-contact-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 6.5pt;
    color: #333;
  }
  .cbn-callcenter {
    background: #e30613;
    color: #fff;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: bold;
    font-size: 7.5pt;
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }

  /* Section Bars */
  .cbn-bar {
    background: #0066a1;
    color: #fff;
    font-weight: 800;
    font-size: 7.8pt;
    padding: 2.5px 6px;
    letter-spacing: 0.4px;
    margin-top: 4px;
    margin-bottom: 3px;
    text-transform: uppercase;
  }

  /* Rows & Labels */
  .cbn-row { display: flex; margin-bottom: 2px; align-items: center; }
  .cbn-lbl {
    width: 140px;
    flex-shrink: 0;
    font-size: 7pt;
    font-weight: 800;
    color: #111;
  }
  .cbn-lbl small {
    display: block;
    font-size: 5.8pt;
    font-weight: 400;
    font-style: italic;
    color: #555;
  }
  .cbn-field { flex: 1; display: flex; align-items: center; }

  /* Character Boxes */
  .cbn-char-row { display: flex; gap: 0px; }
  .cbn-char-box {
    width: 13.5px;
    height: 15px;
    border: 1px solid #555;
    border-right: none;
    text-align: center;
    font-size: 7.5pt;
    font-weight: bold;
    line-height: 14px;
    background: #fff;
    color: #000;
  }
  .cbn-char-box:last-child {
    border-right: 1px solid #555;
  }

  /* Square Checkbox */
  .cbn-chk-wrap {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-right: 12px;
    font-size: 7pt;
    color: #222;
  }
  .cbn-sq {
    width: 11px;
    height: 11px;
    border: 1.2px solid #222;
    display: inline-block;
    text-align: center;
    line-height: 9px;
    font-size: 7.5pt;
    font-weight: 900;
  }

  /* Two Column Grid */
  .cbn-columns {
    display: grid;
    grid-template-columns: 49.5% 49.5%;
    gap: 1%;
    margin-top: 3px;
  }
  .cbn-sub-box {
    border: 1px solid #ccc;
    padding: 4px 6px;
    font-size: 6.8pt;
    margin-top: 2px;
  }
  .cbn-sub-title {
    font-size: 7pt;
    font-weight: bold;
    color: #0066a1;
    border-bottom: 1px dotted #ccc;
    padding-bottom: 1px;
    margin-bottom: 3px;
    text-transform: uppercase;
  }

  /* Table styling */
  .cbn-tbl {
    width: 100%;
    border-collapse: collapse;
    font-size: 6.8pt;
    margin-top: 2px;
  }
  .cbn-tbl td, .cbn-tbl th {
    border: 1px solid #888;
    padding: 1.5px 4px;
  }
  .cbn-tbl th {
    background: #f0f4f8;
    text-align: left;
  }

  /* Disclaimers */
  .cbn-disclaimer {
    font-size: 5.5pt;
    color: #444;
    line-height: 1.15;
    margin: 2px 0;
    font-style: italic;
  }

  /* Signatures */
  .cbn-signatures {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 12px;
    margin-top: 6px;
    text-align: center;
  }
  .cbn-sig-col {
    position: relative;
    padding-top: 28px;
  }
  .cbn-sig-img {
    max-height: 32px;
    max-width: 110px;
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    margin: auto;
  }
  .cbn-sig-line {
    border-top: 1px solid #333;
    padding-top: 2px;
    font-size: 6.8pt;
    font-weight: 700;
    color: #222;
  }
  .cbn-sig-sub {
    font-size: 5.8pt;
    font-weight: normal;
    font-style: italic;
    color: #555;
  }

  .cbn-meta-footer {
    display: flex;
    justify-content: space-between;
    font-size: 5.8pt;
    color: #666;
    margin-top: 4px;
    border-top: 1px solid #ddd;
    padding-top: 2px;
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
      <div class="cbn-brand"><span class="cbn-dot"></span>CBN</div>
      <div class="cbn-title-h1">FORMULIR PENDAFTARAN LAYANAN CBN</div>
      <div class="cbn-title-sub">CBN service application form</div>
    </div>
    <div class="cbn-header-right">
      <div class="cbn-sales-box">
        <span>Sales code</span>
        <?= self::renderBoxes($salesCode, 8) ?>
      </div>
      <div class="cbn-contact-row">
        <span>🌐 www.cbn.id</span>
        <span>f &bull; di_CBN</span>
        <span class="cbn-callcenter">24 Hours CBN Call Center &bull; 1500 780</span>
      </div>
    </div>
  </div>

  <!-- ================= 1. DATA PELANGGAN ================= -->
  <div class="cbn-bar">DATA PELANGGAN / CUSTOMER DATA</div>

  <!-- Nama Pelanggan -->
  <div class="cbn-row">
    <div class="cbn-lbl">NAMA PELANGGAN<small>Full Name</small></div>
    <div class="cbn-field">
      <?= self::renderBoxes($nama, 28) ?>
    </div>
  </div>

  <!-- Tempat / Tanggal Lahir -->
  <div class="cbn-row">
    <div class="cbn-lbl">TEMPAT/TANGGAL LAHIR<small>Place/Date of birth</small></div>
    <div class="cbn-field" style="gap: 4px;">
      <?= self::renderBoxes($ttlKota, 14) ?>
      <span style="font-size:6pt;font-style:italic;color:#444;margin:0 2px;">dd/mm/yyyy</span>
      <?= self::renderBoxes($ttlDay, 2) ?>
      <span>/</span>
      <?= self::renderBoxes($ttlMonth, 2) ?>
      <span>/</span>
      <?= self::renderBoxes($ttlYear, 4) ?>
    </div>
  </div>

  <!-- Nomor Identitas & Jenis Kelamin -->
  <div class="cbn-row">
    <div class="cbn-lbl">NOMOR IDENTITAS<small>ID Card No.</small></div>
    <div class="cbn-field" style="justify-content:space-between;">
      <?= self::renderBoxes($ktp, 16) ?>
      <div style="display:flex;align-items:center;margin-left:8px;">
        <span style="font-size:6.8pt;font-weight:bold;margin-right:6px;">JENIS KELAMIN <small style="font-weight:normal;font-style:italic;">Gender</small></span>
        <div class="cbn-chk-wrap"><span class="cbn-sq"><?= $isPria ? '&#10003;' : '' ?></span> Pria - <i>Male</i></div>
        <div class="cbn-chk-wrap"><span class="cbn-sq"><?= $isWanita ? '&#10003;' : '' ?></span> Wanita - <i>Female</i></div>
      </div>
    </div>
  </div>

  <!-- Telepon Rumah & Selular -->
  <div class="cbn-row">
    <div class="cbn-lbl">TELEPON RUMAH<small>Home Phone</small></div>
    <div class="cbn-field" style="justify-content:space-between;">
      <?= self::renderBoxes($telpRumah, 10) ?>
      <div style="display:flex;align-items:center;margin-left:8px;">
        <span style="font-size:6.8pt;font-weight:bold;margin-right:6px;">TELEPON SELULAR <small style="font-weight:normal;font-style:italic;">Mobile Phone</small></span>
        <?= self::renderBoxes($telpSelular, 13) ?>
      </div>
    </div>
  </div>

  <div class="cbn-disclaimer">
    Data yang tercantum harus sesuai dengan identitas pelanggan yang berlaku. Semua pelanggan baru CBN diwajibkan untuk menyertakan kopi identitas yang berlaku.<br>
    The data declared above must be valid. All CBN subscribers are required to attach a copy of valid ID Card.
  </div>

  <!-- ================= 2. ALAMAT PEMASANGAN ================= -->
  <div class="cbn-bar">ALAMAT PEMASANGAN / INSTALLATION ADDRESS</div>

  <!-- Alamat Baris 1 -->
  <div class="cbn-row">
    <div class="cbn-lbl">ALAMAT PEMASANGAN<small>Installation Address</small></div>
    <div class="cbn-field">
      <?= self::renderBoxes($alamatRow1, 30) ?>
    </div>
  </div>

  <!-- Alamat Baris 2 -->
  <div class="cbn-row">
    <div class="cbn-lbl"></div>
    <div class="cbn-field">
      <?= self::renderBoxes($alamatRow2, 30) ?>
    </div>
  </div>

  <!-- Alamat Baris 3: Sub + RT + RW + Kode Pos -->
  <div class="cbn-row">
    <div class="cbn-lbl"></div>
    <div class="cbn-field" style="gap: 4px;">
      <?= self::renderBoxes($alamatRow3, 13) ?>
      <span style="font-size:6.5pt;font-weight:bold;margin-left:2px;">RT</span>
      <?= self::renderBoxes($rt, 2) ?>
      <span style="font-size:6.5pt;font-weight:bold;margin-left:2px;">RW</span>
      <?= self::renderBoxes($rw, 2) ?>
      <span style="font-size:6.5pt;font-weight:bold;margin-left:4px;">KODE POS <small style="font-style:italic;font-weight:normal;">Zip Code</small></span>
      <?= self::renderBoxes($kodePos, 5) ?>
    </div>
  </div>

  <!-- Status Kepemilikan -->
  <div class="cbn-row">
    <div class="cbn-lbl">STATUS KEPEMILIKAN<small>Ownership Status</small></div>
    <div class="cbn-field">
      <div class="cbn-chk-wrap"><span class="cbn-sq"><?= $isPemilik ? '&#10003;' : '' ?></span> Pemilik - <i>Owner</i></div>
      <div class="cbn-chk-wrap"><span class="cbn-sq"><?= $isPenyewa ? '&#10003;' : '' ?></span> Penyewa - <i>Renter</i></div>
    </div>
  </div>

  <!-- Email -->
  <div class="cbn-row">
    <div class="cbn-lbl">ALAMAT EMAIL<small>Email Address</small></div>
    <div class="cbn-field">
      <?= self::renderBoxes($email, 30) ?>
    </div>
  </div>

  <div class="cbn-disclaimer">
    Alamat pemasangan di atas akan berlaku sebagai alamat penagihan biaya berlangganan Anda. Jika alamat penagihan berbeda dengan alamat pemasangan, maka tagihan akan dikirimkan via e-billing ke alamat email yang tercantum di atas.<br>
    Your installation address will also act as your billing address. If the billing address is different, then the invoice will be sent via e-billing to the email listed above.
  </div>

  <!-- ================= 3. TWO COLUMNS (MIDDLE) ================= -->
  <div class="cbn-columns">
    
    <!-- LEFT: PAKET LAYANAN & CC METHOD -->
    <div>
      <div class="cbn-bar">PILIHAN PAKET LAYANAN / SERVICE PACKAGE OPTIONS</div>
      <div style="padding: 2px 0;">
        <div class="cbn-chk-wrap" style="display:flex;align-items:center;margin-bottom:2px;">
          <span class="cbn-sq"><?= $isFiberStd ? '&#10003;' : '' ?></span>
          <span>CBN Fiber <strong><?= htmlspecialchars($service) ?></strong></span>
        </div>
        <div class="cbn-chk-wrap" style="display:flex;align-items:center;margin-bottom:2px;">
          <span class="cbn-sq"><?= $isFiberSafe ? '&#10003;' : '' ?></span>
          <span>CBN Fiber Safe <small style="font-size:5.5pt;color:#555;">(Free Personal Cyber Insurance by AON/Chubb)</small></span>
        </div>
        <div class="cbn-chk-wrap" style="display:flex;align-items:center;margin-bottom:2px;">
          <span class="cbn-sq"><?= $isFiberPro ? '&#10003;' : '' ?></span>
          <span>CBN Fiber Pro</span>
        </div>
      </div>
      <div class="cbn-disclaimer" style="font-size:5.2pt;">
        Minimal kontrak berlangganan 12 bulan. Penambahan layanan dan perangkat lainnya akan dikenakan biaya tambahan. Harga paket CBN Fiber sudah termasuk paket Dens.TV. Harga belum termasuk rental Dens.TV Smartbox dan ONU.
      </div>

      <div class="cbn-bar" style="margin-top:4px;">PEMBAYARAN VIA KARTU KREDIT / CC PAYMENT METHOD</div>
      <div style="font-size:6.2pt;line-height:1.2;">
        <div>Nama pada kartu - <i>Name on card</i> : ................................................................</div>
        <div>Nomor kartu - <i>Card number</i> : ................................................................</div>
        <div>Masa berlaku - <i>Expiry date</i> : ................................ (MM/YYYY)</div>
        <div>Nama Bank - <i>Bank Name</i> : ................................................................</div>
        <div style="font-size:5.5pt;color:#555;margin:1px 0;">Visa | Mastercard | BCA Card | JCB BCA</div>
        <div class="cbn-chk-wrap" style="align-items:flex-start;margin-top:2px;">
          <span class="cbn-sq" style="margin-top:1px;"></span>
          <span style="font-size:5.2pt;">Saya memberikan wewenang kepada PT. Cyberindo Aditama untuk melakukan penagihan melalui kartu kredit saya untuk segala biaya CBN yang saya gunakan.</span>
        </div>
      </div>
    </div>

    <!-- RIGHT: LAYANAN TAMBAHAN & RINCIAN BIAYA -->
    <div>
      <div class="cbn-bar">PILIHAN LAYANAN TAMBAHAN / ADDITIONAL OPTIONS</div>
      
      <div style="font-size:6.5pt;">
        <strong>PERANGKAT TAMBAHAN - <i>ADDITIONAL DEVICES</i></strong>
        <div style="display:flex;justify-content:space-between;align-items:center;margin:1px 0;">
          <div class="cbn-chk-wrap"><span class="cbn-sq"><?= $hasRouter ? '&#10003;' : '' ?></span> Wireless Router</div>
          <span>[ <?= $hasRouter ? htmlspecialchars($routerQty) : '0' ?> ] Unit</span>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin:1px 0;">
          <div class="cbn-chk-wrap"><span class="cbn-sq"><?= $hasSmartbox ? '&#10003;' : '' ?></span> Smartbox</div>
          <span>[ <?= $hasSmartbox ? htmlspecialchars($smartboxQty) : '0' ?> ] Unit</span>
        </div>

        <strong style="display:block;margin-top:2px;">PAKET ADD-ON TV - <i>ADD-ON TV PACKAGE</i></strong>
        <div style="display:flex;justify-content:space-between;align-items:center;margin:1px 0;">
          <div class="cbn-chk-wrap"><span class="cbn-sq"><?= $hasDensTv ? '&#10003;' : '' ?></span> Dens.TV+ Apps</div>
          <span><?= $hasDensTv ? '[ &#10003; ]' : '[   ]' ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin:1px 0;">
          <div class="cbn-chk-wrap"><span class="cbn-sq"><?= $hasVision ? '&#10003;' : '' ?></span> Vision+ Premium Sports</div>
          <span><?= $hasVision ? '[ &#10003; ]' : '[   ]' ?></span>
        </div>
      </div>

      <div class="cbn-bar" style="margin-top:3px;display:flex;justify-content:space-between;">
        <span>PERINCIAN BIAYA / PAYMENT DETAILS</span>
        <span style="font-size:5.5pt;font-weight:normal;text-transform:none;">Bagian ini diisi oleh CBN</span>
      </div>
      <table class="cbn-tbl">
        <tr>
          <td>Biaya Pemasangan - <i>installation charges</i></td>
          <td style="text-align:right;width:90px;"><?= htmlspecialchars($biayaPasang) ?></td>
        </tr>
        <tr>
          <td>Biaya Paket - <i>monthly charges</i></td>
          <td style="text-align:right;"><?= htmlspecialchars($biayaPaket) ?></td>
        </tr>
        <tr>
          <td>Biaya Tambahan - <i>additional charges</i></td>
          <td style="text-align:right;">-</td>
        </tr>
        <tr>
          <td>PPN 11% - <i>VAT 11%</i></td>
          <td style="text-align:right;">Termasuk</td>
        </tr>
        <tr style="font-weight:bold;background:#f0f4f8;">
          <td>TOTAL</td>
          <td style="text-align:right;color:#005696;"><?= htmlspecialchars($biayaTotal) ?></td>
        </tr>
      </table>
    </div>

  </div>

  <!-- ================= 4. TWO COLUMNS (LOWER) ================= -->
  <div class="cbn-columns" style="margin-top:4px;">

    <!-- LEFT: AKTIVASI LAYANAN -->
    <div>
      <div class="cbn-bar">AKTIVASI LAYANAN / SERVICE ACTIVATION</div>
      <div style="font-size:5.5pt;color:#444;margin-bottom:2px;">
        Username diawali dengan huruf, minimal 3 dan maksimal 16 karakter (huruf kecil, angka, titik, strip).
      </div>
      <div class="cbn-row" style="margin-bottom:3px;">
        <div class="cbn-lbl" style="width:70px;">USERNAME</div>
        <div class="cbn-field" style="gap:2px;">
          <?= self::renderBoxes($usernameCbn, 14) ?>
          <span style="font-size:7pt;font-weight:bold;margin-left:2px;">@ cbn.net.id</span>
        </div>
      </div>
      <div style="border:1px solid #999;padding:3px 4px;font-size:5.2pt;color:#333;line-height:1.15;">
        <strong>Syarat dan ketentuan:</strong> Saya dengan ini menyatakan bahwa semua keterangan yang diisi adalah benar, serta menerima dan bersedia untuk terikat pada seluruh ketentuan berlangganan yang telah ditetapkan CBN seperti tertera pada www.cbn.id/terms-of-service.html. CBN berhak menolak permohonan berlangganan ini berdasarkan peraturan yang berlaku.
      </div>
    </div>

    <!-- RIGHT: JADWAL PEMASANGAN -->
    <div>
      <div class="cbn-bar">JADWAL PEMASANGAN / INSTALLATION SCHEDULE</div>
      <div style="font-size:6.5pt;">
        <div style="display:flex;align-items:center;gap:4px;margin-bottom:2px;">
          <strong>Hari - <i>day</i></strong>
          <?= self::renderBoxes($hariPasang, 6) ?>
          <span style="font-size:5.8pt;font-style:italic;margin-left:2px;">dd/mm/yyyy</span>
          <?= self::renderBoxes($jadwalDay, 2) ?>
          <span>/</span>
          <?= self::renderBoxes($jadwalMonth, 2) ?>
          <span>/</span>
          <?= self::renderBoxes($jadwalYear, 4) ?>
        </div>

        <div style="margin-top:2px;">
          <strong>WAKTU PEMASANGAN - <i>INSTALLATION TIME</i></strong>
          <div style="display:flex;gap:6px;margin-top:1px;">
            <div class="cbn-chk-wrap"><span class="cbn-sq"><?= ($waktuPasang === '09.00-11.00') ? '&#10003;' : '' ?></span> 09.00-11.00</div>
            <div class="cbn-chk-wrap"><span class="cbn-sq"><?= ($waktuPasang === '11.00-13.00') ? '&#10003;' : '' ?></span> 11.00-13.00</div>
            <div class="cbn-chk-wrap"><span class="cbn-sq"><?= ($waktuPasang === '13.00-15.00') ? '&#10003;' : '' ?></span> 13.00-15.00</div>
            <div class="cbn-chk-wrap"><span class="cbn-sq"><?= ($waktuPasang === '15.00-17.00') ? '&#10003;' : '' ?></span> 15.00-17.00</div>
          </div>
        </div>

        <div style="margin-top:3px;font-size:6pt;">
          <strong>Notes:</strong> <?= !empty($catatan) ? htmlspecialchars($catatan) : '..................................................................................................................' ?>
        </div>
      </div>
    </div>

  </div>

  <!-- ================= 5. TANDA TANGAN & FOOTER ================= -->
  <div style="font-size:6.8pt;font-weight:bold;margin-top:5px;">
    Tanggal - <i>date</i> : <?= htmlspecialchars($tglTtd) ?>
  </div>

  <div class="cbn-signatures">
    <div class="cbn-sig-col">
      <?php if (!empty($signatureImg)): ?>
        <img class="cbn-sig-img" src="<?= $signatureImg ?>" alt="TTD Pelanggan">
      <?php endif; ?>
      <div class="cbn-sig-line">Tanda tangan pelanggan</div>
      <div class="cbn-sig-sub">customer signature</div>
    </div>
    <div class="cbn-sig-col">
      <div style="position:absolute;top:4px;left:0;right:0;font-size:7pt;font-weight:bold;color:#005696;">
        <?= htmlspecialchars($salesCode) ?> - <?= htmlspecialchars($salesName) ?>
      </div>
      <div class="cbn-sig-line">Tanda tangan sales</div>
      <div class="cbn-sig-sub">sales signature</div>
    </div>
    <div class="cbn-sig-col">
      <div style="position:absolute;top:4px;left:0;right:0;font-size:7pt;font-weight:bold;color:#005696;">
        PT. SINERGI EMAS PERDANA
      </div>
      <div class="cbn-sig-line">Tanda tangan sales SPV</div>
      <div class="cbn-sig-sub">sales SPV signature</div>
    </div>
  </div>

  <div class="cbn-meta-footer">
    <span>Dokumen Resmi Pendaftaran Layanan CBN &bull; PT. Sinergi Emas Perdana</span>
    <span>F /CA-COMM/CBD-BDSA/IX/2025/</span>
  </div>

</div>

</body>
</html>
        <?php
        return ob_get_clean();
    }
}
