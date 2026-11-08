<?php

namespace App\Controller;

use App\Security\AppUser;
use App\Service\Db;
use App\Service\Game;
use App\Service\Site;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class StaffController extends AbstractController
{
    public function __construct(private readonly Db $db, private readonly Game $game, private readonly Site $site)
    {
    }

    // --- Interface boutique ---
    #[Route('/boutique', name: 'shop_home', methods: ['GET'])]
    public function shop(Request $r): Response
    {
        $q = trim((string) $r->query->get('q'));
        $rows = [];
        if ('' !== $q) {
            $rows = $this->db->all('SELECT g.id, g.code_valeur, g.statut, g.date_limite, g.mode_remise, g.adresse_expedition, g.remis_le, l.libelle, p.prenom, LEFT(p.nom,1) AS initiale, b.nom AS boutique FROM gain g JOIN lot l ON l.id=g.lot_id JOIN participant p ON p.id=g.participant_id LEFT JOIN boutique b ON b.id=g.boutique_id WHERE g.code_valeur=? OR p.email=? ORDER BY g.statut, g.attribue_le DESC LIMIT 50', [Game::normalize($q), mb_strtolower($q)]);
            foreach ($rows as &$x) {
                $x['affiche'] = $this->game->statutAffiche($x);
            }
        }

        return $this->render('staff/shop.html.twig', ['q' => $q, 'rows' => $rows]);
    }

    #[Route('/boutique/remise/{id}', name: 'shop_deliver', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deliver(int $id, Request $r): Response
    {
        $this->csrf($r, 'deliver'.$id);
        /** @var AppUser $u */
        $u = $this->getUser();
        $res = $this->game->deliver($id, $u->id, $u->boutiqueId, trim((string) $r->request->get('motif')) ?: null);
        $this->addFlash($res['ok'] ? 'success' : 'error', $res['message']);

        return $this->redirectToRoute('shop_home', ['q' => $r->request->get('q')]);
    }

    // --- Back-office ---
    #[Route('/admin', name: 'admin_home', methods: ['GET'])]
    public function admin(): Response
    {
        return $this->render('staff/dashboard.html.twig');
    }

    #[Route('/admin/api/stats', name: 'admin_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $lots = $this->db->all('SELECT l.libelle, l.pourcentage_cible, COUNT(c.valeur) AS generes, SUM(c.participant_id IS NOT NULL) AS utilises, SUM(g.statut IN ("remis","expedie")) AS remis FROM lot l LEFT JOIN code_ticket c ON c.lot_id=l.id LEFT JOIN gain g ON g.code_valeur=c.valeur WHERE l.id<6 GROUP BY l.id ORDER BY l.id');
        $tot = array_sum(array_column($lots, 'generes'));
        foreach ($lots as &$l) {
            $l['pourcentage_reel'] = $tot ? round(100 * $l['generes'] / $tot, 2) : 0;
        }

        return $this->json([
            'codes' => ['generes' => (int) $tot, 'distribues' => (int) $this->db->val('SELECT COUNT(*) FROM code_ticket WHERE distribue_le IS NOT NULL'), 'utilises' => (int) $this->db->val('SELECT COUNT(*) FROM code_ticket WHERE participant_id IS NOT NULL')],
            'lots' => $lots, 'remis' => (int) $this->db->val('SELECT COUNT(*) FROM gain WHERE statut IN ("remis","expedie")'),
            'participants' => (int) $this->db->val('SELECT COUNT(*) FROM participant WHERE supprime_le IS NULL'),
            'optin' => (int) $this->db->val('SELECT COUNT(*) FROM participant WHERE supprime_le IS NULL AND marketing_optin=1'),
            'age' => $this->db->all("SELECT CASE WHEN TIMESTAMPDIFF(YEAR,date_naissance,CURDATE())<30 THEN '18-29' WHEN TIMESTAMPDIFF(YEAR,date_naissance,CURDATE())<46 THEN '30-45' WHEN TIMESTAMPDIFF(YEAR,date_naissance,CURDATE())<61 THEN '46-60' ELSE '61+' END AS k, COUNT(*) AS n FROM participant p WHERE supprime_le IS NULL AND date_naissance IS NOT NULL AND EXISTS (SELECT 1 FROM gain g WHERE g.participant_id=p.id) GROUP BY k ORDER BY k"),
            'civilite' => $this->db->all("SELECT COALESCE(civilite,'non renseigné') AS k, COUNT(*) AS n FROM participant p WHERE supprime_le IS NULL AND EXISTS (SELECT 1 FROM gain g WHERE g.participant_id=p.id) GROUP BY k"),
            'zone' => $this->db->all("SELECT COALESCE(LEFT(code_postal,2),'non renseigné') AS k, COUNT(*) AS n FROM participant WHERE supprime_le IS NULL GROUP BY k ORDER BY n DESC LIMIT 10"),
            'boutique' => $this->db->all("SELECT b.nom AS k, COUNT(*) AS n FROM gain g JOIN boutique b ON b.id=g.boutique_id GROUP BY b.id ORDER BY n DESC"),
        ]);
    }

    #[Route('/admin/participants', name: 'admin_users', methods: ['GET'])]
    public function users(Request $r): Response
    {
        $q = trim((string) $r->query->get('q'));
        $sql = 'SELECT id,email,prenom,nom,cree_le,suspendu,supprime_le,marketing_optin FROM participant';
        $rows = '' === $q ? $this->db->all($sql.' ORDER BY id DESC LIMIT 50') : $this->db->all($sql.' WHERE email LIKE ? OR nom LIKE ? ORDER BY id DESC LIMIT 50', ['%'.$q.'%', '%'.$q.'%']);

        return $this->render('staff/users.html.twig', ['rows' => $rows, 'q' => $q]);
    }

    #[Route('/admin/participants/{id}/{action}', name: 'admin_user_action', requirements: ['id' => '\d+', 'action' => 'suspendre|retablir|supprimer'], methods: ['POST'])]
    public function userAction(int $id, string $action, Request $r): Response
    {
        $this->csrf($r, 'user'.$id);
        if ('supprimer' === $action) {
            $this->db->run("UPDATE participant SET email=?, mot_de_passe_hash=NULL, nom='', prenom='', date_naissance=NULL, civilite=NULL, code_postal=NULL, marketing_optin=0, supprime_le=? WHERE id=?", ['supprime-'.$id.'@invalid', date('Y-m-d H:i:s'), $id]);
        } else {
            $this->db->run('UPDATE participant SET suspendu=? WHERE id=?', ['suspendre' === $action ? 1 : 0, $id]);
        }
        $this->log($action.'_participant', (string) $id);
        $this->addFlash('success', 'Action enregistrée.');

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admin/employes', name: 'admin_staff', methods: ['GET', 'POST'])]
    public function staff(Request $r, UserPasswordHasherInterface $hasher): Response
    {
        if ($r->isMethod('POST')) {
            $this->csrf($r, 'staff');
            $id = trim((string) $r->request->get('identifiant'));
            $pw = (string) $r->request->get('password');
            $role = (string) $r->request->get('role');
            if ('' === $id || AuthController::passwordError($pw) || !\in_array($role, ['employe', 'admin', 'huissier'], true) || $this->db->val('SELECT 1 FROM employe WHERE identifiant=?', [$id])) {
                $this->addFlash('error', 'Vérifiez l\'identifiant (unique) et le mot de passe (12 caractères, lettres et chiffres).');
            } else {
                $this->db->run('INSERT INTO employe (boutique_id,nom,identifiant,mot_de_passe_hash,role) VALUES (?,?,?,?,?)', [$r->request->get('boutique_id') ?: null, trim((string) $r->request->get('nom')), $id, $hasher->hashPassword(new AppUser('employe', 0, $id, null, '', ['ROLE_EMPLOYE']), $pw), $role]);
                $this->log('creation_employe', $id);
                $this->addFlash('success', 'Compte créé.');
            }

            return $this->redirectToRoute('admin_staff');
        }

        return $this->render('staff/staff.html.twig', ['rows' => $this->db->all('SELECT e.*, b.nom AS boutique FROM employe e LEFT JOIN boutique b ON b.id=e.boutique_id ORDER BY e.id'), 'boutiques' => $this->db->all('SELECT * FROM boutique ORDER BY ville')]);
    }

    #[Route('/admin/employes/{id}/basculer', name: 'admin_staff_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(int $id, Request $r): Response
    {
        $this->csrf($r, 'emp'.$id);
        if ($id !== $this->getUser()->id) {
            $this->db->run('UPDATE employe SET actif = 1 - actif WHERE id=?', [$id]);
            $this->log('bascule_employe', (string) $id);
        }

        return $this->redirectToRoute('admin_staff');
    }

    #[Route('/admin/emailing', name: 'admin_mail', methods: ['GET'])]
    public function mail(Request $r): Response
    {
        return $this->render('staff/emailing.html.twig', ['count' => (int) $this->db->val('SELECT COUNT(*) FROM participant WHERE supprime_le IS NULL AND marketing_optin=1')]);
    }

    #[Route('/admin/emailing/export', name: 'admin_mail_export', methods: ['GET'])]
    public function export(Request $r): StreamedResponse
    {
        // La restriction aux personnes consentantes est appliquée par le serveur.
        $sql = 'SELECT DISTINCT p.email, p.prenom FROM participant p LEFT JOIN gain g ON g.participant_id=p.id WHERE p.supprime_le IS NULL AND p.marketing_optin=1';
        $a = [];
        if ($z = $r->query->get('zone')) {
            $sql .= ' AND p.code_postal LIKE ?';
            $a[] = preg_replace('/\D/', '', (string) $z).'%';
        }
        if ($l = (int) $r->query->get('lot')) {
            $sql .= ' AND g.lot_id = ?';
            $a[] = $l;
        }
        if (\in_array($m = $r->query->get('mode'), ['boutique', 'expedition'], true)) {
            $sql .= ' AND g.mode_remise = ?';
            $a[] = $m;
        }
        $rows = $this->db->all($sql, $a);
        $this->log('export_emailing', count($rows).' contacts');

        return new StreamedResponse(static function () use ($rows): void {
            $o = fopen('php://output', 'w');
            fputcsv($o, ['email', 'prenom'], ';', '"', '');
            foreach ($rows as $x) {
                fputcsv($o, $x, ';', '"', '');
            }
            fclose($o);
        }, 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="segment-emailing.csv"']);
    }

    #[Route('/admin/tirage', name: 'admin_draw', methods: ['GET', 'POST'])]
    public function draw(Request $r): Response
    {
        if ($r->isMethod('POST')) {
            $this->csrf($r, 'draw');
            $res = $this->game->draw($this->getUser()->getUserIdentifier());
            $this->addFlash($res['ok'] ? 'success' : 'error', $res['message']);

            return $this->redirectToRoute('admin_draw');
        }

        return $this->render('staff/draw.html.twig', $this->drawData());
    }

    #[Route('/journal-tirage', name: 'tirage_journal', methods: ['GET'])]
    public function journal(): Response
    {
        return $this->render('staff/journal.html.twig', $this->drawData() + ['logs' => $this->db->all('SELECT * FROM journal_admin ORDER BY id DESC LIMIT 100')]);
    }

    private function drawData(): array
    {
        return ['t' => $this->db->one('SELECT t.*, p.prenom, LEFT(p.nom,1) AS initiale FROM tirage t LEFT JOIN participant p ON p.id=t.gagnant_participant_id WHERE t.id=1'), 'inscrits' => (int) $this->db->val('SELECT COUNT(*) FROM inscription_tirage WHERE tirage_id=1')];
    }

    private function log(string $action, string $cible): void
    {
        $this->db->run('INSERT INTO journal_admin (auteur,action,cible,horodatage) VALUES (?,?,?,?)', [$this->getUser()->getUserIdentifier(), $action, $cible, date('Y-m-d H:i:s')]);
    }

    private function csrf(Request $r, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $r->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
