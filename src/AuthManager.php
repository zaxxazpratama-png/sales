<?php
namespace App;

class AuthManager
{
    private static function getPath(): string
    {
        return dirname(__DIR__) . '/data/auth.json';
    }

    public static function getUsers(): array
    {
        $path = self::getPath();
        if (!file_exists($path)) {
            return [];
        }
        $users = json_decode(file_get_contents($path), true);
        return is_array($users) ? $users : [];
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
                if (!str_starts_with($stored, '$2')) {
                    $users = self::getUsers();
                    $users[$index]['password'] = password_hash($password, PASSWORD_DEFAULT);
                    file_put_contents(self::getPath(), json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
                return [
                    'username' => $user['username'],
                    'role' => $user['role'] ?? 'admin',
                    'tl_code' => $user['tl_code'] ?? '',
                ];
            }
        }
        return null;
    }

    public static function addTeamLeader(string $username, string $password, string $tlCode): bool
    {
        $username = trim($username);
        $tlCode = strtoupper(trim($tlCode));
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
            'id' => (string)(time() . random_int(100, 999)),
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'tl',
            'tl_code' => $tlCode,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        return (bool)file_put_contents(self::getPath(), json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public static function updateTeamLeader(string $id, string $username, string $password, string $tlCode, string $status): bool
    {
        $username = trim($username);
        $tlCode = strtoupper(trim($tlCode));
        $status = $status === 'inactive' ? 'inactive' : 'active';
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
                $found = true;
                $user['username'] = $username;
                $user['tl_code'] = $tlCode;
                $user['status'] = $status;
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
        return (bool)file_put_contents(self::getPath(), json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
        $newUsername = trim($newUsername);
        if ($newUsername === '') {
            throw new \InvalidArgumentException('Username tidak boleh kosong.');
        }

        $users = self::getUsers();
        $foundIndex = -1;

        foreach ($users as $index => $user) {
            if (($user['username'] ?? '') === $currentUsername) {
                $foundIndex = $index;
            } elseif (($user['username'] ?? '') === $newUsername) {
                throw new \InvalidArgumentException('Username "' . $newUsername . '" sudah digunakan oleh pengguna lain.');
            }
        }

        if ($foundIndex === -1) {
            // Check if this is a default superadmin not yet written to auth.json
            $userObj = [
                'username' => $newUsername,
                'password' => $newPassword !== '' ? password_hash($newPassword, PASSWORD_DEFAULT) : password_hash('admin', PASSWORD_DEFAULT),
                'role' => 'superadmin',
                'status' => 'active'
            ];
            $users[] = $userObj;
            file_put_contents(self::getPath(), json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $userObj;
        }

        $users[$foundIndex]['username'] = $newUsername;
        if ($newPassword !== '') {
            $users[$foundIndex]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        file_put_contents(self::getPath(), json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $users[$foundIndex];
    }
}
