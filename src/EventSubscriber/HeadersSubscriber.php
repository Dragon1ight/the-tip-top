<?php

namespace App\EventSubscriber;

use App\Service\Site;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** En-têtes de sécurité et règle SEO « une seule version indexée ». */
final class HeadersSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Site $site)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $r = $event->getResponse();
        $h = $r->headers;
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');
        $h->set('Content-Security-Policy', "default-src 'self'; img-src 'self' data: https://www.google-analytics.com https://www.googletagmanager.com; script-src 'self' https://www.googletagmanager.com; style-src 'self'; font-src 'self'; connect-src 'self' https://www.google-analytics.com https://region1.google-analytics.com; frame-ancestors 'none'; base-uri 'self'; form-action 'self' https://accounts.google.com https://www.facebook.com");
        if ($event->getRequest()->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000');
        }
        $host = (string) parse_url($this->site->url(), PHP_URL_HOST);
        $private = (bool) preg_match('#^/(admin|boutique|mes-|journal-tirage|connexion|inscription|mot-de-passe|reinitialiser|valider-email|api)#', $event->getRequest()->getPathInfo());
        if ($private || !$this->site->isProd() || $event->getRequest()->getHost() !== $host) {
            $h->set('X-Robots-Tag', 'noindex, nofollow');
        }
    }
}
