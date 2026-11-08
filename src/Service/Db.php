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
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $url['host'], $url['port'] ?? 3306, ltrim($url['path'] ?? '', '/'));
        $options = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, \PDO::ATTR_EMULATE_PREPARES => false];
        if ($this->site->env('DB_SSL', '0') === '1') {
            $options[\PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
        }

        return $this->pdo = new \PDO($dsn, rawurldecode($url['user'] ?? ''), rawurldecode($url['pass'] ?? ''), $options);
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
