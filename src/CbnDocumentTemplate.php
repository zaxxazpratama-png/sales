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

  <?php
    /**
     * $box() - 1 karakter per 1 kotak, terpusat di tengah, font bold
     * $startX = left edge kotak pertama (%)
     * $startY = top (%)
     * $stepX  = lebar 1 kotak (%), default 2.483
     * $fs     = font-size CSS
     * $max    = jumlah kotak maksimum field
     */
    $box = function($text, $startX, $startY, $stepX = 2.483, $fs = '10pt', $max = 50) {
        if (empty($text) && $text !== '0') return '';
        $str = strtoupper(trim((string)$text));
        $html = '';
        $col = 0;
        for ($i = 0; $i < strlen($str) && $col < $max; $i++) {
            $ch = $str[$i];
            if ($ch === ' ') { $col++; continue; } // spasi: kosongi kotak, tetap hitung
            $bLeft = number_format($startX + $col * $stepX, 3, '.', '');
            $bW    = number_format($stepX, 3, '.', '');
            $bTop  = number_format($startY, 2, '.', '');
            $html .= "<div class=\"cbn-fld\" style=\"top:{$bTop}%;left:{$bLeft}%;width:{$bW}%;font-size:{$fs};text-align:center;\">"
                   . htmlspecialchars($ch) . "</div>\n";
            $col++;
        }
        return $html;
    };

    // $f() - render teks biasa (tidak per-kotak)
    $f = function($text, $top, $left, $fs = '11pt', $extra = '') {
        if (empty($text) && $text !== '0') return '';
        return "<div class=\"cbn-fld\" style=\"top:{$top}%;left:{$left}%;font-size:{$fs};{$extra}\">"
               . htmlspecialchars((string)$text) . "</div>\n";
    };
  ?>

  <!-- DATA LAYER DENGAN PENEMPATAN PRESISI (1 HURUF = 1 KOTAK) -->
  <div class="cbn-data-layer">

    <!-- 0. SALES CODE kanan atas (6 kotak, step=2.15%) -->
    <?= $box(preg_replace('/[^A-Z0-9]/', '', strtoupper($salesCode)), 74.0, 3.3, 2.15, '9pt', 6) ?>

    <!-- 1. NAMA PELANGGAN (29 kotak) -->
    <?= $box($nama, 21.15, 11.25, 2.483, '10pt', 29) ?>

    <!-- TEMPAT LAHIR (15 kotak) -->
    <?= $box($ttlKota, 21.15, 13.8, 2.483, '10pt', 15) ?>

    <!-- TANGGAL LAHIR: DD | MM | YYYY -->
    <?= $box($ttlDay,   59.0,  13.8, 2.483, '10pt', 2) ?>
    <?= $box($ttlMonth, 64.45, 13.8, 2.483, '10pt', 2) ?>
    <?= $box($ttlYear,  69.9,  13.8, 2.483, '10pt', 4) ?>

    <!-- NOMOR IDENTITAS KTP (16 kotak) -->
    <?= $box(preg_replace('/[^0-9]/', '', $ktp), 21.15, 16.45, 2.483, '10pt', 16) ?>

    <!-- JENIS KELAMIN -->
    <?php if ($isPria): ?>
      <div class="cbn-fld" style="top:16.35%;left:75.6%;width:2.483%;font-size:12pt;text-align:center;">X</div>
    <?php elseif ($isWanita): ?>
      <div class="cbn-fld" style="top:16.35%;left:84.4%;width:2.483%;font-size:12pt;text-align:center;">X</div>
    <?php endif; ?>

    <!-- TELEPON RUMAH -->
    <?php if (!empty($telpRumah) && $telpRumah !== $telpSelular): ?>
      <?= $box(preg_replace('/[^0-9]/', '', $telpRumah), 21.15, 18.95, 2.483, '10pt', 12) ?>
    <?php endif; ?>

    <!-- TELEPON SELULAR / WA (2 baris sama) -->
    <?= $box(preg_replace('/[^0-9]/', '', $telpSelular), 68.8, 18.95, 2.483, '10pt', 12) ?>
    <?= $box(preg_replace('/[^0-9]/', '', $telpSelular), 68.8, 20.5,  2.483, '10pt', 12) ?>

    <!-- 2. ALAMAT PEMASANGAN (29 kotak × 2 baris) -->
    <?= $box($alamat1, 21.15, 26.8, 2.483, '9.5pt', 29) ?>
    <?php if (!empty($alamat2)): ?>
      <?= $box($alamat2, 21.15, 28.7, 2.483, '9.5pt', 29) ?>
    <?php endif; ?>

    <!-- STATUS KEPEMILIKAN -->
    <?php if ($isPemilik): ?>
      <div class="cbn-fld" style="top:33.15%;left:21.2%;font-size:13pt;font-weight:bold;">&#10004;</div>
    <?php elseif ($isPenyewa): ?>
      <div class="cbn-fld" style="top:33.15%;left:34.8%;font-size:13pt;font-weight:bold;">&#10004;</div>
    <?php endif; ?>

    <!-- ALAMAT EMAIL (29 kotak) -->
    <?= $box(strtolower($email), 21.15, 35.2, 2.483, '9pt', 29) ?>

    <!-- 3. PILIHAN PAKET LAYANAN -->
    <div class="cbn-fld" style="top:42.25%;left:2.9%;font-size:13pt;font-weight:bold;">&#10004;</div>
    <?= $f($service, 42.25, 11.4, '11.5pt') ?>

    <!-- ADD-ON TV -->
    <?php if (!empty($addonTvText)): ?>
      <div class="cbn-fld" style="top:49.25%;left:74.4%;font-size:9pt;font-weight:bold;">
        &#10004; <?= htmlspecialchars($addonTvText) ?>
      </div>
    <?php endif; ?>

    <!-- RINCIAN BIAYA -->
    <?= $f($biayaPaket,  60.0, 69.5, '11pt') ?>
    <?= $f($biayaPasang, 61.5, 69.5, '11pt') ?>
    <?= $f($biayaTotal,  69.3, 69.5, '13pt') ?>

    <!-- 4. USERNAME (11 kotak) -->
    <?= $box(strtolower($usernameCbn), 2.8, 84.5, 2.483, '10pt', 11) ?>

    <!-- 5. NOTES -->
    <?= $f(!empty($catatan) ? $catatan : 'REGULAR PROMO CBN - PT. SEP', 88.8, 51.5, '9.5pt') ?>

    <!-- TANGGAL SURAT -->
    <?= $f($tglTtd, 92.85, 9.5, '10.5pt') ?>

    <!-- TANDA TANGAN PELANGGAN -->
    <?php if (!empty($signatureImg)): ?>
      <img src="<?= $signatureImg ?>" style="position:absolute;top:89.5%;left:4%;max-height:45px;max-width:150px;z-index:3;" alt="TTD">
    <?php endif; ?>

    <!-- NAMA SALES & SALES CODE -->
    <?= $f($salesName, 94.85, 38.5, '10.5pt') ?>
    <?= $f($salesCode . '-' . explode(' ', $salesName)[0], 94.85, 74.0, '10.5pt') ?>

  </div>
</div>

</body>
</html>
        <?php
        return ob_get_clean();
    }
}
