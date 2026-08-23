<?php
namespace App;

class AppsScriptService
{
    private string $scriptUrl;

    public function __construct()
    {
        Config::load();
        $settings = SettingsManager::get();
        $this->scriptUrl = $settings['apps_script_url'] ?? Config::get('apps_script_url', '');

        if (empty($this->scriptUrl)) {
            throw new \RuntimeException(
                'APPS_SCRIPT_URL belum diatur di Dashboard Admin atau file .env. ' .
                'Silakan atur URL Web App Google Apps Script di Dashboard Admin.'
            );
        }
    }

    /**
     * Kirim data form CBN + file KTP + Signature ke Google Apps Script via JSON POST
     *
     * @param  array  $data      Data form yang sudah divalidasi
     * @param  array  $fileInfo  ['tmp_path'=>..., 'name'=>..., 'mime'=>...]
     * @return array             Response dari Apps Script
     */
    public function send(array $data, array $fileInfo = []): array
    {
        // Siapkan payload data
        $payload = [
            'sales_code'        => $data['sales_code']        ?? 'SEP-001',
            'vendor'            => $data['vendor']            ?? 'PT. SINERGI EMAS PERDANA',
            'so_date'           => $data['so_date']           ?? date('d/m/Y'),
            'tl_code'           => $data['tl_code']           ?? '-',
            'ae_name'           => $data['ae_name']           ?? '-',
            'home_id'           => $data['home_id']           ?? 'PENDING',
            'nama_pelanggan'    => $data['nama_pelanggan']    ?? '',
            'nomor_ktp'         => $data['nomor_ktp']         ?? '',
            'ttl'               => $data['ttl']               ?? '',
            'jenis_kelamin'     => $data['jenis_kelamin']     ?? '',
            'telp_rumah'        => $data['telp_rumah']        ?? '',
            'telp'              => $data['telp']              ?? '',
            'telp2'             => $data['telp2']             ?? '',
            'alamat'            => $data['alamat']            ?? '',
            'rt'                => $data['rt']                ?? '',
            'rw'                => $data['rw']                ?? '',
            'kelurahan'         => $data['kelurahan']         ?? '',
            'kecamatan'         => $data['kecamatan']         ?? '',
            'kode_pos'          => $data['kode_pos']          ?? '',
            'status_kepemilikan'=> $data['status_kepemilikan']?? 'PEMILIK',
            'email_pelanggan'   => $data['email_pelanggan']   ?? '',
            'tikor'             => $data['tikor']             ?? '',
            'service'           => $data['service']           ?? 'Fiber 50',
            'addon_tv'          => is_array($data['addon_tv'] ?? '') ? implode(', ', $data['addon_tv']) : ($data['addon_tv'] ?? ''),
            'addon_device'      => is_array($data['addon_device'] ?? '') ? implode(', ', $data['addon_device']) : ($data['addon_device'] ?? ''),
            'router_qty'        => $data['router_qty']        ?? '1',
            'smartbox_qty'      => $data['smartbox_qty']      ?? '0',
            'username_cbn'      => $data['username_cbn']      ?? '',
            'jadwal_tanggal'    => $data['jadwal_tanggal']    ?? '',
            'jadwal_waktu'      => $data['jadwal_waktu']      ?? '09.00-11.00',
            'catatan'           => $data['catatan']           ?? '',
            'biaya_pasang'      => $data['biaya_pasang']      ?? 'Rp 0',
            'biaya_paket'       => $data['biaya_paket']       ?? 'Rp 169.000',
            'biaya_tambahan'    => $data['biaya_tambahan']    ?? 'Rp 5.000',
            'biaya_ppn'         => $data['biaya_ppn']         ?? 'Rp 19.140',
            'biaya_total'       => $data['biaya_total']       ?? 'Rp 193.140',
            'addon_cbn_package' => $data['addon_cbn_package'] ?? '',
            'signature_data'    => $data['signature_data']    ?? '',
        ];

        // Attach Base64 SPV Signature & Sales Signature
        $spvFile = dirname(__DIR__) . '/public/assets/img/ttd_spv_master.png';
        if (file_exists($spvFile)) {
            $payload['ttd_spv_base64'] = 'data:image/png;base64,' . base64_encode(file_get_contents($spvFile));
        }

        $salesCode = $data['sales_code'] ?? 'SEP-001';
        $salesData = SalesManager::findByCode($salesCode);
        $payload['sales_name'] = $salesData['nama_sales'] ?? 'FIRMAN';
        $salesTtdPath = !empty($salesData['ttd_path']) ? dirname(__DIR__) . '/public/' . $salesData['ttd_path'] : '';
        if (empty($salesTtdPath) || !file_exists($salesTtdPath)) {
            $salesTtdPath = dirname(__DIR__) . '/public/assets/img/ttd_sales_master.png';
        }
        if (file_exists($salesTtdPath)) {
            $payload['ttd_sales_base64'] = 'data:image/png;base64,' . base64_encode(file_get_contents($salesTtdPath));
        }

        // Encode file KTP sebagai base64 jika ada
        if (!empty($fileInfo['tmp_path']) && file_exists($fileInfo['tmp_path'])) {
            $payload['file_data'] = base64_encode(file_get_contents($fileInfo['tmp_path']));
            $payload['file_name'] = $fileInfo['name'] ?? 'foto_ktp.jpg';
            $payload['file_mime'] = $fileInfo['mime'] ?? 'image/jpeg';
        }

        // Konfigurasi dinamis dari Dashboard Admin
        $settings = SettingsManager::get();
        $payload['spreadsheet_id']  = $settings['spreadsheet_id']  ?? '';
        $payload['drive_folder_id'] = $settings['drive_folder_id'] ?? '';
        $payload['notif_email']     = $settings['admin_email']      ?? '';

        $jsonPayload = json_encode($payload);

        // Kirim via Stream Context (Native PHP HTTPS POST + Auto Redirect)
        $opts = [
            'http' => [
                'method'          => 'POST',
                'header'          => "Content-Type: text/plain;charset=UTF-8\r\n" .
                                     "Content-Length: " . strlen($jsonPayload) . "\r\n",
                'content'         => $jsonPayload,
                'follow_location' => 1,
                'max_redirects'   => 5,
                'timeout'         => 60,
                'ignore_errors'   => true,
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ]
        ];

        $context  = stream_context_create($opts);
        $response = @file_get_contents($this->scriptUrl, false, $context);

        if ($response === false) {
            $error = error_get_last();
            throw new \RuntimeException("Gagal terhubung ke Google Apps Script: " . ($error['message'] ?? 'Unknown network error'));
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Response tidak valid dari Apps Script: " . substr(strip_tags($response), 0, 300));
        }

        if (($decoded['status'] ?? '') === 'error') {
            throw new \RuntimeException("Apps Script error: " . ($decoded['message'] ?? 'Unknown error'));
        }

        return $decoded;
    }
}
