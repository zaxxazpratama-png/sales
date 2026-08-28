<?php
namespace App;

use PDO;
use PDOException;

/**
 * Database Helper - PDO MySQL Connection for cPanel / VPS / Cloud DB
 * Mendukung auto-fallback jika DB belum diset / offline.
 */
class Database
{
    private static ?PDO $pdo = null;
    private static bool $attempted = false;

    /**
     * Dapatkan instance PDO koneksi database.
     * Mengembalikan null jika DB tidak dikonfigurasi atau gagal koneksi.
     */
    public static function getConnection(): ?PDO
    {
        if (self::$attempted) {
            return self::$pdo;
        }

        self::$attempted = true;
        Config::load();

        $host     = Config::get('db_host', $_ENV['DB_HOST'] ?? '');
        $port     = Config::get('db_port', $_ENV['DB_PORT'] ?? 3306);
        $database = Config::get('db_database', $_ENV['DB_DATABASE'] ?? '');
        $username = Config::get('db_username', $_ENV['DB_USERNAME'] ?? '');
        $password = Config::get('db_password', $_ENV['DB_PASSWORD'] ?? '');

        // Jika database belum dikonfigurasi, gunakan fallback JSON
        if (empty($host) || empty($database)) {
            self::$pdo = null;
            return null;
        }

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
            ];

            self::$pdo = new PDO($dsn, $username, $password, $options);
            return self::$pdo;
        } catch (PDOException $e) {
            error_log("Database Connection Warning (using JSON fallback): " . $e->getMessage());
            self::$pdo = null;
            return null;
        }
    }

    /**
     * Cek apakah koneksi database MySQL aktif
     */
    public static function isConnected(): bool
    {
        return self::getConnection() !== null;
    }
}
