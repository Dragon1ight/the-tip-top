<?php

namespace App\Controller;

use App\Service\Db;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class NewsletterController extends AbstractController
{
    #[Route('/newsletter', name: 'newsletter', methods: ['POST'])]
    public function subscribe(Request $r, Db $db): Response
    {
        if (!$this->isCsrfTokenValid('newsletter', (string) $r->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $email = mb_strtolower(trim((string) $r->request->get('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$r->request->has('consent')) {
            $this->addFlash('error', 'Indiquez une adresse valide et cochez la case de consentement.');
        } else {
            $db->run('INSERT INTO newsletter (email,horodatage,preuve) VALUES (?,?,?) ON DUPLICATE KEY UPDATE desinscrit_le=NULL, horodatage=VALUES(horodatage)', [$email, date('Y-m-d H:i:s'), 'formulaire pied de page']);
            $this->addFlash('success', 'Votre inscription à la newsletter est enregistrée.');
        }

        return $this->redirect($this->generateUrl('home').'#newsletter');
    }
}
