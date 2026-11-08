<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class AppUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(public readonly string $type, public readonly int $id, private readonly string $identifier, private readonly ?string $hash, public readonly string $nom, private readonly array $roles, public readonly ?int $boutiqueId = null)
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): ?string
    {
        return $this->hash;
    }

    public function eraseCredentials(): void
    {
    }

    public function isParticipant(): bool
    {
        return 'participant' === $this->type;
    }
}
