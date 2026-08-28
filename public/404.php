<?php
/**
 * 404 Not Found - Akses Tanpa Link Sales Resmi
 */
require_once dirname(__DIR__) . '/src/autoload.php';

use App\SettingsManager;

http_response_code(404);
$settings = SettingsManager::get();
$callCenter = $settings['call_center'] ?? '1500 780';
$companyName = $settings['company_name'] ?? 'PT. TALENTA INTEGRITAS NASIONAL';
$waHelpdesk = $settings['wa_helpdesk'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Halaman Tidak Ditemukan | Link Sales Tidak Valid</title>
    <link rel="icon" type="image/png" href="<?= $baseUrl ?>/assets/img/logo-tin.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0a1128;
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            text-align: center;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: radial-gradient(ellipse 70% 50% at 50% 30%, rgba(0, 160, 223, 0.15) 0%, transparent 70%);
            pointer-events: none;
        }
        .error-card {
            position: relative;
            background: #111c38;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            padding: 48px 36px;
            max-width: 540px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5);
        }
        .error-logo-img {
            max-width: 140px;
            max-height: 70px;
            object-fit: contain;
            display: block;
            margin: 0 auto 16px;
            background: rgba(255, 255, 255, 0.95);
            padding: 8px 16px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(56, 189, 248, 0.3);
        }
        .cbn-logo {
            font-size: 38px;
            font-weight: 900;
            color: #ffffff;
            font-family: 'Arial Black', sans-serif;
            margin-bottom: 12px;
            letter-spacing: -2px;
        }
        .cbn-logo span { color: #00a0df; }
        .error-badge {
            display: inline-block;
            background: rgba(239, 68, 68, 0.18);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            font-size: 13px;
            font-weight: 800;
            padding: 5px 14px;
            border-radius: 20px;
            margin-bottom: 18px;
            letter-spacing: 0.5px;
        }
        h1 {
            font-size: 22px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 12px;
            line-height: 1.3;
        }
        p {
            font-size: 14px;
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .info-box {
            background: rgba(0, 160, 223, 0.08);
            border: 1px solid rgba(0, 160, 223, 0.25);
            border-radius: 12px;
            padding: 16px;
            font-size: 13px;
            color: #cbd5e1;
            margin-bottom: 28px;
            text-align: left;
        }
        .btn-wa {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #ffffff;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
            transition: all 0.2s;
        }
        .btn-wa:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5);
        }
        .meta-foot {
            font-size: 11.5px;
            color: #64748b;
            margin-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 16px;
        }
    </style>
</head>
<body>

<div class="error-card">
    <img src="<?= $baseUrl ?>/assets/img/logo-tin.png" alt="PT. TALENTA INTEGRITAS NASIONAL" class="error-logo-img">
    <div class="error-badge">404 NOT FOUND</div>
    
    <h1>Link Sales Tidak Ditemukan</h1>
    <p>
        Formulir pendaftaran layanan resmi internet fiber CBN hanya dapat diakses melalui <strong>tautan khusus sales</strong> resmi kami.
    </p>

    <div class="info-box">
        <strong>Pemberitahuan:</strong><br>
        &bull; Pastikan URL yang Anda buka telah mencantumkan kode sales (contoh: <code>/SEP-001</code>).<br>
        &bull; Jika Anda belum memiliki sales perwakilan, hubungi Call Center CBN di <strong><?= htmlspecialchars($callCenter) ?></strong>.
    </div>

    <?php if (!empty($waHelpdesk)): ?>
    <a href="https://wa.me/<?= preg_replace('/\D/', '', $waHelpdesk) ?>?text=Hallo%20min,%20saya%20ingin%20meminta%20link%20pendaftaran%20pengguna%20cbn," target="_blank" class="btn-wa">
        Hubungi Bantuan WhatsApp
    </a>
    <?php endif; ?>

    <div class="meta-foot">
        <?= htmlspecialchars($companyName) ?> &bull; Mitra Resmi Layanan CBN
    </div>
</div>

</body>
</html>
