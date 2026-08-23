<?php
namespace App;

/**
 * CbnDocumentTemplate - Template HTML Formulir Aplikasi Layanan CBN
 * Menggunakan background JPG asli dari blanko resmi CBN (asli_template_hd.png)
 * Semua teks dan tanda tangan ditempatkan dengan koordinat presisi pixel-perfect.
 */
class CbnDocumentTemplate
{
    /**
     * Render Formulir CBN sebagai string HTML lengkap (A4)
     *
     * @param  array  $data      Data form yang sudah divalidasi
     * @param  string $salesCode Kode sales (misal: SEP-001)
     * @param  string $salesName Nama sales (misal: FIRMAN)
     * @return string            HTML document siap render ke browser/mPDF
     */
    public static function render(array $data, string $salesCode = 'SEP-001', string $salesName = 'FIRMAN'): string
    {
        $settings = class_exists('\App\SettingsManager') ? \App\SettingsManager::get() : [];

        $nama        = strtoupper(trim($data['nama_pelanggan'] ?? ''));
        $ktp         = trim($data['nomor_ktp'] ?? '');
        $ttl         = trim($data['ttl'] ?? '');
        $gender      = strtoupper(trim($data['jenis_kelamin'] ?? 'PRIA'));
        $telpSelular = trim($data['telp'] ?? '');
        $telpRumah   = trim($data['telp_rumah'] ?? '');
        $email       = trim($data['email_pelanggan'] ?? '');
        
        $rt          = trim($data['rt'] ?? '');
        $rw          = trim($data['rw'] ?? '');
        $kelurahan   = trim($data['kelurahan'] ?? '');
        $kecamatan   = trim($data['kecamatan'] ?? '');
        $kabupaten   = trim($data['kabupaten'] ?? $data['kota'] ?? '');
        $kodePos     = trim($data['kode_pos'] ?? '');
        $rawAlamat   = trim($data['alamat'] ?? '');

        // Auto-extract RT/RW from raw alamat if present
        if (preg_match('/\bRT[\s.:]*([0-9]{1,3})\b/i', $rawAlamat, $rtM)) {
            if (empty($rt)) $rt = $rtM[1];
            $rawAlamat = preg_replace('/\bRT[\s.:]*[0-9]{1,3}\b/i', '', $rawAlamat);
        }
        if (preg_match('/\bRW[\s.:]*([0-9]{1,3})\b/i', $rawAlamat, $rwM)) {
            if (empty($rw)) $rw = $rwM[1];
            $rawAlamat = preg_replace('/\bRW[\s.:]*[0-9]{1,3}\b/i', '', $rawAlamat);
        }
        $rawAlamat = trim(preg_replace('/[\/,\s]+$/', '', preg_replace('/\s+/', ' ', $rawAlamat)));
        if (!empty($rt) && ctype_digit($rt)) $rt = str_pad($rt, 3, '0', STR_PAD_LEFT);
        if (!empty($rw) && ctype_digit($rw)) $rw = str_pad($rw, 3, '0', STR_PAD_LEFT);

        // Smart distribution across 3 address lines (29 boxes, 29 boxes, 16 boxes)
        $addrParts = [];
        if (!empty($rawAlamat)) $addrParts[] = $rawAlamat;
        if (!empty($kelurahan)) $addrParts[] = (stripos($kelurahan, 'kel') === false ? "Kel. $kelurahan" : $kelurahan);
        if (!empty($kecamatan)) $addrParts[] = (stripos($kecamatan, 'kec') === false ? "Kec. $kecamatan" : $kecamatan);
        if (!empty($kabupaten)) $addrParts[] = $kabupaten;

        $fullAddrStr = implode(', ', $addrParts);
        $words = preg_split('/\s+/', trim($fullAddrStr));
        
        $alamat1 = '';
        $alamat2 = '';
        $alamat3 = '';

        // Line 1: max 29 chars
        while (!empty($words)) {
            $w = $words[0];
            $test = $alamat1 === '' ? $w : "$alamat1 $w";
            if (mb_strlen($test) <= 29) {
                $alamat1 = $test;
                array_shift($words);
            } else {
                if ($alamat1 === '') {
                    $alamat1 = mb_substr($w, 0, 29);
                    $words[0] = mb_substr($w, 29);
                }
                break;
            }
        }

        // Line 2: max 29 chars
        while (!empty($words)) {
            $w = $words[0];
            $test = $alamat2 === '' ? $w : "$alamat2 $w";
            if (mb_strlen($test) <= 29) {
                $alamat2 = $test;
                array_shift($words);
            } else {
                if ($alamat2 === '') {
                    $alamat2 = mb_substr($w, 0, 29);
                    $words[0] = mb_substr($w, 29);
                }
                break;
            }
        }

        // Line 3: max 16 chars (16 empty boxes on the 3rd row)
        while (!empty($words)) {
            $w = $words[0];
            $test = $alamat3 === '' ? $w : "$alamat3 $w";
            if (mb_strlen($test) <= 16) {
                $alamat3 = $test;
                array_shift($words);
            } else {
                if ($alamat3 === '') {
                    $alamat3 = mb_substr($w, 0, 16);
                }
                break;
            }
        }

        $kepemilikan = strtoupper(trim($data['status_kepemilikan'] ?? 'PEMILIK'));
        $service     = trim($data['service'] ?? 'Fiber 50');

        $addonTv = $data['addon_tv'] ?? [];
        if (is_string($addonTv)) {
            $addonTv = array_filter(array_map('trim', explode(',', $addonTv)));
        }
        $hasDensTv   = false;
        $hasVisionTv = false;
        foreach ($addonTv as $tvItem) {
            $tvLower = strtolower($tvItem);
            if (strpos($tvLower, 'dens') !== false) $hasDensTv = true;
            if (strpos($tvLower, 'vision') !== false) $hasVisionTv = true;
        }

        $addonDevice  = $data['addon_device'] ?? [];
        $routerQty    = trim($data['router_qty'] ?? '0');
        $smartboxQty  = trim($data['smartbox_qty'] ?? '0');

        $addonCbnPackage = $data['addon_cbn_package'] ?? [];
        if (is_string($addonCbnPackage)) {
            $parsed = json_decode($addonCbnPackage, true);
            $addonCbnPackage = is_array($parsed) ? $parsed : (!empty($addonCbnPackage) ? [$addonCbnPackage] : []);
        }
        if (empty($addonCbnPackage)) {
            $sLow = strtolower($service);
            if (strpos($sLow, '20') !== false || strpos($sLow, '50') !== false) {
                $addonCbnPackage = ['CBN Fiber July 2026 Package 1 (15 & 20 Mbps) [1]'];
            } elseif (strpos($sLow, '100') !== false || strpos($sLow, '200') !== false || strpos($sLow, '1gbps') !== false) {
                $addonCbnPackage = [
                    'CBN Fiber July 2026 Package 2 (100, 150 & 200 Mbps) [1]',
                    'Trend Micro Maximum Security 1 Months - 1 Device (Free) [1]',
                    'Free Biaya Pemasangan'
                ];
            }
        }

        $usernameCbn = strtolower(trim($data['username_cbn'] ?? ''));
        $defaultNotes= $data['default_notes'] ?? ($settings['default_notes'] ?? 'REGULER PROMO JULY 2026 - NAB');
        $catatan     = !empty($data['catatan']) ? trim($data['catatan']) : $defaultNotes;
        $tglTtd      = trim($data['so_date'] ?? date('d/m/Y'));
        $signatureImg= $data['signature_data'] ?? $data['signature'] ?? '';

        $fmtRp = function($val, $def = '') {
            if (empty($val) && $val !== '0' && $val !== 0) return $def;
            $s = trim((string)$val);
            if (empty($s)) return $def;
            $num = (int)preg_replace('/\D/', '', $s);
            if ($num === 0) return 'Rp0';
            return 'Rp' . number_format($num, 0, ',', '.');
        };

        $biayaPasang   = $fmtRp($data['biaya_pasang'] ?? '', 'Rp0');
        $biayaPaket    = $fmtRp($data['biaya_paket'] ?? '', 'Rp199.000');
        $biayaTambahan = $fmtRp($data['biaya_tambahan'] ?? '', 'Rp5.000');
        $biayaPpn      = $fmtRp($data['biaya_ppn'] ?? '', 'Rp22.440');
        $biayaTotal    = $fmtRp($data['biaya_total'] ?? '', 'Rp226.440');

        $isPria    = ($gender === 'PRIA' || $gender === 'MALE');
        $isWanita  = ($gender === 'WANITA' || $gender === 'FEMALE');
        $isPemilik = ($kepemilikan === 'PEMILIK' || $kepemilikan === 'OWNER');
        $isPenyewa = ($kepemilikan === 'PENYEWA' || $kepemilikan === 'RENTER');

        $ttlKota = ''; $ttlDay = ''; $ttlMonth = ''; $ttlYear = '';
        if (!empty($ttl)) {
            $ttlParts = explode(',', $ttl);
            $ttlKota = trim($ttlParts[0] ?? '');
            if (!empty($ttlParts[1])) {
                $dParts = preg_split('/[\/\-\s]+/', trim($ttlParts[1]));
                if (count($dParts) >= 3) {
                    $ttlDay   = str_pad($dParts[0], 2, '0', STR_PAD_LEFT);
                    $ttlMonth = str_pad($dParts[1], 2, '0', STR_PAD_LEFT);
                    $ttlYear  = $dParts[2];
                }
            }
        }

        $box = function($text, $leftStart, $top, $charWidth, $fontSize, $maxChars) {
            $text = (string)$text;
            $len = min(mb_strlen($text), $maxChars);
            $html = '';
            for ($i = 0; $i < $len; $i++) {
                $ch = mb_substr($text, $i, 1);
                $l = $leftStart + ($i * $charWidth);
                $html .= "<div class='cbn-fld' style='top:{$top}%;left:{$l}%;font-size:{$fontSize};font-weight:bold;width:{$charWidth}%;text-align:center;'>{$ch}</div>";
            }
            return $html;
        };

        $f = function($text, $top, $left, $fontSize, $extra = '') {
            $text = htmlspecialchars((string)$text);
            return "<div class='cbn-fld' style='top:{$top}%;left:{$left}%;font-size:{$fontSize};{$extra}'>{$text}</div>";
        };

        $bgPath = dirname(__DIR__) . '/public/assets/img/asli_bg.jpg';
        $bgBase64 = '';
        if (file_exists($bgPath)) {
            $bgBase64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($bgPath));
        } elseif (file_exists(__DIR__ . '/../public/assets/img/asli_bg.jpg')) {
            $bgBase64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents(__DIR__ . '/../public/assets/img/asli_bg.jpg'));
        }

        $spvSigBase64 = $data['ttd_spv_base64'] ?? '';
        if (empty($spvSigBase64)) {
            $spvPath = dirname(__DIR__) . '/public/assets/img/ttd_spv_master.png';
            if (file_exists($spvPath)) {
                $spvSigBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($spvPath));
            }
        }

        $salesSigBase64 = $data['ttd_sales_base64'] ?? '';
        if (empty($salesSigBase64)) {
            $salesPath = dirname(__DIR__) . '/public/assets/img/ttd_sales_master.png';
            if (file_exists($salesPath)) {
                $salesSigBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($salesPath));
            }
        }

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  @page { size: 210mm 297mm; margin: 0; }
  body { width: 210mm; height: 297mm; margin: 0; padding: 0; background: #fff; font-family: Arial, Helvetica, sans-serif; }
  .cbn-page { position: relative; width: 210mm; height: 297mm; overflow: hidden; }
  .cbn-bg { position: absolute; top:0; left:0; width:210mm; height:297mm; z-index:1; }
  .cbn-overlay { position: absolute; top:0; left:0; width:210mm; height:297mm; z-index:2; }
  .cbn-fld { position: absolute; font-family: Arial, Helvetica, sans-serif; font-weight: bold; color: #000; line-height: 1; }
</style>
</head>
<body>
<div class="cbn-page">
  <?php if (!empty($bgBase64)): ?>
    <img class="cbn-bg" src="<?= $bgBase64 ?>" alt="CBN Form Background">
  <?php endif; ?>
  
  <div class="cbn-overlay">
    <!-- 1. DATA PELANGGAN -->
    <?= $box($nama, 20.88, 11.51, 1.905, '9pt', 29) ?>
    <?= $box($ttlKota, 20.88, 14.07, 1.905, '9pt', 15) ?>
    <?= $box($ttlDay, 57.0, 14.07, 1.905, '9pt', 2) ?>
    <?= $box($ttlMonth, 62.7, 14.07, 1.905, '9pt', 2) ?>
    <?= $box($ttlYear, 68.4, 14.07, 1.905, '9pt', 4) ?>
    <?= $box($ktp, 20.88, 16.63, 1.905, '9pt', 16) ?>

    <?php if ($isPria): ?>
      <div class="cbn-fld" style="top:16.5%;left:75.6%;font-size:11pt;font-weight:bold;width:1.9%;text-align:center;">X</div>
    <?php elseif ($isWanita): ?>
      <div class="cbn-fld" style="top:16.5%;left:84.4%;font-size:11pt;font-weight:bold;width:1.9%;text-align:center;">X</div>
    <?php endif; ?>

    <?php if (!empty($telpRumah) && $telpRumah !== $telpSelular): ?>
      <?= $box($telpRumah, 20.88, 19.19, 1.905, '9pt', 12) ?>
    <?php endif; ?>
    <?= $box($telpSelular, 66.8, 19.19, 1.905, '9pt', 12) ?>

    <!-- 2. ALAMAT PEMASANGAN (BARIS 1: 29 KOTAK, BARIS 2: 29 KOTAK, BARIS 3: 16 KOTAK) -->
    <?= $box($alamat1, 20.88, 26.51, 1.905, '8.5pt', 29) ?>
    <?php if (!empty($alamat2)): ?>
      <?= $box($alamat2, 20.88, 28.49, 1.905, '8.5pt', 29) ?>
    <?php endif; ?>
    <?php if (!empty($alamat3)): ?>
      <?= $box($alamat3, 20.88, 30.50, 1.905, '8.5pt', 16) ?>
    <?php endif; ?>

    <!-- RT, RW, KODE POS -->
    <?= $box($rt, 55.21, 30.50, 1.905, '8.5pt', 3) ?>
    <?= $box($rw, 64.73, 30.50, 1.905, '8.5pt', 3) ?>
    <?= $box($kodePos, 81.86, 30.50, 1.905, '8.5pt', 5) ?>

    <!-- STATUS KEPEMILIKAN -->
    <?php if ($isPemilik): ?>
      <div class="cbn-fld" style="top:33.0%;left:20.9%;font-size:12pt;font-weight:bold;">&#10004;</div>
    <?php elseif ($isPenyewa): ?>
      <div class="cbn-fld" style="top:33.0%;left:34.4%;font-size:12pt;font-weight:bold;">&#10004;</div>
    <?php endif; ?>

    <!-- ALAMAT EMAIL -->
    <?= $box(strtolower($email), 20.88, 34.96, 1.905, '8.5pt', 29) ?>

    <!-- 3. PILIHAN PAKET LAYANAN (KOLOM KIRI SAJA) -->
    <div class="cbn-fld" style="top:42.25%;left:2.9%;font-size:13pt;font-weight:bold;">&#10004;</div>
    <?= $f($service, 42.25, 11.4, '11.5pt') ?>

    <!-- PILIHAN LAYANAN TAMBAHAN (KOLOM KANAN) -->
    <!-- PERANGKAT TAMBAHAN -->
    <?php if ((int)$routerQty >= 1): ?>
      <div class="cbn-fld" style="top:43.85%;left:49.85%;font-size:10pt;font-weight:900;color:#000;">&#10004;</div>
      <div class="cbn-fld" style="top:43.85%;left:79.06%;width:3.42%;text-align:center;font-size:9.5pt;font-weight:bold;"><?= htmlspecialchars($routerQty) ?></div>
    <?php endif; ?>

    <?php if ((int)$smartboxQty >= 1): ?>
      <div class="cbn-fld" style="top:46.00%;left:49.85%;font-size:10pt;font-weight:900;color:#000;">&#10004;</div>
      <div class="cbn-fld" style="top:45.95%;left:79.06%;width:3.42%;text-align:center;font-size:9.5pt;font-weight:bold;"><?= htmlspecialchars($smartboxQty) ?></div>
    <?php endif; ?>

    <!-- CHECKMARK DENS TV+ APPS (KOLOM KANAN - SUBKOLOM KIRI) -->
    <?php if ($hasDensTv): ?>
      <div class="cbn-fld" style="top:50.15%;left:49.85%;font-size:10.5pt;font-weight:900;color:#000;">&#10004;</div>
    <?php endif; ?>

    <!-- CHECKMARK VISION+ PREMIUM SPORTS (KOLOM KANAN - SUBKOLOM KIRI) -->
    <?php if ($hasVisionTv): ?>
      <div class="cbn-fld" style="top:52.15%;left:49.85%;font-size:10.5pt;font-weight:900;color:#000;">&#10004;</div>
    <?php endif; ?>

    <!-- CBN PACKAGE ADD-ON PROMO (KOLOM KANAN - SESUAI KOTAK ASLI) -->
    <?php 
    $promoBoxTops = [50.10, 52.35, 54.50];
    $promoTextTops = [49.50, 51.80, 54.00];
    foreach ($addonCbnPackage as $cbnIdx => $cbnPkg): 
      if ($cbnIdx > 2) break;
      $boxTop = $promoBoxTops[$cbnIdx] ?? (50.10 + ($cbnIdx * 2.25));
      $textTop = $promoTextTops[$cbnIdx] ?? ($boxTop - 0.50);
    ?>
      <div class="cbn-fld" style="top:<?= number_format($boxTop, 2, '.', '') ?>%;left:74.30%;font-size:9pt;font-weight:900;color:#000;">&#10004;</div>
      <div class="cbn-fld" style="top:<?= number_format($textTop, 2, '.', '') ?>%;left:76.80%;width:21.8%;font-size:5.2pt;font-weight:bold;font-family:Arial,sans-serif;line-height:1.1;overflow:hidden;word-break:break-word;">
        <?= htmlspecialchars($cbnPkg) ?>
      </div>
    <?php endforeach; ?>

    <!-- RINCIAN BIAYA -->
    <?php if (!empty($biayaPasang) && $biayaPasang !== 'Rp0' && $biayaPasang !== 'Rp 0'): ?>
      <?= $f($biayaPasang, 58.91, 70.0, '8.5pt') ?>
    <?php endif; ?>
    <?= $f($biayaPaket, 60.08, 70.0, '8.5pt') ?>
    <?= $f($biayaTambahan, 61.24, 70.0, '8.5pt') ?>
    <?= $f($biayaPpn, 67.01, 70.0, '8.5pt') ?>
    <?= $f($biayaTotal, 68.41, 70.0, '10pt', 'font-weight:900;') ?>

    <!-- 4. USERNAME -->
    <?= $box(strtolower($usernameCbn), 2.35, 83.91, 1.905, '9pt', 11) ?>

    <!-- 5. NOTES (ADMIN CONFIGURABLE) -->
    <?= $f($catatan, 88.80, 51.50, '9.5pt') ?>

    <!-- TANGGAL SURAT -->
    <?= $f($tglTtd, 92.85, 9.50, '10.5pt') ?>

    <!-- TANDA TANGAN PELANGGAN -->
    <?php if (!empty($signatureImg)): 
      $sigSrc = (strpos($signatureImg, 'data:') === 0) ? $signatureImg : ('data:image/png;base64,' . $signatureImg);
    ?>
      <img src="<?= $sigSrc ?>" style="position:absolute;top:89.2%;left:6.0%;max-height:52px;max-width:135px;z-index:10;" alt="TTD Pelanggan">
    <?php endif; ?>

    <!-- TANDA TANGAN SALES -->
    <?php if (!empty($salesSigBase64)): ?>
      <img src="<?= $salesSigBase64 ?>" style="position:absolute;top:89.5%;left:43.0%;max-height:55px;max-width:130px;z-index:5;" alt="TTD Sales">
    <?php endif; ?>
    <?= $f($salesName, 94.20, 38.0, '10.5pt', 'width:22.5%;text-align:center;font-weight:bold;') ?>

    <!-- TANDA TANGAN SPV -->
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