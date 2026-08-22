<?php
namespace App;

class Validator
{
    private array $errors = [];
    private array $data   = [];

    /**
     * Validasi data form Formulir Pendaftaran Layanan CBN
     */
    public function validate(array $input, array $files = []): bool
    {
        $this->errors = [];
        $this->data   = [];

        // --- Required Fields ---
        $requiredFields = [
            'nama_pelanggan'   => 'Nama Lengkap Pelanggan',
            'nomor_ktp'        => 'Nomor KTP',
            'ttl'              => 'Tempat & Tanggal Lahir',
            'jenis_kelamin'    => 'Jenis Kelamin',
            'alamat'           => 'Alamat Pemasangan',
            'kode_pos'         => 'Kode Pos',
            'telp'             => 'Nomor Telepon Seluler / WA',
            'service'          => 'Paket Layanan CBN',
            'email_pelanggan'  => 'Email Pelanggan',
        ];

        foreach ($requiredFields as $key => $label) {
            $val = trim($input[$key] ?? '');
            if ($val === '') {
                $this->errors[$key] = "{$label} wajib diisi.";
            } else {
                $this->data[$key] = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
            }
        }

        // --- Optional & Additional CBN Fields ---
        $this->data['sales_code']        = htmlspecialchars(trim($input['sales_code'] ?? 'SEP-001'), ENT_QUOTES, 'UTF-8');
        $this->data['vendor']            = htmlspecialchars(trim($input['vendor'] ?? 'PT. SINERGI EMAS PERDANA'), ENT_QUOTES, 'UTF-8');
        $this->data['so_date']           = htmlspecialchars(trim($input['so_date'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8');
        $this->data['tl_code']           = htmlspecialchars(trim($input['tl_code'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $this->data['ae_name']           = htmlspecialchars(trim($input['ae_name'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $this->data['home_id']           = htmlspecialchars(trim($input['home_id'] ?? 'PENDING'), ENT_QUOTES, 'UTF-8');
        
        $this->data['telp_rumah']        = htmlspecialchars(trim($input['telp_rumah'] ?? ''), ENT_QUOTES, 'UTF-8');
        $this->data['telp2']             = htmlspecialchars(trim($input['telp2'] ?? ''), ENT_QUOTES, 'UTF-8');
        $this->data['rt']                = htmlspecialchars(trim($input['rt'] ?? ''), ENT_QUOTES, 'UTF-8');
        $this->data['rw']                = htmlspecialchars(trim($input['rw'] ?? ''), ENT_QUOTES, 'UTF-8');
        $this->data['kelurahan']         = htmlspecialchars(trim($input['kelurahan'] ?? ''), ENT_QUOTES, 'UTF-8');
        $this->data['kecamatan']         = htmlspecialchars(trim($input['kecamatan'] ?? ''), ENT_QUOTES, 'UTF-8');
        $this->data['status_kepemilikan']= htmlspecialchars(trim($input['status_kepemilikan'] ?? 'PEMILIK'), ENT_QUOTES, 'UTF-8');
        $this->data['tikor']             = htmlspecialchars(trim($input['tikor'] ?? ''), ENT_QUOTES, 'UTF-8');
        
        $this->data['username_cbn']      = htmlspecialchars(trim($input['username_cbn'] ?? ''), ENT_QUOTES, 'UTF-8');
        $this->data['jadwal_tanggal']    = htmlspecialchars(trim($input['jadwal_tanggal'] ?? date('d/m/Y', strtotime('+2 days'))), ENT_QUOTES, 'UTF-8');
        $this->data['jadwal_waktu']      = htmlspecialchars(trim($input['jadwal_waktu'] ?? '09.00-11.00'), ENT_QUOTES, 'UTF-8');
        $this->data['catatan']           = htmlspecialchars(trim($input['catatan'] ?? ''), ENT_QUOTES, 'UTF-8');

        // Arrays (Add-on TV & Devices)
        $this->data['addon_tv']          = isset($input['addon_tv']) && is_array($input['addon_tv']) ? $input['addon_tv'] : [];
        $this->data['addon_device']      = isset($input['addon_device']) && is_array($input['addon_device']) ? $input['addon_device'] : [];
        $this->data['router_qty']        = htmlspecialchars(trim($input['router_qty'] ?? '1'), ENT_QUOTES, 'UTF-8');
        $this->data['smartbox_qty']      = htmlspecialchars(trim($input['smartbox_qty'] ?? '0'), ENT_QUOTES, 'UTF-8');

        // Pricing estimation
        $this->data['biaya_pasang']      = htmlspecialchars(trim($input['biaya_pasang'] ?? 'Rp 0 (Promo Gratis)'), ENT_QUOTES, 'UTF-8');
        $this->data['biaya_paket']       = htmlspecialchars(trim($input['biaya_paket'] ?? 'Rp 299.000'), ENT_QUOTES, 'UTF-8');
        $this->data['biaya_addon']       = htmlspecialchars(trim($input['biaya_addon'] ?? 'Rp 0'), ENT_QUOTES, 'UTF-8');
        $this->data['biaya_ppn']         = htmlspecialchars(trim($input['biaya_ppn'] ?? 'Rp 32.890'), ENT_QUOTES, 'UTF-8');
        $this->data['biaya_total']       = htmlspecialchars(trim($input['biaya_total'] ?? 'Rp 331.890'), ENT_QUOTES, 'UTF-8');

        // Signature data (Base64 URL)
        $signatureData = trim($input['signature_data'] ?? '');
        $this->data['signature_data']    = $signatureData;

        // --- Validasi Format ---
        if (!empty($this->data['nomor_ktp']) && !preg_match('/^\d{16}$/', $input['nomor_ktp'] ?? '')) {
            $this->errors['nomor_ktp'] = 'Nomor KTP harus 16 digit angka.';
        }

        if (!empty($this->data['email_pelanggan']) && !filter_var($input['email_pelanggan'], FILTER_VALIDATE_EMAIL)) {
            $this->errors['email_pelanggan'] = 'Format email pelanggan tidak valid.';
        }

        if (!empty($this->data['telp']) && !preg_match('/^[0-9+\-\s]{8,18}$/', $input['telp'] ?? '')) {
            $this->errors['telp'] = 'Format nomor telepon tidak valid.';
        }

        // --- Validasi Jenis Kelamin ---
        $validGender = ['PRIA', 'WANITA'];
        if (!empty($input['jenis_kelamin']) && !in_array(strtoupper($input['jenis_kelamin']), $validGender)) {
            $this->errors['jenis_kelamin'] = 'Jenis kelamin tidak valid.';
        }

        // --- Validasi File Upload KTP (Opsional) ---
        if (!empty($files['sales_order_file']['name'])) {
            $file       = $files['sales_order_file'];
            $allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
            $maxSize    = 8 * 1024 * 1024; // 8MB
            $ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->errors['sales_order_file'] = 'Terjadi error saat upload foto KTP.';
            } elseif (!in_array($ext, $allowedExt)) {
                $this->errors['sales_order_file'] = 'File harus berformat JPG, PNG, atau PDF.';
            } elseif ($file['size'] > $maxSize) {
                $this->errors['sales_order_file'] = 'Ukuran file foto KTP maksimal 8MB.';
            } else {
                $this->data['upload_file'] = $file;
            }
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
