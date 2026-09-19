#!/usr/bin/env bash
# deploy-fleet.sh — orkestrator deploy multi-hosting / multi-customer.
# Satu baris TSV = satu deploy-customer.sh. Kolom `mode` memetakan skenario:
#   both  → 2 web / 1 hosting | inlis/slims → 1 web / 1 hosting.
# Skenario "2 web / 2 hosting" = dua baris: inlis ke H1 + slims ke H2.
#
# Pakai:
#   ./installer/deploy-fleet.sh customers.tsv [--dry-run] [--build]
#
# Format TSV (TAB-separated, baris pertama = header, baris # = komentar):
#   host  ssh_user  cpanel_user  mode  target  inlis_url  slims_url  admin_user  admin_pass  [ssh_port]  [slims_sample]
# URL aplikasi yang tidak dipakai boleh kosong atau "-".
#
# Contoh: lihat installer/customers.tsv.example
# JANGAN commit file TSV berisi password asli (sudah di .gitignore).
set -uo pipefail

TSV="${1:?pakai: $0 customers.tsv [--dry-run] [--build]}"; shift || true
DRY=0; BUILD=0
for a in "$@"; do case "$a" in
  --dry-run) DRY=1;; --build) BUILD=1;;
  *) echo "argumen tak dikenal: $a"; exit 2;;
esac; done

[ -f "$TSV" ] || { echo "file tidak ada: $TSV"; exit 2; }
HERE="$(cd "$(dirname "$0")" && pwd)"
DEPLOY="$HERE/deploy-customer.sh"

if [ "$BUILD" = 1 ]; then
  echo "== build dist sekali di awal =="
  bash "$HERE/build.sh"
fi

pass=0; fail=0; skipped=0
lineno=0
while IFS=$'\t' read -r host ssh_user cpanel_user mode target inlis_url slims_url admin_user admin_pass ssh_port slims_sample _rest || [ -n "${host:-}" ]; do
  lineno=$((lineno+1))
  case "${host:-}" in ''|'#'*|host) skipped=$((skipped+1)); continue;; esac
  mode="${mode:-both}"; target="${target:-public_html}"
  ssh_port="${ssh_port:-22}"; slims_sample="${slims_sample:-0}"
  admin_user="${admin_user:-admin}"
  admin_pass="${admin_pass:-admin}"
  [ "${inlis_url:-}" = "-" ] && inlis_url=""
  [ "${slims_url:-}" = "-" ] && slims_url=""
  case "$mode" in both|inlis|slims) ;;
    *) echo "[$lineno] mode invalid: $mode (lewati)"; fail=$((fail+1)); continue;;
  esac
  if { [ "$mode" = both ] || [ "$mode" = inlis ]; } && [ -z "${inlis_url:-}" ]; then
    echo "[$lineno] inlis_url wajib untuk mode $mode (lewati)"; fail=$((fail+1)); continue
  fi
  if { [ "$mode" = both ] || [ "$mode" = slims ]; } && [ -z "${slims_url:-}" ]; then
    echo "[$lineno] slims_url wajib untuk mode $mode (lewati)"; fail=$((fail+1)); continue
  fi
  # shellcheck disable=SC2206
  args=(--host "$host" --ssh-user "$ssh_user" --cpanel-user "$cpanel_user"
    --ssh-port "$ssh_port" --mode "$mode" --target "$target"
    --admin-user "$admin_user" --admin-pass "$admin_pass")
  [ -n "${inlis_url:-}" ] && args+=(--inlis-url "$inlis_url")
  [ -n "${slims_url:-}" ] && args+=(--slims-url "$slims_url")
  [ "$slims_sample" = 1 ] && args+=(--slims-sample)
  if [ "$DRY" = 1 ]; then
    echo "[$lineno] DRY: deploy-customer.sh ${args[*]//"$admin_pass"/***}"
    pass=$((pass+1)); continue
  fi
  echo "== [$lineno] $host mode=$mode =="
  if bash "$DEPLOY" "${args[@]}"; then pass=$((pass+1)); else fail=$((fail+1)); fi
done < "$TSV"

echo "== RINGKASAN: sukses=$pass gagal=$fail lewati=$skipped =="
[ "$fail" = 0 ]
