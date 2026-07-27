<?php

declare(strict_types=1);

final class Auth
{
    private ?array $user = null;
    private bool $resolved = false;
    private array $permissionCache = [];
    private array $ownerRoleCache = [];
    private ?bool $granularPermissionsAvailable = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?SecurityManager $security = null,
        private readonly ?TwoFactorManager $twoFactor = null,
    ) {
    }

    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $id = (int) ($_SESSION['auth_user_id'] ?? 0);
        if ($id < 1) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id, username, display_name, email, role, active, last_login_at, created_at
             FROM users WHERE id = :id AND active = 1 LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $this->user = $statement->fetch() ?: null;

        if ($this->user === null) {
            unset($_SESSION['auth_user_id']);
        }

        return $this->user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function loginUserById(int $userId, string $source = 'external'): bool
    {
        if ($userId < 1) {
            return false;
        }
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
        if ($this->security?->isIpBlocked($ip)) {
            $this->security->log(
                'authentication',
                'auth.external_login_blocked_ip',
                'warning',
                'Eine externe Anmeldung von einer gesperrten IP-Adresse wurde abgewiesen.',
                ['source' => mb_substr($source, 0, 40)],
                $userId,
            );
            return false;
        }
        $statement = $this->pdo->prepare(
            'SELECT id, username, display_name, email, role, active, last_login_at, created_at
             FROM users WHERE id = :id AND active = 1 LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();
        if (!is_array($user)) {
            return false;
        }

        if ($this->twoFactor?->isEnabled((int) $user['id'])) {
            $this->user = null;
            $this->resolved = true;
            $this->twoFactor->beginLoginChallenge((int) $user['id'], $source);
            return true;
        }

        return $this->establishSession($user, $source);
    }

    public function refreshUser(): ?array
    {
        $this->user = null;
        $this->resolved = false;
        $this->clearPermissionCache();
        return $this->user();
    }

    public function attempt(string $username, string $password): bool
    {
        $username = strtolower(trim($username));
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
        if ($this->security?->isIpBlocked($ip)) {
            $this->security->log(
                'authentication',
                'auth.blocked_ip',
                'warning',
                'Ein Loginversuch von einer gesperrten IP-Adresse wurde abgewiesen.',
                [],
                null,
                $username,
            );
            return false;
        }

        $rate = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE username = :username AND ip_address = :ip AND successful = 0
             AND attempted_at >= (UTC_TIMESTAMP() - INTERVAL 15 MINUTE)'
        );
        $rate->execute(['username' => $username, 'ip' => $ip]);
        if ((int) $rate->fetchColumn() >= 5) {
            $this->security?->log(
                'authentication',
                'auth.rate_limited',
                'warning',
                'Zu viele fehlgeschlagene Loginversuche.',
                ['window_minutes' => 15],
                null,
                $username,
            );
            return false;
        }

        $statement = $this->pdo->prepare('SELECT * FROM users WHERE username = :username AND active = 1 LIMIT 1');
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();
        $successful = is_array($user) && password_verify($password, (string) $user['password_hash']);

        $log = $this->pdo->prepare(
            'INSERT INTO login_attempts (username, ip_address, successful) VALUES (:username, :ip, :successful)'
        );
        $log->execute(['username' => $username, 'ip' => $ip, 'successful' => $successful ? 1 : 0]);

        if (!$successful) {
            password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
            $failedCount = $this->pdo->prepare(
                'SELECT COUNT(*) FROM login_attempts
                 WHERE username = :username AND ip_address = :ip AND successful = 0
                 AND attempted_at >= (UTC_TIMESTAMP() - INTERVAL 15 MINUTE)'
            );
            $failedCount->execute(['username' => $username, 'ip' => $ip]);
            $count = (int) $failedCount->fetchColumn();
            $this->security?->log(
                'authentication',
                'auth.login_failed',
                $count >= 5 ? 'warning' : 'info',
                'Eine Anmeldung ist fehlgeschlagen.',
                ['attempts_in_window' => $count],
                isset($user['id']) ? (int) $user['id'] : null,
                $username,
            );
            if ($count >= 5 && filter_var($ip, FILTER_VALIDATE_IP)) {
                try {
                    $this->security?->blockIp(
                        $ip,
                        'Automatische Sperre nach fünf fehlgeschlagenen Anmeldungen.',
                        15,
                        null,
                        'automatic',
                    );
                } catch (Throwable) {
                    // Die normale Login-Sperre nach Benutzer und IP bleibt dennoch aktiv.
                }
            }
            return false;
        }

        $this->pdo->prepare('DELETE FROM login_attempts WHERE username = :username AND ip_address = :ip AND successful = 0')
            ->execute(['username' => $username, 'ip' => $ip]);

        if ($this->twoFactor?->isEnabled((int) $user['id'])) {
            $this->user = null;
            $this->resolved = true;
            $this->twoFactor->beginLoginChallenge((int) $user['id'], 'password');
            return true;
        }

        return $this->establishSession($user, 'password');
    }

    public function hasPendingTwoFactor(): bool
    {
        return $this->twoFactor?->pendingLoginChallenge() !== null;
    }

    public function pendingTwoFactor(): ?array
    {
        return $this->twoFactor?->pendingLoginChallenge();
    }

    public function completeTwoFactorLogin(string $code): bool
    {
        $pending = $this->twoFactor?->verifyPendingLogin($code);
        if (!is_array($pending)) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'SELECT id, username, display_name, email, role, active, last_login_at, created_at
             FROM users WHERE id = :id AND active = 1 LIMIT 1'
        );
        $statement->execute(['id' => (int) $pending['user_id']]);
        $user = $statement->fetch();
        if (!is_array($user)) {
            return false;
        }

        $source = (string) ($pending['source'] ?? 'login');
        if (!$this->establishSession($user, $source)) {
            return false;
        }
        $this->security?->log(
            'authentication',
            'auth.two_factor_success',
            'info',
            'Die Zwei-Faktor-Anmeldung war erfolgreich.',
            ['source' => mb_substr($source, 0, 40)],
            (int) $user['id'],
            (string) $user['username'],
        );
        return true;
    }

    public function cancelTwoFactorLogin(): void
    {
        $this->twoFactor?->cancelLoginChallenge();
    }

    public function logout(): void
    {
        $this->user = null;
        $this->resolved = true;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    private function establishSession(array $user, string $source): bool
    {
        session_regenerate_id(true);
        $_SESSION['auth_user_id'] = (int) $user['id'];
        $this->user = $user;
        $this->resolved = true;
        $this->clearPermissionCache();
        $this->pdo->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = :id')
            ->execute(['id' => $user['id']]);

        $external = $source !== 'password';
        $this->security?->log(
            'authentication',
            $external ? 'auth.external_login_success' : 'auth.login_success',
            'info',
            $external ? 'Eine externe Anmeldung war erfolgreich.' : 'Eine Anmeldung war erfolgreich.',
            $external ? ['source' => mb_substr($source, 0, 40)] : [],
            (int) $user['id'],
            (string) $user['username'],
        );
        return true;
    }

    public function can(string $permission): bool
    {
        $role = (string) ($this->user()['role'] ?? 'guest');
        if ($role === 'guest' || !preg_match('/^[a-z][a-z0-9_.-]{1,79}$/', $role)) {
            return false;
        }

        if ($this->hasDynamicPermissions()) {
            if (!array_key_exists($role, $this->ownerRoleCache)) {
                $owner = $this->pdo->prepare('SELECT is_owner FROM roles WHERE role_key = :role LIMIT 1');
                $owner->execute(['role' => $role]);
                $ownerValue = $owner->fetchColumn();
                $this->ownerRoleCache[$role] = $ownerValue === false
                    ? $role === 'owner'
                    : (int) $ownerValue === 1;
            }
            if ($this->ownerRoleCache[$role]) {
                return true;
            }

            if (!$this->hasGranularPermissions()) {
                $legacyCandidates = $this->legacyPermissionCandidates($permission);
                if ($legacyCandidates === ['*']) {
                    return true;
                }
                if ($legacyCandidates !== []) {
                    $cacheKey = $role . ':legacy:' . $permission;
                    if (!array_key_exists($cacheKey, $this->permissionCache)) {
                        $placeholders = [];
                        $parameters = ['role' => $role];
                        foreach ($legacyCandidates as $index => $candidate) {
                            $placeholder = 'permission_' . $index;
                            $placeholders[] = ':' . $placeholder;
                            $parameters[$placeholder] = $candidate;
                        }
                        $statement = $this->pdo->prepare(
                            'SELECT COUNT(*) FROM role_permissions
                             WHERE role_key = :role
                             AND permission_key IN (' . implode(', ', $placeholders) . ')'
                        );
                        $statement->execute($parameters);
                        $this->permissionCache[$cacheKey] = (int) $statement->fetchColumn() > 0;
                    }
                    return $this->permissionCache[$cacheKey];
                }
            }

            $cacheKey = $role . ':' . $permission;
            if (!array_key_exists($cacheKey, $this->permissionCache)) {
                $statement = $this->pdo->prepare(
                    'SELECT COUNT(*) FROM role_permissions
                     WHERE role_key = :role AND permission_key = :permission'
                );
                $statement->execute(['role' => $role, 'permission' => $permission]);
                $this->permissionCache[$cacheKey] = (int) $statement->fetchColumn() > 0;
            }
            return $this->permissionCache[$cacheKey];
        }

        $permissions = [
            'owner' => ['*'],
            'admin' => [
                'content.write',
                'content.archive',
                'team.manage',
                'twitch.use',
                'twitch.configure',
                'settings.manage',
                'design.manage',
                'discord.studio',
                'audit.view',
            ],
            'moderator' => ['content.write', 'twitch.use'],
            'viewer' => [],
        ];

        $granted = $permissions[$role] ?? [];
        if (in_array('*', $granted, true) || in_array($permission, $granted, true)) {
            return true;
        }
        $legacyCandidates = $this->legacyPermissionCandidates($permission);
        if ($legacyCandidates === ['*']) {
            return true;
        }
        foreach ($legacyCandidates as $candidate) {
            if (in_array($candidate, $granted, true)) {
                return true;
            }
        }
        return false;
    }

    public function confirmPassword(string $password): bool
    {
        $user = $this->user();
        if ($user === null || $password === '') {
            return false;
        }
        $statement = $this->pdo->prepare('SELECT password_hash FROM users WHERE id = :id AND active = 1 LIMIT 1');
        $statement->execute(['id' => $user['id']]);
        $hash = $statement->fetchColumn();
        $valid = is_string($hash) && password_verify($password, $hash);
        $this->security?->log(
            'authorization',
            $valid ? 'auth.password_reconfirmed' : 'auth.password_reconfirmation_failed',
            $valid ? 'info' : 'warning',
            $valid
                ? 'Eine sicherheitskritische Aktion wurde per Passwort bestätigt.'
                : 'Die Passwortbestätigung für eine sicherheitskritische Aktion ist fehlgeschlagen.',
            [],
            (int) $user['id'],
            (string) $user['username'],
        );
        return $valid;
    }

    public function roleIsOwner(?string $role = null): bool
    {
        $role ??= (string) ($this->user()['role'] ?? '');
        if ($role === 'owner' && !$this->hasDynamicPermissions()) {
            return true;
        }
        if (!$this->hasDynamicPermissions()) {
            return false;
        }
        if (!array_key_exists($role, $this->ownerRoleCache)) {
            $statement = $this->pdo->prepare('SELECT is_owner FROM roles WHERE role_key = :role LIMIT 1');
            $statement->execute(['role' => $role]);
            $ownerValue = $statement->fetchColumn();
            $this->ownerRoleCache[$role] = $ownerValue === false
                ? $role === 'owner'
                : (int) $ownerValue === 1;
        }
        return $this->ownerRoleCache[$role];
    }

    public function clearPermissionCache(): void
    {
        $this->permissionCache = [];
        $this->ownerRoleCache = [];
        $this->granularPermissionsAvailable = null;
    }

    private function hasDynamicPermissions(): bool
    {
        static $available = null;
        if ($available !== null) {
            return $available;
        }
        try {
            $statement = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                 AND table_name IN ('roles', 'permissions', 'role_permissions')"
            );
            $available = (int) $statement->fetchColumn() === 3;
        } catch (Throwable) {
            $available = false;
        }
        return $available;
    }

    private function hasGranularPermissions(): bool
    {
        if ($this->granularPermissionsAvailable !== null) {
            return $this->granularPermissionsAvailable;
        }
        try {
            $statement = $this->pdo->prepare(
                'SELECT COUNT(*) FROM permissions WHERE permission_key = :permission'
            );
            $statement->execute(['permission' => 'dashboard.view']);
            $this->granularPermissionsAvailable = (int) $statement->fetchColumn() > 0;
        } catch (Throwable) {
            $this->granularPermissionsAvailable = false;
        }
        return $this->granularPermissionsAvailable;
    }

    private function legacyPermissionCandidates(string $permission): array
    {
        if (preg_match('/^module\.[a-z][a-z0-9-]{2,49}\.(?:view|use)$/', $permission)) {
            return ['*'];
        }
        if (in_array($permission, [
            'dashboard.view',
            'news.view',
            'ideas.view',
            'notes.view',
            'links.view',
            'twitch.view',
            'bansync.view',
            'twitch_users.view',
            'cases.view',
        ], true)) {
            return ['*'];
        }

        return match ($permission) {
            'news.create', 'news.edit',
            'ideas.create', 'ideas.edit',
            'notes.create', 'notes.edit',
            'links.create', 'links.edit',
            'cases.create', 'cases.edit' => ['content.write'],
            'news.archive', 'ideas.archive', 'notes.archive', 'links.archive' => ['content.archive'],
            'notes.private.view' => ['settings.manage'],
            'twitch.users.lookup', 'twitch.moderate', 'twitch.sync', 'bansync.execute' => ['twitch.use'],
            'twitch.connect', 'twitch.channels.select', 'bansync.configure' => ['twitch.configure'],
            'discord.view', 'discord.send', 'discord.templates.manage' => ['discord.studio', 'discord.configure'],
            'users.view', 'users.create', 'users.edit', 'users.disable', 'users.password.reset' => ['team.manage'],
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete', 'roles.assign' => ['roles.manage'],
            'settings.view' => ['settings.manage', 'updates.manage', 'migrations.manage'],
            'settings.general.manage', 'authentication.configure', 'smtp.configure' => ['settings.manage'],
            'github.configure' => ['updates.manage'],
            'design.view' => ['design.manage'],
            'modules.view', 'modules.configure', 'modules.install', 'modules.remove' => ['modules.manage'],
            'updates.view', 'updates.install', 'updates.rollback' => ['updates.manage'],
            'migrations.view', 'migrations.run' => ['migrations.manage'],
            'backups.view', 'backups.create', 'backups.download', 'backups.delete' => ['backups.manage', 'backups.restore'],
            'security.ip.manage', 'security.sessions.manage' => ['security.manage'],
            default => [],
        };
    }
}
