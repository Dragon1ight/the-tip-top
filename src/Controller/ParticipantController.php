<?php

namespace App\Controller;

use App\Service\Db;
use App\Service\Game;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ParticipantController extends AbstractController
{
    use PlayResponse;

    public function __construct(private readonly Db $db, private readonly Game $game)
    {
    }

    #[Route('/jouer-en-attente', name: 'play_pending', methods: ['GET'])]
    public function pending(Request $r): Response
    {
        $code = $r->getSession()->remove('pending_code');
        if (!$code) {
            return $this->redirectToRoute('my_gains');
        }

        return $this->playResponse($this->game->play($this->getUser()->id, (string) $code));
    }

    #[Route('/mes-participations', name: 'my_gains', methods: ['GET'])]
    public function gains(): Response
    {
        $rows = $this->db->all('SELECT g.*, l.libelle, b.nom AS boutique FROM gain g JOIN lot l ON l.id=g.lot_id LEFT JOIN boutique b ON b.id=g.boutique_id WHERE g.participant_id=? ORDER BY g.attribue_le DESC', [$this->getUser()->id]);
        foreach ($rows as &$g) {
            $g['affiche'] = $this->game->statutAffiche($g);
        }
        $stats = ['codes' => count($rows), 'a_retirer' => count(array_filter($rows, static fn ($g) => 'a_retirer' === $g['affiche'])), 'tirage' => (bool) $this->db->val('SELECT 1 FROM inscription_tirage WHERE participant_id=?', [$this->getUser()->id])];

        return $this->render('participant/gains.html.twig', ['gains' => $rows, 'stats' => $stats]);
    }

    #[Route('/mes-participations/saisir', name: 'my_play', methods: ['POST'])]
    public function play(Request $r): Response
    {
        $this->csrf($r, 'play');

        return $this->playResponse($this->game->play($this->getUser()->id, (string) $r->request->get('code')));
    }

    #[Route('/mes-participations/{id}', name: 'gain_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, Request $r): Response
    {
        $g = $this->gain($id);
        $g['affiche'] = $this->game->statutAffiche($g);

        return $this->render('participant/gain.html.twig', ['g' => $g, 'nouveau' => $r->query->has('nouveau'), 'boutiques' => $this->db->all('SELECT * FROM boutique ORDER BY ville')]);
    }

    #[Route('/mes-participations/{id}/remise', name: 'gain_mode', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function mode(int $id, Request $r): Response
    {
        $this->csrf($r, 'mode'.$id);
        $g = $this->gain($id);
        if ('a_retirer' !== $g['statut']) {
            $this->addFlash('error', 'Ce lot a déjà été remis.');

            return $this->redirectToRoute('my_gains');
        }
        $mode = (string) $r->request->get('mode');
        if ('boutique' === $mode && $this->db->val('SELECT 1 FROM boutique WHERE id=?', [(int) $r->request->get('boutique_id')])) {
            $this->db->run('UPDATE gain SET mode_remise=?, boutique_id=?, adresse_expedition=NULL WHERE id=?', ['boutique', (int) $r->request->get('boutique_id'), $id]);
        } elseif ('expedition' === $mode && mb_strlen(trim((string) $r->request->get('adresse'))) >= 10) {
            $this->db->run('UPDATE gain SET mode_remise=?, boutique_id=NULL, adresse_expedition=? WHERE id=?', ['expedition', trim((string) $r->request->get('adresse')), $id]);
        } else {
            $this->addFlash('error', 'Choisissez une boutique ou indiquez une adresse complète.');

            return $this->redirectToRoute('gain_show', ['id' => $id]);
        }
        $this->addFlash('success', 'Votre choix est enregistré.');

        return $this->redirectToRoute('my_gains');
    }

    #[Route('/mes-donnees', name: 'my_data', methods: ['GET'])]
    public function data(): Response
    {
        return $this->render('participant/data.html.twig', ['p' => $this->me()]);
    }

    #[Route('/mes-donnees/export', name: 'my_export', methods: ['GET'])]
    public function export(): JsonResponse
    {
        $id = $this->getUser()->id;
        $p = $this->me();
        unset($p['mot_de_passe_hash']);
        $data = ['compte' => $p, 'consentements' => $this->db->all('SELECT type, accorde, horodatage FROM consentement WHERE participant_id=?', [$id]), 'gains' => $this->db->all('SELECT g.code_valeur, l.libelle, g.statut, g.attribue_le, g.remis_le, g.mode_remise FROM gain g JOIN lot l ON l.id=g.lot_id WHERE g.participant_id=?', [$id])];
        $res = new JsonResponse($data, 200, ['Content-Disposition' => 'attachment; filename="mes-donnees-the-tip-top.json"']);
        $res->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return $res;
    }

    #[Route('/mes-donnees/preferences', name: 'my_prefs', methods: ['POST'])]
    public function prefs(Request $r): Response
    {
        $this->csrf($r, 'prefs');
        $v = $r->request->has('marketing') ? 1 : 0;
        $id = $this->getUser()->id;
        $this->db->run('UPDATE participant SET marketing_optin=? WHERE id=?', [$v, $id]);
        $this->db->run('INSERT INTO consentement (participant_id,type,accorde,horodatage,preuve) VALUES (?,?,?,?,?)', [$id, 'marketing', $v, date('Y-m-d H:i:s'), 'espace personnel']);
        $this->addFlash('success', 'Vos préférences sont enregistrées.');

        return $this->redirectToRoute('my_data');
    }

    #[Route('/mes-donnees/suppression', name: 'my_delete', methods: ['POST'])]
    public function delete(Request $r, \Symfony\Bundle\SecurityBundle\Security $security): Response
    {
        $this->csrf($r, 'delete');
        $id = $this->getUser()->id;
        $this->db->run("UPDATE participant SET email=?, mot_de_passe_hash=NULL, nom='', prenom='', date_naissance=NULL, civilite=NULL, code_postal=NULL, sso_fournisseur=NULL, sso_identifiant=NULL, marketing_optin=0, supprime_le=? WHERE id=?", ['supprime-'.$id.'@invalid', date('Y-m-d H:i:s'), $id]);
        $security->logout(false);
        $this->addFlash('success', 'Votre compte a été supprimé.');

        return $this->redirectToRoute('home');
    }

    private function me(): array
    {
        return $this->db->one('SELECT * FROM participant WHERE id=?', [$this->getUser()->id]);
    }

    private function gain(int $id): array
    {
        $g = $this->db->one('SELECT g.*, l.libelle, l.valeur_euros, b.nom AS boutique FROM gain g JOIN lot l ON l.id=g.lot_id LEFT JOIN boutique b ON b.id=g.boutique_id WHERE g.id=? AND g.participant_id=?', [$id, $this->getUser()->id]);
        if (!$g) {
            throw $this->createNotFoundException();
        }

        return $g;
    }

    private function csrf(Request $r, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $r->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
