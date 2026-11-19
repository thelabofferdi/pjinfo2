# Contrat des routes — API des demandes de stage

Écrit avant le code et partagé entre backend et frontend. Chaque route indique
la méthode, le chemin, le rôle autorisé, le corps attendu et les codes de retour.

| Méthode | Chemin | Rôle autorisé | Succès | Erreurs | Statut dans le squelette |
|---|---|---|---|---|---|
| GET | `/api/sante` | public | 200 | 500 | **implémentée** |
| GET | `/api/demandes` | Responsable, Secrétariat | 200 | 401, 403 | 501 |
| POST | `/api/demandes` | Étudiant | 201 | 400, 401 | 501 |
| PATCH | `/api/demandes/{id}/statut` | Responsable | 200 | 400, 403, 404, 409 | 501 |

## GET /api/sante

- **Corps** : aucun.
- **200** : `{"statut":"ok","base":"joignable"}`, la base répond à `SELECT 1`.
- **500** : `{"statut":"indisponible","base":"injoignable"}`, la base ne répond pas (erreur serveur, cours §6.5).
  Le détail technique n'est jamais renvoyé au client.

## GET /api/demandes

- **Corps** : aucun.
- **200** : liste des demandes visibles pour le rôle connecté.
- **401** : aucune session.
- **403** : rôle insuffisant (étudiant, encadreur).

## POST /api/demandes

- **Corps** (JSON) : `{"entrepriseId": 3}`.
- **201** : demande créée, statut `BROUILLON` puis `SOUMISE`.
- **400** : champ absent, de mauvais type, ou entreprise inconnue.
- **401** : aucune session.

## PATCH /api/demandes/{id}/statut

- **Corps** (JSON) :
  - validation : `{"statut":"VALIDEE"}`
  - refus : `{"statut":"REFUSEE","motif":"Convention incomplète"}`
- **200** : statut mis à jour et ligne ajoutée à l'historique (transaction).
- **400** : refus sans motif, ou statut inconnu.
- **403** : rôle différent de Responsable.
- **404** : demande inexistante.
- **409** : transition interdite (ex. valider une demande déjà validée).

## Codes génériques du squelette

| Code | Cas |
|---|---|
| 404 | chemin inconnu |
| 405 | chemin connu, méthode non autorisée |
| 501 | route au contrat, pas encore implémentée |
