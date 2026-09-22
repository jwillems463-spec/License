<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public const ROLES = ['admin', 'editor'];

    /** Permissions granted to each role. */
    private const PERMISSIONS = [
        'admin'  => ['*'],
        'editor' => ['vehicles.view', 'vehicles.create', 'vehicles.update',
                     'brands.view', 'brands.create', 'brands.update',
                     'uploads.create', 'dashboard.view'],
    ];

    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    private static ?array $user = null;
    private static bool $loaded = false;

    /** The logged-in user (fresh from DB each request so role/active changes apply instantly). */
    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = (int) ($_SESSION['user_id'] ?? 0);
            if ($id > 0) {
                $u = Database::one(
                    'SELECT id, name, email, role, is_active, must_change_password, last_login_at FROM users WHERE id = ?',
                    [$id]
                );
                if ($u && (int) $u['is_active'] === 1) {
                    self::$user = self::present($u);
                } else {
                    unset($_SESSION['user_id']);
                }
            }
        }
        return self::$user;
    }

    public static function present(array $u): array
    {
        return [
            'id'                   => (int) $u['id'],
            'name'                 => $u['name'],
            'email'                => $u['email'],
            'role'                 => $u['role'],
            'must_change_password' => (bool) $u['must_change_password'],
            'last_login_at'        => $u['last_login_at'] ?? null,
            'permissions'          => self::PERMISSIONS[$u['role']] ?? [],
        ];
    }

    public static function can(string $permission): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        $perms = self::PERMISSIONS[$u['role']] ?? [];
        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }

    public static function attempt(string $email, string $password): array
    {
        $ip = client_ip();
        $recent = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > (NOW() - INTERVAL ' . self::LOCK_MINUTES . ' MINUTE)',
            [$ip]
        );
        if ($recent >= self::MAX_ATTEMPTS) {
            throw new HttpException(429, 'Too many failed login attempts. Try again in ' . self::LOCK_MINUTES . ' minutes.');
        }

        $u = Database::one('SELECT * FROM users WHERE email = ?', [strtolower(trim($email))]);
        if (!$u || !password_verify($password, $u['password_hash']) || (int) $u['is_active'] !== 1) {
            Database::query('INSERT INTO login_attempts (ip_address, email) VALUES (?, ?)', [$ip, substr($email, 0, 190)]);
            throw new HttpException(401, 'Invalid email or password.');
        }

        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            Database::update('users', (int) $u['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        }

        Database::query('DELETE FROM login_attempts WHERE ip_address = ? OR attempted_at < (NOW() - INTERVAL 1 DAY)', [$ip]);
        Database::query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$u['id']]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $u['id'];
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        self::$loaded = false;

        Audit::log('login', 'user', (int) $u['id'], 'Signed in');
        return self::user();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::$user = null;
        self::$loaded = true;
    }

    // ---- Middleware -------------------------------------------------------

    /** Require a logged-in user. */
    public static function requireLogin(): callable
    {
        return static function (): void {
            if (!self::user()) {
                throw new HttpException(401, 'Authentication required.');
            }
        };
    }

    /** Require a logged-in user who has already replaced a temporary password. */
    public static function requireReady(): callable
    {
        return static function (): void {
            $u = self::user();
            if (!$u) {
                throw new HttpException(401, 'Authentication required.');
            }
            if ($u['must_change_password']) {
                throw new HttpException(403, 'You must change your password before continuing.');
            }
        };
    }

    public static function requirePermission(string $permission): callable
    {
        return static function () use ($permission): void {
            if (!self::can($permission)) {
                throw new HttpException(403, 'You do not have permission to perform this action.');
            }
        };
    }

    /** CSRF check for state-changing requests. */
    public static function csrf(): callable
    {
        return static function (Request $req): void {
            if (in_array($req->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
                return;
            }
            if (!Session::verifyCsrf($req->header('X-CSRF-Token'))) {
                throw new HttpException(403, 'Your session has expired. Please reload the page.', [], 'csrf_mismatch');
            }
        };
    }
}
