# --- Build PHP ---
# On part de l'image officielle FrankenPHP avec PHP 8.4 sur Alpine Linux (très léger)
FROM dunglas/frankenphp:php8.4-alpine

# Installation des extensions PHP nécessaires
# pdo_pgsql : pour votre base de données PostgreSQL
# intl, zip, opcache : extensions standard recommandées pour Symfony
RUN install-php-extensions \
    pdo_pgsql \
    gd \
    intl \
    zip \
    opcache \
    apcu

# --- Configuration de FrankenPHP ---
# Le serveur écoutera sur le port 80
ENV SERVER_NAME=:80
# On pointe le document root sur le dossier /public de Symfony
ENV DOCUMENT_ROOT=/app/public

# Définition du répertoire de travail dans le conteneur
WORKDIR /app

# --- Installation des dépendances PHP ---
# On copie l'exécutable Composer depuis l'image officielle
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# On copie les fichiers de définition des dépendances
COPY composer.json composer.lock symfony.lock ./

# On définit l'environnement en production
ENV APP_ENV=prod

# Installation des paquets Composer pour la production :
# --no-dev : n'installe pas les paquets de développement (phpunit, etc.)
# --no-scripts : n'exécute pas les scripts auto pour l'instant
# --no-progress : ne montre pas la barre de progression (inutile dans les logs)
# --prefer-dist : préfère les archives zip (plus rapide)
RUN composer install --no-dev --no-scripts --no-progress --prefer-dist

# On ajoute gcompat pour que le binaire Tailwind fonctionne sur Alpine
# On ajoute ca-certificates pour les appels HTTPS (Azure Mailer)
RUN apk add --no-cache gcompat ca-certificates

COPY php.ini $PHP_INI_DIR/conf.d/99-custom.ini

# --- Copie du code source ---
COPY . .

# --- Build des Assets ---
# 1. On construit le CSS Tailwind (génère app.css)
RUN php bin/console tailwind:build --minify

# 2. AssetMapper récupère le CSS généré et les autres fichiers
RUN php bin/console asset-map:compile

# --- Optimisation finale ---
RUN composer dump-autoload --classmap-authoritative --no-dev
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]

CMD [ "frankenphp", "php-server", "--root", "public/" ]