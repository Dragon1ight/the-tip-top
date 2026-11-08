<?php

namespace App\Service;

/** Limitation du nombre de tentatives : Redis si disponible, base de données sinon. */
final class Quota
{
    private ?\Redis $redis = null;
    private bool $redisTried = false;

    public function __construct(private readonly Db $db, private readonly Site $site)
    {
    }

    public function hit(string $key, int $max, int $window): bool
    {
        $r = $this->redis();
        if ($r) {
            try {
                $n = $r->incr('quota:'.$key);
                if (1 === (int) $n) {
                    $r->expire('quota:'.$key, $window);
                }

                return $n <= $max;
            } catch (\Throwable) {
                // repli sur la base de données
            }
        }

        return $this->db->tx(function (Db $db) use ($key, $max, $window): bool {
            $row = $db->one('SELECT compteur, fin_fenetre FROM quota WHERE cle = ? FOR UPDATE', [$key]);
            if (null === $row || new \DateTimeImmutable($row['fin_fenetre']) < new \DateTimeImmutable()) {
                $db->run('REPLACE INTO quota (cle, compteur, fin_fenetre) VALUES (?,1,?)', [$key, date('Y-m-d H:i:s', time() + $window)]);

                return true;
            }
            $db->run('UPDATE quota SET compteur = compteur + 1 WHERE cle = ?', [$key]);

            return (int) $row['compteur'] < $max;
        });
    }

    private function redis(): ?\Redis
    {
        if ($this->redisTried) {
            return $this->redis;
        }
        $this->redisTried = true;
        $url = $this->site->env('REDIS_URL');
        if (!$url || !class_exists(\Redis::class)) {
            return null;
        }
        try {
            $p = parse_url($url);
            $r = new \Redis();
            $r->connect($p['host'], $p['port'] ?? 6379, 1.5);
            if (!empty($p['pass'])) {
                $r->auth(rawurldecode($p['pass']));
            }

            return $this->redis = $r;
        } catch (\Throwable) {
            return null;
        }
    }
}
