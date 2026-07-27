<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/Env.php';
Env::load(dirname(__DIR__) . '/.env');
require_once dirname(__DIR__) . '/app/Database.php';
require_once dirname(__DIR__) . '/app/SchemaMigrator.php';

$options = getopt('', ['username:', 'display-name:', 'email:', 'password:']);

function ask(string $question, ?string $default = null): string
{
    $suffix = $default !== null ? " [$default]" : '';
    fwrite(STDOUT, $question . $suffix . ': ');
    $answer = trim((string) fgets(STDIN));
    return $answer !== '' ? $answer : (string) $default;
}

function askPassword(): string
{
    fwrite(STDOUT, 'Admin-Passwort (mindestens 12 Zeichen): ');
    $hidden = DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec');
    if ($hidden) {
        shell_exec('stty -echo');
    }
    $password = trim((string) fgets(STDIN));
    if ($hidden) {
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    }
    return $password;
}

try {
    $pdo = Database::connection();
    SchemaMigrator::executeFile($pdo, dirname(__DIR__) . '/database/schema.sql');
    SchemaMigrator::migrate($pdo, dirname(__DIR__) . '/database/migrations');

    fwrite(STDOUT, "✓ Datenbanktabellen sind bereit.\n\n");

    $username = strtolower(trim((string) ($options['username'] ?? ask('Admin-Benutzername', 'admin'))));
    $displayName = trim((string) ($options['display-name'] ?? ask('Anzeigename', 'Administrator')));
    $email = trim((string) ($options['email'] ?? ask('E-Mail (optional)', ''))) ?: null;
    $password = (string) ($options['password'] ?? getenv('ADMIN_PASSWORD') ?: askPassword());

    if (!preg_match('/^[a-z0-9_.-]{3,50}$/', $username)) {
        throw new InvalidArgumentException('Der Benutzername muss 3–50 Zeichen lang sein und darf Buchstaben, Zahlen, Punkt, Minus und Unterstrich enthalten.');
    }
    if ($displayName === '') {
        throw new InvalidArgumentException('Der Anzeigename darf nicht leer sein.');
    }
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Die E-Mail-Adresse ist ungültig.');
    }
    if (strlen($password) < 12) {
        throw new InvalidArgumentException('Das Passwort muss mindestens 12 Zeichen haben.');
    }

    $exists = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $exists->execute(['username' => $username]);
    if ($exists->fetchColumn()) {
        fwrite(STDOUT, "ℹ Der Zugang '$username' existiert bereits und wurde nicht überschrieben.\n");
        exit(0);
    }

    $insert = $pdo->prepare(
        "INSERT INTO users (username, display_name, email, password_hash, role, active)
         VALUES (:username, :display_name, :email, :password_hash, 'owner', 1)"
    );
    $insert->execute([
        'username' => $username,
        'display_name' => $displayName,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    fwrite(STDOUT, "✓ Owner-Zugang '$username' wurde erstellt.\n");
    fwrite(STDOUT, "✓ Installation abgeschlossen. Du kannst das Panel jetzt öffnen.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Fehler: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
