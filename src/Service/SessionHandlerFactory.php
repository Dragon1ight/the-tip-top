<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

/** Sessions en base : elles survivent à la mise en veille et aux bascules blue/green. */
final class SessionHandlerFactory
{
    public function __construct(private readonly Db $db, private readonly Site $site)
    {
    }

    public function create(): \SessionHandlerInterface
    {
        if (!$this->site->env('DATABASE_URL')) {
            return new NativeFileSessionHandler();
        }

        return new PdoSessionHandler($this->db->pdo(), ['db_table' => 'sessions', 'lock_mode' => PdoSessionHandler::LOCK_NONE]);
    }
}
