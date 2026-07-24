# 🐾 Oxymaux - Gestion de Planning & Activités

Oxymaux est une application web conçue pour la gestion et la présentation d'un planning d'activités (Médiation animale, accompagnement, ateliers, etc.). L'application permet d'afficher les créneaux disponibles, d'indiquer l'état des activités (complet, annulé), de gérer les indisponibilités de l'intervenant(e) et d'exporter le planning mensuel au format image.

---

## 🚀 Fonctionnalités principales

### 📅 Côté Utilisateur / Client
* **Planning interactif :** Visualisation des activités mois par mois avec système de navigation fluide.
* **Prochaine activité :** Module de recherche dynamique de la prochaine date disponible pour une activité spécifique.
* **Légende et filtres :** Identification visuelle rapide des services proposés et de leur statut (Activité complète, annulée, etc.).

### 🛠️ Côté Administration (ROLE_ADMIN)
* **Gestion des activités :** Ajout, modification et annulation d'activités.
* **Blocage de dates :** Possibilité de bloquer/débloquer des dates ou des journées (Repos, Congés, Formation, Jours fériés).
* **Export de planning :** Téléchargement du planning mensuel sous forme d'image propre et stylisée pour impression ou partage sur les réseaux sociaux.

---

## 🛠️ Stack Technique

* **Back-end :** Symfony (PHP)
* **ORM :** Doctrine
* **Front-end :** Twig, Tailwind CSS, JavaScript (Stimulus)
* **Icônes :** FontAwesome
* **Export d'image :** HTML2Canvas
* **Base de données :** MySQL

---

## ⚙️ Installation & Configuration en Local

### Prérequis
* PHP 8.3+
* Composer
* Node.js & NPM
* Un serveur de base de données (MySQL)

### Étapes d'installation

1. **Cloner le projet :**
   ```bash
   git clone https://github.com/Simon0506/Oxymaux.git
   cd oxymaux
   ```

2. **Installer les dépendances PHP :**
   ```bash
   composer install
   ```

3. **Configurer les variables d'environnement :**
   Copiez le fichier `.env` pour créer votre `.env.local` et configurez l'accès à la base de données :
   ```bash
   cp .env .env.local
   ```
   *Dans `.env.local`, modifiez la ligne :*
   `DATABASE_URL="mysql://db_user:db_password@127.0.0.1:3306/db_name?serverVersion=8.0&charset=utf8mb4"`

4. **Créer la base de données et exécuter les migrations :**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

5. **Installer les dépendances JavaScript & compiler les assets :**
   ```bash
   npm install
   npm run dev
   # ou npm run watch pour le développement
   ```

6. **Lancer le serveur local Symfony :**
   ```bash
   symfony server:start
   ```
   Rendez-vous sur `http://127.0.0.1:8000` !

---

## 🔄 Workflow de Déploiement en Production

Pour appliquer les nouvelles fonctionnalités ou modifications de base de données sur le serveur de production :

1. Transférer les fichiers PHP/Twig modifiés ainsi que les nouveaux fichiers dans `migrations/`.
2. Se connecter en SSH au serveur et exécuter :
   ```bash
   php bin/console doctrine:migrations:migrate --env=prod
   php bin/console cache:clear --env=prod
   ```

---

## 📝 Licence

Projet développé pour **Oxymaux**. Tous droits réservés.
