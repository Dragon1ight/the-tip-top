<?php

namespace App\Controller;

use App\Security\AppUser;
use App\Security\AppUserProvider;
use App\Security\LoginSuccessHandler;
use App\Service\Db;
use App\Service\Mail;
use App\Service\Site;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AuthController extends AbstractController
{
    public function __construct(private readonly Db $db, private readonly Mail $mail, private readonly Site $site)
    {
    }

    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $utils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        return $this->render('auth/login.html.twig', ['last' => $utils->getLastUsername(), 'error' => $utils->getLastAuthenticationError(), 'tab' => 'login', 'form' => []]);
    }

    #[Route('/deconnexion', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Géré par le pare-feu.');
    }

    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $r, UserPasswordHasherInterface $hasher, Security $security): Response
    {
        if (!$r->isMethod('POST')) {
            return $this->render('auth/login.html.twig', ['tab' => 'register', 'form' => [], 'errors' => [], 'last' => '', 'error' => null]);
        }
        if (!$this->isCsrfTokenValid('register', (string) $r->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $f = array_map(static fn ($v) => is_string($v) ? trim($v) : $v, $r->request->all());
        $errors = $this->validate($f);
        $email = mb_strtolower((string) ($f['email'] ?? ''));
        if (!isset($errors['email']) && $this->db->val('SELECT 1 FROM participant WHERE email = ?', [$email])) {
            $errors['email'] = 'Cette adresse est déjà associée à un compte. Connectez-vous ou utilisez une autre adresse.';
        }
        if ($errors) {
            unset($f['password']);

            return $this->render('auth/login.html.twig', ['tab' => 'register', 'form' => $f, 'errors' => $errors, 'last' => '', 'error' => null], new Response('', 422));
        }
        $probe = new AppUser('participant', 0, $email, null, '', ['ROLE_PARTICIPANT']);
        $optin = !empty($f['marketing']) ? 1 : 0;
        $now = date('Y-m-d H:i:s');
        $preuve = $this->preuve($r);
        $id = $this->db->tx(function (Db $db) use ($f, $email, $hasher, $probe, $optin, $now, $preuve): int {
            $db->run('INSERT INTO participant (email, mot_de_passe_hash, nom, prenom, date_naissance, civilite, code_postal, marketing_optin, cree_le) VALUES (?,?,?,?,?,?,?,?,?)', [$email, $hasher->hashPassword($probe, (string) $f['password']), $f['nom'], $f['prenom'], $f['date_naissance'], $f['civilite'], $f['code_postal'] ?: null, $optin, $now]);
            $id = $db->lastId();
            $this->consent($id, 'reglement', 1, $now, $preuve);
            $this->consent($id, 'marketing', $optin, $now, $preuve);

            return $id;
        });
        $this->sendValidation($id, $email);
        $security->login($this->loadUser($email), 'form_login', 'main');

        return $this->redirectToRoute($r->getSession()->has('pending_code') ? 'play_pending' : 'my_gains');
    }

    #[Route('/valider-email/{token}', name: 'verify_email', methods: ['GET'])]
    public function verify(string $token): Response
    {
        $t = $this->consume($token, 'validation');
        if ($t) {
            $this->db->run('UPDATE participant SET email_valide_le = ? WHERE id = ?', [date('Y-m-d H:i:s'), $t['participant_id']]);
            $this->addFlash('success', 'Votre adresse est confirmée.');
        } else {
            $this->addFlash('error', 'Ce lien n\'est plus valide.');
        }

        return $this->redirectToRoute('home');
    }

    #[Route('/mot-de-passe-oublie', name: 'forgot', methods: ['GET', 'POST'])]
    public function forgot(Request $r): Response
    {
        if ($r->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('forgot', (string) $r->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $email = mb_strtolower(trim((string) $r->request->get('email')));
            $id = $this->db->val('SELECT id FROM participant WHERE email = ? AND supprime_le IS NULL', [$email]);
            if ($id) {
                $token = $this->token((int) $id, 'reset', 3600);
                $this->mail->send($email, 'Réinitialisez votre mot de passe', '<p><a href="'.$this->site->url().'/reinitialiser/'.$token.'">Choisir un nouveau mot de passe</a>. Ce lien expire dans une heure.</p>');
            }
            $this->addFlash('info', 'Si cette adresse correspond à un compte, un courriel vient de vous être envoyé.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/forgot.html.twig');
    }

    #[Route('/reinitialiser/{token}', name: 'reset', methods: ['GET', 'POST'])]
    public function reset(string $token, Request $r, UserPasswordHasherInterface $hasher): Response
    {
        $hash = hash('sha256', $token);
        $row = $this->db->one('SELECT * FROM jeton WHERE hash = ? AND type = ? AND expire_le > NOW()', [$hash, 'reset']);
        if (!$row) {
            $this->addFlash('error', 'Ce lien n\'est plus valide.');

            return $this->redirectToRoute('forgot');
        }
        $error = null;
        if ($r->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('reset', (string) $r->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $pw = (string) $r->request->get('password');
            $error = self::passwordError($pw);
            if (!$error) {
                $probe = new AppUser('participant', 0, 'x', null, '', ['ROLE_PARTICIPANT']);
                $this->db->run('UPDATE participant SET mot_de_passe_hash = ? WHERE id = ?', [$hasher->hashPassword($probe, $pw), $row['participant_id']]);
                $this->db->run('DELETE FROM jeton WHERE hash = ?', [$hash]);
                $this->addFlash('success', 'Votre mot de passe a été modifié : vous pouvez vous connecter.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('auth/reset.html.twig', ['error' => $error, 'token' => $token]);
    }

    #[Route('/connexion/{provider}', name: 'sso_start', requirements: ['provider' => 'google|facebook'], methods: ['GET'])]
    public function ssoStart(string $provider, Request $r): RedirectResponse
    {
        if (!$this->site->sso($provider)) {
            throw $this->createNotFoundException();
        }
        $state = bin2hex(random_bytes(16));
        $r->getSession()->set('sso_state', $state);
        $q = ['client_id' => $this->site->env(strtoupper($provider).'_CLIENT_ID'), 'redirect_uri' => $this->site->url().'/connexion/'.$provider.'/retour', 'response_type' => 'code', 'state' => $state];
        $base = 'google' === $provider ? 'https://accounts.google.com/o/oauth2/v2/auth' : 'https://www.facebook.com/v19.0/dialog/oauth';
        $q['scope'] = 'google' === $provider ? 'openid email profile' : 'email,public_profile';

        return new RedirectResponse($base.'?'.http_build_query($q));
    }

    #[Route('/connexion/{provider}/retour', name: 'sso_back', requirements: ['provider' => 'google|facebook'], methods: ['GET'])]
    public function ssoBack(string $provider, Request $r, HttpClientInterface $http, Security $security, LoginSuccessHandler $target): Response
    {
        $state = $r->getSession()->remove('sso_state');
        if (!$state || !hash_equals((string) $state, (string) $r->query->get('state')) || !$r->query->get('code')) {
            $this->addFlash('error', 'Connexion annulée.');

            return $this->redirectToRoute('app_login');
        }
        try {
            $profile = $this->ssoProfile($provider, (string) $r->query->get('code'), $http);
        } catch (\Throwable) {
            $this->addFlash('error', 'La connexion n\'a pas abouti. Réessayez ou utilisez le formulaire.');

            return $this->redirectToRoute('app_login');
        }
        if (empty($profile['email'])) {
            $this->addFlash('error', 'Votre compte ne partage pas d\'adresse e-mail : utilisez le formulaire.');

            return $this->redirectToRoute('app_register');
        }
        $email = mb_strtolower($profile['email']);
        $row = $this->db->one('SELECT id FROM participant WHERE email = ?', [$email]);
        if (!$row) {
            $now = date('Y-m-d H:i:s');
            $this->db->run('INSERT INTO participant (email, nom, prenom, sso_fournisseur, sso_identifiant, email_valide_le, cree_le) VALUES (?,?,?,?,?,?,?)', [$email, $profile['nom'] ?? '', $profile['prenom'] ?? '', $provider, $profile['id'], $now, $now]);
            $id = $this->db->lastId();
            $this->consent($id, 'reglement', 1, $now, $this->preuve($r));
            $this->consent($id, 'marketing', 0, $now, $this->preuve($r));
            $this->addFlash('info', 'En vous connectant, vous acceptez le règlement et les CGU. La prospection reste désactivée, modifiable dans Mes données.');
        }
        $security->login($this->loadUser($email), 'form_login', 'main');

        return new RedirectResponse($target->target($r, ['ROLE_PARTICIPANT']));
    }

    private function ssoProfile(string $provider, string $code, HttpClientInterface $http): array
    {
        $body = ['client_id' => $this->site->env(strtoupper($provider).'_CLIENT_ID'), 'client_secret' => $this->site->env(strtoupper($provider).'_CLIENT_SECRET'), 'code' => $code, 'redirect_uri' => $this->site->url().'/connexion/'.$provider.'/retour', 'grant_type' => 'authorization_code'];
        if ('google' === $provider) {
            $t = $http->request('POST', 'https://oauth2.googleapis.com/token', ['body' => $body])->toArray();
            $u = $http->request('GET', 'https://openidconnect.googleapis.com/v1/userinfo', ['auth_bearer' => $t['access_token']])->toArray();

            return ['id' => $u['sub'], 'email' => $u['email'] ?? null, 'nom' => $u['family_name'] ?? '', 'prenom' => $u['given_name'] ?? ''];
        }
        $t = $http->request('GET', 'https://graph.facebook.com/v19.0/oauth/access_token', ['query' => $body])->toArray();
        $u = $http->request('GET', 'https://graph.facebook.com/me', ['query' => ['fields' => 'id,email,first_name,last_name', 'access_token' => $t['access_token']]])->toArray();

        return ['id' => $u['id'], 'email' => $u['email'] ?? null, 'nom' => $u['last_name'] ?? '', 'prenom' => $u['first_name'] ?? ''];
    }

    private function loadUser(string $email): AppUser
    {
        return (new AppUserProvider($this->db))->loadUserByIdentifier($email);
    }

    private function validate(array $f): array
    {
        $e = [];
        foreach (['prenom' => 'Indiquez votre prénom.', 'nom' => 'Indiquez votre nom.'] as $k => $m) {
            if ('' === (string) ($f[$k] ?? '')) {
                $e[$k] = $m;
            }
        }
        if (!filter_var($f['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $e['email'] = 'Saisissez une adresse e-mail valide.';
        }
        if ($m = self::passwordError((string) ($f['password'] ?? ''))) {
            $e['password'] = $m;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($f['date_naissance'] ?? ''));
        if (!$d) {
            $e['date_naissance'] = 'Indiquez votre date de naissance.';
        } elseif ($d->modify('+18 years') > new \DateTimeImmutable()) {
            $e['date_naissance'] = 'Le jeu est réservé aux personnes majeures.';
        }
        if (!\in_array($f['civilite'] ?? '', ['madame', 'monsieur', 'autre'], true)) {
            $e['civilite'] = 'Sélectionnez votre civilité.';
        }
        if ('' !== (string) ($f['code_postal'] ?? '') && !preg_match('/^[0-9]{5}$/', (string) $f['code_postal'])) {
            $e['code_postal'] = 'Le code postal comporte cinq chiffres.';
        }
        if (empty($f['reglement'])) {
            $e['reglement'] = 'L\'acceptation du règlement et des CGU est obligatoire pour participer.';
        }

        return $e;
    }

    public static function passwordError(string $p): ?string
    {
        return (mb_strlen($p) < 12 || !preg_match('/[A-Za-z]/', $p) || !preg_match('/[0-9]/', $p)) ? 'Le mot de passe doit comporter au moins 12 caractères, dont des lettres et des chiffres.' : null;
    }

    private function consent(int $id, string $type, int $val, string $now, string $preuve): void
    {
        $this->db->run('INSERT INTO consentement (participant_id, type, accorde, horodatage, preuve) VALUES (?,?,?,?,?)', [$id, $type, $val, $now, $preuve]);
    }

    private function preuve(Request $r): string
    {
        return 'formulaire v1 ; ip '.hash('sha256', (string) $r->getClientIp());
    }

    private function sendValidation(int $id, string $email): void
    {
        $token = $this->token($id, 'validation', 86400 * 7);
        $this->mail->send($email, 'Confirmez votre adresse', '<p><a href="'.$this->site->url().'/valider-email/'.$token.'">Confirmer mon adresse</a></p>');
    }

    private function token(int $id, string $type, int $ttl): string
    {
        $t = bin2hex(random_bytes(32));
        $this->db->run('INSERT INTO jeton (hash, type, participant_id, expire_le) VALUES (?,?,?,?)', [hash('sha256', $t), $type, $id, date('Y-m-d H:i:s', time() + $ttl)]);

        return $t;
    }

    private function consume(string $token, string $type): ?array
    {
        $hash = hash('sha256', $token);
        $row = $this->db->one('SELECT * FROM jeton WHERE hash=? AND type=? AND expire_le > NOW()', [$hash, $type]);
        if ($row) {
            $this->db->run('DELETE FROM jeton WHERE hash = ?', [$hash]);
        }

        return $row;
    }
}
