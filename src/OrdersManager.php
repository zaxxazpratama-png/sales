<?php
namespace App;

use PDO;

/**
 * OrdersManager
 * Handle data orders dengan dual-engine: MySQL database di cPanel + fallback JSON di serverless/lokal.
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
     * Prioritas: Database MySQL -> /tmp -> /data/orders.json
     */
    public static function getAll(): array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT * FROM orders ORDER BY submitted_at DESC, id DESC");
                $rows = $stmt->fetchAll();
                if ($rows !== false) {
                    return $rows;
                }
            } catch (\Exception $e) {
                error_log("DB Orders getAll Error: " . $e->getMessage());
            }
        }

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
     * Simpan semua orders ke writable path JSON
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
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO orders (ticket_no, nama, nomor_ktp, telp, email, alamat, home_id, tikor, paket, total, sales_code, tl_code, jadwal, status, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $order['ticket_no'] ?? '',
                    $order['nama'] ?? '',
                    $order['nomor_ktp'] ?? '',
                    $order['telp'] ?? '',
                    $order['email'] ?? '',
                    $order['alamat'] ?? '',
                    $order['home_id'] ?? '',
                    $order['tikor'] ?? '',
                    $order['paket'] ?? '',
                    $order['total'] ?? '',
                    $order['sales_code'] ?? '',
                    $order['tl_code'] ?? '',
                    $order['jadwal'] ?? '',
                    $order['status'] ?? 'PENDING',
                    $order['submitted_at'] ?? date('Y-m-d H:i:s'),
                ]);
                return;
            } catch (\Exception $e) {
                error_log("DB Orders add Error: " . $e->getMessage());
            }
        }

        $orders   = self::getAll();
        $orders[] = $order;
        self::saveAll($orders);
    }

    /**
     * Update status order berdasarkan ticket_no
     */
    public static function updateStatus(string $ticketNo, string $newStatus, ?string $tlCode = null): ?array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                if ($tlCode !== null && $tlCode !== '') {
                    $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE ticket_no = ? AND tl_code = ?");
                    $stmt->execute([$newStatus, $ticketNo, $tlCode]);
                } else {
                    $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE ticket_no = ?");
                    $stmt->execute([$newStatus, $ticketNo]);
                }

                $fetchStmt = $pdo->prepare("SELECT * FROM orders WHERE ticket_no = ? LIMIT 1");
                $fetchStmt->execute([$ticketNo]);
                return $fetchStmt->fetch() ?: null;
            } catch (\Exception $e) {
                error_log("DB Orders updateStatus Error: " . $e->getMessage());
            }
        }

        $orders      = self::getAll();
        $targetOrder = null;

        foreach ($orders as &$ord) {
            if (($ord['ticket_no'] ?? '') !== $ticketNo) {
                continue;
            }
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
     */
    public static function delete(string $ticketNo): ?array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $fetchStmt = $pdo->prepare("SELECT * FROM orders WHERE ticket_no = ? LIMIT 1");
                $fetchStmt->execute([$ticketNo]);
                $deleted = $fetchStmt->fetch() ?: null;

                if ($deleted) {
                    $delStmt = $pdo->prepare("DELETE FROM orders WHERE ticket_no = ?");
                    $delStmt->execute([$ticketNo]);
                }
                return $deleted;
            } catch (\Exception $e) {
                error_log("DB Orders delete Error: " . $e->getMessage());
            }
        }

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
