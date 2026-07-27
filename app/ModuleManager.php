<?php

declare(strict_types=1);

final class ModuleManager
{
    private const MAX_ARCHIVE_BYTES = 10_485_760;
    private const MAX_UNCOMPRESSED_BYTES = 41_943_040;
    private const MAX_ENTRY_BYTES = 10_485_760;
    private const MAX_ENTRIES = 500;

    private ?bool $readyCache = null;
    private ?array $rowsCache = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly AppSettings $settings,
        private readonly string $root,
    ) {
    }

    public static function builtIns(): array
    {
        return [
            'news' => ['name' => 'News & Ankündigungen', 'description' => 'Neuigkeiten für das Team.', 'pages' => ['news']],
            'ideas' => ['name' => 'Ideen', 'description' => 'Ideen-Board und Planung.', 'pages' => ['ideas']],
            'notes' => ['name' => 'Notizen', 'description' => 'Teamnotizen und Wissensablage.', 'pages' => ['notes']],
            'links' => ['name' => 'Links', 'description' => 'Geteilte Links und Ressourcen.', 'pages' => ['links']],
            'twitch' => ['name' => 'Twitch', 'description' => 'Twitch OAuth und Moderationswerkzeuge.', 'pages' => ['twitch', 'twitch-users', 'twitch-connect', 'twitch-callback']],
            'ban-sync' => ['name' => 'BanSync', 'description' => 'Kanalübergreifende Bans und Banlog.', 'pages' => ['ban-sync']],
            'cases' => ['name' => 'Moderationsfälle', 'description' => 'Interne Moderationsfälle.', 'pages' => ['cases']],
            'discord' => ['name' => 'Discord', 'description' => 'Discord Studio und Bot-Routing.', 'pages' => ['discord-studio']],
            'team' => ['name' => 'Team & Rechte', 'description' => 'Zugänge und Rollen.', 'pages' => ['team', 'roles']],
            'design' => ['name' => 'Design-Editor', 'description' => 'Branding und Seiteninhalte.', 'pages' => ['design']],
            'backups' => ['name' => 'Backup & Wiederherstellung', 'description' => 'Datenbank- und Dateisicherungen.', 'pages' => ['backups', 'backup-download']],
            'security' => ['name' => 'Sicherheitsbereich', 'description' => 'Systemprüfungen, Sperren und Sitzungen.', 'pages' => ['security']],
            'audit' => ['name' => 'Audit-Protokoll', 'description' => 'System- und Aktionsprotokoll.', 'pages' => ['audit']],
        ];
    }

    public function ready(): bool
    {
        if ($this->readyCache !== null) {
            return $this->readyCache;
        }
        try {
            $statement = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'modules'"
            );
            return $this->readyCache = (int) $statement->fetchColumn() > 0;
        } catch (Throwable) {
            return $this->readyCache = false;
        }
    }

    public function all(): array
    {
        if ($this->rowsCache !== null) {
            return $this->rowsCache;
        }

        $rows = [];
        if ($this->ready()) {
            try {
                foreach ($this->pdo->query('SELECT * FROM modules ORDER BY source, name')->fetchAll() as $row) {
                    $key = (string) $row['module_key'];
                    $row['manifest_data'] = $this->decodeManifest($row['manifest'] ?? null);
                    $rows[$key] = $row;
                }
            } catch (Throwable) {
                $rows = [];
            }
        }

        foreach (self::builtIns() as $key => $definition) {
            if (!isset($rows[$key])) {
                $rows[$key] = [
                    'module_key' => $key,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'version' => '1.0.0',
                    'source' => 'builtin',
                    'enabled' => 1,
                    'protected' => 0,
                    'directory_name' => null,
                    'manifest' => null,
                    'manifest_data' => [],
                ];
            }
        }
        uasort($rows, static fn (array $left, array $right): int => [$left['source'], $left['name']] <=> [$right['source'], $right['name']]);
        return $this->rowsCache = $rows;
    }

    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    public function isEnabled(string $key): bool
    {
        $row = $this->get($key);
        return $row !== null && (int) ($row['enabled'] ?? 0) === 1;
    }

    public function moduleForPage(string $page): ?string
    {
        foreach (self::builtIns() as $key => $definition) {
            if (in_array($page, $definition['pages'], true)) {
                return $key;
            }
        }
        if (str_starts_with($page, 'module-')) {
            $key = substr($page, 7);
            $module = $this->get($key);
            if ($module !== null && ($module['source'] ?? '') === 'custom') {
                return $key;
            }
        }
        return null;
    }

    public function customNavigation(): array
    {
        $navigation = [];
        foreach ($this->all() as $key => $module) {
            if (($module['source'] ?? '') !== 'custom' || (int) ($module['enabled'] ?? 0) !== 1) {
                continue;
            }
            $manifest = $module['manifest_data'] ?? [];
            $nav = is_array($manifest['navigation'] ?? null) ? $manifest['navigation'] : [];
            $navigation['module-' . $key] = [
                'icon' => mb_substr(trim((string) ($nav['icon'] ?? '⬡')), 0, 4) ?: '⬡',
                'label' => mb_substr(trim((string) ($nav['label'] ?? $module['name'])), 0, 45),
                'permission' => 'module.' . $key . '.view',
                'order' => max(0, min(999, (int) ($nav['order'] ?? 200))),
                'module' => $key,
            ];
        }
        return $navigation;
    }

    public function setEnabled(string $key, bool $enabled): void
    {
        if (!$this->ready()) {
            throw new RuntimeException('Bitte führe zuerst die Systemmigrationen aus.');
        }
        $module = $this->get($key);
        if ($module === null) {
            throw new RuntimeException('Das Modul wurde nicht gefunden.');
        }
        if ((int) ($module['protected'] ?? 0) === 1 && !$enabled) {
            throw new RuntimeException('Dieses Kernmodul kann nicht deaktiviert werden.');
        }
        $statement = $this->pdo->prepare('UPDATE modules SET enabled = :enabled WHERE module_key = :module_key');
        $statement->execute(['enabled' => $enabled ? 1 : 0, 'module_key' => $key]);
        $this->rowsCache = null;
    }

    public function saveConfiguration(string $key, array $source, int $userId): int
    {
        $module = $this->get($key);
        if ($module === null) {
            throw new RuntimeException('Das Modul wurde nicht gefunden.');
        }
        $fields = $this->configurationFields($module);
        $saved = 0;
        foreach ($fields as $field) {
            $fieldKey = (string) $field['key'];
            $settingKey = 'module.' . $key . '.' . $fieldKey;
            $type = (string) $field['type'];
            $raw = $type === 'boolean' ? (isset($source[$fieldKey]) ? 'true' : 'false') : trim((string) ($source[$fieldKey] ?? ''));
            if ($type === 'url' && $raw !== '' && (!filter_var($raw, FILTER_VALIDATE_URL) || strtolower((string) parse_url($raw, PHP_URL_SCHEME)) !== 'https')) {
                throw new InvalidArgumentException('„' . $field['label'] . '“ muss eine HTTPS-Adresse sein.');
            }
            if ($type === 'number' && $raw !== '' && !is_numeric($raw)) {
                throw new InvalidArgumentException('„' . $field['label'] . '“ muss eine Zahl sein.');
            }
            if ($type === 'select') {
                $allowed = array_map('strval', array_keys((array) ($field['options'] ?? [])));
                if ($raw !== '' && !in_array($raw, $allowed, true)) {
                    throw new InvalidArgumentException('Ungültige Auswahl für „' . $field['label'] . '“.');
                }
            }
            if ($type === 'password') {
                $this->settings->setSecretWhenProvided($settingKey, $raw, $userId);
            } else {
                $this->settings->set($settingKey, mb_substr($raw, 0, 4000), false, $userId);
            }
            $saved++;
        }
        return $saved;
    }

    public function configurationFields(array $module): array
    {
        $manifest = $module['manifest_data'] ?? [];
        $fields = is_array($manifest['settings'] ?? null) ? $manifest['settings'] : [];
        return array_values(array_filter($fields, static fn (mixed $field): bool => is_array($field)));
    }

    public function configurationValues(array $module): array
    {
        $values = [];
        foreach ($this->configurationFields($module) as $field) {
            $key = (string) $field['key'];
            $settingKey = 'module.' . (string) $module['module_key'] . '.' . $key;
            $values[$key] = [
                'value' => ($field['type'] ?? '') === 'password'
                    ? ''
                    : (string) $this->settings->get($settingKey, (string) ($field['default'] ?? '')),
                'secret_set' => $this->settings->hasValue($settingKey),
            ];
        }
        return $values;
    }

    public function customPageFile(string $key): ?string
    {
        $module = $this->get($key);
        if ($module === null || ($module['source'] ?? '') !== 'custom' || (int) $module['enabled'] !== 1) {
            return null;
        }
        $manifest = $module['manifest_data'] ?? [];
        $entry = (string) ($manifest['entry'] ?? 'page.php');
        $directory = (string) ($module['directory_name'] ?? $key);
        if (!$this->safeRelativePath($entry) || !preg_match('/\.php$/i', $entry)) {
            return null;
        }
        $file = $this->root . '/modules/' . $directory . '/' . $entry;
        return is_file($file) ? $file : null;
    }

    public function customAsset(string $key, string $relativePath): ?array
    {
        $module = $this->get($key);
        if ($module === null || ($module['source'] ?? '') !== 'custom' || (int) $module['enabled'] !== 1) {
            return null;
        }
        $relativePath = str_replace('\\', '/', trim($relativePath));
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'css' => 'text/css; charset=UTF-8',
            'js' => 'text/javascript; charset=UTF-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
        ];
        if (!$this->safeRelativePath($relativePath) || !isset($mimeTypes[$extension])) {
            return null;
        }
        $directory = (string) ($module['directory_name'] ?? $key);
        $base = realpath($this->root . '/modules/' . $directory);
        $file = realpath($this->root . '/modules/' . $directory . '/' . $relativePath);
        if ($base === false || $file === false || !is_file($file)) {
            return null;
        }
        $normalizedBase = rtrim(str_replace('\\', '/', $base), '/') . '/';
        $normalizedFile = str_replace('\\', '/', $file);
        if (!str_starts_with($normalizedFile, $normalizedBase)) {
            return null;
        }
        return [
            'path' => $file,
            'mime_type' => $mimeTypes[$extension],
            'size' => (int) (filesize($file) ?: 0),
            'etag' => hash_file('sha256', $file) ?: '',
        ];
    }

    public function installUploaded(array $upload, int $userId): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Für Modul-ZIPs wird die PHP-Erweiterung ZIP benötigt.');
        }
        if (!$this->ready()) {
            throw new RuntimeException('Bitte führe zuerst die Systemmigrationen aus.');
        }
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($error === UPLOAD_ERR_NO_FILE ? 'Bitte wähle ein Modul-ZIP aus.' : 'Das Modul-ZIP konnte nicht hochgeladen werden.');
        }
        $temporaryFile = (string) ($upload['tmp_name'] ?? '');
        $size = (int) ($upload['size'] ?? 0);
        if ($temporaryFile === '' || !is_uploaded_file($temporaryFile) || $size < 1 || $size > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('Das Modul-ZIP darf höchstens 10 MB groß sein.');
        }

        $workDirectory = $this->root . '/storage/module-work/' . bin2hex(random_bytes(12));
        $this->ensureDirectory($workDirectory);
        try {
            $manifest = $this->extractAndValidate($temporaryFile, $workDirectory);
            $key = (string) $manifest['key'];
            $existing = $this->get($key);
            if ($existing !== null && ($existing['source'] ?? '') !== 'custom') {
                throw new RuntimeException('Der Modulname kollidiert mit einem eingebauten Modul.');
            }
            if ($existing !== null && !version_compare((string) $manifest['version'], (string) $existing['version'], '>')) {
                throw new RuntimeException('Ein Modul-Update muss eine höhere Versionsnummer besitzen.');
            }

            $target = $this->root . '/modules/' . $key;
            $backup = null;
            $this->ensureDirectory($this->root . '/modules');
            if (is_dir($target)) {
                $backup = $this->root . '/storage/module-backups/' . $key . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
                $this->ensureDirectory(dirname($backup));
                if (!rename($target, $backup)) {
                    throw new RuntimeException('Das vorhandene Modul konnte nicht gesichert werden.');
                }
            }
            if (!rename($workDirectory . '/package', $target)) {
                if ($backup !== null) {
                    @rename($backup, $target);
                }
                throw new RuntimeException('Das Modul konnte nicht installiert werden.');
            }

            try {
                $statement = $this->pdo->prepare(
                    'INSERT INTO modules
                        (module_key, name, description, version, source, enabled, protected, directory_name, manifest, installed_by)
                     VALUES (:module_key, :name, :description, :version, \'custom\', 0, 0, :directory_name, :manifest, :installed_by)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description),
                        version = VALUES(version), source = \'custom\', enabled = 0, directory_name = VALUES(directory_name),
                        manifest = VALUES(manifest), installed_by = VALUES(installed_by), updated_at = UTC_TIMESTAMP()'
                );
                $statement->execute([
                    'module_key' => $key,
                    'name' => $manifest['name'],
                    'description' => $manifest['description'] !== '' ? $manifest['description'] : null,
                    'version' => $manifest['version'],
                    'directory_name' => $key,
                    'manifest' => json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'installed_by' => $userId,
                ]);
                $migrations = $this->runModuleMigrations($key, $target . '/migrations');
                $this->registerCustomModulePermissions($key, (string) $manifest['name'], $userId);
                $this->pdo->prepare('UPDATE modules SET enabled = 1 WHERE module_key = :module_key')->execute(['module_key' => $key]);
            } catch (Throwable $exception) {
                $this->removeTree($target, $this->root . '/modules/');
                if ($backup !== null) {
                    @rename($backup, $target);
                }
                try {
                    if ($existing !== null) {
                        $restore = $this->pdo->prepare(
                            'UPDATE modules SET name = :name, description = :description, version = :version,
                             source = :source, enabled = :enabled, protected = :protected,
                             directory_name = :directory_name, manifest = :manifest, installed_by = :installed_by
                             WHERE module_key = :module_key'
                        );
                        $restore->execute([
                            'name' => $existing['name'],
                            'description' => $existing['description'],
                            'version' => $existing['version'],
                            'source' => $existing['source'],
                            'enabled' => $existing['enabled'],
                            'protected' => $existing['protected'],
                            'directory_name' => $existing['directory_name'],
                            'manifest' => $existing['manifest'],
                            'installed_by' => $existing['installed_by'] ?? null,
                            'module_key' => $key,
                        ]);
                    } else {
                        $this->pdo->prepare('DELETE FROM modules WHERE module_key = :module_key AND source = \'custom\'')
                            ->execute(['module_key' => $key]);
                        $this->removeCustomModulePermissions($key);
                    }
                } catch (Throwable) {
                    // Der Installationsfehler bleibt maßgeblich; das Modul bleibt vorsichtshalber deaktiviert.
                }
                throw $exception;
            }

            $this->rowsCache = null;
            return ['key' => $key, 'name' => $manifest['name'], 'version' => $manifest['version'], 'migrations' => $migrations];
        } finally {
            $this->removeTree($workDirectory, $this->root . '/storage/module-work/');
        }
    }

    public function removeCustom(string $key): void
    {
        $module = $this->get($key);
        if ($module === null || ($module['source'] ?? '') !== 'custom') {
            throw new RuntimeException('Nur hochgeladene Module können entfernt werden.');
        }
        $target = $this->root . '/modules/' . (string) ($module['directory_name'] ?? $key);
        if (is_dir($target)) {
            $backup = $this->root . '/storage/module-backups/' . $key . '-removed-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
            $this->ensureDirectory(dirname($backup));
            if (!rename($target, $backup)) {
                throw new RuntimeException('Das Modul konnte nicht in die Sicherung verschoben werden.');
            }
        }
        $this->pdo->prepare('DELETE FROM modules WHERE module_key = :module_key AND source = \'custom\'')->execute(['module_key' => $key]);
        $this->removeCustomModulePermissions($key);
        $this->rowsCache = null;
    }

    private function registerCustomModulePermissions(string $key, string $name, int $userId): void
    {
        $tables = $this->pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE()
             AND table_name IN ('permissions', 'role_permissions')"
        );
        if ((int) $tables->fetchColumn() !== 2) {
            return;
        }
        $permission = $this->pdo->prepare(
            'INSERT IGNORE INTO permissions
                (permission_key, name, category, description, `sensitive`)
             VALUES
                (:view_key, :view_name, :view_category, :view_description, 0),
                (:use_key, :use_name, :use_category, :use_description, 1)'
        );
        $permission->execute([
            'view_key' => 'module.' . $key . '.view',
            'view_name' => $name . ' ansehen',
            'view_category' => 'Zusatzmodule',
            'view_description' => 'Das Zusatzmodul „' . $name . '“ im Panel öffnen.',
            'use_key' => 'module.' . $key . '.use',
            'use_name' => $name . ' verwenden',
            'use_category' => 'Zusatzmodule',
            'use_description' => 'Aktionen des Zusatzmoduls „' . $name . '“ ausführen.',
        ]);

        $role = $this->pdo->prepare('SELECT role FROM users WHERE id = :user_id AND active = 1 LIMIT 1');
        $role->execute(['user_id' => $userId]);
        $roleKey = (string) ($role->fetchColumn() ?: '');
        if (!preg_match('/^[a-z][a-z0-9_.-]{1,79}$/', $roleKey)) {
            return;
        }
        $assignment = $this->pdo->prepare(
            'INSERT IGNORE INTO role_permissions (role_key, permission_key)
             VALUES (:view_role_key, :view_key), (:use_role_key, :use_key)'
        );
        $assignment->execute([
            'view_role_key' => $roleKey,
            'view_key' => 'module.' . $key . '.view',
            'use_role_key' => $roleKey,
            'use_key' => 'module.' . $key . '.use',
        ]);
    }

    private function removeCustomModulePermissions(string $key): void
    {
        if (!preg_match('/^[a-z][a-z0-9-]{2,49}$/', $key)) {
            return;
        }
        try {
            $statement = $this->pdo->prepare(
                'DELETE FROM permissions WHERE permission_key IN (:view_key, :use_key)'
            );
            $statement->execute([
                'view_key' => 'module.' . $key . '.view',
                'use_key' => 'module.' . $key . '.use',
            ]);
        } catch (Throwable) {
            // Ältere Installationen besitzen die Berechtigungstabellen möglicherweise noch nicht.
        }
    }

    private function extractAndValidate(string $archivePath, string $workDirectory): array
    {
        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Das Modul-ZIP konnte nicht geöffnet werden.');
        }
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ENTRIES) {
                throw new RuntimeException('Das Modul-ZIP enthält zu viele oder keine Dateien.');
            }
            $entries = [];
            $totalSize = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if (!is_array($stat)) {
                    throw new RuntimeException('Ein Moduleintrag konnte nicht geprüft werden.');
                }
                $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
                if (!$this->safeRelativePath($name)) {
                    throw new RuntimeException('Das Modul-ZIP enthält einen unsicheren Pfad.');
                }
                $entrySize = (int) ($stat['size'] ?? 0);
                if ($entrySize < 0 || $entrySize > self::MAX_ENTRY_BYTES) {
                    throw new RuntimeException('Eine Moduldatei ist zu groß.');
                }
                $totalSize += $entrySize;
                if ($totalSize > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new RuntimeException('Das entpackte Modul überschreitet 40 MB.');
                }
                $operatingSystem = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($index, $operatingSystem, $attributes) && (($attributes >> 16) & 0xF000) === 0xA000) {
                    throw new RuntimeException('Symbolische Links sind in Modulen nicht erlaubt.');
                }
                $entries[] = ['name' => $name, 'size' => $entrySize];
            }

            $prefix = $this->detectPackagePrefix(array_column($entries, 'name'));
            $seen = [];
            $packageDirectory = $workDirectory . '/package';
            $this->ensureDirectory($packageDirectory);
            foreach ($entries as $entry) {
                $name = (string) $entry['name'];
                $relative = $prefix !== '' ? substr($name, strlen($prefix)) : $name;
                $relative = ltrim((string) $relative, '/');
                if ($relative === '') {
                    continue;
                }
                if (!$this->safeRelativePath($relative) || preg_match('#(^|/)\.htaccess$#i', $relative)) {
                    throw new RuntimeException('Das Modul enthält einen nicht erlaubten Dateipfad.');
                }
                $key = strtolower(rtrim($relative, '/'));
                if (isset($seen[$key])) {
                    throw new RuntimeException('Das Modul enthält einen Dateipfad mehrfach.');
                }
                $seen[$key] = true;
                if (str_ends_with($relative, '/')) {
                    $this->ensureDirectory($packageDirectory . '/' . rtrim($relative, '/'));
                    continue;
                }
                $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
                if (!in_array($extension, ['php', 'json', 'sql', 'css', 'js', 'md', 'txt', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], true)) {
                    throw new RuntimeException('Nicht erlaubter Dateityp im Modul: .' . $extension);
                }
                $target = $packageDirectory . '/' . $relative;
                $this->ensureDirectory(dirname($target));
                $source = $zip->getStream($name);
                $destination = fopen($target, 'wb');
                if (!is_resource($source) || !is_resource($destination)) {
                    if (is_resource($source)) fclose($source);
                    if (is_resource($destination)) fclose($destination);
                    throw new RuntimeException('Eine Moduldatei konnte nicht entpackt werden.');
                }
                $copied = stream_copy_to_stream($source, $destination);
                fclose($source);
                fclose($destination);
                if ($copied === false || $copied !== (int) $entry['size']) {
                    throw new RuntimeException('Eine Moduldatei wurde nicht vollständig entpackt.');
                }
            }
        } finally {
            $zip->close();
        }

        $manifestFile = $workDirectory . '/package/module.json';
        if (!is_file($manifestFile)) {
            throw new RuntimeException('Im Modul-ZIP fehlt module.json.');
        }
        $manifest = json_decode((string) file_get_contents($manifestFile), true, 32, JSON_THROW_ON_ERROR);
        return $this->validateManifest(is_array($manifest) ? $manifest : [], $workDirectory . '/package');
    }

    private function validateManifest(array $manifest, string $packageDirectory): array
    {
        $key = strtolower(trim((string) ($manifest['key'] ?? '')));
        $name = mb_substr(trim((string) ($manifest['name'] ?? '')), 0, 120);
        $description = mb_substr(trim((string) ($manifest['description'] ?? '')), 0, 500);
        $version = trim((string) ($manifest['version'] ?? ''));
        $entry = trim((string) ($manifest['entry'] ?? 'page.php'));
        if (!preg_match('/^[a-z][a-z0-9-]{2,49}$/', $key) || isset(self::builtIns()[$key])) {
            throw new RuntimeException('Der Modulschlüssel ist ungültig oder reserviert.');
        }
        if ($name === '' || !preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
            throw new RuntimeException('Modulname oder Versionsnummer ist ungültig.');
        }
        if (!$this->safeRelativePath($entry) || !preg_match('/\.php$/i', $entry) || !is_file($packageDirectory . '/' . $entry)) {
            throw new RuntimeException('Der PHP-Einstieg des Moduls fehlt oder ist ungültig.');
        }

        $navigation = is_array($manifest['navigation'] ?? null) ? $manifest['navigation'] : [];
        $navigation = [
            'label' => mb_substr(trim((string) ($navigation['label'] ?? $name)), 0, 45) ?: $name,
            'icon' => mb_substr(trim((string) ($navigation['icon'] ?? '⬡')), 0, 4) ?: '⬡',
            'order' => max(0, min(999, (int) ($navigation['order'] ?? 200))),
        ];

        $settings = [];
        foreach (array_slice((array) ($manifest['settings'] ?? []), 0, 30) as $field) {
            if (!is_array($field)) continue;
            $fieldKey = strtolower(trim((string) ($field['key'] ?? '')));
            $label = mb_substr(trim((string) ($field['label'] ?? '')), 0, 100);
            $type = (string) ($field['type'] ?? 'text');
            if (!preg_match('/^[a-z][a-z0-9_]{1,49}$/', $fieldKey) || $label === '' || !in_array($type, ['text', 'url', 'number', 'boolean', 'select', 'password'], true)) {
                throw new RuntimeException('Ein Einstellungsfeld in module.json ist ungültig.');
            }
            $normalized = ['key' => $fieldKey, 'label' => $label, 'type' => $type, 'default' => mb_substr((string) ($field['default'] ?? ''), 0, 1000)];
            if ($type === 'select') {
                $options = [];
                foreach (array_slice((array) ($field['options'] ?? []), 0, 50, true) as $optionValue => $optionLabel) {
                    $options[mb_substr((string) $optionValue, 0, 100)] = mb_substr((string) $optionLabel, 0, 100);
                }
                if ($options === []) throw new RuntimeException('Ein Auswahlfeld im Modul besitzt keine Optionen.');
                $normalized['options'] = $options;
            }
            $settings[] = $normalized;
        }

        return [
            'key' => $key,
            'name' => $name,
            'description' => $description,
            'version' => $version,
            'entry' => $entry,
            'navigation' => $navigation,
            'settings' => $settings,
        ];
    }

    private function runModuleMigrations(string $key, string $directory): array
    {
        if (!is_dir($directory)) return [];
        $knownStatement = $this->pdo->prepare('SELECT migration_name FROM module_migrations WHERE module_key = :module_key');
        $knownStatement->execute(['module_key' => $key]);
        $known = array_fill_keys(array_map('strval', $knownStatement->fetchAll(PDO::FETCH_COLUMN)), true);
        $files = glob($directory . '/*.sql') ?: [];
        sort($files, SORT_NATURAL);
        $applied = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (isset($known[$name])) continue;
            SchemaMigrator::executeFile($this->pdo, $file);
            $statement = $this->pdo->prepare('INSERT INTO module_migrations (module_key, migration_name) VALUES (:module_key, :migration_name)');
            $statement->execute(['module_key' => $key, 'migration_name' => $name]);
            $applied[] = $name;
        }
        return $applied;
    }

    private function detectPackagePrefix(array $names): string
    {
        if (in_array('module.json', $names, true)) return '';
        $prefixes = [];
        foreach ($names as $name) {
            if (preg_match('#^([^/]+)/module\.json$#', (string) $name, $match)) $prefixes[] = $match[1] . '/';
        }
        $prefixes = array_values(array_unique($prefixes));
        if (count($prefixes) !== 1) throw new RuntimeException('Der Modulordner im ZIP ist nicht eindeutig.');
        foreach ($names as $name) {
            if ($name !== rtrim($prefixes[0], '/') && !str_starts_with((string) $name, $prefixes[0])) {
                throw new RuntimeException('Das ZIP enthält Dateien außerhalb des Modulordners.');
            }
        }
        return $prefixes[0];
    }

    private function safeRelativePath(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) return false;
        foreach (explode('/', trim(str_replace('\\', '/', $path), '/')) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') return false;
        }
        return true;
    }

    private function decodeManifest(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') return [];
        try {
            $decoded = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Ordner konnte nicht angelegt werden: ' . basename($directory));
        }
    }

    private function removeTree(string $directory, string $allowedPrefix): void
    {
        $normalized = str_replace('\\', '/', $directory);
        $prefix = rtrim(str_replace('\\', '/', $allowedPrefix), '/') . '/';
        if (!is_dir($directory) || !str_starts_with($normalized . '/', $prefix)) return;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) @rmdir($item->getPathname()); else @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
