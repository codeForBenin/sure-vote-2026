# Sure Vote

Sure Vote est une application Symfony conçue pour la gestion des élections. Elle permet de gérer les circonscriptions, les bureaux de vote, ainsi que la saisie et le suivi de la participation et des résultats électoraux.

## Fonctionnalités

- **Gestion des entités électorales** : Administration des circonscriptions et des centres/bureaux de vote.
- **Suivi de la participation** : Saisie et visualisation des taux de participation.
- **Gestion des résultats** : Enregistrement et suivi des résultats des élections.
- **Import/Export** : Fonctionnalités d'importation et d'exportation de données (ex: bureaux de vote, résultats) via fichiers CSV/Excel/ODS.
- **Tableau de bord** : Interface administrateur intuitive (basée sur EasyAdmin).

## Prérequis

- PHP 8.2+
- Composer
- Symfony CLI
- Docker (pour la base de données et le mailer)

## Installation Rapide (Script)

Un script d'initialisation est disponible pour configurer rapidement le projet (dépendances, Docker, variables d'environnement).

```bash
chmod +x init.sh
./init.sh
```

Une fois le script terminé, finalisez l'installation en créant la base de données :

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

## Installation Manuelle

1.  **Cloner le dépôt** :
    ```bash
    git clone https://github.com/votre-utilisateur/sure-vote.git
    cd sure-vote
    ```

2.  **Installer les dépendances** :
    ```bash
    composer install
    ```

3.  **Configuration de l'environnement** :
    - Copiez le fichier d'exemple `.env.example` (ou utilisez `.env` par défaut) :
      ```bash
      # Si vous avez besoin de personnaliser, créez un .env.local
      cp .env .env.local
      ```
    - Configurez votre base de données dans `.env.local` si nécessaire.
    - Si vous utilisez Docker, les services sont définis dans `compose.yaml`. Vous pouvez surcharger la configuration locale avec `compose.override.yaml` (voir `compose.override.yaml.example`).

4.  **Démarrer les services (Docker)** :
    ```bash
    docker compose up -d
    ```

5.  **Initialiser la base de données** :
    ```bash
    php bin/console doctrine:database:create
    php bin/console doctrine:migrations:migrate
    ```

6.  **Compiler les assets (Tailwind CSS / AssetMapper)** :
    ```bash
    php bin/console asset-map:compile
    # Ou en mode dev
    php bin/console tailwind:build --watch
    ```

7.  **Lancer le serveur de développement** :
    ```bash
    symfony server:start
    ```

## Structure du projet

- `src/Entity` : Modèles de données (Circonscription, Bureau de vote, etc.)
- `src/Controller/Admin` : Contrôleurs pour l'interface d'administration EasyAdmin.
- `src/Form` : Formulaires, notamment pour les imports.

## Contribution

Les contributions sont les bienvenues ! Veuillez consulter le fichier pour plus de détails sur la procédure de contribution (si disponible).

## Licence

Ce projet est sous licence propriétaire.
