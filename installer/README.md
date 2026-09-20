# Perpus Auto-Installer

Deploy INLISLite V3 + SLiMS 9 Bulian ke shared hosting cPanel
(dengan atau tanpa SSH), satu hosting atau dua hosting terpisah.

## Isi folder ini

| File | Fungsi |
|---|---|
| `install.sh` | Alternatif SSH non-interaktif (`--mixcode` = terapkan Label Mixcode) |
| `installer.php` | Installer web single-file (utama, tanpa SSH; checkbox Label Mixcode) |
| `apply-mixcode.php` | Pemasang Label Mixcode (dipakai keduanya; bisa mandiri via CLI) |
| `build.sh` | Pembuat artefak `dist/` dari workspace |
| `dist/` | Artefak: `inlis.zip`, `slims.zip`, `sql-*.sql`, `mixcode.zip` (dibuat via `build.sh`, jangan commit manual) |
| `SETUP-PC-BARU.md` | Checklist end-to-end: mesin fresh → hosting baru |
| `SCENARIOS.md` | Matriks 3 skenario: 1 web/1 hosting, 2 web/1 hosting, 2 web/2 hosting |
| `MANUAL-UPLOAD.md` | Panduan upload manual tanpa installer/SSH (File Manager + phpMyAdmin) |
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

## Label Mixcode Warna (patch opsional)

Template label barcode warna + menu Pengaturan > Label Mixcode
(10 warna DDC, posisi barcode, judul, header). Isi paket sumber ada di
`tmp/label_mixcode_color_inlislite/` (src + patches + seeds + bukti uji).

- Fresh install: centang **Label Mixcode** di installer web, atau
  `./install.sh ... --mixcode` untuk SSH. SQL-nya idempoten; user wajib
  logout+login ulang agar menu muncul.
- Inlislite existing (customer A/B/C/D beda fitur): upload
  `mixcode.zip` + `apply-mixcode.php`, lalu di folder app:
  `php apply-mixcode.php --app-dir=. --zip=mixcode.zip`
  dilanjut import `sql-mixcode.sql` via phpMyAdmin (abaikan error 1060
  bila kolom sudah ada), lalu logout+login ulang.
- Pola ini (zip overlay + patch terverifikasi + sql idempoten + sentinel
  versi) dipakai ulang untuk tiap fitur per-customer berikutnya.

## Prasyarat hosting

PHP >= 8.2, ekstensi `mysqli` + `zip`, 2 database + user (dibuat via cPanel),
`config/` SLiMS writable saat instalasi.

Detail langkah cPanel: lihat `DEPLOY-SATU-HOSTING.md` di root repo.
