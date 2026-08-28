<?php
namespace App;

class SalesManager
{
    /**
     * Path file sumber (read-only di Vercel serverless)
     */
    private static function getSourcePath(): string
    {
        return dirname(__DIR__) . '/data/sales.json';
    }

    /**
     * Path untuk write: /tmp jika filesystem read-only (Vercel),
     * otherwise gunakan path asli
     */
    private static function getWritePath(): string
    {
        $sourcePath = self::getSourcePath();
        $dir        = dirname($sourcePath);

        if (is_writable($dir)) {
            return $sourcePath;
        }

        $tmpDir = sys_get_temp_dir() . '/formgoogle_data';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        return $tmpDir . '/sales.json';
    }

    /**
     * Ambil semua data sales
     * Prioritas: /tmp (data runtime) → file asli (default/commit)
     */
    public static function getAll(): array
    {
        $tmpPath    = sys_get_temp_dir() . '/formgoogle_data/sales.json';
        $sourcePath = self::getSourcePath();

        $path = file_exists($tmpPath) ? $tmpPath : $sourcePath;

        if (!file_exists($path)) {
            return [];
        }
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Cari sales berdasarkan kode sales
     */
    public static function findByCode(string $code): ?array
    {
        $code = strtoupper(trim($code));
        foreach (self::getAll() as $item) {
            if (strtoupper($item['sales_code'] ?? '') === $code) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Cari sales berdasarkan ID
     */
    public static function findById(string $id): ?array
    {
        foreach (self::getAll() as $item) {
            if (($item['id'] ?? '') === $id) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Simpan data sales baru
     */
    public static function add(array $data): array
    {
        $all  = self::getAll();
        $code = strtoupper(trim($data['sales_code'] ?? ''));

        if (self::findByCode($code)) {
            throw new \InvalidArgumentException("Kode Sales '{$code}' sudah digunakan.");
        }

        $newSales = [
            'id'                     => (string)(time() . rand(100, 999)),
            'sales_code'             => $code,
            'nama_sales'             => trim($data['nama_sales'] ?? ''),
            'no_wa'                  => trim($data['no_wa'] ?? ''),
            'email'                  => trim($data['email'] ?? ''),
            'tl_code'                => trim($data['tl_code'] ?? 'TL-01'),
            'ttd_path'               => trim($data['ttd_path'] ?? ''),
            'status'                 => ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
            'email_customer_enabled' => isset($data['email_customer_enabled']) ? (bool)$data['email_customer_enabled'] : true,
            'created_at'             => date('Y-m-d H:i:s'),
        ];

        $all[] = $newSales;
        self::saveAll($all);
        return $newSales;
    }

    /**
     * Update data sales
     */
    public static function update(string $id, array $data): bool
    {
        $all     = self::getAll();
        $found   = false;
        $newCode = strtoupper(trim($data['sales_code'] ?? ''));

        foreach ($all as &$item) {
            if (($item['id'] ?? '') === $id) {
                if (strtoupper($item['sales_code']) !== $newCode && self::findByCode($newCode)) {
                    throw new \InvalidArgumentException("Kode Sales '{$newCode}' sudah digunakan sales lain.");
                }
                $item['sales_code']             = $newCode;
                $item['nama_sales']             = trim($data['nama_sales'] ?? $item['nama_sales']);
                $item['no_wa']                  = trim($data['no_wa'] ?? $item['no_wa']);
                $item['email']                  = trim($data['email'] ?? $item['email']);
                $item['tl_code']                = trim($data['tl_code'] ?? $item['tl_code']);
                $item['ttd_path']               = trim($data['ttd_path'] ?? ($item['ttd_path'] ?? ''));
                $item['status']                 = ($data['status'] ?? $item['status']) === 'inactive' ? 'inactive' : 'active';
                $item['email_customer_enabled']  = isset($data['email_customer_enabled']) ? (bool)$data['email_customer_enabled'] : ($item['email_customer_enabled'] ?? true);
                $found = true;
                break;
            }
        }
        unset($item);

        if ($found) {
            self::saveAll($all);
        }
        return $found;
    }

    /**
     * Hapus sales berdasarkan ID
     */
    public static function delete(string $id): bool
    {
        $all      = self::getAll();
        $filtered = array_values(array_filter($all, fn($item) => ($item['id'] ?? '') !== $id));

        if (count($filtered) !== count($all)) {
            self::saveAll($filtered);
            return true;
        }
        return false;
    }

    public static function reassignTeamLeader(string $oldCode, string $newCode): int
    {
        $all     = self::getAll();
        $changed = 0;
        foreach ($all as &$item) {
            if (($item['tl_code'] ?? '') === $oldCode) {
                $item['tl_code'] = $newCode;
                $changed++;
            }
        }
        unset($item);
        if ($changed > 0) {
            self::saveAll($all);
        }
        return $changed;
    }

    /**
     * Simpan array ke writable path (/tmp atau asli)
     */
    private static function saveAll(array $data): void
    {
        $path = self::getWritePath();
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
