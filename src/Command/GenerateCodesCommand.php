<?php

namespace App\Command;

use App\Service\Db;
use App\Service\Game;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Pré-génère les codes, chacun associé dès sa création à son lot, en respectant exactement les pourcentages. */
#[AsCommand(name: 'app:codes:generate')]
final class GenerateCodesCommand extends Command
{
    public function __construct(private readonly Db $db)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('total', InputArgument::OPTIONAL, 'Nombre de codes', '500000');
    }

    public static function randomCode(): string
    {
        $c = '';
        for ($i = 0; $i < 10; ++$i) {
            $c .= Game::ALPHABET[random_int(0, strlen(Game::ALPHABET) - 1)];
        }

        return $c;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Verrou : deux démarrages simultanés ne peuvent pas générer deux séries.
        if (1 !== (int) $this->db->val("SELECT GET_LOCK('generation_codes', 0)") || (int) $this->db->val('SELECT COUNT(*) FROM code_ticket') > 0) {
            $output->writeln('Des codes existent déjà ou sont en cours de génération : annulation.');

            return Command::SUCCESS;
        }
        $this->db->pdo()->beginTransaction();
        $total = (int) $input->getArgument('total');
        $pool = [];
        foreach ([1 => 60, 2 => 20, 3 => 10, 4 => 6, 5 => 4] as $lot => $pct) {
            $pool = array_merge($pool, array_fill(0, (int) ($total * $pct / 100), $lot));
        }
        for ($i = count($pool) - 1; $i > 0; --$i) {
            $j = random_int(0, $i);
            [$pool[$i], $pool[$j]] = [$pool[$j], $pool[$i]];
        }
        $seen = [];
        $now = date('Y-m-d H:i:s');
        $batch = [];
        $sql = static fn (int $n): string => 'INSERT INTO code_ticket (valeur, lot_id, genere_le) VALUES '.implode(',', array_fill(0, $n, '(?,?,?)'));
        foreach ($pool as $n => $lot) {
            do {
                $code = self::randomCode();
            } while (isset($seen[$code]));
            $seen[$code] = true;
            array_push($batch, $code, $lot, $now);
            if (3000 === count($batch) / 3) {
                $this->db->run($sql(3000), $batch);
                $batch = [];
                if (0 === ($n + 1) % 30000) {
                    $output->writeln(($n + 1).' codes');
                }
            }
        }
        if ($batch) {
            $this->db->run($sql((int) (count($batch) / 3)), $batch);
        }
        $this->db->pdo()->commit();
        $output->writeln(count($pool).' codes générés.');

        return Command::SUCCESS;
    }
}
