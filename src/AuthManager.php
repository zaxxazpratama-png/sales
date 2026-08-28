<?php
namespace App;

class AuthManager
{
    private static string $tmpDir = '';

    /**
     * Path file sumber (read-only di Vercel serverless)
     */
    private static function getSourcePath(): string
    {
        return dirname(__DIR__) . '/data/auth.json';
    }

    /**
     * Path untuk write: /tmp jika filesystem read-only (Vercel),
     * otherwise gunakan path asli
     */
    private static function getWritePath(): string
    {
        $sourcePath = self::getSourcePath();
        $dir        = dirname($sourcePath);

        if (is_writable($dir)) {
            return $sourcePath;
        }

        // Fallback ke /tmp untuk environment serverless (Vercel, Lambda, dll)
        $tmpDir = sys_get_temp_dir() . '/formgoogle_data';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        return $tmpDir . '/auth.json';
    }

    /**
     * Baca users: prioritas /tmp (data runtime) → file asli (default/commit)
     */
    public static function getUsers(): array
    {
        $tmpPath    = sys_get_temp_dir() . '/formgoogle_data/auth.json';
        $sourcePath = self::getSourcePath();

        // Gunakan /tmp jika ada (data yang sudah diupdate di runtime)
        $path = file_exists($tmpPath) ? $tmpPath : $sourcePath;

        if (!file_exists($path)) {
            return [];
        }

        $users = json_decode(file_get_contents($path), true);
        return is_array($users) ? $users : [];
    }

    /**
     * Simpan users ke writable path
     */
    private static function saveUsers(array $users): bool
    {
        $path = self::getWritePath();
        $dir  = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return (bool) file_put_contents(
            $path,
            json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    public static function authenticate(string $username, string $password): ?array
    {
        foreach (self::getUsers() as $index => $user) {
            if (($user['username'] ?? '') !== $username || ($user['status'] ?? 'active') !== 'active') {
                continue;
            }
            $stored = (string)($user['password'] ?? '');
            $valid = str_starts_with($stored, '$2')
                ? password_verify($password, $stored)
                : hash_equals($stored, $password);
            if ($valid) {
                // Upgrade plaintext password ke bcrypt
                if (!str_starts_with($stored, '$2')) {
                    $users = self::getUsers();
                    $users[$index]['password'] = password_hash($password, PASSWORD_DEFAULT);
                    self::saveUsers($users);
                }
                return [
                    'username' => $user['username'],
                    'role'     => $user['role'] ?? 'admin',
                    'tl_code'  => $user['tl_code'] ?? '',
                ];
            }
        }
        return null;
    }

    public static function addTeamLeader(string $username, string $password, string $tlCode): bool
    {
        $username = trim($username);
        $tlCode   = strtoupper(trim($tlCode));
        if ($username === '' || $password === '' || $tlCode === '') {
            throw new \InvalidArgumentException('Username, password, dan kode team leader wajib diisi.');
        }
        $users = self::getUsers();
        foreach ($users as $user) {
            if (($user['username'] ?? '') === $username || ($user['tl_code'] ?? '') === $tlCode) {
                throw new \InvalidArgumentException('Username atau kode team leader sudah digunakan.');
            }
        }
        $users[] = [
            'id'         => (string)(time() . random_int(100, 999)),
            'username'   => $username,
            'password'   => password_hash($password, PASSWORD_DEFAULT),
            'role'       => 'tl',
            'tl_code'    => $tlCode,
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        return self::saveUsers($users);
    }

    public static function updateTeamLeader(string $id, string $username, string $password, string $tlCode, string $status): bool
    {
        $username = trim($username);
        $tlCode   = strtoupper(trim($tlCode));
        $status   = $status === 'inactive' ? 'inactive' : 'active';
        if ($username === '' || $tlCode === '') {
            throw new \InvalidArgumentException('Username dan kode team leader wajib diisi.');
        }

        $users = self::getUsers();
        $found = false;
        foreach ($users as $index => &$user) {
            if (($user['role'] ?? '') !== 'tl') {
                continue;
            }
            if (($user['id'] ?? $user['username'] ?? '') === $id) {
                $found             = true;
                $user['username']  = $username;
                $user['tl_code']   = $tlCode;
                $user['status']    = $status;
                if ($password !== '') {
                    $user['password'] = password_hash($password, PASSWORD_DEFAULT);
                }
                continue;
            }
            if (($user['username'] ?? '') === $username || ($user['tl_code'] ?? '') === $tlCode) {
                throw new \InvalidArgumentException('Username atau kode team leader sudah digunakan.');
            }
        }
        unset($user);

        if (!$found) {
            throw new \InvalidArgumentException('Akun team leader tidak ditemukan.');
        }
        return self::saveUsers($users);
    }

    public static function getUserByUsername(string $username): ?array
    {
        foreach (self::getUsers() as $user) {
            if (($user['username'] ?? '') === $username) {
                return $user;
            }
        }
        return null;
    }

    public static function updateProfile(string $currentUsername, string $newUsername, string $newPassword = ''): array
    {
        $currentUsername = trim($currentUsername);
        $newUsername     = trim($newUsername);
        if ($newUsername === '') {
            throw new \InvalidArgumentException('Username tidak boleh kosong.');
        }

        $users      = self::getUsers();
        $foundIndex = -1;

        foreach ($users as $index => $user) {
            if (($user['username'] ?? '') === $currentUsername) {
                $foundIndex = $index;
            } elseif (($user['username'] ?? '') === $newUsername) {
                throw new \InvalidArgumentException('Username "' . $newUsername . '" sudah digunakan oleh pengguna lain.');
            }
        }

        if ($foundIndex === -1) {
            // Superadmin belum ada di auth.json, buat baru
            $userObj = [
                'username' => $newUsername,
                'password' => $newPassword !== '' ? password_hash($newPassword, PASSWORD_DEFAULT) : password_hash('superadmin', PASSWORD_DEFAULT),
                'role'     => 'superadmin',
                'status'   => 'active',
            ];
            $users[] = $userObj;
            self::saveUsers($users);
            return $userObj;
        }

        $users[$foundIndex]['username'] = $newUsername;
        if ($newPassword !== '') {
            $users[$foundIndex]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        self::saveUsers($users);
        return $users[$foundIndex];
    }
}
