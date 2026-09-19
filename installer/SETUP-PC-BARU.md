# Setup PC Baru → Hosting Baru

Checklist end-to-end: dari mesin fresh sampai kedua web live.
Detail tiap jalur ada di `SCENARIOS.md`, `MANUAL-UPLOAD.md`, `PRIVATE-REPO.md`.

## 1. Siapkan PC baru

Butuh: `git`, `zip`, `unzip`, `php` (CLI >= 8.1), `ssh`, `scp`, `openssl`.

```bash
for t in git zip unzip php ssh scp openssl; do command -v "$t" || echo "kurang: $t"; done
```

## 2. Clone repo

```bash
git clone https://github.com/Viole403/perpus.git
cd perpus
```

Clone baru langsung siap deploy, tanpa download tambahan:

| Isi | Status |
|---|---|
| `INLISLIteV33/vendor/` (7943 file) + `slims9_bulian/vendor/` (893 file) | tracked |
| `INLISLIteV33/public/uploads/` (20 file) | tracked |
| `local/inlislite_v33-mariadb.sql`, `slims9_bulian/install/senayan.sql`, `sampledata.sql` | tracked |
| `installer/dist/` (6 file: `inlis.zip.000/.001`, `slims.zip`, 3 sql) | tracked |

## 3. Verifikasi cepat

```bash
test -f INLISLIteV33/vendor/autoload.php && test -f slims9_bulian/vendor/autoload.php && echo VENDOR-OK
ls installer/dist/   # harus 6 file
```

## 4. Rebuild `dist/` (hanya bila kode berubah)

```bash
bash installer/build.sh
```

Regenerasi zip + sql dari workspace, cek no-secrets, split zip >100MB.
Tidak perlu dijalankan bila deploy dari clone segar tanpa perubahan kode.

## 5. Siapkan hosting baru (cPanel)

1. MySQL Databases: 1 DB + 1 user per app (mode both = 2 DB), ALL PRIVILEGES. Catat nama + password.
2. Subdomain/addon domain dengan docroot:
   - INLISLite → `public_html/inlis/public`
   - SLiMS → `public_html/slims`
3. MultiPHP Manager → PHP **8.2** untuk semua host.
4. SSL/TLS Status → Run AutoSSL.

## 6. Deploy — pilih satu jalur

**A. Web tanpa SSH** (`installer.php`): upload `installer.php` + isi `dist/`
ke satu folder di hosting → buka di browser → isi form (mode, URL, DB,
admin) → Install → Finish (hapus otomatis).

**B. SSH otomatis:**
```bash
# satu customer
./installer/deploy-customer.sh \
  --host H --ssh-user U --cpanel-user U \
  --mode both --target public_html \
  --inlis-url https://inlis.DOM --slims-url https://slims.DOM \
  --admin-user admin --admin-pass 'XXX'
# banyak customer sekaligus
./installer/deploy-fleet.sh customers.tsv   # salin dari customers.tsv.example
```

**C. Manual** (File Manager + phpMyAdmin, tanpa installer/SSH):
lihat `MANUAL-UPLOAD.md`.

Mode `inlis`/`slims` untuk 1 web / 1 hosting; `both` untuk 2 web / 1 hosting
(lihat `SCENARIOS.md`).

## 7. Checklist pasca-install

- [ ] `inlis.DOM/` tampil Beranda, `/login` 200, login admin bisa
- [ ] `slims.DOM/` tampil OPAC, `?p=login` 200, login admin bisa
- [ ] Folder `slims/install/` sudah terhapus
- [ ] Zip + SQL + `installer.php` sudah dihapus dari server
- [ ] HTTPS aktif semua host

## 8. Kredensial

- Password DB: dibuat acak otomatis oleh `deploy-customer.sh` (tampil sekali
  di terminal) atau manual saat buat DB di cPanel.
- Password admin: `--admin-pass` (min 6) atau kolom `admin_pass` di TSV.
- Simpan semua ke password manager. `customers.tsv` jangan pernah di-commit
  (sudah di `.gitignore`).
