<?php

namespace Tests\Unit;

use App\Support\Seal\Sealer;
use App\Support\Seal\SealException;
use Closure;
use PHPUnit\Framework\TestCase;

class SealerTest extends TestCase
{
    private const REFERENCE_KEY = 'AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8';

    private const REFERENCE_KID = '2026-05';

    private const REFERENCE_ISSUER = 'example-app';

    private const REFERENCE_AUDIENCE = 'web';

    private const REFERENCE_PURPOSE = 'password-reset';

    private const REFERENCE_NOW = 1_779_340_000_000;

    private const REFERENCE_TOKEN = 'stseal.v1.eyJhbGciOiJBMjU2R0NNIiwia2lkIjoiMjAyNi0wNSIsInB1ciI6InBhc3N3b3JkLXJlc2V0IiwiaXNzIjoiZXhhbXBsZS1hcHAiLCJhdWQiOiJ3ZWIifQ.ICEiIyQlJicoKSor.qRjPERi6ID8tS3v99SjEyeB53KyrogaWHNRWI369ZzpBvtw0vhUF6jjfCvMsaD3ymQiZ_f9OZAXsWF1oP9d4gukU_inr6nF-AXdUdiEzDRz0fj_IAgM';

    private const KEY_ID = '2026-11';

    private const NOW = 1_770_000_000_000;

    public function test_it_unseals_the_official_test_vector(): void
    {
        $this->assertSame(
            ['userId' => 'user_123'],
            $this->referenceSealer()->unseal(self::REFERENCE_PURPOSE, self::REFERENCE_TOKEN),
        );
    }

    public function test_it_round_trips_a_session_token(): void
    {
        $sealer = $this->sealer();

        $token = $sealer->seal(Sealer::PURPOSE_SESSION, [
            'email' => 'warga@nearfind.test',
            'name' => 'Warga nearFind',
        ], '12h');

        $this->assertStringStartsWith('stseal.v1.', $token);
        $this->assertSame([
            'email' => 'warga@nearfind.test',
            'name' => 'Warga nearFind',
        ], $sealer->unseal(Sealer::PURPOSE_SESSION, $token));
    }

    public function test_it_accepts_a_token_with_an_audience_when_the_policy_does_not_require_one(): void
    {
        $sealer = $this->referenceSealer(audience: null);

        $this->assertSame(
            ['userId' => 'user_123'],
            $sealer->unseal(self::REFERENCE_PURPOSE, self::REFERENCE_TOKEN),
        );
    }

    public function test_it_rejects_the_vector_for_a_different_purpose(): void
    {
        $this->assertSealReason('purpose_mismatch', fn () => $this->referenceSealer()->unseal('session', self::REFERENCE_TOKEN));
    }

    public function test_it_rejects_the_vector_for_a_different_issuer(): void
    {
        $sealer = $this->referenceSealer(issuer: 'another-app');

        $this->assertSealReason('issuer_mismatch', fn () => $sealer->unseal(self::REFERENCE_PURPOSE, self::REFERENCE_TOKEN));
    }

    public function test_it_rejects_the_vector_for_a_different_audience(): void
    {
        $sealer = $this->referenceSealer(audience: 'mobile');

        $this->assertSealReason('audience_mismatch', fn () => $sealer->unseal(self::REFERENCE_PURPOSE, self::REFERENCE_TOKEN));
    }

    public function test_it_rejects_an_expired_token(): void
    {
        $sealer = $this->referenceSealer(clock: static fn (): int => self::REFERENCE_NOW + 1_000_000);

        $this->assertSealReason('expired', fn () => $sealer->unseal(self::REFERENCE_PURPOSE, self::REFERENCE_TOKEN));
    }

    public function test_it_allows_expiry_within_the_clock_tolerance(): void
    {
        $sealer = $this->referenceSealer(
            clock: static fn (): int => self::REFERENCE_NOW + 1_000_000,
            clockToleranceMs: 1_000_000,
        );

        $this->assertSame(
            ['userId' => 'user_123'],
            $sealer->unseal(self::REFERENCE_PURPOSE, self::REFERENCE_TOKEN),
        );
    }

    public function test_it_rejects_an_unknown_key_id(): void
    {
        $sealer = new Sealer(
            issuer: self::REFERENCE_ISSUER,
            audience: self::REFERENCE_AUDIENCE,
            keys: ['2026-06' => self::REFERENCE_KEY],
            currentKeyId: self::REFERENCE_KID,
            clockToleranceMs: 0,
            clock: static fn (): int => self::REFERENCE_NOW,
        );

        $this->assertSealReason('unknown_kid', fn () => $sealer->unseal(self::REFERENCE_PURPOSE, self::REFERENCE_TOKEN));
    }

    public function test_it_rejects_a_tampered_ciphertext(): void
    {
        $segments = explode('.', self::REFERENCE_TOKEN);
        $replacement = $segments[4][8] === 'A' ? 'B' : 'A';
        $segments[4] = substr_replace($segments[4], $replacement, 8, 1);
        $token = implode('.', $segments);

        $this->assertSealReason('decrypt_failed', fn () => $this->referenceSealer()->unseal(self::REFERENCE_PURPOSE, $token));
    }

    public function test_it_rejects_a_tampered_header(): void
    {
        $segments = explode('.', self::REFERENCE_TOKEN);
        $header = '{"iss":"example-app","aud":"web","pur":"password-reset","kid":"2026-05","alg":"A256GCM"}';
        $segments[2] = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $token = implode('.', $segments);

        $this->assertSealReason('decrypt_failed', fn () => $this->referenceSealer()->unseal(self::REFERENCE_PURPOSE, $token));
    }

    public function test_it_rejects_a_malformed_token(): void
    {
        $sealer = $this->sealer();

        $this->assertSealReason('malformed_token', fn () => $sealer->unseal('session', 'nearfind'));
        $this->assertSealReason('malformed_token', fn () => $sealer->unseal('session', 'stseal.v1...'));
    }

    public function test_it_rejects_an_unsupported_version(): void
    {
        $this->assertSealReason('unsupported_version', fn () => $this->sealer()->unseal('session', 'stseal.v2.header.iv.ciphertext'));
    }

    public function test_it_rejects_a_token_that_is_too_large(): void
    {
        $sealer = $this->sealer(maxTokenSize: 64);

        $this->assertSealReason('token_too_large', fn () => $sealer->unseal(self::REFERENCE_PURPOSE, self::REFERENCE_TOKEN));
        $this->assertSealReason('token_too_large', fn () => $sealer->seal('session', ['email' => 'warga@nearfind.test']));
    }

    public function test_it_rejects_an_invalid_key(): void
    {
        $shortKey = rtrim(strtr(base64_encode(str_repeat('a', 31)), '+/', '-_'), '=');

        $this->assertSealReason('invalid_key', fn () => new Sealer(
            issuer: 'nearFind',
            audience: 'web',
            keys: ['local' => $shortKey],
            currentKeyId: 'local',
        ));
    }

    public function test_it_rejects_a_missing_current_key(): void
    {
        $sealer = $this->sealer(keys: ['2026-12' => self::REFERENCE_KEY], currentKeyId: 'missing');

        $this->assertSealReason('unknown_kid', fn () => $sealer->seal('session', []));
    }

    public function test_it_rejects_an_invalid_purpose(): void
    {
        $this->assertSealReason('invalid_policy', fn () => $this->sealer()->seal('Invalid Purpose', []));
    }

    public function test_it_converts_duration_units(): void
    {
        $this->assertSame(500, Sealer::durationToMilliseconds('500ms'));
        $this->assertSame(1_000, Sealer::durationToMilliseconds('1s'));
        $this->assertSame(60_000, Sealer::durationToMilliseconds('1m'));
        $this->assertSame(3_600_000, Sealer::durationToMilliseconds('1h'));
        $this->assertSame(86_400_000, Sealer::durationToMilliseconds('1d'));
        $this->assertSame(43_200_000, Sealer::durationToMilliseconds('12h'));
        $this->assertSame(2_592_000_000, Sealer::durationToMilliseconds('30d'));
        $this->assertSame(1_500, Sealer::durationToMilliseconds(1_500));
    }

    public function test_it_rejects_an_invalid_duration(): void
    {
        foreach (['', '0s', '1w', '1.5h', 'h'] as $duration) {
            $this->assertSealReason('invalid_policy', fn () => Sealer::durationToMilliseconds($duration));
        }

        $this->assertSealReason('invalid_policy', fn () => Sealer::durationToMilliseconds(0));
        $this->assertSealReason('invalid_policy', fn () => Sealer::durationToMilliseconds(-1));
    }

    private function referenceSealer(
        ?Closure $clock = null,
        string $issuer = self::REFERENCE_ISSUER,
        ?string $audience = self::REFERENCE_AUDIENCE,
        int $clockToleranceMs = 0,
    ): Sealer {
        return new Sealer(
            issuer: $issuer,
            audience: $audience,
            keys: [self::REFERENCE_KID => self::REFERENCE_KEY],
            currentKeyId: self::REFERENCE_KID,
            clockToleranceMs: $clockToleranceMs,
            clock: $clock ?? static fn (): int => self::REFERENCE_NOW,
        );
    }

    /**
     * @param  array<string, string>|null  $keys
     */
    private function sealer(
        ?Closure $clock = null,
        ?array $keys = null,
        string $currentKeyId = self::KEY_ID,
        int $maxTokenSize = 16384,
    ): Sealer {
        return new Sealer(
            issuer: 'nearFind',
            audience: 'web',
            keys: $keys ?? [self::KEY_ID => self::REFERENCE_KEY],
            currentKeyId: $currentKeyId,
            maxTokenSize: $maxTokenSize,
            clock: $clock ?? static fn (): int => self::NOW,
        );
    }

    private function assertSealReason(string $expectedReason, Closure $operation): void
    {
        try {
            $operation();
        } catch (SealException $exception) {
            $this->assertSame($expectedReason, $exception->reason);

            return;
        }

        $this->fail("Expected a SealException with reason [{$expectedReason}].");
    }
}
