#!/usr/bin/env bash
# Lance MariaDB 11 et l'API sur le port 8081, avec le schéma et le jeu d'essai du TP3.
# Usage (depuis TP7/) : tests/lancer_stack.sh
set -eu
cd "$(dirname "$0")/.."
RACINE="$(cd .. && pwd)"
MDP=test-local-only

attendre_base() {
  local i
  for i in $(seq 1 60); do
    if docker exec "$1" mariadb -uroot -e 'SELECT 1' >/dev/null 2>&1; then
      sleep 3
      docker exec "$1" mariadb -uroot -e 'SELECT 1' >/dev/null 2>&1 && return 0
    fi
    sleep 2
  done
  echo "la base $1 ne répond pas" >&2
  return 1
}

docker rm -f tp7-web tp7-db >/dev/null 2>&1 || true
docker network rm tp7net >/dev/null 2>&1 || true
docker network create tp7net >/dev/null
docker run -d --name tp7-db --network tp7net -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=yes mariadb:11 >/dev/null
attendre_base tp7-db

docker exec -i tp7-db mariadb -uroot -e "CREATE DATABASE stages CHARACTER SET utf8mb4;
  CREATE USER 'stages_app'@'%' IDENTIFIED BY '$MDP';
  GRANT SELECT, INSERT, UPDATE, DELETE ON stages.* TO 'stages_app'@'%';"
docker exec -i tp7-db mariadb -uroot stages < "$RACINE/TP3/001_schema.sql"
docker exec -i tp7-db mariadb -uroot stages < "$RACINE/TP3/002_seed.sql"

docker run -d --name tp7-web --network tp7net -p 8081:8080 \
  -v "$PWD/squelette":/app -w /app \
  -e DB_HOST=tp7-db -e DB_PORT=3306 -e DB_NAME=stages -e DB_USER=stages_app -e DB_PASSWORD="$MDP" \
  tp4-php php -S 0.0.0.0:8080 -t public >/dev/null
sleep 2
echo "pile prête : http://localhost:8081/connexion.html"
