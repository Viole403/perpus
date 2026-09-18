#!/usr/bin/env bash
# customer-install.sh — jalan DI hosting customer (via SSH).
# Membuat 2 database+user via UAPI cPanel, lalu memanggil install.sh.
# Dibangkitkan/dikirim oleh deploy-customer.sh. JANGAN commit file ini
# dengan kredensial — semua secret masuk via argumen (sekali pakai).
set -euo pipefail

MODE=both; TARGET="public_html"; CPANEL_USER=""; SLIMS_SAMPLE=0
INLIS_URL=""; SLIMS_URL=""
INLIS_DB_SFX=inlisdb; INLIS_USR_SFX=inlisuser; INLIS_PASS=""
SLIMS_DB_SFX=slimsdb; SLIMS_USR_SFX=slimsuser; SLIMS_PASS=""
ADMIN_USER=admin; ADMIN_PASS=""

while [ $# -gt 0 ]; do case "$1" in
  --mode) MODE="$2"; shift 2;; --target) TARGET="$2"; shift 2;;
  --cpanel-user) CPANEL_USER="$2"; shift 2;;
  --inlis-url) INLIS_URL="$2"; shift 2;; --slims-url) SLIMS_URL="$2"; shift 2;;
  --inlis-pass) INLIS_PASS="$2"; shift 2;; --slims-pass) SLIMS_PASS="$2"; shift 2;;
  --inlis-db-sfx) INLIS_DB_SFX="$2"; shift 2;; --inlis-usr-sfx) INLIS_USR_SFX="$2"; shift 2;;
  --slims-db-sfx) SLIMS_DB_SFX="$2"; shift 2;; --slims-usr-sfx) SLIMS_USR_SFX="$2"; shift 2;;
  --admin-user) ADMIN_USER="$2"; shift 2;; --admin-pass) ADMIN_PASS="$2"; shift 2;;
  --slims-sample) SLIMS_SAMPLE=1; shift;;
  -h|--help) grep '^# ' "$0" | sed 's/^# //'; exit 0;;
  *) echo "argumen tak dikenal: $1"; exit 2;;
esac; done

[ -n "$CPANEL_USER" ] || { echo "--cpanel-user wajib"; exit 2; }
command -v uapi >/dev/null || { echo "uapi tidak ditemukan (butuh SSH hosting cPanel)"; exit 1; }

ujson() { uapi --output=json "$@"; }
ok() { # ok <json> — exit 1 bila status != 1 (log ke stderr agar stdout bersih untuk capture)
  echo "$1" | grep -qE '"status":\s*1' || { echo "UAPI gagal: $2" >&2; echo "$1" | head -5 >&2; return 1; }
}

mkdb() { # mkdb <db_sfx> <user_sfx> <pass> — SATU-SATUNYA output stdout: "full_db full_user"; log ke stderr
  local db="${CPANEL_USER}_${1}" user="${CPANEL_USER}_${2}" pass="$3"
  echo "== DB $db / user $user ==" >&2
  ok "$(ujson Mysql create_database "name=$db")" "create_database $db" || {
    # Mungkin sudah ada (re-run): verifikasi di list, lanjut bila ketemu
    ujson Mysql list_databases | grep -q "\"$db\"" || return 1
    echo "(database sudah ada, lanjut)" >&2
  }
  R=$(ujson Mysql create_user "name=$user" "password=$pass") || true
  echo "$R" | grep -qE '"status":\s*1' || {
    echo "(user mungkin sudah ada — password TIDAK dirotasi; lanjut)" >&2
  }
  ok "$(ujson Mysql set_privileges_on_database "user=$user" "database=$db" "privileges=ALL PRIVILEGES")" \
    "set_privileges $user@$db"
  echo "$db $user"
}

ARGS=(--mode "$MODE" --target "$TARGET" --admin-user "$ADMIN_USER" --admin-pass "$ADMIN_PASS")
[ "$SLIMS_SAMPLE" = 1 ] && ARGS+=(--slims-sample)

if [ "$MODE" = both ] || [ "$MODE" = inlis ]; then
  : "${INLIS_URL:?--inlis-url wajib}" "${INLIS_PASS:?--inlis-pass wajib}"
  read -r FULL_DB FULL_USER < <(mkdb "$INLIS_DB_SFX" "$INLIS_USR_SFX" "$INLIS_PASS")
  ARGS+=(--inlis-url "$INLIS_URL" --inlis-db "$FULL_DB" --inlis-user "$FULL_USER" --inlis-pass "$INLIS_PASS")
fi
if [ "$MODE" = both ] || [ "$MODE" = slims ]; then
  : "${SLIMS_URL:?--slims-url wajib}" "${SLIMS_PASS:?--slims-pass wajib}"
  read -r FULL_DB FULL_USER < <(mkdb "$SLIMS_DB_SFX" "$SLIMS_USR_SFX" "$SLIMS_PASS")
  ARGS+=(--slims-url "$SLIMS_URL" --slims-db "$FULL_DB" --slims-user "$FULL_USER" --slims-pass "$SLIMS_PASS")
fi

HERE="$(cd "$(dirname "$0")" && pwd)"
echo "== install.sh ${ARGS[*]//"$ADMIN_PASS"/***} =="
bash "$HERE/install.sh" "${ARGS[@]}"
echo "DONE: atur docroot subdomain/addon (inlis→.../inlis/public, slims→.../slims) + SSL via cPanel."
