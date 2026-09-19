# Alur private-repo → auto-install ke hosting customer

Repo ini **private**: customer tidak pernah dapat akses GitHub.
Yang naik ke hosting customer hanya artefak (`installer.php`, `dist/`,
`customer-install.sh`) — tanpa `.git`, tanpa token.

## Pilihan A — full otomatis via SSH (disarankan, ArenHost ada SSH)

Di mesin operator (punya akses `git pull` ke repo private ini):

```bash
./installer/deploy-customer.sh \
  --host customer.com --ssh-user custuser --cpanel-user custuser \
  --mode both --target public_html \
  --inlis-url https://inlis.customer.com \
  --slims-url https://slims.customer.com \
  --admin-user admin --admin-pass 'RahasiaKuat123'
```

Script ini: `git pull` (token hanya di sini) → `build.sh` bila perlu →
generate password DB acak → upload artefak via `scp` → jalankan
`customer-install.sh` via SSH (buat 2 DB+user lewat `uapi cPanel`,
lalu panggil `install.sh`) → hapus file staging di hosting.
Kredensial DB ditampilkan sekali di terminal operator: simpan ke
password manager, lalu `history -d`.

Ulangi per customer dengan `--host/--ssh-user/--cpanel-user` berbeda.
Mode `inlis` / `slims` untuk hosting terpisah.
Banyak customer: pakai `./installer/deploy-fleet.sh customers.tsv`
(lihat `SCENARIOS.md` + `customers.tsv.example`).

## Pilihan B — semi-otomatis tanpa SSH customer

1. Operator: `bash installer/build.sh`, lalu zip `installer.php` + `dist/`.
2. Kirim zip ke customer (atau upload sendiri via File Manager customer).
3. Customer/operator buka `installer.php` di browser → isi form → Install.
4. DB tetap dibuat manual via cPanel (installer web hanya import+config).

## Setelah install (kedua pilihan)

- Atur docroot subdomain/addon: INLISLite → `.../inlis/public`,
  SLiMS → `.../slims`. Aktifkan SSL.
- `slims/install/` sudah dihapus otomatis oleh installer.
- Artefak installer dihapus otomatis (Pilihan A) atau via tombol
  Finish (Pilihan B: `installer.php`).

## Keamanan token

- `GITHUB_TOKEN`/SSH key hanya hidup di mesin operator.
- Jangan pernah `scp .git/`, `local/`, `insilite/`, atau file `.env`
  ke hosting customer. Daftar blokir ada di root `.gitignore`.
