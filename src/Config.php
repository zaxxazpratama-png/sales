<?php
namespace App;

use Dotenv\Dotenv;

class Config
{
    private static bool $loaded = false;
    private static array $data = [];

    public static function load(): void
    {
        if (self::$loaded) return;

        // Load .env dari root project (2 level di atas /src)
        $rootPath = dirname(__DIR__);
        
        if (file_exists($rootPath . '/.env')) {
            $dotenv = Dotenv::createImmutable($rootPath);
            $dotenv->load();
        }

        self::$data = [
            // Apps Script Config (tanpa kartu kredit)
            'apps_script_url' => $_ENV['APPS_SCRIPT_URL'] ?? '',

            // Mail Config (opsional)
            'mail_host'       => $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
            'mail_port'       => (int)($_ENV['MAIL_PORT'] ?? 587),
            'mail_username'   => $_ENV['MAIL_USERNAME'] ?? '',
            'mail_password'   => $_ENV['MAIL_PASSWORD'] ?? '',
            'mail_from_name'  => $_ENV['MAIL_FROM_NAME'] ?? 'Sales Order Form',
            'mail_from_email' => $_ENV['MAIL_FROM_EMAIL'] ?? '',
            'mail_to_email'   => $_ENV['MAIL_TO_EMAIL'] ?? '',
            'mail_to_name'    => $_ENV['MAIL_TO_NAME'] ?? 'Admin',

            // App Config
            'app_name'  => $_ENV['APP_NAME'] ?? 'Sales Order Form',
            'app_debug' => strtolower($_ENV['APP_DEBUG'] ?? 'false') === 'true',
        ];

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        return self::$data[$key] ?? $default;
    }
}
