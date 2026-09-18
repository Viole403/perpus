#!/usr/bin/env bash
# deploy-customer.sh — operator-side: kirim + install ke hosting customer via SSH.
# Repo private tetap private: token GitHub HANYA dipakai untuk `git pull`
# di mesin operator, TIDAK PERNAH dikirim ke hosting customer.
# Yang naik ke hosting: installer.php + dist/ + customer-install.sh saja.
#
# Contoh:
#   GITHUB_TOKEN=ghp_xxx ./installer/deploy-customer.sh \
#     --host customer.com --ssh-user custuser --cpanel-user custuser \
#     --mode both --target public_html \
#     --inlis-url https://inlis.customer.com --slims-url https://slims.customer.com \
#     --admin-user admin --admin-pass 'Rahasia123'
# Password DB dibuat otomatis bila argumen --*-pass tidak diisi (ditampilkan sekali).
set -euo pipefail

REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
MODE=both; TARGET="public_html"; SLIMS_SAMPLE=0
HOST=""; SSH_USER=""; CPANEL_USER=""; SSH_PORT=22
INLIS_URL=""; SLIMS_URL=""
INLIS_DB_SFX=inlisdb; INLIS_USR_SFX=inlisuser; INLIS_PASS=""
SLIMS_DB_SFX=slimsdb; SLIMS_USR_SFX=slimsuser; SLIMS_PASS=""
ADMIN_USER=admin; ADMIN_PASS=""
DO_BUILD=0

usage() { sed -n '2,/^$/p' "$0"; grep -n '^#   ' "$0" | sed 's/^ *[0-9]*:#\{0,1\} //'; }
while [ $# -gt 0 ]; do case "$1" in
  --host) HOST="$2"; shift 2;; --ssh-user) SSH_USER="$2"; shift 2;;
  --cpanel-user) CPANEL_USER="$2"; shift 2;; --ssh-port) SSH_PORT="$2"; shift 2;;
  --mode) MODE="$2"; shift 2;; --target) TARGET="$2"; shift 2;;
  --inlis-url) INLIS_URL="$2"; shift 2;; --slims-url) SLIMS_URL="$2"; shift 2;;
  --inlis-pass) INLIS_PASS="$2"; shift 2;; --slims-pass) SLIMS_PASS="$2"; shift 2;;
  --admin-user) ADMIN_USER="$2"; shift 2;; --admin-pass) ADMIN_PASS="$2"; shift 2;;
  --slims-sample) SLIMS_SAMPLE=1; shift;; --build) DO_BUILD=1; shift;;
  -h|--help) usage; exit 0;;
  *) echo "argumen tak dikenal: $1"; exit 2;;
esac; done

[ -n "$HOST" ] && [ -n "$SSH_USER" ] && [ -n "$CPANEL_USER" ] || { echo "--host, --ssh-user, --cpanel-user wajib"; exit 2; }
[ -n "$ADMIN_PASS" ] || { echo "--admin-pass wajib"; exit 2; }
if [ "$MODE" = both ] || [ "$MODE" = inlis ]; then : "${INLIS_URL:?--inlis-url wajib untuk mode $MODE}"; fi
if [ "$MODE" = both ] || [ "$MODE" = slims ]; then : "${SLIMS_URL:?--slims-url wajib untuk mode $MODE}"; fi

# 1. Sinkron kode dari private repo (token hanya di sini, tidak ke hosting)
if [ -d "$REPO_DIR/.git" ]; then
  echo "== git pull =="
  git -C "$REPO_DIR" pull --ff-only
fi

# 2. Pastikan artefak dist ada
if [ "$DO_BUILD" = 1 ] || [ ! -f "$REPO_DIR/installer/dist/sql-inlis.sql" ]; then
  echo "== build dist =="
  bash "$REPO_DIR/installer/build.sh"
fi

# 3. Password DB acak bila tidak diisi
[ -n "$INLIS_PASS" ] || INLIS_PASS=$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | head -c 20)
[ -n "$SLIMS_PASS" ] || SLIMS_PASS=$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | head -c 20)

SSH="ssh -p $SSH_PORT $SSH_USER@$HOST"
REMOTE_TMP="~/perpus-deploy-$$"

# 4. Upload artefak + script (tanpa token, tanpa .git)
echo "== upload =="
$SSH "mkdir -p $REMOTE_TMP"
scp -P "$SSH_PORT" -r "$REPO_DIR/installer/installer.php" "$REPO_DIR/installer/customer-install.sh" \
  "$REPO_DIR/installer/install.sh" "$REPO_DIR/installer/dist" "$SSH_USER@$HOST:$REMOTE_TMP/"

# 5. Jalankan instalasi di hosting customer (quoting aman via printf %q)
Q() { printf '%q' "$1"; }
REMOTE_RUN="bash $REMOTE_TMP/customer-install.sh"
REMOTE_RUN="$REMOTE_RUN --mode $(Q "$MODE") --target $(Q "$TARGET") --cpanel-user $(Q "$CPANEL_USER")"
REMOTE_RUN="$REMOTE_RUN --inlis-url $(Q "$INLIS_URL") --slims-url $(Q "$SLIMS_URL")"
REMOTE_RUN="$REMOTE_RUN --inlis-pass $(Q "$INLIS_PASS") --slims-pass $(Q "$SLIMS_PASS")"
REMOTE_RUN="$REMOTE_RUN --inlis-db-sfx $(Q "$INLIS_DB_SFX") --inlis-usr-sfx $(Q "$INLIS_USR_SFX")"
REMOTE_RUN="$REMOTE_RUN --slims-db-sfx $(Q "$SLIMS_DB_SFX") --slims-usr-sfx $(Q "$SLIMS_USR_SFX")"
REMOTE_RUN="$REMOTE_RUN --admin-user $(Q "$ADMIN_USER") --admin-pass $(Q "$ADMIN_PASS")"
[ "$SLIMS_SAMPLE" = 1 ] && REMOTE_RUN="$REMOTE_RUN --slims-sample"
$SSH "$REMOTE_RUN; rm -rf $REMOTE_TMP"

echo "== SELESAI =="
echo "INLIS DB pass : $INLIS_PASS"
echo "SLiMS DB pass : $SLIMS_PASS"
echo "Simpan kredensial di atas ke password manager, lalu hapus dari riwayat shell (history -d)."
