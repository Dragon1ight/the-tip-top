# Serveur du workflow

Prérequis : un VPS Linux avec Docker, et les enregistrements DNS de `jenkins.`, `sonar.`, `grafana.`, `prometheus.`, `dev.` et `preprod.` sur le domaine workflow, ainsi que le domaine du site, vers l'adresse du VPS.

## Installation

```bash
git clone https://github.com/Dragon1ight/the-tip-top.git && cd the-tip-top/infra
cp .env.example .env   # puis remplir les valeurs
sudo mkdir -p /opt/workflow/env
docker compose up -d --build
```

Créer ensuite `/opt/workflow/env/ttt-dev.env` et `ttt-preprod.env` avec `HOST`, `SITE_ENV`, `APP_SECRET`, `API_HMAC_SECRET`, `DB_PASSWORD` et `ADMIN_PASSWORD`.

Déclarer dans GitHub le webhook `https://jenkins.<domaine-workflow>/github-webhook/`, sur l'événement `push`.

## Première mise en service de la bascule

```bash
docker run --rm -v workflow_traefik_dynamic:/traefik-dynamic -v "$PWD/..:/repo" -w /repo -e PUBLIC_HOST=dsp5-archi-o25dis-15m-g1.fr bash:5.2 bash ci/switch-color.sh blue
```

Cette commande crée la première route publique. Ensuite, seuls le pipeline de production et le job `rollback` la modifient.

## Sauvegardes

```bash
docker compose exec backup backup.sh
docker compose exec backup restore-test.sh
```

## Reprise d'activité

Les procédures sont décrites dans le cahier des spécifications techniques, partie G.
