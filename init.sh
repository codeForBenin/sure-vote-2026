#!/usr/bin/env bash
# Script d'initialisation pour le projet Sure Vote - Élections Bénin
# Symfony 7.4 / PHP 8.4 / PostgreSQL / UUIDv7
# Utilise AssetMapper (No-Node) + TailwindBundle

set -euo pipefail

APP_DIR="${1:-.}"
PROJECT_NAME="sure-vote"

# Ports host
PG_HOST_PORT="${PG_HOST_PORT:-5440}"

need() { command -v "$1" >/dev/null 2>&1 || { echo "Missing: $1" >&2; exit 1; }; }

need php
need composer
need docker

echo "🚀 Initialisation du projet Symfony 7.4 dans ${APP_DIR}..."

if [ "${APP_DIR}" != "." ] && [ ! -d "${APP_DIR}/vendor" ]; then
    composer create-project symfony/skeleton:7.4.* "${APP_DIR}"
    cd "${APP_DIR}"
fi

echo "📦 Installation des dépendances (AssetMapper, Security, ORM, UID, Tailwind)..."
composer require \
    webapp \
    symfony/uid \
    symfony/tailwind-bundle \
    easycorp/easyadmin-bundle \
    vich/uploader-bundle \
    symfony/runtime

# Dev dependencies
composer require --dev symfony/maker-bundle symfony/debug-bundle

echo "🐳 Configuration Docker Compose (Postgres 17)..."
cat > compose.yaml <<YAML
services:
  database:
    image: postgres:17-alpine
    environment:
      POSTGRES_DB: ${PROJECT_NAME}
      POSTGRES_USER: your_postgres_user
      POSTGRES_PASSWORD: your_postgres_password
    ports:
      - "${PG_HOST_PORT}:5432"
    volumes:
      - db_data:/var/lib/postgresql/data
volumes:
  db_data:
YAML

echo "🔑 Configuration DATABASE_URL..."
if [ -f .env ]; then
    echo "DATABASE_URL=\"postgresql://your_postgres_user:your_postgres_password@127.0.0.1:${PG_HOST_PORT}/${PROJECT_NAME}?serverVersion=17\&charset=utf8\"" > .env
fi

echo "🎨 Initialisation Tailwind (Standalone CLI)..."
php bin/console tailwind:init

echo "🏗️ Configuration UUIDv7 par défaut..."
mkdir -p config/packages
cat > config/packages/doctrine.yaml <<YAML
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
    orm:
        auto_generate_proxy_classes: true
        enable_lazy_ghost_objects: true
        report_fields_where_declared: true
        validate_xml_mapping: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true
        mappings:
            App:
                type: attribute
                is_bundle: false
                dir: '%kernel.project_dir%/src/Entity'
                prefix: 'App\Entity'
                alias: App
YAML

echo "🛠️ Lancement de la base de données..."
docker compose up -d

echo "✅ Environnement prêt."
