# Thé Tip Top — site du jeu-concours

Projet étudiant fictif (ESTD Archi, groupe G1, agence Furious Ducks). Aucun achat, aucune réservation ni participation réelle n'est possible.

PHP 8.3, Symfony 7.4 LTS, Apache, MariaDB, Redis, le tout en Docker.

## Branches

| Branche | Environnement |
|---|---|
| `dev` | Développement |
| `preprod` | Préproduction et recette |
| `prod` | Production |

Le code suit le chemin `dev` → `preprod` → `prod`.

## Lancer le projet en local

```bash
docker compose up -d --build
docker compose exec web php -d memory_limit=-1 bin/console app:codes:generate 5000
```

Le site est alors disponible sur http://localhost:8080. Les identifiants de démonstration locale sont dans `docker-compose.yml`.

## Tests

```bash
docker run --rm -v "$PWD:/app" -w /app php:8.3-cli php vendor/bin/phpunit
```

## Variables d'environnement

Aucun secret n'est versionné. En production, renseigner :

| Variable | Rôle |
|---|---|
| `APP_ENV`, `APP_SECRET` | Symfony |
| `SITE_ENV` | `prod` pour autoriser l'indexation, toute autre valeur la bloque |
| `SITE_URL` | Adresse officielle (balises canoniques, sitemap) |
| `DATABASE_URL` | `mysql://utilisateur:motdepasse@hote:3306/base` |
| `DB_SSL` | `1` pour une connexion TLS |
| `REDIS_URL` | Cache et quotas (facultatif) |
| `API_HMAC_SECRET` | Signature de l'API `/api/v1` |
| `ADMIN_LOGIN`, `ADMIN_PASSWORD` | Compte administrateur créé au démarrage |
| `EMPLOYE_LOGIN`, `EMPLOYE_PASSWORD` | Compte boutique |
| `HUISSIER_LOGIN`, `HUISSIER_PASSWORD` | Compte huissier |
| `CODES_AUTOGEN` | Nombre de codes à générer au premier démarrage |
| `GAME_START`, `GAME_END`, `GAME_CLAIM_END` | Dates du jeu |
| `GAME_FORCE_OPEN` | `1` pour tester hors période |
| `BREVO_API_KEY`, `MAIL_FROM` | Courriels transactionnels |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Connexion Google |
| `FACEBOOK_CLIENT_ID`, `FACEBOOK_CLIENT_SECRET` | Connexion Facebook |
| `GA_ID` | Google Analytics, chargé après consentement |

## API

Les routes `/api/v1` (sauf `/sante`) exigent les en-têtes `X-Timestamp`, `X-Nonce` et `X-Signature`, où la signature est le HMAC-SHA256 de `METHODE\nURI\nTIMESTAMP\nNONCE\nCORPS`.
