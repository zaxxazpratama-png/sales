<?php
namespace App;

/**
 * CbnDocumentTemplate
 * Template Dokumen Resmi Formulir Pendaftaran Layanan CBN
 * Menggunakan cetakan resmi asli.pdf dengan penempatan data presisi (100% Identik dengan contoh resmi kantor)
 */
class CbnDocumentTemplate
{
    /**
     * Ambil Base64 Background Template Asli
     */
    public static function getTemplateBase64(): string
    {
        $bgFile = dirname(__DIR__) . '/asli_bg_base64.txt';
        if (file_exists($bgFile)) {
            return trim(file_get_contents($bgFile));
        }
        return '';
    }

    /**
     * Render Formulir CBN HTML Lengkap (100% Identik dengan contoh.jpeg / asli.pdf)
     */
    public static function render(array $data): string
    {
        $nama        = strtoupper(trim($data['nama_pelanggan'] ?? ''));
        $salesCode   = strtoupper(trim($data['sales_code'] ?? 'SEP-001'));
        $salesName   = strtoupper(trim($data['sales_name'] ?? 'PUJA PANGESTU'));
        $ttl         = strtoupper(trim($data['ttl'] ?? ''));
        $ktp         = trim($data['nomor_ktp'] ?? '');
        $gender      = strtoupper(trim($data['jenis_kelamin'] ?? 'PRIA'));
        $telpRumah   = trim($data['telp_rumah'] ?? '');
        $telpSelular = trim($data['telp'] ?? '');
        
        $alamat      = strtoupper(trim($data['alamat'] ?? ''));
        $rt          = trim($data['rt'] ?? '');
        $rw          = trim($data['rw'] ?? '');
        $kodePos     = trim($data['kode_pos'] ?? '');
        $kepemilikan = strtoupper(trim($data['status_kepemilikan'] ?? 'PEMILIK'));
        $email       = strtolower(trim($data['email_pelanggan'] ?? ''));
        
        $service     = trim($data['service'] ?? 'Fiber 50');
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
        
        $usernameCbn = trim($data['username_cbn'] ?? '');
        if (empty($usernameCbn)) {
            $usernameCbn = strtolower(explode(' ', $nama)[0] ?? 'user');
        }
        
        $tglPasang   = $data['jadwal_tanggal'] ?? date('d/m/Y', strtotime('+2 days'));
        $waktuPasang = $data['jadwal_waktu'] ?? '09.00-11.00';
        $catatan     = trim($data['catatan'] ?? '');
        
        $biayaPasang = $data['biaya_pasang'] ?? 'Rp 0';
        $biayaPaket  = $data['biaya_paket'] ?? 'Rp 299.000';
        $biayaTotal  = $data['biaya_total'] ?? 'Rp 331.890';
        
        $tglTtd      = $data['so_date'] ?? date('d/m/Y');
        $signatureImg= $data['signature_data'] ?? '';

        // Checkbox Status
        $isPria      = ($gender === 'PRIA' || $gender === 'MALE');
        $isWanita    = ($gender === 'WANITA' || $gender === 'FEMALE');
        $isPemilik   = ($kepemilikan === 'PEMILIK' || $kepemilikan === 'OWNER');
        $isPenyewa   = ($kepemilikan === 'PENYEWA' || $kepemilikan === 'RENTER');

        // Pisahkan TTL
        $ttlKota = ''; $ttlDay = ''; $ttlMonth = ''; $ttlYear = '';
        if (!empty($ttl)) {
            $ttlParts = explode(',', $ttl);
            $ttlKota = trim($ttlParts[0] ?? '');
            if (isset($ttlParts[1])) {
                $dParts = preg_split('/[\/\-\s]+/', trim($ttlParts[1]));
                if (count($dParts) >= 3) {
                    $ttlDay   = str_pad($dParts[0], 2, '0', STR_PAD_LEFT);
                    $ttlMonth = str_pad($dParts[1], 2, '0', STR_PAD_LEFT);
                    $ttlYear  = $dParts[2];
                }
            }
        }

        // Pisahkan Alamat jadi 2 baris
        $alamat1 = $alamat;
        $alamat2 = '';
        if (strlen($alamat) > 38) {
            $pos = strrpos(substr($alamat, 0, 38), ' ');
            if ($pos !== false) {
                $alamat1 = substr($alamat, 0, $pos);
                $alamat2 = trim(substr($alamat, $pos));
            }
        }

        // Add-on text
        $addonTvText = !empty($addonTv) ? implode(', ', $addonTv) : '';
        $bgBase64 = self::getTemplateBase64();

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Formulir Pendaftaran Layanan CBN - <?= htmlspecialchars($nama) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  @page { size: A4 portrait; margin: 0; }
  body {
    background: #1e293b;
    font-family: Arial, Helvetica, sans-serif;
    color: #000;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .cbn-toolbar {
    max-width: 210mm;
    margin: 15px auto 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #0f172a;
    padding: 10px 18px;
    border-radius: 8px;
    color: white;
  }
  .cbn-btn {
    background: #0088cc;
    color: white;
    border: none;
    padding: 7px 16px;
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

  .cbn-page-sheet {
    width: 210mm;
    height: 297mm;
    margin: 0 auto 30px;
    position: relative;
    background: #fff;
    box-shadow: 0 8px 30px rgba(0,0,0,0.3);
    overflow: hidden;
  }
  @media print {
    body { background: #fff; margin: 0; padding: 0; }
    .cbn-toolbar { display: none !important; }
    .cbn-page-sheet { width: 210mm; height: 297mm; margin: 0; box-shadow: none; }
  }

  .cbn-template-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 210mm;
    height: 297mm;
    z-index: 1;
    display: block;
  }
  .cbn-data-layer {
    position: absolute;
    top: 0;
    left: 0;
    width: 210mm;
    height: 297mm;
    z-index: 2;
  }
  .cbn-fld {
    position: absolute;
    font-weight: bold;
    color: #000;
    font-family: Arial, Helvetica, sans-serif;
    white-space: nowrap;
    line-height: 1;
  }
</style>
</head>
<body>

<div class="cbn-toolbar no-print">
  <div style="font-weight:bold;font-size:14px;">
    Formulir Pendaftaran Resmi CBN &mdash; <?= htmlspecialchars($nama) ?>
  </div>
  <div style="display:flex;gap:8px;">
    <button type="button" class="cbn-btn" onclick="window.print()">Cetak / Simpan PDF</button>
    <a href="javascript:window.history.back();" class="cbn-btn cbn-btn-back">Kembali ke Form</a>
  </div>
</div>

<div class="cbn-page-sheet">
  <!-- BACKGROUND TEMPLATE ASLI CBN -->
  <?php if (!empty($bgBase64)): ?>
    <img class="cbn-template-bg" src="data:image/jpeg;base64,<?= $bgBase64 ?>" alt="Template Asli CBN">
  <?php else: ?>
    <img class="cbn-template-bg" src="<?= $baseUrl ?>/asli_page_1.png" alt="Template Asli CBN">
  <?php endif; ?>

  <!-- DATA LAYER DENGAN PENEMPATAN PRESISI (100% IDENTIK DENGAN CONTOH KANTOR) -->
  <div class="cbn-data-layer">
    
    <!-- 1. DATA PELANGGAN -->
    <!-- Nama Pelanggan -->
    <div class="cbn-fld" style="top: 9.3%; left: 21.5%; font-size: 11pt;">
      <?= htmlspecialchars($nama) ?>
    </div>

    <!-- Tempat / Tanggal Lahir -->
    <?php if (!empty($ttlKota)): ?>
      <div class="cbn-fld" style="top: 12.0%; left: 21.5%; font-size: 10.5pt;">
        <?= htmlspecialchars($ttlKota) ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($ttlDay)): ?>
      <div class="cbn-fld" style="top: 12.0%; left: 58.4%; font-size: 10.5pt; width: 18px; text-align: center;">
        <?= htmlspecialchars($ttlDay) ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($ttlMonth)): ?>
      <div class="cbn-fld" style="top: 12.0%; left: 63.3%; font-size: 10.5pt; width: 18px; text-align: center;">
        <?= htmlspecialchars($ttlMonth) ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($ttlYear)): ?>
      <div class="cbn-fld" style="top: 12.0%; left: 69.0%; font-size: 10.5pt; width: 38px; text-align: center;">
        <?= htmlspecialchars($ttlYear) ?>
      </div>
    <?php endif; ?>

    <!-- Nomor KTP (16 Digit) -->
    <div class="cbn-fld" style="top: 14.6%; left: 21.5%; font-size: 11pt; letter-spacing: 2.2px;">
      <?= htmlspecialchars($ktp) ?>
    </div>

    <!-- Jenis Kelamin -->
    <?php if ($isPria): ?>
      <div class="cbn-fld" style="top: 14.7%; left: 75.3%; font-size: 11pt;">&#10006;</div>
    <?php elseif ($isWanita): ?>
      <div class="cbn-fld" style="top: 14.7%; left: 84.4%; font-size: 11pt;">&#10006;</div>
    <?php endif; ?>

    <!-- Telepon Selular / WhatsApp -->
    <div class="cbn-fld" style="top: 17.2%; left: 69.0%; font-size: 11pt; letter-spacing: 0.8px;">
      <?= htmlspecialchars($telpSelular) ?>
    </div>
    <?php if (!empty($telpRumah) && $telpRumah !== $telpSelular): ?>
      <div class="cbn-fld" style="top: 19.1%; left: 69.0%; font-size: 11pt; letter-spacing: 0.8px;">
        <?= htmlspecialchars($telpRumah) ?>
      </div>
    <?php else: ?>
      <div class="cbn-fld" style="top: 19.1%; left: 69.0%; font-size: 11pt; letter-spacing: 0.8px;">
        <?= htmlspecialchars($telpSelular) ?>
      </div>
    <?php endif; ?>

    <!-- 2. ALAMAT PEMASANGAN -->
    <div class="cbn-fld" style="top: 24.9%; left: 21.8%; font-size: 10.5pt;">
      <?= htmlspecialchars($alamat1) ?>
    </div>
    <?php if (!empty($alamat2)): ?>
      <div class="cbn-fld" style="top: 26.8%; left: 21.8%; font-size: 10.5pt;">
        <?= htmlspecialchars($alamat2) ?>
      </div>
    <?php endif; ?>

    <!-- Status Kepemilikan -->
    <?php if ($isPemilik): ?>
      <div class="cbn-fld" style="top: 31.9%; left: 21.6%; font-size: 12pt;">&#10004;</div>
    <?php elseif ($isPenyewa): ?>
      <div class="cbn-fld" style="top: 31.9%; left: 34.8%; font-size: 12pt;">&#10004;</div>
    <?php endif; ?>

    <!-- Alamat Email -->
    <div class="cbn-fld" style="top: 33.9%; left: 21.8%; font-size: 11pt;">
      <?= htmlspecialchars($email) ?>
    </div>

    <!-- 3. PILIHAN PAKET LAYANAN & ADDON -->
    <div class="cbn-fld" style="top: 41.7%; left: 2.9%; font-size: 12pt;">&#10004;</div>
    <div class="cbn-fld" style="top: 41.7%; left: 11.4%; font-size: 11pt;">
      <?= htmlspecialchars($service) ?> ....................................................
    </div>

    <!-- Add-On TV & Devices -->
    <?php if (!empty($addonTvText)): ?>
      <div class="cbn-fld" style="top: 48.4%; left: 74.4%; font-size: 8.5pt;">
        &#10004; <?= htmlspecialchars($addonTvText) ?>
      </div>
    <?php endif; ?>

    <!-- Perincian Biaya -->
    <div class="cbn-fld" style="top: 59.3%; left: 69.5%; font-size: 11pt;">
      <?= htmlspecialchars($biayaPaket) ?>
    </div>
    <div class="cbn-fld" style="top: 60.9%; left: 69.5%; font-size: 11pt;">
      <?= htmlspecialchars($biayaPasang) ?>
    </div>
    <div class="cbn-fld" style="top: 68.8%; left: 69.5%; font-size: 13pt;">
      <?= htmlspecialchars($biayaTotal) ?>
    </div>

    <!-- 4. AKTIVASI LAYANAN (USERNAME) -->
    <div class="cbn-fld" style="top: 84.1%; left: 2.8%; font-size: 11pt;">
      <?= htmlspecialchars($usernameCbn) ?>
    </div>

    <!-- 5. JADWAL & NOTES -->
    <div class="cbn-fld" style="top: 88.8%; left: 53.0%; font-size: 9.5pt;">
      <?= htmlspecialchars(!empty($catatan) ? $catatan : 'REGULAR PROMO CBN - PT. SEP') ?>
    </div>

    <!-- Tanggal Surat -->
    <div class="cbn-fld" style="top: 92.8%; left: 10.5%; font-size: 10pt;">
      <?= htmlspecialchars($tglTtd) ?>
    </div>

    <!-- Tanda Tangan Pelanggan -->
    <?php if (!empty($signatureImg)): ?>
      <img src="<?= $signatureImg ?>" style="position:absolute;top:90.5%;left:5%;max-height:40px;max-width:140px;z-index:3;" alt="TTD Pelanggan">
    <?php endif; ?>

    <!-- Tanda Tangan Sales -->
    <div class="cbn-fld" style="top: 94.6%; left: 42.0%; font-size: 10.5pt;">
      <?= htmlspecialchars($salesName) ?>
    </div>

    <!-- Tanda Tangan Sales SPV -->
    <div class="cbn-fld" style="top: 94.6%; left: 77.5%; font-size: 10.5pt;">
      <?= htmlspecialchars($salesCode) ?> - PT. SEP
    </div>

  </div>
</div>

</body>
</html>
        <?php
        return ob_get_clean();
    }
}
