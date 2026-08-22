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
            Config::get('mail_from_name', 'Sales Order Form')
        );

        // To (admin penerima)
        $this->mail->addAddress(
            Config::get('mail_to_email'),
            Config::get('mail_to_name', 'Admin')
        );
    }

    /**
     * Kirim notifikasi email Sales Order baru
     */
    public function sendSalesOrderNotification(array $data, string $driveUrl = ''): bool
    {
        $this->mail->isHTML(true);
        $this->mail->Subject = "Sales Order CBN - {$data['nama_pelanggan']}";
        $this->mail->Body    = $this->buildEmailBody($data, $driveUrl);
        $this->mail->AltBody = $this->buildEmailBodyText($data, $driveUrl);

        $this->mail->send();
        return true;
    }

    /**
     * Buat HTML body email bersih tanpa emoji korup
     */
    private function buildEmailBody(array $data, string $driveUrl): string
    {
        $driveLink = $driveUrl
            ? "<a href='{$driveUrl}' style='color:#0088cc;font-weight:bold;'>[Buka File di Google Drive]</a>"
            : '-';

        $timestamp = date('d/m/Y H:i:s');

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
    <p>PT. SINERGI EMAS PERDANA &bull; {$timestamp}</p>
  </div>
  <div class="body">

    <div class="section">
      <h3>1. INFORMASI SALES ORDER</h3>
      <table>
        <tr><td>Vendor</td><td>{$data['vendor']}</td></tr>
        <tr><td>Tanggal SO</td><td>{$data['so_date']}</td></tr>
        <tr><td>Kode Sales</td><td>{$data['sales_code']}</td></tr>
      </table>
    </div>

    <div class="section">
      <h3>2. DATA PELANGGAN</h3>
      <table>
        <tr><td>Nama Lengkap</td><td><strong>{$data['nama_pelanggan']}</strong></td></tr>
        <tr><td>Nomor KTP</td><td>{$data['nomor_ktp']}</td></tr>
        <tr><td>Tempat / Tgl Lahir</td><td>{$data['ttl']}</td></tr>
        <tr><td>Jenis Kelamin</td><td>{$data['jenis_kelamin']}</td></tr>
        <tr><td>Email Pelanggan</td><td>{$data['email_pelanggan']}</td></tr>
        <tr><td>No. Telepon / WA</td><td>{$data['telp']}</td></tr>
        <tr><td>Telepon Rumah</td><td>{$data['telp_rumah']}</td></tr>
      </table>
    </div>

    <div class="section">
      <h3>3. ALAMAT PEMASANGAN</h3>
      <table>
        <tr><td>Alamat Lengkap</td><td>{$data['alamat']}</td></tr>
        <tr><td>RT / RW</td><td>RT {$data['rt']} / RW {$data['rw']}</td></tr>
        <tr><td>Kode Pos</td><td>{$data['kode_pos']}</td></tr>
        <tr><td>Status Kepemilikan</td><td>{$data['status_kepemilikan']}</td></tr>
      </table>
    </div>

    <div class="section">
      <h3>4. PAKET LAYANAN & PERANGKAT</h3>
      <table>
        <tr><td>Paket Internet</td><td><span class="badge">{$data['service']}</span></td></tr>
        <tr><td>Add-On TV</td><td>{$data['addon_tv']}</td></tr>
        <tr><td>Perangkat Tambahan</td><td>{$data['addon_device']}</td></tr>
        <tr><td>Username CBN</td><td>{$data['username_cbn']}@cbn.net.id</td></tr>
        <tr><td>Jadwal Pemasangan</td><td>{$data['jadwal_tanggal']} ({$data['jadwal_waktu']})</td></tr>
        <tr><td>Catatan Lokasi</td><td>{$data['catatan']}</td></tr>
        <tr><td>Total Biaya</td><td><strong>{$data['biaya_total']}</strong></td></tr>
      </table>
    </div>

    <div class="section">
      <h3>5. LAMPIRAN FILE DOKUMEN</h3>
      <p style="color:#555; font-size:13px;">Dokumen dan formulir telah diunggah:</p>
      <p>{$driveLink}</p>
    </div>

  </div>
  <div class="footer">
    Email ini dikirim otomatis oleh sistem Formulir Layanan CBN &bull; PT. Sinergi Emas Perdana
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
        return <<<TEXT
=== PENDAFTARAN LAYANAN CBN - PT. SINERGI EMAS PERDANA ===

Sales Code : {$data['sales_code']}
Tanggal SO : {$data['so_date']}
Nama       : {$data['nama_pelanggan']}
Nomor KTP  : {$data['nomor_ktp']}
TTL        : {$data['ttl']}
Gender     : {$data['jenis_kelamin']}
Email      : {$data['email_pelanggan']}
Telepon    : {$data['telp']}
Alamat     : {$data['alamat']}
Paket      : {$data['service']}
Jadwal     : {$data['jadwal_tanggal']} ({$data['jadwal_waktu']})
Total      : {$data['biaya_total']}
File Drive : {$driveUrl}

=== Dikirim otomatis oleh sistem ===
TEXT;
    }
}
