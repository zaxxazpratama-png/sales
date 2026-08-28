<?php
namespace App;

/**
 * OrdersManager
 * Handle baca/tulis orders.json dengan fallback /tmp untuk Vercel serverless
 */
class OrdersManager
{
    private static function getSourcePath(): string
    {
        return dirname(__DIR__) . '/data/orders.json';
    }

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
        return $tmpDir . '/orders.json';
    }

    /**
     * Ambil semua orders
     * Prioritas: /tmp (data runtime) → file asli
     */
    public static function getAll(): array
    {
        $tmpPath    = sys_get_temp_dir() . '/formgoogle_data/orders.json';
        $sourcePath = self::getSourcePath();

        $path = file_exists($tmpPath) ? $tmpPath : $sourcePath;

        if (!file_exists($path)) {
            return [];
        }
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Simpan semua orders ke writable path
     */
    public static function saveAll(array $orders): void
    {
        $path = self::getWritePath();
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Tambah order baru
     */
    public static function add(array $order): void
    {
        $orders   = self::getAll();
        $orders[] = $order;
        self::saveAll($orders);
    }

    /**
     * Update status order berdasarkan ticket_no
     * Return array order yang diupdate, atau null jika tidak ditemukan
     */
    public static function updateStatus(string $ticketNo, string $newStatus, ?string $tlCode = null): ?array
    {
        $orders      = self::getAll();
        $targetOrder = null;

        foreach ($orders as &$ord) {
            if (($ord['ticket_no'] ?? '') !== $ticketNo) {
                continue;
            }
            // Jika ada filter TL, pastikan hanya TL yang punya order ini
            if ($tlCode !== null && ($ord['tl_code'] ?? '') !== $tlCode) {
                continue;
            }
            $ord['status']     = $newStatus;
            $ord['updated_at'] = date('Y-m-d H:i:s');
            $targetOrder       = $ord;
            break;
        }
        unset($ord);

        if ($targetOrder !== null) {
            self::saveAll($orders);
        }
        return $targetOrder;
    }

    /**
     * Hapus order berdasarkan ticket_no
     * Return order yang dihapus, atau null jika tidak ditemukan
     */
    public static function delete(string $ticketNo): ?array
    {
        $orders      = self::getAll();
        $deleted     = null;
        $newOrders   = [];

        foreach ($orders as $ord) {
            if (($ord['ticket_no'] ?? '') === $ticketNo) {
                $deleted = $ord;
            } else {
                $newOrders[] = $ord;
            }
        }

        if ($deleted !== null) {
            self::saveAll($newOrders);
        }
        return $deleted;
    }
}
