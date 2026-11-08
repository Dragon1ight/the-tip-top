<?php

namespace App\Command;

use App\Security\AppUser;
use App\Service\Db;
use App\Service\Site;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Crée le schéma (idempotent) et les comptes de service définis par variables d'environnement. */
#[AsCommand(name: 'app:migrate')]
final class MigrateCommand extends Command
{
    public function __construct(private readonly Db $db, private readonly Site $site, private readonly UserPasswordHasherInterface $hasher, private readonly string $projectDir = __DIR__.'/../..')
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (glob($this->projectDir.'/migrations/*.sql') as $file) {
            foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', (string) file_get_contents($file)))) as $sql) {
                $this->db->run($sql);
            }
        }
        foreach ([['ADMIN', 'admin', null], ['EMPLOYE', 'employe', 10], ['HUISSIER', 'huissier', null]] as [$k, $role, $boutique]) {
            $login = $this->site->env($k.'_LOGIN');
            $pw = $this->site->env($k.'_PASSWORD');
            if ($login && $pw && !$this->db->val('SELECT 1 FROM employe WHERE identifiant=?', [$login])) {
                $this->db->run('INSERT INTO employe (boutique_id,nom,identifiant,mot_de_passe_hash,role) VALUES (?,?,?,?,?)', [$boutique, ucfirst($role), $login, $this->hasher->hashPassword(new AppUser('employe', 0, $login, null, '', ['ROLE_EMPLOYE']), $pw), $role]);
            }
        }
        $output->writeln('Schéma à jour.');

        return Command::SUCCESS;
    }
}
