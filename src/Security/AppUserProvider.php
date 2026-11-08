<?php

namespace App\Security;

use App\Service\Db;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class AppUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(private readonly Db $db)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $id = trim($identifier);
        $p = $this->db->one('SELECT * FROM participant WHERE email = ? AND supprime_le IS NULL AND suspendu = 0', [mb_strtolower($id)]);
        if ($p) {
            return new AppUser('participant', (int) $p['id'], $p['email'], $p['mot_de_passe_hash'], $p['prenom'], ['ROLE_PARTICIPANT']);
        }
        $e = $this->db->one('SELECT * FROM employe WHERE identifiant = ? AND actif = 1', [$id]);
        if ($e) {
            $role = ['admin' => 'ROLE_ADMIN', 'huissier' => 'ROLE_HUISSIER'][$e['role']] ?? 'ROLE_EMPLOYE';

            return new AppUser('employe', (int) $e['id'], $e['identifiant'], $e['mot_de_passe_hash'], $e['nom'], [$role], $e['boutique_id'] ? (int) $e['boutique_id'] : null);
        }
        $ex = new UserNotFoundException();
        $ex->setUserIdentifier($identifier);
        throw $ex;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return AppUser::class === $class;
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        \assert($user instanceof AppUser);
        $this->db->run($user->isParticipant() ? 'UPDATE participant SET mot_de_passe_hash=? WHERE id=?' : 'UPDATE employe SET mot_de_passe_hash=? WHERE id=?', [$newHashedPassword, $user->id]);
    }
}
