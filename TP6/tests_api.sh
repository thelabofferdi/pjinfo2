#!/usr/bin/env bash
# Rejoue les cas de test du TP6 contre une base MariaDB jetable et l'API réelle.
# Sorties : preuves/sorties.tsv (un cas par ligne), preuves/etat_base.tsv, preuves/capture_api.png
# Usage : ./tests_api.sh   (Docker et l'image tp4-php sont requis)
set -u
cd "$(dirname "$0")"
RACINE="$(cd .. && pwd)"
SQUELETTE="$PWD/squelette"
RESEAU=tp6net; BASE=tp6-db; WEB=tp6-web; MDP=test-local-only
SORTIES=preuves/sorties.tsv
ETAT=preuves/etat_base.tsv
JAR_RESP=/tmp/tp6_resp.jar; JAR_ETU=/tmp/tp6_etu.jar; JAR_VIDE=/tmp/tp6_vide.jar

nettoyer() {
  docker rm -f "$WEB" "$BASE" >/dev/null 2>&1
  docker network rm "$RESEAU" >/dev/null 2>&1
  rm -f "$JAR_RESP" "$JAR_ETU" "$JAR_VIDE" /tmp/tp6_corps
}
trap nettoyer EXIT
nettoyer

docker network create "$RESEAU" >/dev/null
docker run -d --name "$BASE" --network "$RESEAU" -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=yes mariadb:11 >/dev/null
for i in $(seq 1 60); do
  if docker exec "$BASE" mariadb -uroot -e 'SELECT 1' >/dev/null 2>&1; then
    sleep 3
    docker exec "$BASE" mariadb -uroot -e 'SELECT 1' >/dev/null 2>&1 && break
  fi
  sleep 2
done

docker exec -i "$BASE" mariadb -uroot -e "CREATE DATABASE stages CHARACTER SET utf8mb4;
  CREATE USER 'stages_app'@'%' IDENTIFIED BY '$MDP';
  GRANT SELECT, INSERT, UPDATE, DELETE ON stages.* TO 'stages_app'@'%';"
docker exec -i "$BASE" mariadb -uroot stages < "$RACINE/TP3/001_schema.sql" || { echo "schéma en erreur"; exit 1; }
docker exec -i "$BASE" mariadb -uroot stages < "$RACINE/TP3/002_seed.sql" || { echo "seed en erreur"; exit 1; }

docker run -d --name "$WEB" --network "$RESEAU" -p 8080:8080 \
  -v "$SQUELETTE":/app -w /app \
  -e DB_HOST="$BASE" -e DB_PORT=3306 -e DB_NAME=stages -e DB_USER=stages_app -e DB_PASSWORD="$MDP" \
  tp4-php php -S 0.0.0.0:8080 -t public >/dev/null
sleep 2

: > "$SORTIES"
printf 'numero\tcas\tattendu\trequete\tobtenu\tconforme\treponse\n' > "$SORTIES"

# appel NUMERO LIBELLE ATTENDU METHODE CHEMIN JAR [CORPS_JSON]
appel() {
  local num=$1 lib=$2 attendu=$3 methode=$4 chemin=$5 jar=$6 corps=${7:-}
  local args=(-s -o /tmp/tp6_corps -w '%{http_code}' -X "$methode" "http://localhost:8080$chemin" -H 'Content-Type: application/json')
  [ -n "$jar" ] && args+=(-b "$jar" -c "$jar")
  [ -n "$corps" ] && args+=(--data "$corps")
  local obtenu; obtenu=$(curl "${args[@]}")
  local reponse; reponse=$(tr -d '\n\t' < /tmp/tp6_corps)
  local conforme=NON; [ "$obtenu" = "$attendu" ] && conforme=OUI
  printf '%s\t%s\t%s\t%s %s\t%s\t%s\t%s\n' "$num" "$lib" "$attendu" "$methode" "$chemin" "$obtenu" "$conforme" "$reponse" >> "$SORTIES"
}

appel S1 "Connexion du responsable" 200 POST /api/connexion "$JAR_RESP" '{"email":"resp.pedago@univ.test","motDePasse":"Demo1234!"}'
appel S2 "Connexion de l'étudiante Aicha Bello" 200 POST /api/connexion "$JAR_ETU" '{"email":"aicha.bello@univ.test","motDePasse":"Demo1234!"}'
appel S3 "Connexion avec un mauvais mot de passe" 401 POST /api/connexion "$JAR_VIDE" '{"email":"aicha.bello@univ.test","motDePasse":"faux"}'
appel S4 "Création d'une demande par l'étudiante (entreprise 2)" 201 POST /api/demandes "$JAR_ETU" '{"entrepriseId":2}'
appel S5 "Succès : le responsable valide la demande 2 (SOUMISE vers VALIDEE)" 200 PATCH /api/demandes/2/statut "$JAR_RESP" '{"statut":"VALIDEE"}'
appel S6 "Erreur 400 : refus sans motif (demande 5)" 400 PATCH /api/demandes/5/statut "$JAR_RESP" '{"statut":"REFUSEE"}'
appel S7 "Erreur 401 : aucune session" 401 PATCH /api/demandes/5/statut "$JAR_VIDE" '{"statut":"VALIDEE"}'
appel S8 "Erreur 403 : l'étudiante tente de valider" 403 PATCH /api/demandes/5/statut "$JAR_ETU" '{"statut":"VALIDEE"}'
appel S9 "Erreur 404 : demande inexistante (999)" 404 PATCH /api/demandes/999/statut "$JAR_RESP" '{"statut":"VALIDEE"}'
appel S10 "Erreur 409 : valider une demande déjà validée (demande 3)" 409 PATCH /api/demandes/3/statut "$JAR_RESP" '{"statut":"VALIDEE"}'
appel S11 "Refus motivé par le responsable (demande 5)" 200 PATCH /api/demandes/5/statut "$JAR_RESP" '{"statut":"REFUSEE","motif":"Convention incomplète"}'
appel S12 "Liste des demandes par le responsable" 200 GET /api/demandes "$JAR_RESP"
appel S13 "Liste réservée au personnel : refus pour l'étudiante" 403 GET /api/demandes "$JAR_ETU"
appel S14 "Entreprise inconnue à la création" 400 POST /api/demandes "$JAR_ETU" '{"entrepriseId":999}'

# État de la base après les cas (sortie brute en mode tabulé, reprise dans le document)
docker exec "$BASE" mariadb -uroot -B stages -e \
  "SELECT id, etudiant_id, statut, motif_refus, date_decision FROM demande_stage ORDER BY id;" > "$ETAT"
docker exec "$BASE" mariadb -uroot -B stages -e \
  "SELECT COUNT(*) AS lignes_historique FROM historique;" >> "$ETAT"

# Capture réelle de l'API : Chrome rend la réponse JSON de /api/sante telle que le navigateur la reçoit
google-chrome --headless --disable-gpu --no-sandbox --hide-scrollbars --window-size=900,260 \
  --screenshot="$PWD/preuves/capture_api.png" http://localhost:8080/api/sante >/dev/null 2>&1

echo "sorties : $(($(wc -l < "$SORTIES") - 1)) cas"
echo "conformes : $(awk -F'\t' 'NR>1 && $6=="OUI"' "$SORTIES" | wc -l)"
