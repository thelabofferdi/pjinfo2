# Application de gestion des stages — squelette technique (TP4)

Squelette PHP 8 + PDO. Une seule route est implémentée : `GET /api/sante`.
Les trois autres routes du contrat existent dans la table de routage mais répondent `501`.

## Prérequis

| Logiciel | Version | Vérification |
|---|---|---|
| PHP | 8.1 ou plus, avec les extensions `pdo` et `pdo_mysql` | `php -m \| grep pdo_mysql` |
| MariaDB ou MySQL | MariaDB 11 / MySQL 8 | `mariadb --version` |
| Git | 2.30 ou plus | `git --version` |

## Structure

```
public/index.php          point d'entrée unique (front controller)
src/Controllers/          reçoit la requête, écrit la réponse
src/Services/             règles métier
src/Repositories/         seule couche qui exécute du SQL (PDO)
src/Http/                 routage et réponses JSON
config/config.php         lit .env (ou les variables d'environnement)
config/autoload.php       chargement des classes App\...
tests/test_connexion.php  test de connexion à la base
docs/                     contrat des routes, ADR
```

## Installation

```bash
git clone <adresse-du-depot> projet-stages
cd projet-stages/squelette
cp .env.example .env          # puis adapter les valeurs locales
```

Créer la base et un utilisateur SQL dédié (jamais `root` pour l'application) :

```sql
CREATE DATABASE stages CHARACTER SET utf8mb4;
CREATE USER 'stages_app'@'localhost' IDENTIFIED BY 'mot-de-passe-local';
GRANT SELECT, INSERT, UPDATE, DELETE ON stages.* TO 'stages_app'@'localhost';
```

## Lancer et tester

```bash
php tests/verifier_prerequis.php     # vérifie PHP 8.1+, pdo_mysql, git
php tests/test_connexion.php          # doit afficher « OK : connexion PDO établie… »
php -S localhost:8000 -t public       # serveur de développement
curl -i http://localhost:8000/api/sante
```

Réponses attendues sur `/api/sante` :

| Situation | Code | Corps |
|---|---|---|
| base joignable | `200` | `{"statut":"ok","base":"joignable"}` |
| base injoignable | `500` | `{"statut":"indisponible","base":"injoignable"}` |

Le détail de l'erreur (hôte, message PDO) est écrit dans le journal du serveur, jamais dans la réponse.

## Secrets

- Les identifiants vivent dans `.env`, non versionné (voir `.gitignore`).
- `.env.example` ne contient que des valeurs factices.
- Les variables d'environnement, si elles sont définies, remplacent celles du fichier.
