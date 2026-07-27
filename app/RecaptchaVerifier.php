<?php

declare(strict_types=1);

final class RecaptchaVerifier
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function __construct(private readonly AppSettings $settings)
    {
    }

    public function enabled(): bool
    {
        return $this->settings->bool('recaptcha_enabled', false);
    }

    public function configured(): bool
    {
        return trim($this->siteKey()) !== '' && $this->settings->hasValue('recaptcha_secret_key');
    }

    public function siteKey(): string
    {
        return trim((string) $this->settings->get('recaptcha_site_key', ''));
    }

    public function verify(string $responseToken, ?string $remoteIp = null): array
    {
        if (!$this->enabled()) {
            return ['success' => true, 'error_codes' => []];
        }
        if (!$this->configured()) {
            return ['success' => false, 'error_codes' => ['not-configured']];
        }
        $responseToken = trim($responseToken);
        if ($responseToken === '') {
            return ['success' => false, 'error_codes' => ['missing-input-response']];
        }

        $payload = [
            'secret' => (string) $this->settings->get('recaptcha_secret_key', ''),
            'response' => $responseToken,
        ];
        if ($remoteIp !== null && filter_var($remoteIp, FILTER_VALIDATE_IP)) {
            $payload['remoteip'] = $remoteIp;
        }

        $handle = curl_init(self::VERIFY_URL);
        if ($handle === false) {
            return ['success' => false, 'error_codes' => ['transport-unavailable']];
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $transportError = curl_error($handle);
        curl_close($handle);

        if (!is_string($body) || $body === '' || $status < 200 || $status >= 300) {
            return [
                'success' => false,
                'error_codes' => ['verification-unavailable'],
                'status' => $status,
                'transport_error' => $transportError !== '' ? $transportError : null,
            ];
        }

        try {
            $decoded = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return ['success' => false, 'error_codes' => ['invalid-verification-response']];
        }
        if (!is_array($decoded)) {
            return ['success' => false, 'error_codes' => ['invalid-verification-response']];
        }

        return [
            'success' => ($decoded['success'] ?? false) === true,
            'error_codes' => array_values(array_filter(
                (array) ($decoded['error-codes'] ?? []),
                'is_string',
            )),
            'hostname' => isset($decoded['hostname']) ? (string) $decoded['hostname'] : null,
            'challenge_ts' => isset($decoded['challenge_ts']) ? (string) $decoded['challenge_ts'] : null,
        ];
    }
}
