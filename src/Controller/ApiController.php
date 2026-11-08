<?php

namespace App\Controller;

use App\Service\Db;
use App\Service\Game;
use App\Service\Quota;
use App\Service\Signature;
use App\Service\Site;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** API REST v1 pour les caisses et le futur site marchand : signature HMAC, anti-rejeu, limite de débit. */
#[Route('/api/v1')]
final class ApiController
{
    public function __construct(private readonly Db $db, private readonly Game $game, private readonly Quota $quota, private readonly Site $site)
    {
    }

    #[Route('/sante', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse(['statut' => 'ok', 'version' => 'v1']);
    }

    #[Route('/lots', methods: ['GET'])]
    public function lots(Request $r): JsonResponse
    {
        return $this->guard($r) ?? new JsonResponse($this->db->all('SELECT id, libelle, categorie, valeur_euros, pourcentage_cible FROM lot ORDER BY id'));
    }

    #[Route('/tickets/{code}', methods: ['GET'])]
    public function ticket(string $code, Request $r): JsonResponse
    {
        if ($e = $this->guard($r)) {
            return $e;
        }
        $t = $this->db->one('SELECT c.valeur, c.distribue_le, c.utilise_le, l.libelle FROM code_ticket c JOIN lot l ON l.id=c.lot_id WHERE c.valeur=?', [Game::normalize($code)]);

        return $t ? new JsonResponse($t + ['lot' => $t['utilise_le'] ? $t['libelle'] : null]) : new JsonResponse(['erreur' => 'code_introuvable'], 404);
    }

    #[Route('/tickets/{code}/emission', methods: ['POST'])]
    public function emit(string $code, Request $r): JsonResponse
    {
        if ($e = $this->guard($r)) {
            return $e;
        }
        $b = json_decode($r->getContent(), true) ?: [];
        $n = $this->db->run('UPDATE code_ticket SET distribue_le=?, boutique_emission_id=? WHERE valeur=? AND distribue_le IS NULL', [date('Y-m-d H:i:s'), (int) ($b['boutique_id'] ?? 0) ?: null, Game::normalize($code)])->rowCount();

        return new JsonResponse(['emis' => 1 === $n], 1 === $n ? 200 : 409);
    }

    #[Route('/gains', methods: ['GET'])]
    public function gains(Request $r): JsonResponse
    {
        if ($e = $this->guard($r)) {
            return $e;
        }

        return new JsonResponse($this->db->all('SELECT g.id, g.code_valeur, g.statut, g.date_limite, l.libelle FROM gain g JOIN lot l ON l.id=g.lot_id JOIN participant p ON p.id=g.participant_id WHERE p.email=?', [mb_strtolower((string) $r->query->get('email'))]));
    }

    #[Route('/gains/{id}/remise', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deliver(int $id, Request $r): JsonResponse
    {
        if ($e = $this->guard($r)) {
            return $e;
        }
        $b = json_decode($r->getContent(), true) ?: [];
        $res = $this->game->deliver($id, null, (int) ($b['boutique_id'] ?? 0) ?: null);

        return new JsonResponse(['message' => $res['message']], $res['ok'] ? 200 : 409);
    }

    private function guard(Request $r): ?JsonResponse
    {
        if (!$this->quota->hit('api:'.$r->getClientIp(), 120, 60)) {
            return new JsonResponse(['erreur' => 'debit_depasse'], 429);
        }
        $secret = $this->site->env('API_HMAC_SECRET');
        $ts = (string) $r->headers->get('X-Timestamp');
        $nonce = (string) $r->headers->get('X-Nonce');
        $sig = (string) $r->headers->get('X-Signature');
        if (!$secret || !ctype_digit($ts) || !preg_match('/^[A-Za-z0-9\-]{16,64}$/', $nonce) || abs(time() - (int) $ts) > 300) {
            return new JsonResponse(['erreur' => 'authentification'], 401);
        }
        if (!Signature::verify($secret, $r->getMethod(), $r->getRequestUri(), $ts, $nonce, $r->getContent(), $sig)) {
            return new JsonResponse(['erreur' => 'signature'], 401);
        }
        $this->db->run('DELETE FROM api_nonce WHERE expire_le < NOW()');
        if (0 === $this->db->run('INSERT IGNORE INTO api_nonce (nonce, expire_le) VALUES (?,?)', [$nonce, date('Y-m-d H:i:s', time() + 600)])->rowCount()) {
            return new JsonResponse(['erreur' => 'rejeu'], 409);
        }

        return null;
    }
}
