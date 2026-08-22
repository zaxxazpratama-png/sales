<?php
namespace App;

class SalesManager
{
    private static string $filePath = '';

    private static function getPath(): string
    {
        if (empty(self::$filePath)) {
            self::$filePath = dirname(__DIR__) . '/data/sales.json';
        }
        return self::$filePath;
    }

    /**
     * Ambil semua data sales
     */
    public static function getAll(): array
    {
        $path = self::getPath();
        if (!file_exists($path)) {
            return [];
        }
        $json = file_get_contents($path);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Cari sales berdasarkan kode sales
     */
    public static function findByCode(string $code): ?array
    {
        $code = strtoupper(trim($code));
        $all  = self::getAll();
        foreach ($all as $item) {
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
        $all = self::getAll();
        foreach ($all as $item) {
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
        $all = self::getAll();
        $code = strtoupper(trim($data['sales_code'] ?? ''));

        // Cek duplikasi kode
        if (self::findByCode($code)) {
            throw new \InvalidArgumentException("Kode Sales '{$code}' sudah digunakan.");
        }

        $newSales = [
            'id'         => (string) (time() . rand(100, 999)),
            'sales_code' => $code,
            'nama_sales' => trim($data['nama_sales'] ?? ''),
            'no_wa'      => trim($data['no_wa'] ?? ''),
            'email'      => trim($data['email'] ?? ''),
            'tl_code'    => trim($data['tl_code'] ?? 'TL-01'),
            'status'     => ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
            'created_at' => date('Y-m-d H:i:s'),
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
        $all = self::getAll();
        $found = false;
        $newCode = strtoupper(trim($data['sales_code'] ?? ''));

        foreach ($all as &$item) {
            if (($item['id'] ?? '') === $id) {
                // Jika ganti kode sales, pastikan tidak bentrok dengan sales lain
                if (strtoupper($item['sales_code']) !== $newCode && self::findByCode($newCode)) {
                    throw new \InvalidArgumentException("Kode Sales '{$newCode}' sudah digunakan sales lain.");
                }

                $item['sales_code'] = $newCode;
                $item['nama_sales'] = trim($data['nama_sales'] ?? $item['nama_sales']);
                $item['no_wa']      = trim($data['no_wa'] ?? $item['no_wa']);
                $item['email']      = trim($data['email'] ?? $item['email']);
                $item['tl_code']    = trim($data['tl_code'] ?? $item['tl_code']);
                $item['status']     = ($data['status'] ?? $item['status']) === 'inactive' ? 'inactive' : 'active';
                $found = true;
                break;
            }
        }

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
        $all = self::getAll();
        $filtered = array_values(array_filter($all, function ($item) use ($id) {
            return ($item['id'] ?? '') !== $id;
        }));

        if (count($filtered) !== count($all)) {
            self::saveAll($filtered);
            return true;
        }
        return false;
    }

    /**
     * Simpan array ke file JSON
     */
    private static function saveAll(array $data): void
    {
        $path = self::getPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
