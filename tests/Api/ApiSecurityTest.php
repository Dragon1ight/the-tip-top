<?php

namespace App\Tests\Api;

use App\Service\Signature;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;

/** Sécurité de l'API, testée sur l'application réellement démarrée. */
final class ApiSecurityTest extends TestCase
{
    private function call(string $path, array $o = []): int
    {
        $base = (string) getenv('API_BASE');
        $secret = $o['secret'] ?? (string) getenv('API_HMAC_SECRET');
        $ts = (string) ($o['ts'] ?? time());
        $nonce = $o['nonce'] ?? bin2hex(random_bytes(16));
        $h = ['X-Timestamp' => $ts, 'X-Nonce' => $nonce, 'X-Signature' => Signature::sign($secret, 'GET', $path, $ts, $nonce, '')];

        return HttpClient::create()->request('GET', $base.$path, ['headers' => $o['nosign'] ?? false ? [] : $h])->getStatusCode();
    }

    public function testRequeteSigneeAcceptee(): void
    {
        self::assertSame(200, $this->call('/api/v1/lots'));
    }

    public function testSansSignatureRefusee(): void
    {
        self::assertSame(401, $this->call('/api/v1/lots', ['nosign' => true]));
    }

    public function testMauvaiseSignatureRefusee(): void
    {
        self::assertSame(401, $this->call('/api/v1/lots', ['secret' => 'mauvais-secret']));
    }

    public function testHorodatagePerimeRefuse(): void
    {
        self::assertSame(401, $this->call('/api/v1/lots', ['ts' => time() - 3600]));
    }

    public function testRejeuRefuse(): void
    {
        $n = bin2hex(random_bytes(16));
        self::assertSame(200, $this->call('/api/v1/lots', ['nonce' => $n]));
        self::assertSame(409, $this->call('/api/v1/lots', ['nonce' => $n]));
    }

    #[Depends('testRequeteSigneeAcceptee')]
    public function testLimiteDeDebit(): void
    {
        $last = 200;
        for ($i = 0; $i < 130 && 429 !== $last; ++$i) {
            $last = $this->call('/api/v1/lots');
        }
        self::assertSame(429, $last);
    }
}
