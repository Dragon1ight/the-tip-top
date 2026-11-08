<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/** Oriente chaque profil vers son espace ; un code saisi avant connexion est repris automatiquement. */
final class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        return new RedirectResponse($this->target($request, $token->getRoleNames()));
    }

    public function target(Request $request, array $roles): string
    {
        if (\in_array('ROLE_ADMIN', $roles, true)) {
            return $this->urls->generate('admin_home');
        }
        if (\in_array('ROLE_HUISSIER', $roles, true)) {
            return $this->urls->generate('tirage_journal');
        }
        if (\in_array('ROLE_EMPLOYE', $roles, true)) {
            return $this->urls->generate('shop_home');
        }

        return $this->urls->generate($request->getSession()->has('pending_code') ? 'play_pending' : 'my_gains');
    }
}
