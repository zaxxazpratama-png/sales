<?php
namespace App;

use PDO;

class SalesManager
{
    private static function getSourcePath(): string
    {
        return dirname(__DIR__) . '/data/sales.json';
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
        return $tmpDir . '/sales.json';
    }

    /**
     * Ambil semua data sales
     * Prioritas: Database MySQL -> /tmp -> /data/sales.json
     */
    public static function getAll(): array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT id, sales_code, nama_sales, no_wa, email, tl_code, ttd_path, status, email_customer_enabled, created_at FROM sales ORDER BY created_at ASC, id ASC");
                $rows = $stmt->fetchAll();
                if ($rows !== false) {
                    foreach ($rows as &$r) {
                        $r['email_customer_enabled'] = (bool)($r['email_customer_enabled'] ?? true);
                    }
                    unset($r);
                    return $rows;
                }
            } catch (\Exception $e) {
                error_log("DB Sales getAll Error: " . $e->getMessage());
            }
        }

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

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM sales WHERE UPPER(sales_code) = ? LIMIT 1");
                $stmt->execute([$code]);
                $row = $stmt->fetch();
                if ($row) {
                    $row['email_customer_enabled'] = (bool)($row['email_customer_enabled'] ?? true);
                    return $row;
                }
                return null;
            } catch (\Exception $e) {
                error_log("DB Sales findByCode Error: " . $e->getMessage());
            }
        }

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
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                $row = $stmt->fetch();
                if ($row) {
                    $row['email_customer_enabled'] = (bool)($row['email_customer_enabled'] ?? true);
                    return $row;
                }
                return null;
            } catch (\Exception $e) {
                error_log("DB Sales findById Error: " . $e->getMessage());
            }
        }

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
        $code = strtoupper(trim($data['sales_code'] ?? ''));

        if (self::findByCode($code)) {
            throw new \InvalidArgumentException("Kode Sales '{$code}' sudah digunakan.");
        }

        $id = (string)(time() . rand(100, 999));
        $newSales = [
            'id'                     => $id,
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

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO sales (id, sales_code, nama_sales, no_wa, email, tl_code, ttd_path, status, email_customer_enabled, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $newSales['id'],
                    $newSales['sales_code'],
                    $newSales['nama_sales'],
                    $newSales['no_wa'],
                    $newSales['email'],
                    $newSales['tl_code'],
                    $newSales['ttd_path'],
                    $newSales['status'],
                    $newSales['email_customer_enabled'] ? 1 : 0,
                    $newSales['created_at'],
                ]);
                return $newSales;
            } catch (\Exception $e) {
                error_log("DB Sales add Error: " . $e->getMessage());
            }
        }

        $all = self::getAll();
        $all[] = $newSales;
        self::saveAll($all);
        return $newSales;
    }

    /**
     * Update data sales
     */
    public static function update(string $id, array $data): bool
    {
        $newCode = strtoupper(trim($data['sales_code'] ?? ''));

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $checkStmt = $pdo->prepare("SELECT id FROM sales WHERE UPPER(sales_code) = ? AND id != ? LIMIT 1");
                $checkStmt->execute([$newCode, $id]);
                if ($checkStmt->fetch()) {
                    throw new \InvalidArgumentException("Kode Sales '{$newCode}' sudah digunakan sales lain.");
                }

                $stmt = $pdo->prepare("UPDATE sales SET sales_code = ?, nama_sales = ?, no_wa = ?, email = ?, tl_code = ?, ttd_path = ?, status = ?, email_customer_enabled = ? WHERE id = ?");
                return $stmt->execute([
                    $newCode,
                    trim($data['nama_sales'] ?? ''),
                    trim($data['no_wa'] ?? ''),
                    trim($data['email'] ?? ''),
                    trim($data['tl_code'] ?? 'TL-01'),
                    trim($data['ttd_path'] ?? ''),
                    ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
                    isset($data['email_customer_enabled']) ? ((bool)$data['email_customer_enabled'] ? 1 : 0) : 1,
                    $id
                ]);
            } catch (\InvalidArgumentException $iae) {
                throw $iae;
            } catch (\Exception $e) {
                error_log("DB Sales update Error: " . $e->getMessage());
            }
        }

        $all   = self::getAll();
        $found = false;

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
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM sales WHERE id = ?");
                return $stmt->execute([$id]);
            } catch (\Exception $e) {
                error_log("DB Sales delete Error: " . $e->getMessage());
            }
        }

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
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE sales SET tl_code = ? WHERE tl_code = ?");
                $stmt->execute([$newCode, $oldCode]);
                return $stmt->rowCount();
            } catch (\Exception $e) {
                error_log("DB Sales reassignTeamLeader Error: " . $e->getMessage());
            }
        }

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
