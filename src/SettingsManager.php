<?php
namespace App;

class SettingsManager
{
    private static string $filePath = '';

    private static function getPath(): string
    {
        if (empty(self::$filePath)) {
            self::$filePath = dirname(__DIR__) . '/data/settings.json';
        }
        return self::$filePath;
    }

    /**
     * Ambil seluruh konfigurasi pengaturan
     */
    public static function get(): array
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
     * Simpan pembaruan pengaturan
     */
    public static function update(array $newData): bool
    {
        $current = self::get();
        $merged  = array_merge($current, $newData);
        $path    = self::getPath();
        $dir     = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return (bool) file_put_contents($path, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Simpan paket layanan
     */
    public static function savePackages(array $packages): bool
    {
        $current = self::get();
        $current['packages'] = $packages;
        return self::update($current);
    }
}
