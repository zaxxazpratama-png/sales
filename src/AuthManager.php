<?php
namespace App;

use PDO;

class AuthManager
{
    /**
     * Path file sumber (read-only di Vercel serverless)
     */
    private static function getSourcePath(): string
    {
        return dirname(__DIR__) . '/data/auth.json';
    }

    /**
     * Path untuk write fallback: /tmp jika filesystem read-only (Vercel)
     */
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
        return $tmpDir . '/auth.json';
    }

    /**
     * Baca users: Database MySQL -> /tmp -> /data/auth.json
     */
    public static function getUsers(): array
    {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT id, username, password, role, tl_code, status, created_at FROM users ORDER BY created_at ASC");
                return $stmt->fetchAll() ?: [];
            } catch (\Exception $e) {
                error_log("DB getUsers Error: " . $e->getMessage());
            }
        }

        $tmpPath    = sys_get_temp_dir() . '/formgoogle_data/auth.json';
        $sourcePath = self::getSourcePath();

        $path = file_exists($tmpPath) ? $tmpPath : $sourcePath;

        if (!file_exists($path)) {
            return [];
        }

        $users = json_decode(file_get_contents($path), true);
        return is_array($users) ? $users : [];
    }

    /**
     * Simpan users ke fallback JSON
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
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active' LIMIT 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user) {
                    $stored = (string)($user['password'] ?? '');
                    $valid = str_starts_with($stored, '$2')
                        ? password_verify($password, $stored)
                        : hash_equals($stored, $password);

                    if ($valid) {
                        if (!str_starts_with($stored, '$2')) {
                            $newHash = password_hash($password, PASSWORD_DEFAULT);
                            $upStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                            $upStmt->execute([$newHash, $user['id']]);
                        }
                        return [
                            'username' => $user['username'],
                            'role'     => $user['role'] ?? 'admin',
                            'tl_code'  => $user['tl_code'] ?? '',
                        ];
                    }
                }
                return null;
            } catch (\Exception $e) {
                error_log("DB authenticate Error: " . $e->getMessage());
            }
        }

        // Fallback JSON
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

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR tl_code = ? LIMIT 1");
                $checkStmt->execute([$username, $tlCode]);
                if ($checkStmt->fetch()) {
                    throw new \InvalidArgumentException('Username atau kode team leader sudah digunakan.');
                }

                $id = 'usr_' . time() . random_int(100, 999);
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insertStmt = $pdo->prepare("INSERT INTO users (id, username, password, role, tl_code, status, created_at) VALUES (?, ?, ?, 'tl', ?, 'active', NOW())");
                return $insertStmt->execute([$id, $username, $hash, $tlCode]);
            } catch (\InvalidArgumentException $iae) {
                throw $iae;
            } catch (\Exception $e) {
                error_log("DB addTeamLeader Error: " . $e->getMessage());
            }
        }

        // Fallback JSON
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

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR tl_code = ?) AND id != ? LIMIT 1");
                $checkStmt->execute([$username, $tlCode, $id]);
                if ($checkStmt->fetch()) {
                    throw new \InvalidArgumentException('Username atau kode team leader sudah digunakan.');
                }

                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, tl_code = ?, status = ? WHERE id = ? AND role = 'tl'");
                    return $stmt->execute([$username, $hash, $tlCode, $status, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, tl_code = ?, status = ? WHERE id = ? AND role = 'tl'");
                    return $stmt->execute([$username, $tlCode, $status, $id]);
                }
            } catch (\InvalidArgumentException $iae) {
                throw $iae;
            } catch (\Exception $e) {
                error_log("DB updateTeamLeader Error: " . $e->getMessage());
            }
        }

        // Fallback JSON
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
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
                $stmt->execute([$username]);
                $res = $stmt->fetch();
                return $res ?: null;
            } catch (\Exception $e) {
                error_log("DB getUserByUsername Error: " . $e->getMessage());
            }
        }

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

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND username != ? LIMIT 1");
                $checkStmt->execute([$newUsername, $currentUsername]);
                if ($checkStmt->fetch()) {
                    throw new \InvalidArgumentException('Username "' . $newUsername . '" sudah digunakan oleh pengguna lain.');
                }

                $user = self::getUserByUsername($currentUsername);
                if (!$user) {
                    $id = 'usr_' . time() . random_int(100, 999);
                    $hash = $newPassword !== '' ? password_hash($newPassword, PASSWORD_DEFAULT) : password_hash('superadmin', PASSWORD_DEFAULT);
                    $insertStmt = $pdo->prepare("INSERT INTO users (id, username, password, role, status, created_at) VALUES (?, ?, ?, 'superadmin', 'active', NOW())");
                    $insertStmt->execute([$id, $newUsername, $hash]);
                    return ['id' => $id, 'username' => $newUsername, 'role' => 'superadmin', 'status' => 'active'];
                }

                if ($newPassword !== '') {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ? WHERE username = ?");
                    $stmt->execute([$newUsername, $hash, $currentUsername]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE username = ?");
                    $stmt->execute([$newUsername, $currentUsername]);
                }

                return self::getUserByUsername($newUsername) ?: [];
            } catch (\InvalidArgumentException $iae) {
                throw $iae;
            } catch (\Exception $e) {
                error_log("DB updateProfile Error: " . $e->getMessage());
            }
        }

        // Fallback JSON
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
