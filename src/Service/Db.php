<?php

namespace App\Service;

/** Accès base de données : PDO et requêtes préparées uniquement. */
final class Db
{
    private ?\PDO $pdo = null;

    public function __construct(private readonly Site $site)
    {
    }

    public function pdo(): \PDO
    {
        if ($this->pdo instanceof \PDO) {
            return $this->pdo;
        }
        $url = parse_url((string) $this->site->env('DATABASE_URL', ''));
        if (empty($url['host'])) {
            throw new \RuntimeException('DATABASE_URL absente.');
        }
        $name = preg_replace('/[^A-Za-z0-9_]/', '', ltrim($url['path'] ?? '', '/'));
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $url['host'], $url['port'] ?? 3306);
        $options = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, \PDO::ATTR_EMULATE_PREPARES => false];
        if ($this->site->env('DB_SSL', '0') === '1') {
            $options[\PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
        }

        // DB_PASSWORD évite d'encoder dans une URL un mot de passe contenant des caractères spéciaux.
        $password = $this->site->env('DB_PASSWORD') ?? rawurldecode($url['pass'] ?? '');
        $this->pdo = new \PDO($dsn, rawurldecode($url['user'] ?? ''), $password, $options);
        try {
            $this->pdo->exec('USE `'.$name.'`');
        } catch (\PDOException) {
            // Première connexion : l'hébergeur fournit un serveur sans base applicative.
            $this->pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$name.'` CHARACTER SET utf8mb4');
            $this->pdo->exec('USE `'.$name.'`');
        }

        return $this->pdo;
    }

    public function run(string $sql, array $params = []): \PDOStatement
    {
        $s = $this->pdo()->prepare($sql);
        $s->execute($params);

        return $s;
    }

    public function one(string $sql, array $params = []): ?array
    {
        $r = $this->run($sql, $params)->fetch();

        return false === $r ? null : $r;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function val(string $sql, array $params = []): mixed
    {
        return $this->run($sql, $params)->fetchColumn();
    }

    public function lastId(): int
    {
        return (int) $this->pdo()->lastInsertId();
    }

    public function tx(callable $fn): mixed
    {
        $this->pdo()->beginTransaction();
        try {
            $r = $fn($this);
            $this->pdo()->commit();

            return $r;
        } catch (\Throwable $e) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
            throw $e;
        }
    }
}
