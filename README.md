# Sure Vote 🇧🇯

**Sure Vote** est une plateforme open-source de **compilation citoyenne des résultats électoraux**. Elle permet de collecter, sécuriser et visualiser en temps réel les données issues des bureaux de vote, garantissant transparence et fiabilité grâce à un processus de validation rigoureux.

## 🚀 Fonctionnalités Clés

### 🗳️ Surveillance Électorale
- **Rôles Hiérarchiques** :
  - **Admin Global** : Gestion technique et configuration des élections.
  - **Superviseur** : Responsable d'une zone (Département/Circonscription), valide les inscriptions des assesseurs.
  - **Assesseur** : Citoyen sur le terrain, assigné à un bureau de vote spécifique.
- **Processus de Validation** : Les assesseurs s'inscrivent et doivent être **validés et assignés** à un bureau par un Superviseur avant de pouvoir agir.

### 🛡️ Sécurité & Fiabilité
- **Géolocalisation Obligatoire** : La saisie des résultats et le pointage de la participation sont bloqués si l'assesseur n'est pas physiquement proche du centre de vote (Vérification GPS serveur).
- **Preuve par l'Image** : Le téléchargement de la photo du **Procès-Verbal (PV)** est obligatoire pour soumettre un résultat.
- **Traçabilité** : Chaque action sensible est loguée avec IP, User-Agent et localisation.

### 📊 Visualisation & Data
- **Tableau de Bord Live** : Taux de participation et résultats en temps réel.
- **Projections de Sièges** : Algorithme de calcul des sièges selon le code électoral béninois (Méthode du quotient électoral + 10% national, 20% circonscription).
- **Cartographie** : Recherche intuitive des centres et visualisation des données.

---

## 🛠️ Installation

### Prérequis
- PHP 8.2+
- Composer
- Symfony CLI
- Docker (recommandé pour la base de données PostgreSQL)

### 1. Initialisation du projet

Clonez le dépôt et installez les dépendances :
```bash
git clone https://github.com/codeForBenin/sure-vote-2026.git
cd sure-vote-2026
composer install
```

### 2. Configuration de l'environnement

Copiez le fichier d'exemple et configurez vos variables (Base de données, Mailer...) :
```bash
cp .env.example .env.local
```

Générez un **APP_SECRET** sécurisé pour votre application :
```bash
# Affiche une clé aléatoire hexadécimale
php generate-secret.php
```
Copiez cette clé dans votre fichier `.env.local` pour la variable `APP_SECRET`.

### 3. Base de Données

Lancez les conteneurs Docker (PostgreSQL) :
```bash
docker compose up -d
```

Créez la base et appliquez les migrations (Note: les migrations ne sont pas versionnées, générez-les si besoin ou utilisez le schéma) :
```bash
php bin/console doctrine:database:create
php bin/console doctrine:schema:update --force
# OU si vous avez des migrations locales
# php bin/console doctrine:migrations:migrate
```

### 4. Lancement

Compilez les assets (TailwindCSS) et lancez le serveur :
```bash
php bin/console tailwind:build
symfony server:start
```
Accédez à `https://127.0.0.1:8000`.

---

## 🏗️ Architecture Technique

- **Framework** : Symfony 7.x
- **Admin** : EasyAdmin Bundle
- **Frontend** : Twig, TailwindCSS, Stimulus (UX Turbo)
- **Base de données** : PostgreSQL
- **Uploads** : VichUploader (PVs stockés localement ou S3 compatible)

## 🤝 Contribution

Les contributions pour améliorer la transparence électorale sont les bienvenues. Merci de respecter le code de conduite.

1. Forkez le projet
2. Créez votre branche (`git checkout -b feature/AmazingFeature`)
3. Commitez vos changements (`git commit -m 'Add some AmazingFeature'`)
4. Poussez vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrez une Pull Request

## 📄 Licence

Ce projet est sous licence propriétaire/fermée pour le moment. Contactez l'auteur pour toute demande d'utilisation.
