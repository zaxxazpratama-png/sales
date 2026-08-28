<?php
namespace App;

class Config
{
    private static bool $loaded = false;
    private static array $data = [];

    public static function load(): void
    {
        if (self::$loaded) return;

        // Load .env dari root project (2 level di atas /src)
        $rootPath = dirname(__DIR__);
        $envFile  = $rootPath . '/.env';
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line) || str_starts_with($line, '#')) continue;
                    $parts = explode('=', $line, 2);
                    if (count($parts) === 2) {
                        $key = trim($parts[0]);
                        $val = trim($parts[1]);
                        $val = trim($val, "\"'");
                        if (!isset($_ENV[$key])) {
                            $_ENV[$key] = $val;
                            putenv("$key=$val");
                        }
                    }
                }
            }
        }

        self::$data = [
            // Database MySQL (cPanel / Server / Cloud)
            'db_host'         => $_ENV['DB_HOST'] ?? 'localhost',
            'db_port'         => (int)($_ENV['DB_PORT'] ?? 3306),
            'db_database'     => $_ENV['DB_DATABASE'] ?? '',
            'db_username'     => $_ENV['DB_USERNAME'] ?? '',
            'db_password'     => $_ENV['DB_PASSWORD'] ?? '',

            // Apps Script Config
            'apps_script_url' => $_ENV['APPS_SCRIPT_URL'] ?? '',

            // Mail Config (opsional)
            'mail_host'       => $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
            'mail_port'       => (int)($_ENV['MAIL_PORT'] ?? 587),
            'mail_username'   => $_ENV['MAIL_USERNAME'] ?? '',
            'mail_password'   => $_ENV['MAIL_PASSWORD'] ?? '',
            'mail_from_name'  => $_ENV['MAIL_FROM_NAME'] ?? 'PT. TALENTA INTEGRITAS NASIONAL',
            'mail_from_email' => $_ENV['MAIL_FROM_EMAIL'] ?? '',
            'mail_to_email'   => $_ENV['MAIL_TO_EMAIL'] ?? '',
            'mail_to_name'    => $_ENV['MAIL_TO_NAME'] ?? 'Admin TIN',

            // App Config
            'app_name'        => $_ENV['APP_NAME'] ?? 'Sales Order Form - PT. TALENTA INTEGRITAS NASIONAL',
            'app_url'         => $_ENV['APP_URL'] ?? 'https://idpanel.site',
            'app_debug'       => strtolower($_ENV['APP_DEBUG'] ?? 'false') === 'true',
        ];

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        return self::$data[$key] ?? $default;
    }
}
