<?php

declare(strict_types=1);

final class SmtpException extends RuntimeException
{
    public function __construct(string $message, public readonly int $smtpCode = 0)
    {
        parent::__construct($message, $smtpCode);
    }
}

final class SmtpMailer
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly AppSettings $settings,
    ) {
    }

    public function isConfigured(): bool
    {
        return trim((string) $this->settings->get('smtp_host', '')) !== ''
            && filter_var((string) $this->settings->get('smtp_from_email', ''), FILTER_VALIDATE_EMAIL) !== false;
    }

    public function sendTest(string $recipient): void
    {
        $this->send(
            $recipient,
            'Twitch ModDesk – SMTP-Test erfolgreich',
            "Hallo,\n\nDiese Testmail bestätigt, dass dein SMTP-Server im Twitch ModDesk funktioniert.\n\nZeitpunkt: "
                . gmdate('d.m.Y H:i') . " UTC\n",
            'test',
        );
    }

    public function send(string $recipient, string $subject, string $body, string $eventKey = 'mail'): void
    {
        try {
            $code = $this->sendInternal($recipient, $subject, $body);
            $this->logDelivery($eventKey, $recipient, true, $code, null, ['subject' => $subject]);
        } catch (SmtpException $exception) {
            $this->logDelivery(
                $eventKey,
                $recipient,
                false,
                $exception->smtpCode > 0 ? $exception->smtpCode : null,
                $exception->getMessage(),
                ['subject' => $subject],
            );
            throw $exception;
        }
    }

    private function sendInternal(string $recipient, string $subject, string $body): int
    {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new SmtpException('Die Empfängeradresse ist ungültig.');
        }
        foreach ([$recipient, $subject] as $headerValue) {
            if (preg_match('/[\r\n]/', $headerValue)) {
                throw new SmtpException('Ungültige Zeichen in den E-Mail-Kopfdaten.');
            }
        }

        $host = trim((string) $this->settings->get('smtp_host', ''));
        $port = max(1, min(65535, (int) $this->settings->get('smtp_port', 587)));
        $encryption = (string) $this->settings->get('smtp_encryption', 'tls');
        $authMode = (string) $this->settings->get('smtp_auth', 'login');
        $username = (string) $this->settings->get('smtp_username', '');
        $password = (string) $this->settings->get('smtp_password', '');
        $fromEmail = trim((string) $this->settings->get('smtp_from_email', ''));
        $fromName = trim((string) $this->settings->get('smtp_from_name', 'Twitch ModDesk'));

        if ($host === '' || str_contains($host, '://') || preg_match('/[\s\r\n]/', $host)) {
            throw new SmtpException('Der SMTP-Hostname ist ungültig.');
        }
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            throw new SmtpException('Ungültige SMTP-Verschlüsselung.');
        }
        if (!in_array($authMode, ['login', 'plain', 'none'], true)) {
            throw new SmtpException('Ungültige SMTP-Anmeldemethode.');
        }
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $fromName)) {
            throw new SmtpException('Die SMTP-Absenderdaten sind ungültig.');
        }
        if ($authMode !== 'none' && ($username === '' || $password === '')) {
            throw new SmtpException('Für die SMTP-Anmeldung fehlen Benutzername oder Passwort.');
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'peer_name' => $host,
            ],
        ]);
        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $errorNumber = 0;
        $errorMessage = '';
        $stream = @stream_socket_client(
            $remote,
            $errorNumber,
            $errorMessage,
            15,
            STREAM_CLIENT_CONNECT,
            $context,
        );
        if (!is_resource($stream)) {
            throw new SmtpException('SMTP-Verbindung fehlgeschlagen: ' . ($errorMessage ?: 'Server nicht erreichbar.'));
        }

        stream_set_timeout($stream, 20);
        try {
            $this->expect($this->readResponse($stream), [220], 'SMTP-Server hat die Verbindung nicht angenommen.');
            $hostname = preg_replace('/[^a-z0-9.-]/i', '', (string) gethostname()) ?: 'localhost';
            $this->command($stream, 'EHLO ' . $hostname, [250], 'SMTP-EHLO wurde abgelehnt.');

            if ($encryption === 'tls') {
                $this->command($stream, 'STARTTLS', [220], 'SMTP-Server unterstützt STARTTLS nicht.');
                $cryptoEnabled = stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($cryptoEnabled !== true) {
                    throw new SmtpException('Die verschlüsselte SMTP-Verbindung konnte nicht aufgebaut werden.');
                }
                $this->command($stream, 'EHLO ' . $hostname, [250], 'SMTP-EHLO nach STARTTLS wurde abgelehnt.');
            }

            if ($authMode === 'login') {
                $this->command($stream, 'AUTH LOGIN', [334], 'SMTP AUTH LOGIN wird nicht akzeptiert.');
                $this->command($stream, base64_encode($username), [334], 'SMTP-Benutzername wurde abgelehnt.');
                $this->command($stream, base64_encode($password), [235], 'SMTP-Anmeldung ist fehlgeschlagen.');
            } elseif ($authMode === 'plain') {
                $credentials = base64_encode("\0" . $username . "\0" . $password);
                $this->command($stream, 'AUTH PLAIN ' . $credentials, [235], 'SMTP-Anmeldung ist fehlgeschlagen.');
            }

            $this->command($stream, 'MAIL FROM:<' . $fromEmail . '>', [250], 'SMTP-Absender wurde abgelehnt.');
            $this->command($stream, 'RCPT TO:<' . $recipient . '>', [250, 251], 'SMTP-Empfänger wurde abgelehnt.');
            $this->command($stream, 'DATA', [354], 'SMTP-Server nimmt keine Nachrichtendaten an.');

            $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
            $encodedFromName = mb_encode_mimeheader($fromName, 'UTF-8', 'B', "\r\n");
            $normalizedBody = preg_replace("/\r\n|\r|\n/", "\r\n", $body) ?? $body;
            $normalizedBody = preg_replace('/^\./m', '..', $normalizedBody) ?? $normalizedBody;
            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . $encodedFromName . ' <' . $fromEmail . '>',
                'To: <' . $recipient . '>',
                'Subject: ' . $encodedSubject,
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];
            fwrite($stream, implode("\r\n", $headers) . "\r\n\r\n" . $normalizedBody . "\r\n.\r\n");
            [$code, $response] = $this->readResponse($stream);
            $this->expect([$code, $response], [250], 'SMTP-Server hat die E-Mail nicht angenommen.');
            @fwrite($stream, "QUIT\r\n");
            return $code;
        } finally {
            fclose($stream);
        }
    }

    private function command($stream, string $command, array $expectedCodes, string $fallback): array
    {
        fwrite($stream, $command . "\r\n");
        $response = $this->readResponse($stream);
        $this->expect($response, $expectedCodes, $fallback);
        return $response;
    }

    private function readResponse($stream): array
    {
        $lines = [];
        $code = 0;
        do {
            $line = fgets($stream, 4096);
            if ($line === false) {
                $meta = stream_get_meta_data($stream);
                throw new SmtpException(!empty($meta['timed_out']) ? 'SMTP-Zeitüberschreitung.' : 'SMTP-Verbindung wurde unerwartet beendet.');
            }
            $lines[] = rtrim($line, "\r\n");
            if (preg_match('/^(\d{3})([ -])/', $line, $match)) {
                $code = (int) $match[1];
                $continued = $match[2] === '-';
            } else {
                $continued = false;
            }
        } while ($continued);

        return [$code, implode(' ', $lines)];
    }

    private function expect(array $response, array $expectedCodes, string $fallback): void
    {
        [$code, $message] = $response;
        if (!in_array((int) $code, $expectedCodes, true)) {
            throw new SmtpException($fallback . ' ' . mb_substr((string) $message, 0, 500), (int) $code);
        }
    }

    private function logDelivery(
        string $eventKey,
        string $destination,
        bool $success,
        ?int $status,
        ?string $error,
        array $payload,
    ): void {
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO integration_deliveries
                    (provider, event_key, destination, success, response_status, error_message, payload)
                 VALUES (\'smtp\', :event_key, :destination, :success, :response_status, :error_message, :payload)'
            );
            $statement->execute([
                'event_key' => mb_substr($eventKey, 0, 80),
                'destination' => mb_substr($destination, 0, 190),
                'success' => $success ? 1 : 0,
                'response_status' => $status,
                'error_message' => $error !== null ? mb_substr($error, 0, 1000) : null,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
        } catch (Throwable) {
            // Die E-Mail-Zustellung bleibt unabhängig vom internen Protokoll.
        }
    }
}
