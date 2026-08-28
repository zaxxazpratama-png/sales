<?php
namespace App;

use PDO;

class SettingsManager
{
    private static string $filePath = '';

    private static function getSourcePath(): string
    {
        return dirname(__DIR__) . '/data/settings.json';
    }

    private static function getWritePath(): string
    {
        if (empty(self::$filePath)) {
            $sourcePath = self::getSourcePath();
            $dir        = dirname($sourcePath);

            if (is_writable($dir)) {
                self::$filePath = $sourcePath;
            } else {
                $tmpDir = sys_get_temp_dir() . '/formgoogle_data';
                if (!is_dir($tmpDir)) {
                    mkdir($tmpDir, 0755, true);
                }
                self::$filePath = $tmpDir . '/settings.json';
            }
        }
        return self::$filePath;
    }

    /**
     * Ambil konfigurasi pengaturan.
     * Prioritas: Database MySQL -> /tmp -> file asli /data/settings.json
     */
    public static function get(): array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'app_config' LIMIT 1");
                $stmt->execute();
                $row = $stmt->fetch();
                if ($row && !empty($row['setting_value'])) {
                    $decoded = json_decode($row['setting_value'], true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                }
            } catch (\Exception $e) {
                error_log("DB Settings get Error: " . $e->getMessage());
            }
        }

        $tmpPath    = sys_get_temp_dir() . '/formgoogle_data/settings.json';
        $sourcePath = self::getSourcePath();

        $path = (file_exists($tmpPath)) ? $tmpPath : $sourcePath;

        if (!file_exists($path)) {
            return [];
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Simpan pembaruan pengaturan
     */
    public static function update(array $newData): bool
    {
        $current = self::get();
        $merged  = array_merge($current, $newData);
        $jsonStr = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES ('app_config', ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
                $stmt->execute([$jsonStr]);
            } catch (\Exception $e) {
                error_log("DB Settings update Error: " . $e->getMessage());
            }
        }

        $path    = self::getWritePath();
        $dir     = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return (bool) file_put_contents($path, $jsonStr);
    }

    /**
     * Simpan paket layanan
     */
    public static function savePackages(array $packages): bool
    {
        $current             = self::get();
        $current['packages'] = $packages;
        return self::update($current);
    }
}
