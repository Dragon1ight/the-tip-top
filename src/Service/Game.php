<?php

namespace App\Service;

/** Règles du jeu : validation d'un code, attribution du lot pré-associé, remise, tirage. */
final class Game
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    public const MAX_TRIES = 10;
    private const TIRAGE = 1;

    public function __construct(private readonly Db $db, private readonly Site $site, private readonly Quota $quota, private readonly Mail $mail)
    {
    }

    public static function normalize(string $raw): string
    {
        return strtoupper(preg_replace('/[\s\-]+/', '', $raw) ?? '');
    }

    public static function validFormat(string $code): bool
    {
        return 1 === preg_match('/^['.self::ALPHABET.']{10}$/', $code);
    }

    /** @return array{ok:bool,error?:string,message?:string,gain_id?:int} */
    public function play(int $participantId, string $raw): array
    {
        if (!$this->site->isOpen()) {
            return ['ok' => false, 'error' => 'clos', 'message' => 'La saisie des codes est ouverte du '.$this->site->dateFr($this->site->start()).' au '.$this->site->dateFr($this->site->claimEnd()).'.'];
        }
        if (!$this->quota->hit('code:'.$participantId, self::MAX_TRIES, 3600)) {
            return ['ok' => false, 'error' => 'quota', 'message' => 'Trop de tentatives : vous pourrez réessayer dans une heure.'];
        }
        $code = self::normalize($raw);
        if (!self::validFormat($code)) {
            return ['ok' => false, 'error' => 'introuvable', 'message' => 'Ce code ne correspond à aucun ticket. Vérifiez votre saisie : le code comporte dix caractères.'];
        }

        $r = $this->db->tx(function (Db $db) use ($participantId, $code): array {
            $row = $db->one('SELECT valeur, lot_id, participant_id, utilise_le FROM code_ticket WHERE valeur = ? FOR UPDATE', [$code]);
            if (null === $row) {
                return ['ok' => false, 'error' => 'introuvable', 'message' => 'Ce code ne correspond à aucun ticket. Vérifiez votre saisie : le code comporte dix caractères.'];
            }
            if (null !== $row['participant_id']) {
                $d = (new \DateTimeImmutable($row['utilise_le']))->format('d/m/Y');

                return ['ok' => false, 'error' => 'deja', 'message' => 'Ce code a déjà été utilisé le '.$d.'. Retrouvez le lot associé dans votre historique.'];
            }
            $now = date('Y-m-d H:i:s');
            $db->run('UPDATE code_ticket SET participant_id = ?, utilise_le = ? WHERE valeur = ?', [$participantId, $now, $code]);
            $db->run('INSERT INTO gain (participant_id, code_valeur, lot_id, statut, attribue_le, date_limite) VALUES (?,?,?,?,?,?)', [$participantId, $code, $row['lot_id'], 'a_retirer', $now, $this->site->claimEnd()->format('Y-m-d H:i:s')]);
            $id = $db->lastId();
            $db->run('INSERT IGNORE INTO inscription_tirage (tirage_id, participant_id, inscrit_le) VALUES (?,?,?)', [self::TIRAGE, $participantId, $now]);

            return ['ok' => true, 'gain_id' => $id];
        });

        if ($r['ok']) {
            $this->mail->gainConfirmation($r['gain_id']);
        }

        return $r;
    }

    public function statutAffiche(array $gain): string
    {
        return ('a_retirer' === $gain['statut'] && new \DateTimeImmutable($gain['date_limite']) < new \DateTimeImmutable()) ? 'expire' : $gain['statut'];
    }

    /** Remise ou expédition d'un lot, tracée de façon immuable dans le journal. */
    public function deliver(int $gainId, ?int $employeId, ?int $boutiqueId, ?string $motif = null): array
    {
        return $this->db->tx(function (Db $db) use ($gainId, $employeId, $boutiqueId, $motif): array {
            $g = $db->one('SELECT * FROM gain WHERE id = ? FOR UPDATE', [$gainId]);
            if (null === $g) {
                return ['ok' => false, 'message' => 'Lot introuvable.'];
            }
            if ('a_retirer' !== $g['statut']) {
                return ['ok' => false, 'message' => 'Ce lot a déjà été remis.'];
            }
            if ('expire' === $this->statutAffiche($g)) {
                return ['ok' => false, 'message' => 'Le délai de réclamation est expiré : la remise est bloquée.'];
            }
            $statut = 'expedition' === $g['mode_remise'] ? 'expedie' : 'remis';
            $now = date('Y-m-d H:i:s');
            $db->run('UPDATE gain SET statut = ?, remis_le = ?, boutique_id = COALESCE(boutique_id, ?), employe_id = ? WHERE id = ?', [$statut, $now, $boutiqueId, $employeId, $gainId]);
            $db->run('INSERT INTO journal_remise (gain_id, employe_id, boutique_id, action, motif_substitution, horodatage) VALUES (?,?,?,?,?,?)', [$gainId, $employeId, $boutiqueId, $motif ? 'substitution' : $statut, $motif, $now]);

            return ['ok' => true, 'message' => 'expedie' === $statut ? 'Expédition enregistrée.' : 'Remise enregistrée.', 'participant_id' => (int) $g['participant_id']];
        });
    }

    /** Index gagnant déterminé par la graine et la liste : le tirage peut être rejoué par l'huissier. */
    public static function winnerIndex(string $seed, string $listHash, int $count): int
    {
        $hash = hash('sha256', $seed.'|'.$listHash);

        return (int) (hexdec(substr($hash, 0, 12)) % $count);
    }

    /** Exécuté côté serveur, une seule fois : chaque participant ne compte que pour une entrée. */
    public function draw(string $auteur): array
    {
        if (!$this->site->drawAllowed()) {
            return ['ok' => false, 'message' => 'Le tirage ne peut être lancé qu\'après la fin de la période de réclamation.'];
        }

        return $this->db->tx(function (Db $db) use ($auteur): array {
            $t = $db->one('SELECT * FROM tirage WHERE id = ? FOR UPDATE', [self::TIRAGE]);
            if ((int) $t['verrouille'] === 1) {
                return ['ok' => false, 'message' => 'Le tirage a déjà été effectué : il est verrouillé.'];
            }
            $ids = array_map('intval', array_column($db->all('SELECT i.participant_id FROM inscription_tirage i JOIN participant p ON p.id = i.participant_id WHERE i.tirage_id = ? AND p.supprime_le IS NULL ORDER BY i.participant_id', [self::TIRAGE]), 'participant_id'));
            if ([] === $ids) {
                return ['ok' => false, 'message' => 'Aucun participant éligible.'];
            }
            $seed = bin2hex(random_bytes(32));
            $list = hash('sha256', implode(',', $ids));
            $winner = $ids[self::winnerIndex($seed, $list, count($ids))];
            $now = date('Y-m-d H:i:s');
            $db->run('UPDATE tirage SET execute_le=?, graine=?, empreinte_liste=?, effectif_participants=?, gagnant_participant_id=?, verrouille=1 WHERE id=?', [$now, $seed, $list, count($ids), $winner, self::TIRAGE]);
            $db->run('INSERT INTO journal_admin (auteur, action, cible, horodatage) VALUES (?,?,?,?)', [$auteur, 'tirage_gros_lot', 'participant '.$winner, $now]);

            return ['ok' => true, 'message' => 'Tirage effectué et verrouillé.', 'winner' => $winner];
        });
    }
}
