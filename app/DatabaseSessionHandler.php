<?php

declare(strict_types=1);

final class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $statement = $this->pdo->prepare('SELECT payload FROM sessions WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $payload = $statement->fetchColumn();
        return $payload === false ? '' : (string) $payload;
    }

    public function write(string $id, string $data): bool
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO sessions (id, user_id, payload, ip_address, user_agent, last_activity)
             VALUES (:id, :user_id, :payload, :ip, :agent, :activity)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), payload = VALUES(payload),
             ip_address = VALUES(ip_address), user_agent = VALUES(user_agent),
             last_activity = VALUES(last_activity), updated_at = CURRENT_TIMESTAMP'
        );

        return $statement->execute([
            'id' => $id,
            'user_id' => isset($_SESSION['auth_user_id']) ? (int) $_SESSION['auth_user_id'] : null,
            'payload' => $data,
            'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            'agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
            'activity' => time(),
        ]);
    }

    public function destroy(string $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM sessions WHERE id = :id');
        return $statement->execute(['id' => $id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $statement = $this->pdo->prepare('DELETE FROM sessions WHERE last_activity < :expires');
        $statement->execute(['expires' => time() - $max_lifetime]);
        return $statement->rowCount();
    }

    public function validateId(string $id): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM sessions WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        return (bool) $statement->fetchColumn();
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE sessions SET last_activity = :activity, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        return $statement->execute(['activity' => time(), 'id' => $id]);
    }
}

