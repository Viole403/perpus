#!/usr/bin/env bash
# Alternatif SSH: instalasi non-interaktif via argumen. Untuk klik-klik gunakan installer.php.
# Contoh 1 hosting (keduanya):
#   ./install.sh --mode both --target public_html \
#     --inlis-url https://inlis.domain.id --inlis-db user_inlisdb --inlis-user user_inlisuser --inlis-pass '...' \
#     --slims-url https://slims.domain.id --slims-db user_slimsdb --slims-user user_slimsuser --slims-pass '...' \
#     --admin-user admin --admin-pass '...' [--slims-sample]
# Contoh hosting terpisah: --mode inlis ... (argumen slims diabaikan) dan sebaliknya.
set -euo pipefail

MODE=both; TARGET="."; SLIMS_SAMPLE=0; MIXCODE=0
ADMIN_USER=admin; ADMIN_PASS="admin"
INLIS_URL=""; INLIS_DB=; INLIS_USER=; INLIS_PASS=; INLIS_HOST=localhost; INLIS_PORT=3306
SLIMS_URL=""; SLIMS_DB=; SLIMS_USER=; SLIMS_PASS=; SLIMS_HOST=localhost; SLIMS_PORT=3306
while [ $# -gt 0 ]; do case "$1" in
  --mode) MODE="$2"; shift 2;; --target) TARGET="$2"; shift 2;;
  --inlis-url) INLIS_URL="$2"; shift 2;; --inlis-db) INLIS_DB="$2"; shift 2;;
  --inlis-user) INLIS_USER="$2"; shift 2;; --inlis-pass) INLIS_PASS="$2"; shift 2;;
  --inlis-host) INLIS_HOST="$2"; shift 2;; --inlis-port) INLIS_PORT="$2"; shift 2;;
  --slims-url) SLIMS_URL="$2"; shift 2;; --slims-db) SLIMS_DB="$2"; shift 2;;
  --slims-user) SLIMS_USER="$2"; shift 2;; --slims-pass) SLIMS_PASS="$2"; shift 2;;
  --slims-host) SLIMS_HOST="$2"; shift 2;; --slims-port) SLIMS_PORT="$2"; shift 2;;
  --slims-sample) SLIMS_SAMPLE=1; shift;;
  --mixcode) MIXCODE=1; shift;;
  --admin-user) ADMIN_USER="$2"; shift 2;; --admin-pass) ADMIN_PASS="$2"; shift 2;;
  *) echo "argumen tak dikenal: $1"; exit 2;;
esac; done

need() { command -v "$1" >/dev/null || { echo "butuh: $1"; exit 1; }; }
need php; need unzip; need mysql
HERE="$(cd "$(dirname "$0")" && pwd)"
mycmd() { mysql --protocol=tcp -h"$1" -P"$2" -u"$3" -p"$4" "$5"; }

[ -n "$ADMIN_PASS" ] || ADMIN_PASS="admin"
[ "$ADMIN_USER" = admin ] && [ "$ADMIN_PASS" = admin ] && echo "PERINGATAN: kredensial default admin/admin — segera ganti setelah install!" >&2

if [ "$MODE" = both ] || [ "$MODE" = "inlis" ]; then
  : "${INLIS_URL:?--inlis-url wajib}" "${INLIS_DB:?--inlis-db wajib}" "${INLIS_USER:?--inlis-user wajib}" "${INLIS_PASS:?--inlis-pass wajib}"
  echo "== INLISLite =="
  D="$TARGET/inlis"; mkdir -p "$D"
  if ls "$HERE"/dist/inlis.zip.??? 1>/dev/null 2>&1; then cat "$HERE"/dist/inlis.zip.??? > "$D/inlis.zip"; else cp "$HERE/dist/inlis.zip" "$D/"; fi
  (cd "$D" && unzip -qo inlis.zip && rm -f inlis.zip)
  cp "$D/env.sample" "$D/.env"
  TOKEN=$(php -r 'echo bin2hex(random_bytes(32));')
  set_kv() {
    ENV_FILE="$1" ENV_KEY="$2" ENV_VAL="$3" php -r '
      $f = getenv("ENV_FILE"); $k = getenv("ENV_KEY"); $v = getenv("ENV_VAL");
      $c = file_get_contents($f);
      $pat = "/^([#\s]*" . preg_quote($k, "/") . "\s*=).*/m";
      $rep = '"'"'$1 '"'"' . addcslashes($v, '"'"'\\$'"'"');
      $c = preg_match($pat, $c) ? preg_replace($pat, $rep, $c) : $c . "\n$k = $v\n";
      file_put_contents($f, $c);'
  }
  set_kv "$D/.env" CI_ENVIRONMENT production
  set_kv "$D/.env" app.baseURL "'${INLIS_URL%/}/'"
  for g in default data; do
    set_kv "$D/.env" "database.$g.hostname" "$INLIS_HOST"
    set_kv "$D/.env" "database.$g.database" "$INLIS_DB"
    set_kv "$D/.env" "database.$g.username" "$INLIS_USER"
    set_kv "$D/.env" "database.$g.password" "$INLIS_PASS"
    set_kv "$D/.env" "database.$g.port" "$INLIS_PORT"
  done
  set_kv "$D/.env" security.TOKEN_SECRET "$TOKEN"
  chmod 755 "$D/writable"
  mycmd "$INLIS_HOST" "$INLIS_PORT" "$INLIS_USER" "$INLIS_PASS" "$INLIS_DB" < "$HERE/dist/sql-inlis.sql"
  # Myth/Auth: hash = bcrypt(base64(sha384(pass))) — password_hash polos TIDAK bisa login
  H=$(php -r 'echo password_hash(base64_encode(hash("sha384", $argv[1], true)), PASSWORD_DEFAULT, ["cost" => 10]);' "$ADMIN_PASS")
  mycmd "$INLIS_HOST" "$INLIS_PORT" "$INLIS_USER" "$INLIS_PASS" "$INLIS_DB" \
    -e "UPDATE users SET password_hash='$H', username='$ADMIN_USER', active=1 WHERE id=1;"
  echo "INLISLite OK"
fi

if [ "$MIXCODE" = 1 ] && { [ "$MODE" = both ] || [ "$MODE" = inlis ]; }; then
  echo "== Label Mixcode (opsional) =="
  D="$TARGET/inlis"
  [ -f "$HERE/dist/mixcode.zip" ] || { echo "FATAL: dist/mixcode.zip tak ada — jalankan build.sh dulu"; exit 1; }
  [ -f "$HERE/dist/sql-mixcode.sql" ] || { echo "FATAL: dist/sql-mixcode.sql tak ada — jalankan build.sh dulu"; exit 1; }
  php "$HERE/apply-mixcode.php" --app-dir="$D" --zip="$HERE/dist/mixcode.zip"
  # --force: ALTER ISPopuler boleh gagal (1060) bila kolom sudah ada; lanjutkan sisanya
  mysql --protocol=tcp --force -h"$INLIS_HOST" -P"$INLIS_PORT" -u"$INLIS_USER" -p"$INLIS_PASS" "$INLIS_DB" < "$HERE/dist/sql-mixcode.sql"
  echo "Label Mixcode OK (user wajib logout+login ulang di app agar menu muncul)"
fi

if [ "$MODE" = both ] || [ "$MODE" = "slims" ]; then
  : "${SLIMS_URL:?--slims-url wajib}" "${SLIMS_DB:?--slims-db wajib}" "${SLIMS_USER:?--slims-user wajib}" "${SLIMS_PASS:?--slims-pass wajib}"
  echo "== SLiMS =="
  D="$TARGET/slims"; mkdir -p "$D"
  if ls "$HERE"/dist/slims.zip.??? 1>/dev/null 2>&1; then cat "$HERE"/dist/slims.zip.??? > "$D/slims.zip"; else cp "$HERE/dist/slims.zip" "$D/"; fi
  (cd "$D" && unzip -qo slims.zip && rm -f slims.zip)
  HTTPS=false; case "$SLIMS_URL" in https://*) HTTPS=true;; esac
  cat > "$D/config/database.php" <<PHP
<?php

return [
    'default_profile' => 'SLiMS',
    'proxy' => false,
    'nodes' => [
        'SLiMS' => [
            'host' => '$SLIMS_HOST',
            'database' => '$SLIMS_DB',
            'port' => '$SLIMS_PORT',
            'username' => '$SLIMS_USER',
            'password' => '$SLIMS_PASS',
            'options' => [
                'storage_engine' => 'InnoDB'
            ]
        ],
    ]
];
PHP
  php -r '$e=file_get_contents($argv[1]);$e=str_replace(["\x27<environment>\x27","\x27<conditional_environment>\x27","\x27<based_on_ip>\x27"],["\x27production\x27","\x27production\x27","false"],$e);file_put_contents($argv[2],$e);' \
    "$D/config/env.sample.php" "$D/config/env.php"
  php -r '$u=file_get_contents($argv[1]);$u=str_replace("\x27force_https\x27 => false","\x27force_https\x27 => $argv[3]",$u);file_put_contents($argv[2],$u);' \
    "$D/config/url.sample.php" "$D/config/url.php" "$HTTPS"
  chmod 755 "$D/files" "$D/images" "$D/repository" "$D/config"
  mycmd "$SLIMS_HOST" "$SLIMS_PORT" "$SLIMS_USER" "$SLIMS_PASS" "$SLIMS_DB" < "$HERE/dist/sql-slims-schema.sql"
  [ "$SLIMS_SAMPLE" = 1 ] && mycmd "$SLIMS_HOST" "$SLIMS_PORT" "$SLIMS_USER" "$SLIMS_PASS" "$SLIMS_DB" < "$HERE/dist/sql-slims-sample.sql"
  H=$(php -r 'echo password_hash($argv[1], PASSWORD_BCRYPT);' "$ADMIN_PASS")
  mycmd "$SLIMS_HOST" "$SLIMS_PORT" "$SLIMS_USER" "$SLIMS_PASS" "$SLIMS_DB" \
    -e "UPDATE user SET passwd='$H', username='$ADMIN_USER' WHERE user_id=1;"
  rm -rf "$D/install"
  echo "SLiMS OK"
fi
echo DONE
