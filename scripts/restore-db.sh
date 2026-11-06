#!/usr/bin/env bash
# Zet een dump terug in een LOKALE of STAGING database. Nooit rechtstreeks in productie.
#
#   ./scripts/restore-db.sh ../ce-db-backups/daily/ce-production-20260807-033000.sql.gz local
#   ./scripts/restore-db.sh dump.sql staging
#
# Waarom niet naar productie: een restore is een DROP TABLE + herimport. Herstel op
# productie doe je bewust en handmatig via phpMyAdmin of hosting-support, zodat er
# geen script bestaat dat live data in één commando kan wissen.
#
# Een backup die je nooit hebt teruggezet is een aanname, geen zekerheid — draai dit
# minstens één keer na het inrichten van de backups.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
source "$ROOT/scripts/ce-env.sh"

DUMP="${1:-}"
TARGET_ENV="${2:-local}"

if [[ -z "$DUMP" ]]; then
  echo "Gebruik: ./scripts/restore-db.sh <dump.sql|dump.sql.gz> [local|staging]" >&2
  exit 1
fi

if [[ ! -f "$DUMP" ]]; then
  echo "Dump niet gevonden: $DUMP" >&2
  exit 1
fi

if [[ "$TARGET_ENV" == "production" ]]; then
  echo "Restore naar productie is bewust niet ondersteund in dit script." >&2
  echo "Doe dat handmatig via phpMyAdmin of hosting-support." >&2
  exit 1
fi

ENV_FILE="$(ce_env_file "$ROOT" "$TARGET_ENV")"
if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE" >&2
  exit 1
fi

DB_HOST="$(ce_env_value "$ENV_FILE" DB_HOST)"
DB_PORT="$(ce_env_value "$ENV_FILE" DB_PORT)"
DB_NAME="$(ce_env_value "$ENV_FILE" DB_NAME)"
DB_USER="$(ce_env_value "$ENV_FILE" DB_USER)"
DB_PASS="$(ce_env_value "$ENV_FILE" DB_PASS)"
DB_PORT="${DB_PORT:-3306}"

if [[ -z "$DB_HOST" || -z "$DB_NAME" || -z "$DB_USER" ]]; then
  echo "DB_HOST/DB_NAME/DB_USER ontbreken in $(basename "$ENV_FILE")" >&2
  exit 1
fi

# CE_RESTORE_DOCKER=<container> gebruikt de client in die container, zodat je geen
# mysql-client op de Mac hoeft te installeren.
DOCKER_CONTAINER="${CE_RESTORE_DOCKER:-}"

if [[ -n "$DOCKER_CONTAINER" ]]; then
  CLIENT_BIN="$(docker exec "$DOCKER_CONTAINER" sh -c 'command -v mariadb || command -v mysql' 2>/dev/null | tr -d '\r')"
  if [[ -z "$CLIENT_BIN" ]]; then
    echo "Geen mariadb/mysql-client in container '$DOCKER_CONTAINER'" >&2
    exit 1
  fi
elif ! command -v mysql >/dev/null 2>&1; then
  echo "Geen mysql-client gevonden. Twee opties:" >&2
  echo "  brew install mysql-client" >&2
  echo "  CE_RESTORE_DOCKER=<container> ./scripts/restore-db.sh \"$DUMP\" $TARGET_ENV" >&2
  exit 1
fi

# Leest SQL van stdin, of voert -e "..." uit. Wachtwoord via MYSQL_PWD, niet via -p,
# zodat het niet in de procestabel belandt.
run_client() {
  if [[ -n "$DOCKER_CONTAINER" ]]; then
    docker exec -i -e "MYSQL_PWD=$DB_PASS" "$DOCKER_CONTAINER" \
      "$CLIENT_BIN" -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$@"
  else
    MYSQL_PWD="$DB_PASS" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$@"
  fi
}

echo "Dump:     $DUMP"
echo "Doel:     $TARGET_ENV — $DB_USER@$DB_HOST:$DB_PORT/$DB_NAME"
if [[ -n "$DOCKER_CONTAINER" ]]; then
  echo "Client:   $CLIENT_BIN in container $DOCKER_CONTAINER"
fi
echo
echo "Dit VERWIJDERT bestaande tabellen in $DB_NAME."
read -r -p "Typ '$DB_NAME' om te bevestigen: " confirm
if [[ "$confirm" != "$DB_NAME" ]]; then
  echo "Afgebroken."
  exit 1
fi

echo "==> Importeren"
if [[ "$DUMP" == *.gz ]]; then
  gunzip -c "$DUMP" | run_client "$DB_NAME"
else
  run_client "$DB_NAME" < "$DUMP"
fi

TABLES="$(run_client -N -B \
  -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$DB_NAME'" </dev/null | tr -d '\r')"

echo "==> Klaar — $TABLES tabellen in $DB_NAME"
