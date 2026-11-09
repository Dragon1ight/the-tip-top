<?php

namespace App\Tests\Integration;

use App\Service\Db;
use App\Service\Game;
use App\Service\Mail;
use App\Service\Quota;
use App\Service\Site;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

/** Règles du jeu vérifiées sur une vraie base MariaDB. */
final class GameFlowTest extends TestCase
{
    private Db $db;
    private Game $game;

    protected function setUp(): void
    {
        putenv('GAME_FORCE_OPEN=1');
        $_ENV['GAME_FORCE_OPEN'] = $_SERVER['GAME_FORCE_OPEN'] = '1';
        $site = new Site();
        $this->db = new Db($site);
        foreach (preg_split('/;\s*\n/', (string) file_get_contents(__DIR__.'/../../migrations/001_schema.sql')) as $sql) {
            if ('' !== trim($sql)) {
                $this->db->run($sql);
            }
        }
        $this->db->run('SET FOREIGN_KEY_CHECKS=0');
        foreach (['journal_remise', 'gain', 'inscription_tirage', 'code_ticket', 'consentement', 'jeton', 'participant', 'employe', 'quota'] as $t) {
            $this->db->run('TRUNCATE TABLE '.$t);
        }
        $this->db->run('SET FOREIGN_KEY_CHECKS=1');
        $this->db->run('UPDATE tirage SET execute_le=NULL, graine=NULL, empreinte_liste=NULL, effectif_participants=NULL, gagnant_participant_id=NULL, verrouille=0');
        $this->game = new Game($this->db, $site, new Quota($this->db, $site), new Mail($site, new MockHttpClient(), new NullLogger(), $this->db));
    }

    private function participant(string $mail): int
    {
        $this->db->run('INSERT INTO participant (email, nom, prenom, cree_le) VALUES (?,?,?,NOW())', [$mail, 'Test', 'Test']);

        return $this->db->lastId();
    }

    private function code(string $v, int $lot = 1): void
    {
        $this->db->run('INSERT INTO code_ticket (valeur, lot_id, genere_le) VALUES (?,?,NOW())', [$v, $lot]);
    }

    public function testCodeValideAttribueLeLotPreAssocie(): void
    {
        $p = $this->participant('a@test.fr');
        $this->code('AB3K7Q9XMT', 4);
        $r = $this->game->play($p, ' ab3k 7q9x mt');
        self::assertTrue($r['ok']);
        self::assertSame(4, (int) $this->db->val('SELECT lot_id FROM gain WHERE id=?', [$r['gain_id']]));
        self::assertSame('a_retirer', $this->db->val('SELECT statut FROM gain WHERE id=?', [$r['gain_id']]));
    }

    public function testCodeInconnuEstRefuse(): void
    {
        self::assertSame('introuvable', $this->game->play($this->participant('b@test.fr'), 'ZZZZZZZZZZ')['error']);
    }

    public function testCodeNePeutServirQuUneFois(): void
    {
        $this->code('AB3K7Q9XMT');
        self::assertTrue($this->game->play($this->participant('c@test.fr'), 'AB3K7Q9XMT')['ok']);
        self::assertSame('deja', $this->game->play($this->participant('d@test.fr'), 'AB3K7Q9XMT')['error']);
        self::assertSame(1, (int) $this->db->val('SELECT COUNT(*) FROM gain'));
    }

    public function testUneSeuleInscriptionAuTirageParParticipant(): void
    {
        $p = $this->participant('e@test.fr');
        $this->code('AB3K7Q9XMT');
        $this->code('CD4M8R2YNU');
        $this->game->play($p, 'AB3K7Q9XMT');
        $this->game->play($p, 'CD4M8R2YNU');
        self::assertSame(1, (int) $this->db->val('SELECT COUNT(*) FROM inscription_tirage WHERE participant_id=?', [$p]));
    }

    public function testQuotaBloqueLaRechercheExhaustive(): void
    {
        $p = $this->participant('f@test.fr');
        for ($i = 0; $i < Game::MAX_TRIES; ++$i) {
            $this->game->play($p, 'ZZZZZZZZZZ');
        }
        self::assertSame('quota', $this->game->play($p, 'ZZZZZZZZZZ')['error']);
    }

    public function testRemiseTraceeEtNonRejouable(): void
    {
        $p = $this->participant('g@test.fr');
        $this->code('AB3K7Q9XMT');
        $id = $this->game->play($p, 'AB3K7Q9XMT')['gain_id'];
        self::assertTrue($this->game->deliver($id, null, 10)['ok']);
        self::assertFalse($this->game->deliver($id, null, 10)['ok']);
        self::assertSame(1, (int) $this->db->val('SELECT COUNT(*) FROM journal_remise WHERE gain_id=?', [$id]));
    }

    public function testTirageUniqueEtVerrouille(): void
    {
        $p = $this->participant('h@test.fr');
        $this->code('AB3K7Q9XMT');
        $this->game->play($p, 'AB3K7Q9XMT');
        self::assertTrue($this->game->draw('test')['ok']);
        self::assertFalse($this->game->draw('test')['ok']);
        $t = $this->db->one('SELECT * FROM tirage WHERE id=1');
        self::assertSame($p, (int) $t['gagnant_participant_id']);
        self::assertSame(64, strlen($t['graine']));
    }

    public function testContrainteUniciteDesCodes(): void
    {
        $this->code('AB3K7Q9XMT');
        $this->expectException(\PDOException::class);
        $this->code('AB3K7Q9XMT');
    }
}
