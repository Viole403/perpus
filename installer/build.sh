#!/usr/bin/env bash
# Build artefak staging untuk auto-installer. Jalankan dari root workspace.
# Output: installer/dist/{inlis.zip, slims.zip, sql-*.sql} (+ part .001 bila >100MB)
# ponytail: tanpa CI; cukup upload isi dist/ + installer.php ke hosting.
set -euo pipefail
cd "$(dirname "$0")/.."

DIST=installer/dist
STAGE=$(mktemp -d)
trap 'rm -rf "$STAGE"' EXIT
MAXPART=$((100*1024*1024))

need() { command -v "$1" >/dev/null || { echo "butuh: $1"; exit 1; }; }
need zip; need unzip

echo "== staging INLISLite =="
mkdir -p "$STAGE/inlis_stage"
tar -cf - --exclude='./.git' --exclude='./.env' --exclude='./composer.lock' \
  --exclude='./writable/debugbar/*' --exclude='./writable/session/*' \
  --exclude='./writable/logs/*' --exclude='./writable/cache/*' \
  --exclude='./node_modules' --exclude='./tests' \
  -C INLISLIteV33 . | tar -xf - -C "$STAGE/inlis_stage"
test -f "$STAGE/inlis_stage/vendor/autoload.php" || { echo "FATAL: vendor/autoload.php hilang — salin vendor dari bundle Laragon dulu"; exit 1; }
test -d "$STAGE/inlis_stage/public/uploads" || echo "WARN: public/uploads/ kosong — tampilan bisa polos"
find "$STAGE/inlis_stage/writable" "$STAGE/inlis_stage/public/uploads" -type d -exec chmod 755 {} + 2>/dev/null || true

echo "== staging SLiMS =="
mkdir -p "$STAGE/slims_stage"
tar -cf - --exclude='./.git' --exclude='./config/database.php' --exclude='./config/env.php' \
  --exclude='./config/csp.php' --exclude='./config/auth.php' --exclude='./config/url.php' \
  --exclude='./vendor' --exclude='./composer.lock' \
  -C slims9_bulian . | tar -xf - -C "$STAGE/slims_stage"
rm -rf "$STAGE/slims_stage/files/backup" "$STAGE/slims_stage/files/cache" 2>/dev/null || true

echo "== no-secrets check (file config aktif saja; sample+vendor dikecualikan) =="
if grep -rIlE "inlis123|slims123|admin123" \
    "$STAGE/inlis_stage/.env" "$STAGE/inlis_stage/app/Config/" \
    "$STAGE/slims_stage/config/" "$STAGE/slims_stage/sysconfig"*.php 2>/dev/null | head; then
  echo "FATAL: kredensial lokal terdeteksi di file config staging. Bersihkan dulu."
  exit 1
fi
echo "bersih."

mkdir -p "$DIST"
rm -f "$DIST"/*

echo "== zip =="
(cd "$STAGE/inlis_stage" && zip -qr "$OLDPWD/$DIST/inlis.zip" .)
(cd "$STAGE/slims_stage" && zip -qr "$OLDPWD/$DIST/slims.zip" .)
cp -f local/inlislite_v33-mariadb.sql "$DIST/sql-inlis.sql"
cp -f slims9_bulian/install/senayan.sql "$DIST/sql-slims-schema.sql"
cp -f slims9_bulian/install/sampledata.sql "$DIST/sql-slims-sample.sql"
cp -f installer/seed-captcha.sql "$DIST/sql-captcha.sql"

echo "== split >100MB =="
for f in "$DIST"/*.zip; do
  sz=$(stat -c%s "$f")
  if [ "$sz" -gt "$MAXPART" ]; then
    echo "split $(basename "$f") ($(numfmt --to=iec "$sz"))"
    split -b "$MAXPART" -d --suffix-length=3 "$f" "$f."
    rm -f "$f"
  fi
done

echo "== hasil =="
ls -lh "$DIST"
echo "== uji ekstrak =="
T=$(mktemp -d); unzip -q "$DIST"/inlis.zip* -d "$T/inlis" 2>/dev/null || (cat "$DIST"/inlis.zip.* > "$T/combined.zip" && unzip -q "$T/combined.zip" -d "$T/inlis")
test -f "$T/inlis/public/index.php" && test -f "$T/inlis/vendor/autoload.php" && echo "inlis.zip OK"
rm -rf "$T"
echo DONE
