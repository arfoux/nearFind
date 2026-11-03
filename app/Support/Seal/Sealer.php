<?php

namespace App\Support\Seal;

use Closure;
use JsonException;

final class Sealer
{
    public const PURPOSE_SESSION = 'session';

    private const TOKEN_PREFIX = 'stseal';

    private const TOKEN_VERSION = 'v1';

    private const ALGORITHM = 'A256GCM';

    /**
     * @var array<string, string>
     */
    private array $keys;

    private Closure $clock;

    /**
     * @param  array<string, string>  $keys
     */
    public function __construct(
        private readonly string $issuer,
        private readonly ?string $audience,
        array $keys,
        private readonly string $currentKeyId,
        private readonly int $clockToleranceMs = 0,
        private readonly int $maxTokenSize = 16384,
        ?Closure $clock = null,
    ) {
        $this->keys = [];

        foreach ($keys as $keyId => $encodedKey) {
            $this->keys[(string) $keyId] = $this->decodeKey((string) $encodedKey);
        }

        $this->clock = $clock ?? static fn (): int => (int) floor(microtime(true) * 1000);
    }

    public function seal(string $purpose, array $data, int|string $ttl = '1h'): string
    {
        $this->assertPurpose($purpose);

        if (! $this->isSafeIdentifier($this->issuer, 256)) {
            throw new SealException('invalid_policy', 'Issuer must be a safe identifier.');
        }

        if ($this->audience !== null && ! $this->isSafeIdentifier($this->audience, 256)) {
            throw new SealException('invalid_policy', 'Audience must be a safe identifier.');
        }

        $key = $this->keys[$this->currentKeyId] ?? null;

        if ($key === null) {
            throw new SealException('unknown_kid', 'The current key id does not exist.');
        }

        $now = ($this->clock)();
        $header = [
            'alg' => self::ALGORITHM,
            'kid' => $this->currentKeyId,
            'pur' => $purpose,
            'iss' => $this->issuer,
        ];

        if ($this->audience !== null) {
            $header['aud'] = $this->audience;
        }

        $body = [
            'iat' => $now,
            'exp' => $now + self::durationToMilliseconds($ttl),
            'data' => $data,
        ];

        try {
            $headerB64 = $this->base64UrlEncode(
                json_encode($header, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            );

            $plaintext = json_encode(
                $body,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new SealException('malformed_token', 'Unable to encode the token body.', $exception);
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::additionalAuthenticatedData($headerB64),
            16,
        );

        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new SealException('invalid_key', 'Unable to encrypt the token.');
        }

        $token = implode('.', [
            self::TOKEN_PREFIX,
            self::TOKEN_VERSION,
            $headerB64,
            $this->base64UrlEncode($iv),
            $this->base64UrlEncode($ciphertext.$tag),
        ]);

        if (strlen($token) > $this->maxTokenSize) {
            throw new SealException('token_too_large', 'The token exceeds the configured size limit.');
        }

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    public function unseal(string $purpose, string $token): array
    {
        if (strlen($token) > $this->maxTokenSize) {
            throw new SealException('token_too_large', 'The token exceeds the configured size limit.');
        }

        $segments = explode('.', $token);

        if (count($segments) !== 5 || $segments[0] !== self::TOKEN_PREFIX) {
            throw new SealException('malformed_token', 'The token format is invalid.');
        }

        if ($segments[1] !== self::TOKEN_VERSION) {
            throw new SealException('unsupported_version', 'The token version is not supported.');
        }

        [, , $headerB64, $ivB64, $ciphertextB64] = $segments;

        if ($headerB64 === '' || $ivB64 === '' || $ciphertextB64 === '') {
            throw new SealException('malformed_token', 'The token format is invalid.');
        }

        $header = $this->decodeHeader($headerB64);

        if (! isset($header['alg'])) {
            throw new SealException('malformed_token', 'The token header is invalid.');
        }

        if ($header['alg'] !== self::ALGORITHM) {
            throw new SealException('unsupported_algorithm', 'The token algorithm is not supported.');
        }

        $keyId = $header['kid'] ?? null;

        if (! $this->isSafeIdentifier($keyId, 128)) {
            throw new SealException('malformed_token', 'The token key id is invalid.');
        }

        if (($header['pur'] ?? null) !== $purpose) {
            throw new SealException('purpose_mismatch', 'The token purpose does not match.');
        }

        if (($header['iss'] ?? null) !== $this->issuer) {
            throw new SealException('issuer_mismatch', 'The token issuer does not match.');
        }

        $audience = $header['aud'] ?? null;

        if ($audience !== null && ! $this->isSafeIdentifier($audience, 256)) {
            throw new SealException('malformed_token', 'The token audience is invalid.');
        }

        if ($this->audience !== null && $audience !== $this->audience) {
            throw new SealException('audience_mismatch', 'The token audience does not match.');
        }

        $key = $this->keys[$keyId] ?? null;

        if ($key === null) {
            throw new SealException('unknown_kid', 'The token key id is unknown.');
        }

        $iv = $this->base64UrlDecode($ivB64);
        $ciphertext = $this->base64UrlDecode($ciphertextB64);

        if (strlen($iv) !== 12 || strlen($ciphertext) < 16) {
            throw new SealException('decrypt_failed', 'The token could not be decrypted.');
        }

        $plaintext = openssl_decrypt(
            substr($ciphertext, 0, -16),
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            substr($ciphertext, -16),
            self::additionalAuthenticatedData($headerB64),
        );

        if ($plaintext === false) {
            throw new SealException('decrypt_failed', 'The token could not be decrypted.');
        }

        $body = $this->decodeBody($plaintext);

        if (! isset($body['iat']) || ! is_int($body['iat'])) {
            throw new SealException('malformed_token', 'The token body is invalid.');
        }

        if (! isset($body['data']) || ! is_array($body['data'])) {
            throw new SealException('malformed_token', 'The token body is invalid.');
        }

        $now = ($this->clock)();

        if (! isset($body['exp']) || ! is_int($body['exp']) || $now - $this->clockToleranceMs > $body['exp']) {
            throw new SealException('expired', 'The token has expired.');
        }

        if (isset($body['nbf']) && (! is_int($body['nbf']) || $now + $this->clockToleranceMs < $body['nbf'])) {
            throw new SealException('not_yet_valid', 'The token is not yet valid.');
        }

        return $body['data'];
    }

    public static function durationToMilliseconds(int|string $duration): int
    {
        if (is_int($duration)) {
            if ($duration <= 0) {
                throw new SealException('invalid_policy', 'The duration must be positive.');
            }

            return $duration;
        }

        if (preg_match('/^(\d+)(ms|s|m|h|d)$/D', $duration, $matches) !== 1) {
            throw new SealException('invalid_policy', 'The duration format is invalid.');
        }

        $value = (int) $matches[1];

        if ($value <= 0) {
            throw new SealException('invalid_policy', 'The duration must be positive.');
        }

        return $value * match ($matches[2]) {
            'ms' => 1,
            's' => 1000,
            'm' => 60_000,
            'h' => 3_600_000,
            'd' => 86_400_000,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeHeader(string $headerB64): array
    {
        try {
            $header = json_decode($this->base64UrlDecode($headerB64), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new SealException('malformed_token', 'The token header is invalid.', $exception);
        }

        if (! is_array($header)) {
            throw new SealException('malformed_token', 'The token header is invalid.');
        }

        return $header;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(string $plaintext): array
    {
        try {
            $body = json_decode($plaintext, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new SealException('malformed_token', 'The token body is invalid.', $exception);
        }

        if (! is_array($body)) {
            throw new SealException('malformed_token', 'The token body is invalid.');
        }

        return $body;
    }

    private function decodeKey(string $encodedKey): string
    {
        try {
            $key = $this->base64UrlDecode($encodedKey);
        } catch (SealException $exception) {
            throw new SealException('invalid_key', 'The seal key is not valid base64url.', $exception);
        }

        if (strlen($key) !== 32) {
            throw new SealException('invalid_key', 'Seal keys must contain exactly 32 bytes.');
        }

        return $key;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $value) !== 1 || strlen($value) % 4 === 1) {
            throw new SealException('malformed_token', 'The token encoding is invalid.');
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new SealException('malformed_token', 'The token encoding is invalid.');
        }

        return $decoded;
    }

    private function assertPurpose(string $purpose): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9._:-]{0,127}$/D', $purpose) !== 1) {
            throw new SealException('invalid_policy', 'The token purpose is invalid.');
        }
    }

    private function isSafeIdentifier(mixed $value, int $maxLength): bool
    {
        return is_string($value)
            && strlen($value) >= 1
            && strlen($value) <= $maxLength
            && preg_match('/^[A-Za-z0-9._:\/@-]+$/D', $value) === 1;
    }

    private static function additionalAuthenticatedData(string $headerB64): string
    {
        return self::TOKEN_PREFIX.'.'.self::TOKEN_VERSION.'.'.$headerB64;
    }
}
