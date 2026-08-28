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

    public const PROVINCES = [
        'Sumatera Utara',
        'DKI Jakarta',
        'Aceh',
        'Sumatera Barat',
        'Riau',
        'Kepulauan Riau',
        'Jambi',
        'Sumatera Selatan',
        'Kepulauan Bangka Belitung',
        'Bengkulu',
        'Lampung',
        'Jawa Barat',
        'Banten',
        'Jawa Tengah',
        'DI Yogyakarta',
        'Jawa Timur',
        'Bali',
        'Nusa Tenggara Barat',
        'Nusa Tenggara Timur',
        'Kalimantan Barat',
        'Kalimantan Tengah',
        'Kalimantan Selatan',
        'Kalimantan Timur',
        'Kalimantan Utara',
        'Sulawesi Utara',
        'Gorontalo',
        'Sulawesi Tengah',
        'Sulawesi Barat',
        'Sulawesi Selatan',
        'Sulawesi Tenggara',
        'Maluku',
        'Maluku Utara',
        'Papua',
        'Papua Barat',
        'Papua Selatan',
        'Papua Tengah',
        'Papua Pegunungan',
        'Papua Barat Daya'
    ];

    /**
     * Ambil data promo per provinsi.
     */
    public static function getProvincePromos(): array
    {
        $settings = self::get();
        return $settings['province_promos'] ?? [];
    }

    /**
     * Ambil daftar paket untuk provinsi tertentu (fallback ke paket default).
     */
    public static function getPackagesForProvince(string $province = 'Sumatera Utara'): array
    {
        $settings = self::get();
        $promos   = $settings['province_promos'] ?? [];
        $province = trim($province);

        if (!empty($promos[$province]['packages']) && is_array($promos[$province]['packages'])) {
            return $promos[$province]['packages'];
        }

        // Cek case-insensitive
        foreach ($promos as $pName => $pData) {
            if (strcasecmp($pName, $province) === 0 && !empty($pData['packages'])) {
                return $pData['packages'];
            }
        }

        return $settings['packages'] ?? [];
    }

    /**
     * Ambil catatan promo untuk provinsi tertentu.
     */
    public static function getPromoNotesForProvince(string $province = 'Sumatera Utara'): string
    {
        $settings = self::get();
        $promos   = $settings['province_promos'] ?? [];
        $province = trim($province);

        if (isset($promos[$province]['default_notes']) && $promos[$province]['default_notes'] !== '') {
            return $promos[$province]['default_notes'];
        }

        foreach ($promos as $pName => $pData) {
            if (strcasecmp($pName, $province) === 0 && !empty($pData['default_notes'])) {
                return $pData['default_notes'];
            }
        }

        return $settings['default_notes'] ?? 'REGULER PROMO JULY 2026 - NAB';
    }

    /**
     * Simpan promo dan paket untuk provinsi tertentu.
     */
    public static function saveProvincePromo(string $province, array $packages, string $defaultNotes = ''): bool
    {
        $settings = self::get();
        $promos   = $settings['province_promos'] ?? [];
        $province = trim($province);
        if ($province === '') {
            $province = 'Sumatera Utara';
        }

        $promos[$province] = [
            'default_notes' => $defaultNotes ?: ($settings['default_notes'] ?? 'REGULER PROMO JULY 2026 - NAB'),
            'packages'      => $packages,
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        $settings['province_promos'] = $promos;
        // Jika provinsi adalah Sumatera Utara atau DKI Jakarta atau default, update juga global packages jika belum ada
        if (empty($settings['packages'])) {
            $settings['packages'] = $packages;
        }

        return self::update($settings);
    }

    /**
     * Salin pengaturan promo dari satu provinsi ke provinsi lain atau ke semua provinsi.
     */
    public static function copyProvincePromo(string $fromProvince, string $toProvince): bool
    {
        $fromPackages = self::getPackagesForProvince($fromProvince);
        $fromNotes    = self::getPromoNotesForProvince($fromProvince);

        if ($toProvince === 'ALL') {
            foreach (self::PROVINCES as $prov) {
                self::saveProvincePromo($prov, $fromPackages, $fromNotes);
            }
            return true;
        }

        return self::saveProvincePromo($toProvince, $fromPackages, $fromNotes);
    }

    /**
     * Simpan paket layanan default
     */
    public static function savePackages(array $packages): bool
    {
        $current             = self::get();
        $current['packages'] = $packages;
        return self::update($current);
    }
}
