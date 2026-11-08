<?php

namespace App\Service;

final class Signature
{
    public static function sign(string $secret, string $method, string $uri, string $ts, string $nonce, string $body): string
    {
        return hash_hmac('sha256', implode("\n", [strtoupper($method), $uri, $ts, $nonce, $body]), $secret);
    }

    public static function verify(string $secret, string $method, string $uri, string $ts, string $nonce, string $body, string $given): bool
    {
        return hash_equals(self::sign($secret, $method, $uri, $ts, $nonce, $body), $given);
    }
}
