<?php
namespace App;

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;

class GoogleSheetService
{
    private Sheets $service;
    private string $spreadsheetId;
    private string $sheetName;

    public function __construct()
    {
        Config::load();

        $credentialsPath  = Config::get('google_credentials');
        $this->spreadsheetId = Config::get('google_spreadsheet_id');
        $this->sheetName     = Config::get('google_sheet_name', 'Sheet1');

        if (!file_exists($credentialsPath)) {
            throw new \RuntimeException("Google credentials file tidak ditemukan: {$credentialsPath}");
        }

        $client = new Client();
        $client->setApplicationName('Sales Order Form - PT. SEP');
        $client->setScopes([Sheets::SPREADSHEETS]);
        $client->setAuthConfig($credentialsPath);

        $this->service = new Sheets($client);
    }

    /**
     * Tambahkan baris data ke Google Sheet
     */
    public function appendRow(array $data, string $driveFileUrl = ''): bool
    {
        $row = [
            date('d/m/Y H:i:s'),              // Timestamp
            $data['vendor']           ?? '',
            $data['so_date']          ?? '',
            $data['tl_code']          ?? '',
            $data['ae_name']          ?? '',
            $data['home_id']          ?? '',
            $data['nama_pelanggan']   ?? '',
            $data['nomor_ktp']        ?? '',
            $data['ttl']              ?? '',
            $data['jenis_kelamin']    ?? '',
            $data['alamat']           ?? '',
            $data['kelurahan']        ?? '',
            $data['kecamatan']        ?? '',
            $data['kode_pos']         ?? '',
            $data['tikor']            ?? '',
            $data['telp']             ?? '',
            $data['telp2']            ?? '',
            $data['username']         ?? '',
            $data['service']          ?? '',
            $data['email_pelanggan']  ?? '',
            $data['catatan']          ?? '',
            $driveFileUrl,                     // Link file di Google Drive
        ];

        $body = new ValueRange(['values' => [$row]]);

        $params = ['valueInputOption' => 'USER_ENTERED'];
        $range  = "{$this->sheetName}!A1";

        $this->service->spreadsheets_values->append(
            $this->spreadsheetId,
            $range,
            $body,
            $params
        );

        return true;
    }

    /**
     * Pastikan header row sudah ada di baris pertama
     */
    public function ensureHeader(): void
    {
        $range    = "{$this->sheetName}!A1:V1";
        $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range);
        $values   = $response->getValues();

        if (empty($values)) {
            $headers = [
                'Timestamp', 'Vendor', 'SO Date', 'TL Code/Nama', 'AE Name',
                'Home ID', 'Nama Pelanggan', 'Nomor KTP', 'TTL', 'Jenis Kelamin',
                'Alamat Pemasangan', 'Kelurahan', 'Kecamatan', 'Kode Pos', 'Tikor',
                'Telepon', 'Telepon 2', 'Username', 'Service', 'Email Pelanggan',
                'Catatan', 'File Drive URL'
            ];

            $body = new ValueRange(['values' => [$headers]]);
            $this->service->spreadsheets_values->update(
                $this->spreadsheetId,
                $range,
                $body,
                ['valueInputOption' => 'RAW']
            );
        }
    }
}
