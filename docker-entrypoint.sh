#!/bin/sh
set -e

# On lance les migrations automatiquement si on est en prod
if [ "$APP_ENV" = 'prod' ]; then
    # php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
    php bin/console doctrine:schema:update --force --no-interaction
    # php bin/console doctrine:fixtures:load --no-interaction --yes
    touch var/log/prod.log 
    chmod 777 var/log/prod.log
    php bin/console cache:clear --no-interaction
    # php bin/console app:import-electoral-data import_electoral_data_geocoded.csv --delimiter=";"
fi

# On lance la commande par défaut (FrankenPHP)
exec "$@"