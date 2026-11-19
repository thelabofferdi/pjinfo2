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

## Interface web (TP7)

Pages dans `public/`, servies par le même serveur :

| Page | Rôle | Contenu |
|---|---|---|
| `connexion.html` | tous | Connexion, redirection selon le rôle |
| `etudiant.html` | Étudiant | Dépôt (ou brouillon), suivi, soumission et renvoi après refus |
| `responsable.html` | Responsable | Valider ou refuser avec motif |
| `secretariat.html` | Secrétariat | Recherche par statut et par nom, affectation d'un encadreur |
| `encadreur.html` | Encadreur | Demandes qui lui sont affectées |
| `detail.html?id=N` | selon le rôle | Informations et historique des décisions |
| `compte.html` | tous | Changer son mot de passe |

Routes ajoutées pour l'interface : `GET /api/entreprises`, `GET /api/encadreurs`,
`GET /api/demandes?statut=&nom=`, `GET /api/demandes/{id}`, `PATCH /api/demandes/{id}/encadreur`,
`GET /api/mes-etudiants`, `GET /api/csrf`, `POST /api/deconnexion`, `PATCH /api/mon-mot-de-passe`.

Sécurité : toute requête qui modifie des données exige l'en-tête `X-CSRF-Token` ; cinq échecs de
connexion pour un même couple (adresse, e-mail) bloquent les essais pendant quinze minutes ; les mots
de passe font au moins dix caractères au changement.

Tests : `php tests/test_transitions.php` (unitaire) et `tests/e2e.mjs` (scénario dans Chrome, après
`tests/lancer_stack.sh`).
