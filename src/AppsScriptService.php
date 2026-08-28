<?php
namespace App;

/**
 * AppsScriptService
 * Mengirim data form pendaftaran ke Google Apps Script via HTTP POST (cURL / Stream)
 * Mengembalikan array response dari Apps Script (URL PDF Google Drive, no baris, dsb).
 */
class AppsScriptService
{
    private string $scriptUrl;

    public function __construct(string $scriptUrl = '')
    {
        Config::load();
        $settings = SettingsManager::get();

        // 1. Cek parameter langsung -> 2. Cek settings.json (dashboard) -> 3. Cek .env -> 4. Fallback URL Default
        $this->scriptUrl = $scriptUrl
            ?: ($settings['apps_script_url'] ?? '')
            ?: Config::get('apps_script_url', '')
            ?: 'https://script.google.com/macros/s/AKfycbwIRsM7AJx9q7CdJle7T6LAeTQnllIK8PIBwNQB_LwO42pZrhBgxUTTj12mLGJVHmog/exec';
    }

    /**
     * Kirim data pendaftaran dan file KTP (base64) ke Apps Script
     */
    public function send(array $data, array $fileInfo = []): array
    {
        $settings = SettingsManager::get();

        // Siapkan payload data
        $payload = [
            'sales_code'        => $data['sales_code']        ?? 'SEP-001',
            'ticket_no'         => $data['ticket_no']         ?? '',
            'vendor'            => $data['vendor']            ?? ($settings['company_name'] ?? 'PT. TALENTA INTEGRITAS NASIONAL'),
            'so_date'           => $data['so_date']           ?? date('d/m/Y'),
            'tl_code'           => $data['tl_code']           ?? '-',
            'team_leader'       => $data['team_leader']       ?? ($data['tl_code'] ?? '-'),
            'ae_name'           => $data['ae_name']           ?? ($data['sales_name'] ?? ($data['sales_code'] ?? '-')),
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
            'router_qty'        => $data['router_qty']        ?? '0',
            'smartbox_qty'      => $data['smartbox_qty']      ?? '0',
            'smartbox_v3_qty'   => $data['smartbox_v3_qty']   ?? '0',
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
            'default_notes'     => $settings['default_notes'] ?? 'REGULER PROMO JULY 2026 - NAB',
        ];

        // Attach Base64 SPV Signature & Sales Signature
        $spvFile = dirname(__DIR__) . '/public/assets/img/ttd_spv_master.png';
        if (file_exists($spvFile)) {
            $payload['ttd_spv_base64'] = 'data:image/png;base64,' . base64_encode(file_get_contents($spvFile));
        }

        $salesCode = $data['sales_code'] ?? 'SEP-001';
        $salesData = SalesManager::findByCode($salesCode);
        $payload['sales_name'] = $salesData['nama_sales'] ?? 'FIRMAN';

        $tlCode = !empty($data['tl_code']) && $data['tl_code'] !== '-' ? $data['tl_code'] : ($salesData['tl_code'] ?? 'TIN006-SUHARTA');
        $tlAccount = \App\AuthManager::getTlByCode($tlCode);
        if (!$tlAccount) {
            $tlAccount = \App\AuthManager::getUserByUsername($tlCode);
        }
        $tlUsername = $tlAccount['username'] ?? $tlCode;
        $tlAdminEmail = !empty($tlAccount['admin_email']) ? trim($tlAccount['admin_email']) : '';

        $payload['team_leader'] = $tlUsername;
        $payload['tl_code']     = $tlCode;

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
        $masterEmail = trim((string)($settings['master_email'] ?? 'pujapangestu02@gmail.com'));
        $adminEmail  = $tlAdminEmail ?: trim((string)($settings['admin_email'] ?? '1seopageone@gmail.com'));
        $customerEmailEnabled = isset($salesData['email_customer_enabled']) ? (bool)$salesData['email_customer_enabled'] : true;

        $payload['spreadsheet_id']            = $settings['spreadsheet_id']  ?? '';
        $payload['drive_folder_id']           = $settings['drive_folder_id'] ?? '';
        $payload['master_email']              = $masterEmail;
        $payload['admin_email']               = $adminEmail;
        $payload['notif_email']               = $adminEmail ?: $masterEmail;
        $payload['customer_email_enabled']    = $customerEmailEnabled ? '1' : '0';
        $payload['email_customer_enabled']    = $customerEmailEnabled ? '1' : '0';

        $jsonPayload = json_encode($payload);

        // Eksekusi HTTP Request via cURL 2-Step Handler
        $response = $this->postJson($this->scriptUrl, $jsonPayload, 60);

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Response tidak valid dari Apps Script: " . substr(strip_tags($response), 0, 300));
        }

        if (($decoded['status'] ?? '') === 'error') {
            throw new \RuntimeException("Apps Script error: " . ($decoded['message'] ?? 'Unknown error'));
        }

        return $decoded;
    }

    /**
     * Update status pendaftaran di Google Spreadsheet via Apps Script
     */
    public function updateStatus(string $ticketNo, string $newStatus, array $extraData = []): array
    {
        $settings = SettingsManager::get();
        $payload = [
            'action'         => 'update_status',
            'ticket_no'      => $ticketNo,
            'status'         => strtoupper(trim($newStatus)),
            'nama_pelanggan' => $extraData['nama'] ?? ($extraData['nama_pelanggan'] ?? ''),
            'nomor_ktp'      => $extraData['nomor_ktp'] ?? '',
            'spreadsheet_id' => $settings['spreadsheet_id'] ?? '',
            'drive_folder_id'=> $settings['drive_folder_id'] ?? '',
        ];

        $jsonPayload = json_encode($payload);
        $response = $this->postJson($this->scriptUrl, $jsonPayload, 30);

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Response tidak valid dari Apps Script: " . substr(strip_tags($response), 0, 200));
        }

        return $decoded ?: ['status' => 'success'];
    }

    /**
     * Hapus dan tandai baris merah di Google Spreadsheet via Apps Script (Khusus Super Admin)
     */
    public function deleteRow(string $ticketNo, array $extraData = []): array
    {
        $settings = SettingsManager::get();
        $payload = [
            'action'         => 'delete_order',
            'ticket_no'      => $ticketNo,
            'nama_pelanggan' => $extraData['nama'] ?? ($extraData['nama_pelanggan'] ?? ''),
            'nomor_ktp'      => $extraData['nomor_ktp'] ?? '',
            'spreadsheet_id' => $settings['spreadsheet_id'] ?? '',
            'drive_folder_id'=> $settings['drive_folder_id'] ?? '',
        ];

        $jsonPayload = json_encode($payload);
        $response = $this->postJson($this->scriptUrl, $jsonPayload, 30);

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Response tidak valid dari Apps Script: " . substr(strip_tags($response), 0, 200));
        }

        return $decoded ?: ['status' => 'success'];
    }

    /**
     * Kirim HTTP POST JSON payload ke Google Apps Script secara aman
     * Menangani 302 Redirect Google Apps Script tanpa header leaking yang memicu Error 400
     */
    private function postJson(string $url, string $jsonPayload, int $timeout = 60): string
    {
        $url = trim($url);

        if (function_exists('curl_init')) {
            // STEP 1: Kirim POST ke script.google.com tanpa auto-follow location
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $jsonPayload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: text/plain;charset=UTF-8'
                ]
            ]);

            $rawResponse = curl_exec($ch);
            $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $curlError   = curl_error($ch);
            curl_close($ch);

            $headers = substr($rawResponse, 0, $headerSize);
            $body    = substr($rawResponse, $headerSize);

            // Jika Google Apps Script mengembalikan 302 / 301 / 303 Redirect
            if (in_array($httpCode, [301, 302, 303, 307, 308])) {
                if (preg_match('/Location:\s*([^\r\n]+)/i', $headers, $matches)) {
                    $redirectUrl = trim($matches[1]);

                    // STEP 2: Lakukan clean GET request ke script.googleusercontent.com
                    $chGet = curl_init();
                    curl_setopt_array($chGet, [
                        CURLOPT_URL            => $redirectUrl,
                        CURLOPT_HTTPGET        => true,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_MAXREDIRS      => 5,
                        CURLOPT_TIMEOUT        => $timeout,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => 0,
                        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                        CURLOPT_HTTPHEADER     => [
                            'Accept: application/json, text/plain, */*'
                        ]
                    ]);

                    $getResponse  = curl_exec($chGet);
                    $getCurlError = curl_error($chGet);
                    curl_close($chGet);

                    if ($getResponse !== false && !empty($getResponse)) {
                        return $getResponse;
                    }

                    if (!empty($getCurlError)) {
                        throw new \RuntimeException("Gagal mengambil respon Google Apps Script: " . $getCurlError);
                    }
                }
            }

            // Jika langsung 200 OK
            if ($httpCode === 200 && !empty($body)) {
                return $body;
            }

            if (!empty($curlError)) {
                throw new \RuntimeException("cURL error ke Google Apps Script: " . $curlError);
            }
        }

        // Fallback Stream Context
        $opts = [
            'http' => [
                'method'          => 'POST',
                'header'          => "Content-Type: text/plain;charset=UTF-8\r\n",
                'content'         => $jsonPayload,
                'follow_location' => 1,
                'max_redirects'   => 5,
                'timeout'         => $timeout,
                'ignore_errors'   => true,
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ]
        ];

        $context  = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            throw new \RuntimeException("Gagal terhubung ke Google Apps Script: " . ($error['message'] ?? 'Network error'));
        }

        return $response;
    }
}
