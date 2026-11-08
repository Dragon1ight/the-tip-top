<?php

namespace App\Service;

/** Configuration du site, lue dans les variables d'environnement : aucun secret dans le code. */
final class Site
{
    public function env(string $key, ?string $default = null): ?string
    {
        $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return (false === $v || '' === $v || null === $v) ? $default : (string) $v;
    }

    public function url(): string
    {
        return rtrim($this->env('SITE_URL', 'http://localhost:8080'), '/');
    }

    public function isProd(): bool
    {
        return 'prod' === $this->env('SITE_ENV', 'dev');
    }

    public function start(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->env('GAME_START', '2026-11-16 00:00:00'), new \DateTimeZone('Europe/Paris'));
    }

    public function end(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->env('GAME_END', '2026-12-15 23:59:59'), new \DateTimeZone('Europe/Paris'));
    }

    public function claimEnd(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->env('GAME_CLAIM_END', '2027-01-14 23:59:59'), new \DateTimeZone('Europe/Paris'));
    }

    public function forceOpen(): bool
    {
        return '1' === $this->env('GAME_FORCE_OPEN', '0');
    }

    public function isOpen(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));

        return $this->forceOpen() || ($now >= $this->start() && $now <= $this->claimEnd());
    }

    public function drawAllowed(): bool
    {
        return $this->forceOpen() || new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')) > $this->claimEnd();
    }

    public function gaId(): ?string
    {
        return $this->env('GA_ID');
    }

    public function sso(string $provider): bool
    {
        return (bool) $this->env(strtoupper($provider).'_CLIENT_ID') && (bool) $this->env(strtoupper($provider).'_CLIENT_SECRET');
    }

    public function dateFr(\DateTimeInterface $d): string
    {
        static $m = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return $d->format('j').' '.$m[(int) $d->format('n') - 1].' '.$d->format('Y');
    }
}
