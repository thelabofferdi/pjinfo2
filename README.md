# Projet informatique 2 — Gestion des stages

Application web de gestion des stages : dépôt des dossiers par les étudiants, validation ou refus motivé, affectation d'un encadreur.

| | |
|---|---|
| **Cours** | Projet informatique 2 — L3 SIL (10 chapitres, 25 h, 10 TP) |
| **Groupe** | ABDOULAYE Mansouratou, AHOUANSÉ Léa, KOUAGOU Fernandez, YACOUBOU FAISSOL (groupe de 4) |
| **Dépôt** | <https://github.com/thelabofferdi/pjinfo2> |
| **Environnement** | PHP 8, MySQL ou MariaDB, Git — données fictives uniquement |

## Organisation du dépôt

| Dossier | Contenu |
|---|---|
| `TP1/` | Fiche de cadrage (`.docx` à rendre, `.pdf` pour lecture, `.html` source du rendu) |
| `TP2/` | Exigences et matrice de traçabilité |
| `TP3/` | Diagrammes UML, scripts SQL, jeu d'essai |
| `TP4/` | Squelette PHP, contrat des routes, ADR-001 |

## Branches

`main` reste toujours exécutable. Le travail se fait sur des branches courtes
(`tp1`, `tp2`, …) fusionnées après relecture du diff.

| Branche | Contenu |
|---|---|
| `main` | Squelette du dépôt : `README.md`, `.gitignore` |
| `tp1` | Fiche de cadrage du projet |
| `tp2` | Exigences fonctionnelles et non fonctionnelles, matrice de traçabilité |
| `tp3` | Modèle UML, scripts SQL et jeu d'essai vérifié |
| `tp4` | Squelette technique, contrat d'API, ADR-001 (fusionné dans `main`) |
| `tp6` | API des demandes : authentification, transitions, transaction, tests |
| `tp7` | Interface web (pages étudiant et responsable) et scénario de bout en bout |

Les branches `tp6` et `tp7` ne sont pas encore fusionnées dans `main`.

## Périmètre retenu (v1)

**Inclus** : authentification et rôles, dépôt d'une demande de stage, validation ou refus motivé,
affectation d'un encadreur, consultation de l'état des demandes, journal des décisions.

**Exclus** (reportés en v2) : programmation des soutenances, paiement en ligne, notifications SMS,
portail entreprise, convention signée électroniquement, messagerie interne.

## État d'avancement

| TP | Livrable | Statut |
|---|---|---|
| TP1 | Fiche de cadrage (2 pages) | Fait |
| TP2 | Exigences (EF, ENF) et matrice de traçabilité | Fait |
| TP3 | Diagrammes UML, schéma SQL, jeu d'essai | Fait |
| TP4 | Squelette technique, contrat d'API, ADR-001 | Fait |
| TP5 | Kanban, flux de branches, conflit résolu | Fait en local, non publié |
| TP6 | API des demandes (14 cas testés) | Fait, branche `tp6` |
| TP7 | Interface web et scénario de bout en bout (15 vérifications) | Fait, branche `tp7` |
| TP8 à TP10 | — | À venir |
