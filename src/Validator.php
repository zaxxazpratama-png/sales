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

        // --- 1. Required Fields & Pesan Spesifik Bahasa Indonesia ---
        $requiredFields = [
            'nama_pelanggan'   => 'Nama Lengkap Pelanggan belum diisi',
            'nomor_ktp'        => 'Nomor Identitas KTP (16 Digit) belum diisi',
            'ttl'              => 'Tempat & Tanggal Lahir belum diisi (Contoh: Medan, 15/08/1995)',
            'jenis_kelamin'    => 'Jenis Kelamin (Pria / Wanita) belum dipilih',
            'telp'             => 'Nomor Telepon Seluler / WhatsApp belum diisi',
            'email_pelanggan'  => 'Alamat Email Pelanggan belum diisi',
            'alamat'           => 'Alamat Pemasangan Rumah/Gedung belum diisi',
            'kode_pos'         => 'Kode Pos belum diisi',
            'service'          => 'Pilihan Paket Internet Fiber CBN belum dipilih',
        ];

        foreach ($requiredFields as $key => $msg) {
            $val = trim($input[$key] ?? '');
            if ($val === '') {
                $this->errors[$key] = $msg;
            } else {
                $this->data[$key] = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
            }
        }

        // --- 2. Optional & Additional CBN Fields ---
        $this->data['sales_code']        = htmlspecialchars(trim($input['sales_code'] ?? 'SEP-001'), ENT_QUOTES, 'UTF-8');
        $this->data['vendor']            = htmlspecialchars(trim($input['vendor'] ?? 'PT. TIN'), ENT_QUOTES, 'UTF-8');
        $this->data['so_date']           = htmlspecialchars(trim($input['so_date'] ?? date('d/m/Y')), ENT_QUOTES, 'UTF-8');
        $this->data['tl_code']           = htmlspecialchars(trim($input['tl_code'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $this->data['ae_name']           = htmlspecialchars(trim($input['ae_name'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $this->data['home_id']           = htmlspecialchars(trim($input['home_id'] ?? ''), ENT_QUOTES, 'UTF-8');
        
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
        $this->data['biaya_pasang']      = htmlspecialchars(trim($input['biaya_pasang'] ?? 'Rp 0'), ENT_QUOTES, 'UTF-8');
        $this->data['biaya_paket']       = htmlspecialchars(trim($input['biaya_paket'] ?? 'Rp 169.000'), ENT_QUOTES, 'UTF-8');
        $this->data['biaya_tambahan']    = htmlspecialchars(trim($input['biaya_tambahan'] ?? 'Rp 5.000'), ENT_QUOTES, 'UTF-8');
        $this->data['biaya_addon']       = htmlspecialchars(trim($input['biaya_addon'] ?? 'Rp 0'), ENT_QUOTES, 'UTF-8');
        $this->data['biaya_ppn']         = htmlspecialchars(trim($input['biaya_ppn'] ?? 'Rp 19.140'), ENT_QUOTES, 'UTF-8');
        $this->data['biaya_total']       = htmlspecialchars(trim($input['biaya_total'] ?? 'Rp 193.140'), ENT_QUOTES, 'UTF-8');
        $this->data['addon_cbn_package'] = trim($input['addon_cbn_package'] ?? '');

        // --- 4. Validasi Format & Struktur Data ---
        if (!empty($input['nomor_ktp'])) {
            $cleanKtp = preg_replace('/\D/', '', $input['nomor_ktp']);
            if (strlen($cleanKtp) !== 16) {
                $this->errors['nomor_ktp'] = 'Nomor KTP harus terdiri dari tepat 16 digit angka (saat ini: ' . strlen($cleanKtp) . ' digit).';
            }
        }

        if (!empty($input['email_pelanggan']) && !filter_var($input['email_pelanggan'], FILTER_VALIDATE_EMAIL)) {
            $this->errors['email_pelanggan'] = 'Format email pelanggan tidak valid (Contoh: nama@gmail.com).';
        }

        if (!empty($input['telp'])) {
            $cleanTelp = preg_replace('/[^\d+]/', '', $input['telp']);
            if (strlen($cleanTelp) < 9 || strlen($cleanTelp) > 16) {
                $this->errors['telp'] = 'Nomor telepon seluler tidak valid (minimal 10-15 digit angka).';
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
