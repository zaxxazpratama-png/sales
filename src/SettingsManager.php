<?php
namespace App;

class SettingsManager
{
    private static string $filePath = '';

    /**
     * Path file asli (read-only di serverless, writable di lokal)
     */
    private static function getSourcePath(): string
    {
        return dirname(__DIR__) . '/data/settings.json';
    }

    /**
     * Path untuk write: gunakan /tmp jika filesystem read-only (serverless),
     * otherwise gunakan path asli
     */
    private static function getWritePath(): string
    {
        if (empty(self::$filePath)) {
            $sourcePath = self::getSourcePath();
            $dir        = dirname($sourcePath);

            // Cek apakah direktori bisa ditulis
            if (is_writable($dir)) {
                self::$filePath = $sourcePath;
            } else {
                // Fallback ke /tmp untuk environment serverless (Lambda, dll)
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
     * Ambil seluruh konfigurasi pengaturan.
     * Prioritas: /tmp (data runtime) → file asli (default)
     */
    public static function get(): array
    {
        $tmpPath    = sys_get_temp_dir() . '/formgoogle_data/settings.json';
        $sourcePath = self::getSourcePath();

        // Gunakan /tmp jika ada (data yang sudah diupdate di runtime)
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
        $path    = self::getWritePath();
        $dir     = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return (bool) file_put_contents(
            $path,
            json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
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
