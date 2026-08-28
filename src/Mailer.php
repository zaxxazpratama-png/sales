<?php
namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private PHPMailer $mail;

    public function __construct()
    {
        Config::load();

        $this->mail = new PHPMailer(true);

        // SMTP Config
        $this->mail->isSMTP();
        $this->mail->Host       = Config::get('mail_host', 'smtp.gmail.com');
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = Config::get('mail_username');
        $this->mail->Password   = Config::get('mail_password');
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = Config::get('mail_port', 587);
        $this->mail->CharSet    = 'UTF-8';

        // From
        $this->mail->setFrom(
            Config::get('mail_from_email'),
            Config::get('mail_from_name', 'PT. TALENTA INTEGRITAS NASIONAL')
        );

        // To (admin penerima)
        $this->mail->addAddress(
            Config::get('mail_to_email'),
            Config::get('mail_to_name', 'Admin TIN')
        );
    }

    /**
     * Kirim notifikasi email Sales Order baru
     */
    public function sendSalesOrderNotification(array $data, string $driveUrl = '', string $pdfPath = ''): bool
    {
        $settings = SettingsManager::get();
        $vendorName = $data['vendor'] ?? ($settings['company_name'] ?? 'PT. TALENTA INTEGRITAS NASIONAL');
        $data['vendor'] = $vendorName;

        $this->mail->isHTML(true);
        $this->mail->Subject = "[SALES ORDER CBN] " . ($data['sales_code'] ?? 'SEP-001') . " - " . ($data['nama_pelanggan'] ?? 'Pelanggan') . " (" . ($data['service'] ?? 'Fiber') . ")";
        $this->mail->Body    = $this->buildEmailBody($data, $driveUrl);
        $this->mail->AltBody = $this->buildEmailBodyText($data, $driveUrl);

        if ($pdfPath && file_exists($pdfPath)) {
            $this->mail->addAttachment($pdfPath, 'Formulir_CBN_' . ($data['ticket_no'] ?? 'tiket') . '.pdf');
        }

        $this->mail->send();
        return true;
    }

    /**
     * Buat HTML body email dengan format data ketik sesuai revisi
     */
    private function buildEmailBody(array $data, string $driveUrl): string
    {
        $driveLink = $driveUrl
            ? "<a href='{$driveUrl}' target='_blank' style='color:#0088cc;font-weight:bold;'>[Buka File di Google Drive]</a>"
            : '<em>(PDF terlampir pada email ini)</em>';

        $timestamp = date('d/m/Y H:i:s');
        $vendorName = htmlspecialchars($data['vendor'] ?? 'PT. TALENTA INTEGRITAS NASIONAL');
        $soDate     = htmlspecialchars($data['so_date'] ?? date('d/m/Y'));
        $teamLeader = htmlspecialchars($data['tl_code'] ?? $data['team_leader'] ?? '-');
        $aeName     = htmlspecialchars($data['ae_name'] ?? $data['sales_name'] ?? ($data['sales_code'] ?? '-'));
        $namaKtp    = htmlspecialchars($data['nama_pelanggan'] ?? '-');
        $noKtp      = htmlspecialchars($data['nomor_ktp'] ?? '-');
        $ttl        = htmlspecialchars($data['ttl'] ?? '-');
        $alamat     = htmlspecialchars($data['alamat'] ?? '-');
        $kelurahan  = htmlspecialchars($data['kelurahan'] ?? '-');
        $kecamatan  = htmlspecialchars($data['kecamatan'] ?? '-');
        $homeId     = htmlspecialchars($data['home_id'] ?? '-');
        $telp1      = htmlspecialchars($data['telp'] ?? '-');
        $telp2      = htmlspecialchars($data['telp2'] ?? $data['telp_rumah'] ?? '-');
        $usernameCbn= htmlspecialchars($data['username_cbn'] ?? (explode(' ', $data['nama_pelanggan'] ?? '')[0] ?? 'user'));
        $emailPel   = htmlspecialchars($data['email_pelanggan'] ?? '-');
        $paket      = htmlspecialchars($data['service'] ?? '-');
        
        $rawTikor   = trim($data['tikor'] ?? '');
        if ($rawTikor !== '' && $rawTikor !== '-') {
            $mapUrl = 'https://www.google.com/maps?q=' . urlencode($rawTikor);
            $tikorDisplay = "<a href='{$mapUrl}' target='_blank' style='color:#005696;font-weight:bold;text-decoration:underline;'>&#128205; {$rawTikor} (Buka Google Maps)</a>";
            $tikorText = "{$rawTikor} ({$mapUrl})";
        } else {
            $tikorDisplay = '-';
            $tikorText = '-';
        }

        $textFormat = "Vendor    =  {$vendorName}\n" .
                      "SO Date  =  {$soDate}\n" .
                      "Team Leader : {$teamLeader}\n" .
                      "AE Name (nama sales) = {$aeName}\n\n" .
                      "Nama di KTP : {$namaKtp}\n" .
                      "Nomor KTP    : {$noKtp}\n" .
                      "Tempat & Tanggal Lahir : {$ttl}\n" .
                      "Alamat Pemasangan : {$alamat}\n" .
                      "Kel.   : {$kelurahan}\n" .
                      "Kec.   : {$kecamatan}\n" .
                      "Home id : {$homeId}\n" .
                      "Tikor   : {$tikorText}\n" .
                      "Telp 1: {$telp1}\n" .
                      "Telp 2: {$telp2}\n" .
                      "--------------------------------------\n" .
                      "Username   : {$usernameCbn}\n" .
                      "Email           : {$emailPel}\n" .
                      "Paket : {$paket}";

        return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, Helvetica, sans-serif; background: #f4f6f8; margin: 0; padding: 20px; color: #222; }
  .container { max-width: 680px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; }
  .header { background: #005696; color: #ffffff; padding: 24px; text-align: center; }
  .header h1 { margin: 0; font-size: 20px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; }
  .header p { margin: 5px 0 0; opacity: 0.9; font-size: 13px; }
  .body { padding: 24px 28px; }
  .section { margin-bottom: 22px; }
  .section h3 { color: #005696; border-bottom: 2px solid #00a0df; padding-bottom: 5px; margin-bottom: 12px; font-size: 13px; text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: 7px 10px; font-size: 13px; vertical-align: top; }
  td:first-child { color: #555; width: 38%; font-weight: 600; }
  td:last-child { color: #111; }
  tr:nth-child(even) td { background: #f8fafc; }
  .badge { display: inline-block; background: #005696; color: #ffffff; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
  .footer { background: #f8fafc; padding: 18px 24px; text-align: center; color: #777; font-size: 12px; border-top: 1px solid #e2e8f0; }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>PENDAFTARAN LAYANAN CBN - SALES ORDER</h1>
    <p>{$vendorName} &bull; {$timestamp}</p>
  </div>
  <div class="body">

    <div class="section">
      <h3>1. INFORMASI SALES ORDER</h3>
      <table>
        <tr><td>Vendor</td><td>{$vendorName}</td></tr>
        <tr><td>SO Date</td><td>{$soDate}</td></tr>
        <tr><td>Team Leader</td><td>{$teamLeader}</td></tr>
        <tr><td>AE Name (nama sales)</td><td><strong>{$aeName}</strong></td></tr>
        <tr><td>No. Tiket</td><td><strong>{$data['ticket_no']}</strong></td></tr>
      </table>
    </div>

    <div class="section">
      <h3>2. DATA PELANGGAN</h3>
      <table>
        <tr><td>Nama di KTP</td><td><strong>{$namaKtp}</strong></td></tr>
        <tr><td>Nomor KTP</td><td>{$noKtp}</td></tr>
        <tr><td>Tempat &amp; Tanggal Lahir</td><td>{$ttl}</td></tr>
        <tr><td>Alamat Pemasangan</td><td>{$alamat}</td></tr>
        <tr><td>Kel.</td><td>{$kelurahan}</td></tr>
        <tr><td>Kec.</td><td>{$kecamatan}</td></tr>
        <tr><td>Home id</td><td>{$homeId}</td></tr>
        <tr><td>Tikor</td><td>{$tikorDisplay}</td></tr>
        <tr><td>Telp 1</td><td>{$telp1}</td></tr>
        <tr><td>Telp 2</td><td>{$telp2}</td></tr>
      </table>
    </div>

    <div class="section">
      <h3>3. AKUN &amp; PAKET</h3>
      <table>
        <tr><td>Username</td><td>{$usernameCbn}</td></tr>
        <tr><td>Email</td><td>{$emailPel}</td></tr>
        <tr><td>Paket</td><td><span class="badge">{$paket}</span></td></tr>
        <tr><td>Jadwal Pemasangan</td><td>{$data['jadwal_tanggal']} ({$data['jadwal_waktu']})</td></tr>
        <tr><td>Total Biaya</td><td><strong>{$data['biaya_total']}</strong></td></tr>
      </table>
    </div>

    <div class="section">
      <h3>4. LAMPIRAN BERKAS &amp; DOKUMEN</h3>
      <p style="color:#555; font-size:13px;">Dokumen formulir PDF resmi telah dilampirkan pada email ini.</p>
      <p>{$driveLink}</p>
    </div>

  </div>
  <div class="footer">
    Email ini dikirim otomatis oleh sistem Formulir Layanan CBN &bull; {$vendorName}
  </div>
</div>
</body>
</html>
HTML;
    }

    /**
     * Versi plain-text email
     */
    private function buildEmailBodyText(array $data, string $driveUrl): string
    {
        $settings = SettingsManager::get();
        $vendorName = $data['vendor'] ?? ($settings['company_name'] ?? 'PT. TALENTA INTEGRITAS NASIONAL');
        $soDate     = $data['so_date'] ?? date('d/m/Y');
        $teamLeader = $data['tl_code'] ?? $data['team_leader'] ?? '-';
        $aeName     = $data['ae_name'] ?? $data['sales_name'] ?? ($data['sales_code'] ?? '-');
        $namaKtp    = $data['nama_pelanggan'] ?? '-';
        $noKtp      = $data['nomor_ktp'] ?? '-';
        $ttl        = $data['ttl'] ?? '-';
        $alamat     = $data['alamat'] ?? '-';
        $kelurahan  = $data['kelurahan'] ?? '-';
        $kecamatan  = $data['kecamatan'] ?? '-';
        $homeId     = $data['home_id'] ?? '-';
        $rawTikor   = trim($data['tikor'] ?? '');
        $mapUrl     = ($rawTikor !== '' && $rawTikor !== '-') ? "https://www.google.com/maps?q=" . urlencode($rawTikor) : '';
        $tikorText  = $mapUrl ? "{$rawTikor} ({$mapUrl})" : ($rawTikor ?: '-');
        $telp1      = $data['telp'] ?? '-';
        $telp2      = $data['telp2'] ?? $data['telp_rumah'] ?? '-';
        $usernameCbn= $data['username_cbn'] ?? (explode(' ', $data['nama_pelanggan'] ?? '')[0] ?? 'user');
        $emailPel   = $data['email_pelanggan'] ?? '-';
        $paket      = $data['service'] ?? '-';

        return <<<TEXT
Vendor    =  {$vendorName}
SO Date  =  {$soDate}
Team Leader : {$teamLeader}
AE Name (nama sales) = {$aeName}

Nama di KTP : {$namaKtp}
Nomor KTP    : {$noKtp}
Tempat & Tanggal Lahir : {$ttl}
Alamat Pemasangan : {$alamat}
Kel.   : {$kelurahan}
Kec.   : {$kecamatan}
Home id : {$homeId}
Tikor   : {$tikorText}
Telp 1: {$telp1}
Telp 2: {$telp2}
--------------------------------------
Username   : {$usernameCbn}
Email           : {$emailPel}
Paket : {$paket}

(Tiket PDF resmi terlampir pada email ini)
TEXT;
    }
}
