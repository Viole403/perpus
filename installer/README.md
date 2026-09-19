# Perpus Auto-Installer

Deploy INLISLite V3 + SLiMS 9 Bulian ke shared hosting cPanel
(dengan atau tanpa SSH), satu hosting atau dua hosting terpisah.

## Isi folder ini

| File | Fungsi |
|---|---|
| `installer.php` | Installer web single-file (utama, tanpa SSH) |
| `install.sh` | Alternatif SSH non-interaktif |
| `build.sh` | Pembuat artefak `dist/` dari workspace |
| `dist/` | Artefak: `inlis.zip`, `slims.zip`, `sql-*.sql` (dibuat via `build.sh`, jangan commit manual) |
| `SCENARIOS.md` | Matriks 3 skenario: 1 web/1 hosting, 2 web/1 hosting, 2 web/2 hosting |
| `deploy-customer.sh` | Operator-side: kirim + install ke 1 hosting customer via SSH |
| `deploy-fleet.sh` | Orkestrator multi-hosting dari file TSV |
| `customers.tsv.example` | Contoh TSV fleet (salin jadi `customers.tsv`, jangan commit) |

## Alur GitHub (sat set)

1. Push repo ini ke GitHub (installer + kode, **tanpa** `dist/` dan **tanpa** secret).
2. Di mesin/lokal: `./installer/build.sh` → hasilkan `installer/dist/`.
3. Upload via cPanel File Manager ke satu folder:
   `installer.php` + seluruh isi `installer/dist/`.
4. Buka `https://domain/.../installer.php` → isi form → Install → Finish (hapus otomatis).
5. Atur docroot: INLISLite → `.../inlis/public`, SLiMS → `.../slims`. Aktifkan SSL.

Dua hosting terpisah: ulangi langkah 3–5 per hosting dengan mode
`inlis` / `slims` (hanya artefak app itu yang perlu diupload).

## Prasyarat hosting

PHP >= 8.2, ekstensi `mysqli` + `zip`, 2 database + user (dibuat via cPanel),
`config/` SLiMS writable saat instalasi.

Detail langkah cPanel: lihat `DEPLOY-SATU-HOSTING.md` di root repo.
