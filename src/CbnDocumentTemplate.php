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
        $kelurahan   = strtoupper(trim($data['kelurahan'] ?? ''));
        $kecamatan   = strtoupper(trim($data['kecamatan'] ?? ''));
        
        // Format Alamat Lengkap: Alamat, Kelurahan, Kecamatan (Presisi 2 Baris Kotak)
        $cleanKel = preg_replace('/^(KELURAHAN|KEL\\.?)\\s*/i', '', $kelurahan);
        $cleanKec = preg_replace('/^(KECAMATAN|KEC\\.?)\\s*/i', '', $kecamatan);

        $alamat1 = $alamat;
        if (strlen($alamat1) > 29) {
            $pos = strrpos(substr($alamat1, 0, 29), ' ');
            if ($pos !== false) {
                $alamat1 = substr($alamat1, 0, $pos);
            } else {
                $alamat1 = substr($alamat1, 0, 29);
            }
        }

        $alamat2 = '';
        if (!empty($cleanKel) && !empty($cleanKec)) {
            $alamat2 = "KEL. {$cleanKel}, KEC. {$cleanKec}";
            if (strlen($alamat2) > 29) {
                $sKel = strlen($cleanKel) > 10 ? substr($cleanKel, 0, 10) : $cleanKel;
                $sKec = strlen($cleanKec) > 9 ? substr($cleanKec, 0, 9) : $cleanKec;
                $alamat2 = "KEL. {$sKel}, KEC. {$sKec}";
                if (strlen($alamat2) > 29) {
                    $alamat2 = substr($alamat2, 0, 29);
                }
            }
        } elseif (!empty($cleanKel)) {
            $alamat2 = "KEL. " . substr($cleanKel, 0, 24);
        } elseif (!empty($cleanKec)) {
            $alamat2 = "KEC. " . substr($cleanKec, 0, 24);
        }
        // Format Alamat Lengkap: Alamat, Kelurahan, Kecamatan (Presisi 2 Baris Kotak)
        $cleanKel = preg_replace('/^(KELURAHAN|KEL\\.?)\\s*/i', '', $kelurahan);
        $cleanKec = preg_replace('/^(KECAMATAN|KEC\\.?)\\s*/i', '', $kecamatan);

        $alamat1 = $alamat;
        if (strlen($alamat1) > 29) {
            $pos = strrpos(substr($alamat1, 0, 29), ' ');
            if ($pos !== false) {
                $alamat1 = substr($alamat1, 0, $pos);
            } else {
                $alamat1 = substr($alamat1, 0, 29);
            }
        }

        $alamat2 = '';
        if (!empty($cleanKel) && !empty($cleanKec)) {
            $alamat2 = "KEL. {$cleanKel}, KEC. {$cleanKec}";
            if (strlen($alamat2) > 29) {
                $sKel = strlen($cleanKel) > 10 ? substr($cleanKel, 0, 10) : $cleanKel;
                $sKec = strlen($cleanKec) > 9 ? substr($cleanKec, 0, 9) : $cleanKec;
                $alamat2 = "KEL. {$sKel}, KEC. {$sKec}";
                if (strlen($alamat2) > 29) {
                    $alamat2 = substr($alamat2, 0, 29);
                }
            }
        } elseif (!empty($cleanKel)) {
            $alamat2 = "KEL. " . substr($cleanKel, 0, 24);
        } elseif (!empty($cleanKec)) {
            $alamat2 = "KEC. " . substr($cleanKec, 0, 24);
        }
        $rt          = trim($data['rt'] ?? '');
        $rw          = trim($data['rw'] ?? '');
        $kodePos     = trim($data['kode_pos'] ?? '');
        $statusRumah = strtoupper(trim($data['status_kepemilikan'] ?? 'PEMILIK'));
        $email       = strtoupper(trim($data['email_pelanggan'] ?? ''));
        $service     = trim($data['service'] ?? 'Fiber 100');
        $routerQty   = trim($data['router_qty'] ?? '1');
        $smartboxQty = trim($data['smartbox_qty'] ?? '0');
        $usernameCbn = trim($data['username_cbn'] ?? '');
        $catatan     = trim($data['catatan'] ?? '');
        $tglPasang   = trim($data['jadwal_tanggal'] ?? '');
        $waktuPasang = trim($data['jadwal_waktu'] ?? '09.00-11.00');
        $tglTtd      = trim($data['so_date'] ?? date('d/m/Y'));
        $signatureImg= trim($data['signature_data'] ?? '');

        // Addon TV
        $addonTv = $data['addon_tv'] ?? [];
        if (!is_array($addonTv)) {
            $addonTv = !empty($addonTv) ? explode(',', $addonTv) : [];
        }
        $hasDensTv = false;
        $hasVisionTv = false;
        foreach ($addonTv as $tv) {
            $tvLower = strtolower(trim($tv));
            if (strpos($tvLower, 'dens') !== false) $hasDensTv = true;
            if (strpos($tvLower, 'vision') !== false) $hasVisionTv = true;
        }

        // Addon CBN Package
        $addonCbnPackage = $data['addon_cbn_package'] ?? [];
        if (is_string($addonCbnPackage)) {
            $decoded = json_decode($addonCbnPackage, true);
            $addonCbnPackage = is_array($decoded) ? $decoded : (!empty($addonCbnPackage) ? [$addonCbnPackage] : []);
        } elseif (!is_array($addonCbnPackage)) {
            $addonCbnPackage = [];
        }

        // Biaya
        $biayaPasang    = trim($data['biaya_pasang'] ?? 'Rp 0');
        $biayaPaket     = trim($data['biaya_paket'] ?? 'Rp 199.000');
        $biayaTambahan  = trim($data['biaya_tambahan'] ?? 'Rp 5.000');
        $biayaPpn       = trim($data['biaya_ppn'] ?? 'Rp 22.440');
        $biayaTotal     = trim($data['biaya_total'] ?? 'Rp 226.440');

        $isPria    = ($gender === 'PRIA' || $gender === 'MALE');
        $isWanita  = ($gender === 'WANITA' || $gender === 'FEMALE');
        $isPemilik = ($statusRumah === 'PEMILIK' || $statusRumah === 'OWNER');
        $isPenyewa = ($statusRumah === 'PENYEWA' || $statusRumah === 'RENTER');

        // Parsing TTL
        $ttlKota = ''; $ttlDay = ''; $ttlMonth = ''; $ttlYear = '';
        if (!empty($ttl)) {
            $ttlParts = explode(',', $ttl);
            $ttlKota = trim($ttlParts[0] ?? '');
            if (!empty($ttlParts[1])) {
                $dParts = preg_split('/[\/\-\s]+/', trim($ttlParts[1]));
                if (count($dParts) >= 3) {
                    $ttlDay = str_pad($dParts[0], 2, '0', STR_PAD_LEFT);
                    $ttlMonth = str_pad($dParts[1], 2, '0', STR_PAD_LEFT);
                    $ttlYear = $dParts[2];
                }
            }
        }

        // Parsing Jadwal Pemasangan (Hari, dd, mm, yyyy)
        $jadwalHari  = '';
        $jadwalDay   = '';
        $jadwalMonth = '';
        $jadwalYear  = '';
        if (!empty($tglPasang)) {
            $timestamp = false;
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tglPasang, $m)) {
                $timestamp   = mktime(0, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
                $jadwalDay   = $m[3];
                $jadwalMonth = $m[2];
                $jadwalYear  = $m[1];
            } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $tglPasang, $m)) {
                $timestamp   = mktime(0, 0, 0, (int)$m[2], (int)$m[1], (int)$m[3]);
                $jadwalDay   = $m[1];
                $jadwalMonth = $m[2];
                $jadwalYear  = $m[3];
            }
            if ($timestamp !== false) {
                $dayNames = [
                    1 => 'SENIN',
                    2 => 'SELASA',
                    3 => 'RABU',
                    4 => 'KAMIS',
                    5 => 'JUMAT',
                    6 => 'SABTU',
                    7 => 'MINGGU'
                ];
                $dayNum = (int)date('N', $timestamp);
                $jadwalHari = $dayNames[$dayNum] ?? '';
            }
        }

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

    <!-- 0. SALES CODE kanan atas (Dikosongkan sesuai revisi 10) -->
    <!-- <?= $box($salesCode, 84.3, 2.87, 1.905, '8pt', 6) ?> -->

    <!-- 1. NAMA PELANGGAN (29 kotak) -->
    <?= $box($nama, 20.88, 11.51, 1.905, '9pt', 29) ?>

    <!-- TEMPAT LAHIR (15 kotak) -->
    <?= $box($ttlKota, 20.88, 14.07, 1.905, '9pt', 15) ?>

    <!-- TANGGAL LAHIR: DD (2), / (1 skip), MM (2), / (1 skip), YYYY (4) -->
    <?= $box($ttlDay,   57.0, 14.07, 1.905, '9pt', 2) ?>
    <?= $box($ttlMonth, 62.7, 14.07, 1.905, '9pt', 2) ?>
    <?= $box($ttlYear,  68.4, 14.07, 1.905, '9pt', 4) ?>

    <!-- NOMOR IDENTITAS KTP (16 kotak) -->
    <?= $box(preg_replace('/[^0-9]/', '', $ktp), 20.88, 16.63, 1.905, '9pt', 16) ?>

    <!-- JENIS KELAMIN -->
    <?php if ($isPria): ?>
      <div class="cbn-fld" style="top:16.5%;left:75.6%;font-size:11pt;font-weight:bold;width:1.9%;text-align:center;">X</div>
    <?php elseif ($isWanita): ?>
      <div class="cbn-fld" style="top:16.5%;left:84.4%;font-size:11pt;font-weight:bold;width:1.9%;text-align:center;">X</div>
    <?php endif; ?>

    <!-- TELEPON RUMAH -->
    <?php if (!empty($telpRumah) && $telpRumah !== $telpSelular): ?>
      <?= $box(preg_replace('/[^0-9]/', '', $telpRumah), 20.88, 19.19, 1.905, '9pt', 12) ?>
    <?php endif; ?>

    <!-- TELEPON SELULAR / WA (Hanya Baris Pertama Sesuai Revisi 3) -->
    <?= $box(preg_replace('/[^0-9]/', '', $telpSelular), 66.8, 19.19, 1.905, '9pt', 12) ?>

    <!-- 2. ALAMAT PEMASANGAN (29 kotak × 2 baris - Sudah termasuk Kel & Kec) -->
    <?= $box($alamat1, 20.88, 26.51, 1.905, '8.5pt', 29) ?>
    <?php if (!empty($alamat2)): ?>
      <?= $box($alamat2, 20.88, 28.49, 1.905, '8.5pt', 29) ?>
    <?php endif; ?>

    <!-- RT (3 kotak), RW (3 kotak), KODE POS (5 kotak) -->
    <?= $box($rt, 55.21, 30.50, 1.905, '8.5pt', 3) ?>
    <?= $box($rw, 64.73, 30.50, 1.905, '8.5pt', 3) ?>
    <?= $box($kodePos, 81.86, 30.50, 1.905, '8.5pt', 5) ?>

    <!-- STATUS KEPEMILIKAN -->
    <?php if ($isPria): ?>
      <div class="cbn-fld" style="top:33.0%;left:20.9%;font-size:12pt;font-weight:bold;">&#10004;</div>
    <?php elseif ($isWanita): ?>
      <div class="cbn-fld" style="top:33.0%;left:34.4%;font-size:12pt;font-weight:bold;">&#10004;</div>
    <?php endif; ?>

    <!-- ALAMAT EMAIL (29 kotak) -->
    <?= $box(strtolower($email), 20.88, 34.96, 1.905, '8.5pt', 29) ?>

    <!-- 3. PILIHAN PAKET LAYANAN -->
    <div class="cbn-fld" style="top:42.25%;left:2.9%;font-size:13pt;font-weight:bold;">&#10004;</div>
    <?= $f($service, 42.25, 11.4, '11.5pt') ?>

    <!-- PERANGKAT TAMBAHAN (ADDITIONAL DEVICES) -->
    <!-- Wireless Router (Hanya checked jika qty >= 1 sesuai revisi 1) -->
    <?php if ((int)$routerQty >= 1): ?>
      <div class="cbn-fld" style="top:44.00%;left:49.85%;font-size:10pt;font-weight:900;color:#000;">&#10004;</div>
      <div class="cbn-fld" style="top:43.85%;left:79.06%;width:3.42%;text-align:center;font-size:9.5pt;font-weight:bold;"><?= htmlspecialchars($routerQty) ?></div>
    <?php endif; ?>

    <!-- Smartbox Android TV (jika dipilih) -->
    <?php if ((int)$smartboxQty >= 1): ?>
      <div class="cbn-fld" style="top:45.60%;left:49.85%;font-size:10pt;font-weight:900;color:#000;">&#10004;</div>
      <div class="cbn-fld" style="top:45.35%;left:79.06%;width:3.42%;text-align:center;font-size:9.5pt;font-weight:bold;"><?= htmlspecialchars($smartboxQty) ?></div>
    <?php endif; ?>

    <!-- CHECKMARK DENS TV+ APPS (kotak kiri form, jika dipilih) -->
    <?php if ($hasDensTv): ?>
      <div class="cbn-fld" style="top:50.40%;left:49.85%;font-size:10pt;font-weight:900;color:#000;">&#10004;</div>
    <?php endif; ?>

    <!-- CHECKMARK VISION+ PREMIUM SPORTS (kotak kiri form, jika dipilih) -->
    <?php if ($hasVisionTv): ?>
      <div class="cbn-fld" style="top:52.00%;left:49.85%;font-size:10pt;font-weight:900;color:#000;">&#10004;</div>
    <?php endif; ?>

    <!-- CBN PACKAGE ADD-ON (kolom kanan - auto-claim sesuai paket internet dipilih) -->
    <?php foreach ($addonCbnPackage as $cbnIdx => $cbnPkg): ?>
      <!-- Checkmark di kotak kanan (checkbox built-in template) -->
      <div class="cbn-fld" style="top:<?= number_format(50.8 + ($cbnIdx * 1.6), 2, '.', '') ?>%;left:73.8%;font-size:10pt;font-weight:900;color:#000;">&#10004;</div>
      <!-- Teks deskripsi paket CBN -->
      <div class="cbn-fld" style="top:<?= number_format(50.8 + ($cbnIdx * 1.6), 2, '.', '') ?>%;left:75.8%;font-size:4.8pt;font-weight:bold;font-family:Arial,sans-serif;white-space:nowrap;width:60mm;">
        <?= htmlspecialchars($cbnPkg) ?>
      </div>
    <?php endforeach; ?>

    <!-- RINCIAN BIAYA — posisi tepat sesuai baris tabel form CBN -->
    <!-- Row 1: Biaya Pemasangan (58.91%) - skip jika Rp0 promo -->
    <?php if (!empty($biayaPasang) && $biayaPasang !== 'Rp0' && $biayaPasang !== 'Rp 0' && $biayaPasang !== 'Rp.0' && $biayaPasang !== 'Rp. 0'): ?>
    <?= $f($biayaPasang,    58.91,  70.0, '8.5pt') ?>
    <?php endif; ?>
    <!-- Row 2: Biaya Paket - monthly charges (60.08%) -->
    <?= $f($biayaPaket,    60.08, 70.0, '8.5pt') ?>
    <!-- Row 3: Biaya Tambahan - additional charges (61.24%) -->
    <?= $f($biayaTambahan, 61.24,  70.0, '8.5pt') ?>
    <!-- Row 8: PPN 11% (67.01%) -->
    <?= $f($biayaPpn,      67.01,  70.0, '8.5pt') ?>
    <!-- Row 9: TOTAL (68.41%) -->
    <?= $f($biayaTotal,    68.41,  70.0, '10pt', 'font-weight:900;') ?>

    <!-- JADWAL PEMASANGAN (Dikosongkan sesuai revisi 4) -->
    <!-- Hari (8 kotak) -->
    <!-- <?= $box($jadwalHari, 56.4, 80.8, 1.86, '7.5pt', 8) ?> -->
    <!-- Tanggal dd (2 kotak) -->
    <!-- <?= $box($jadwalDay, 79.1, 80.8, 1.86, '7.5pt', 2) ?> -->
    <!-- Bulan mm (2 kotak) -->
    <!-- <?= $box($jadwalMonth, 84.7, 80.8, 1.86, '7.5pt', 2) ?> -->
    <!-- Tahun yyyy (4 kotak) -->
    <!-- <?= $box($jadwalYear, 90.3, 80.8, 1.86, '7.5pt', 4) ?> -->

    <!-- Waktu Pemasangan Checkboxes (84.7%) -->

    <!-- 4. USERNAME (11 kotak) -->
    <?= $box(strtolower($usernameCbn), 2.35, 83.91, 1.905, '9pt', 11) ?>

    <!-- 5. NOTES (Ganti teks promo revisi 5) -->
    <?= $f(!empty($catatan) ? $catatan : 'REGULER PROMO JULY 2026 - NAB', 88.8, 51.5, '9.5pt') ?>

    <!-- TANGGAL SURAT -->
    <?= $f($tglTtd, 92.85, 9.5, '10.5pt') ?>

    <!-- TANDA TANGAN PELANGGAN -->
    <?php if (!empty($signatureImg)): ?>
      <img src="<?= $signatureImg ?>" style="position:absolute;top:87.0%;left:4.5%;max-height:55px;max-width:140px;z-index:3;" alt="TTD Pelanggan">
    <?php endif; ?>

    <?php
      // Load Base64 TTD SPV
      $spvSigBase64 = '';
      if (!empty($data['ttd_spv_base64'])) {
          $spvSigBase64 = $data['ttd_spv_base64'];
      } else {
          $settings = \App\SettingsManager::get();
          $spvRel = $settings['ttd_spv_path'] ?? 'assets/img/ttd_spv_master.png';
          $spvFile = dirname(__DIR__) . '/public/' . ltrim($spvRel, '/');
          if (!file_exists($spvFile)) {
              $spvFile = dirname(__DIR__) . '/public/assets/img/ttd_spv_master.png';
          }
          if (file_exists($spvFile)) {
              $spvSigBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($spvFile));
          }
      }

      // Load Base64 TTD Sales (Khusus untuk masing-masing sales code)
      $salesSigBase64 = '';
      if (!empty($data['ttd_sales_base64'])) {
          $salesSigBase64 = $data['ttd_sales_base64'];
      } else {
          $salesData = \App\SalesManager::findByCode($salesCode);
          $salesRel = $salesData['ttd_path'] ?? '';
          $salesFile = '';
          if (!empty($salesRel)) {
              $salesFile = dirname(__DIR__) . '/public/' . ltrim($salesRel, '/');
          }
          if (empty($salesFile) || !file_exists($salesFile)) {
              $cleanCode = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $salesCode));
              $candidate = dirname(__DIR__) . '/public/assets/img/ttd_sales_' . $cleanCode . '.png';
              if (file_exists($candidate)) {
                  $salesFile = $candidate;
              } else {
                  $salesFile = dirname(__DIR__) . '/public/assets/img/ttd_sales_master.png';
              }
          }
          if (file_exists($salesFile)) {
              $salesSigBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($salesFile));
          }
      }
    ?>

    <!-- KOLOM 3: TANDA TANGAN SALES (TEPAT DI ATAS NAMA SALES) -->
    <?php if (!empty($salesSigBase64)): ?>
      <img src="<?= $salesSigBase64 ?>" style="position:absolute;top:89.5%;left:43.0%;max-height:55px;max-width:130px;z-index:5;" alt="TTD Sales">
    <?php endif; ?>
    <?= $f($salesName ?: 'FIRMAN', 94.20, 38.0, '10.5pt', 'width:22.5%;text-align:center;font-weight:bold;') ?>

    <!-- KOLOM 4: TANDA TANGAN SPV (TEPAT DI ATAS NAMA TIN006-SUHARTA) -->
    <?php if (!empty($spvSigBase64)): ?>
      <img src="<?= $spvSigBase64 ?>" style="position:absolute;top:88.8%;left:78.0%;max-height:60px;max-width:130px;z-index:5;" alt="TTD SPV">
    <?php endif; ?>
    <?= $f("TIN006-SUHARTA", 94.20, 73.5, '10.5pt', 'width:22.5%;text-align:center;font-weight:bold;') ?>

  </div>
</div>

</body>
</html>
        <?php
        return ob_get_clean();
    }
}
