<?php

namespace App\Controller;

use App\Service\Db;
use App\Service\Game;
use App\Service\Site;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicController extends AbstractController
{
    use PlayResponse;

    private const PAGES = [
        'reglement' => ['/reglement', 'Règlement du jeu-concours Thé Tip Top', 'Règlement complet du jeu-concours Thé Tip Top, déposé auprès de Maître Arnaud Rick, huissier de justice : dates, lots, conditions et remise.'],
        'mentions' => ['/mentions-legales', 'Mentions légales — Thé Tip Top', 'Mentions légales du site du jeu-concours Thé Tip Top : éditeur, hébergeur, propriété intellectuelle et mention de projet étudiant fictif.'],
        'cgu' => ['/cgu', 'Conditions générales d\'utilisation — Thé Tip Top', 'Conditions générales d\'utilisation du site du jeu-concours Thé Tip Top : accès, compte, participation, responsabilité et contenus.'],
        'confidentialite' => ['/confidentialite', 'Politique de confidentialité — Thé Tip Top', 'Comment Thé Tip Top traite vos données : finalités, durées de conservation, destinataires, droits et contact du délégué à la protection des données.'],
        'cookies' => ['/cookies', 'Politique de cookies — Thé Tip Top', 'Les cookies déposés sur le site du jeu-concours Thé Tip Top, leur finalité, leur durée et la façon de modifier votre choix à tout moment.'],
        'accessibilite' => ['/accessibilite', 'Déclaration d\'accessibilité — Thé Tip Top', 'Déclaration d\'accessibilité du site du jeu-concours Thé Tip Top : niveau visé, mesures mises en œuvre et moyens de nous signaler un problème.'],
    ];

    public function __construct(private readonly Site $site)
    {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('public/home.html.twig');
    }

    #[Route('/jouer', name: 'play', methods: ['POST'])]
    public function play(Request $r, Game $game): Response
    {
        if (!$this->isCsrfTokenValid('play', (string) $r->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $code = (string) $r->request->get('code');
        if (!$this->getUser()) {
            $r->getSession()->set('pending_code', $code);
            $this->addFlash('info', 'Votre code est conservé : connectez-vous ou créez un compte pour découvrir votre lot.');

            return $this->redirectToRoute('app_login');
        }
        if (!$this->isGranted('ROLE_PARTICIPANT')) {
            $this->addFlash('error', 'La saisie d\'un code est réservée aux participants.');

            return $this->redirectToRoute('home');
        }

        return $this->playResponse($game->play($this->getUser()->id, $code));
    }

    #[Route('/les-lots', name: 'lots', methods: ['GET'])]
    public function lots(Db $db): Response
    {
        return $this->render('public/lots.html.twig', ['lots' => $this->safeLots($db)]);
    }

    #[Route('/comment-participer', name: 'how', methods: ['GET'])]
    public function how(): Response
    {
        return $this->render('public/how.html.twig');
    }

    #[Route('/le-jeu-concours', name: 'game_alias', methods: ['GET'])]
    public function alias(): Response
    {
        return $this->redirectToRoute('how', [], 301);
    }

    #[Route('/le-concept', name: 'concept', methods: ['GET'])]
    public function concept(): Response
    {
        return $this->render('public/concept.html.twig');
    }

    #[Route('/contact', name: 'contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->render('public/contact.html.twig');
    }

    #[Route('/{page}', name: 'legal', requirements: ['page' => 'reglement|mentions-legales|cgu|confidentialite|cookies|accessibilite'], methods: ['GET'])]
    public function legal(string $page): Response
    {
        $key = 'mentions-legales' === $page ? 'mentions' : $page;
        [, $title, $desc] = self::PAGES[$key];

        return $this->render('public/legal.html.twig', ['file' => 'legal/'.$key.'.html.twig', 'title' => $title, 'description' => $desc]);
    }

    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'])]
    public function sitemap(): Response
    {
        if (!$this->site->isProd()) {
            throw $this->createNotFoundException();
        }
        $paths = ['/', '/les-lots', '/comment-participer', '/le-concept', '/reglement', '/mentions-legales', '/cgu', '/confidentialite', '/cookies', '/accessibilite', '/contact'];
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($paths as $p) {
            $xml .= '<url><loc>'.htmlspecialchars($this->site->url().$p).'</loc></url>';
        }

        return new Response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml']);
    }

    #[Route('/robots.txt', name: 'robots', methods: ['GET'])]
    public function robots(): Response
    {
        $txt = $this->site->isProd()
            ? "User-agent: *\nDisallow: /admin\nDisallow: /boutique\nDisallow: /mes-participations\nDisallow: /mes-donnees\nDisallow: /api\nSitemap: ".$this->site->url()."/sitemap.xml\n"
            : "User-agent: *\nDisallow: /\n";

        return new Response($txt, 200, ['Content-Type' => 'text/plain']);
    }

    #[Route('/sante', name: 'health', methods: ['GET'])]
    public function health(Db $db): Response
    {
        try {
            $db->val('SELECT 1');

            return $this->json(['statut' => 'ok']);
        } catch (\Throwable) {
            return $this->json(['statut' => 'base_indisponible'], 503);
        }
    }

    private function safeLots(Db $db): array
    {
        try {
            return $db->all('SELECT * FROM lot WHERE id < 6 ORDER BY id');
        } catch (\Throwable) {
            return [];
        }
    }
}
