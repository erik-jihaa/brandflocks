#!/usr/bin/env bash
# Stel login-wachtwoord voor een CE-tenant in (via CE_RESET_TOKEN in .env)
#
# Gebruik (kies één):
#   bash scripts/reset-login-password.sh 'jouw-nieuwe-wachtwoord'
#   CE_NEW_PASSWORD='jouw-nieuwe-wachtwoord' bash scripts/reset-login-password.sh
#   bash scripts/reset-login-password.sh   # interactief (typing is onzichtbaar bij -s)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
source "$ROOT/scripts/ce-env.sh"

TARGET_ENV="${1:-}"
if [[ "$TARGET_ENV" == "staging" || "$TARGET_ENV" == "production" ]]; then
  shift
fi

ENV_FILE="$(ce_env_file "$ROOT" "$TARGET_ENV")" || exit 1
if [[ -z "$ENV_FILE" || ! -f "$ENV_FILE" ]]; then
  echo "Missing ${ENV_FILE:-.env.$TARGET_ENV}" >&2
  exit 1
fi

TOKEN="$(ce_env_value "$ENV_FILE" CE_RESET_TOKEN)"
BASE="$(ce_env_value "$ENV_FILE" CE_PUBLIC_BASE_URL)"
BASE="${BASE:-https://ce.igisnext.com}"
CLIENT_ID="016384af-b26e-4415-aa9c-abc46b804798"

usage() {
  cat <<EOF
Gebruik:
  bash scripts/reset-login-password.sh [staging|production] 'nieuw-wachtwoord'
  CE_NEW_PASSWORD='nieuw-wachtwoord' bash scripts/reset-login-password.sh staging

Optioneel laatste argument = client_id (default: iGiSNext).

Interactief: bash scripts/reset-login-password.sh staging
  → je ziet niets tijdens typen (beveiliging); dat is normaal. Enter om te bevestigen.
EOF
}

if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
  usage
  exit 0
fi

if [[ -z "$TOKEN" ]]; then
  echo "Zet CE_RESET_TOKEN in $(basename "$ENV_FILE")" >&2
  exit 1
fi

echo "==> Omgeving: ${TARGET_ENV:-default} — $BASE"

if [[ -n "${2:-}" && "${2:-}" =~ ^[0-9a-f-]{36}$ ]]; then
  CLIENT_ID="$2"
  PASS="${1:-}"
elif [[ -n "${1:-}" ]]; then
  PASS="$1"
else
  PASS="${CE_NEW_PASSWORD:-}"
fi

if [[ -z "$PASS" ]]; then
  echo "Nieuw wachtwoord (min. 8 tekens)."
  echo "Let op: tijdens typen zie je geen tekens — dat hoort zo. Daarna Enter."
  if [[ -e /dev/tty ]]; then
    read -r -s -p "> " PASS </dev/tty
    echo "" >/dev/tty
  else
    read -r -s -p "> " PASS
    echo ""
  fi
fi

if [[ ${#PASS} -lt 8 ]]; then
  echo "Wachtwoord moet minimaal 8 tekens zijn." >&2
  exit 1
fi

python3 - "$BASE" "$TOKEN" "$CLIENT_ID" "$PASS" <<'PY'
import json, sys, urllib.request

base, token, client_id, password = sys.argv[1:5]
body = json.dumps({"password": password, "clientId": client_id}).encode()
req = urllib.request.Request(
    f"{base.rstrip('/')}/api/admin/set-password",
    data=body,
    headers={
        "Accept": "application/json",
        "Content-Type": "application/json",
        "X-CE-Reset-Token": token,
    },
    method="POST",
)
try:
    with urllib.request.urlopen(req, timeout=30) as resp:
        data = json.loads(resp.read().decode())
except urllib.error.HTTPError as exc:
    payload = json.loads(exc.read().decode())
    raise SystemExit(f"Mislukt (HTTP {exc.code}): {payload.get('error', exc.reason)}") from exc

print(f"OK — wachtwoord gezet voor {data.get('company', client_id)} ({data.get('contactEmail', '')})")
print("Log in op", base, "met je e-mail + dit wachtwoord.")
PY
