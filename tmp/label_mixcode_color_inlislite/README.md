# Label Mixcode Warna — Port Inlislite

Port plugin SLiMS **label_mixcode_color_slims**
(https://github.com/drajathasan/label_mixcode_color_slims, oleh Drajat Hasan)
ke **INLISLite v3.3** sebagai template cetak label eksemplar.

Salinan plugin SLiMS aslinya (untuk perbandingan) ada di:
`tmp/label_mixcode_color_slims_ori/`.

## Isi paket ini

| File | Tujuan |
|---|---|
| `src/template/cetak-label-a4-mix-left.php` | Label mixcode A4: header nama perpus (warna kelas) + barcode vertikal besar di **kiri** + kanan dibagi no. panggil (atas) & judul penuh (bawah) |
| `src/template/cetak-label-a4-mix-right.php` | Cerminnya: barcode besar di **kanan** |
| `src/template/cetak-label-a4-mix-both.php` | Barcode besar di **kiri + kanan**, tengah dibagi no. panggil & judul (1 label/baris karena lebih lebar) |
| `src/template/cetak-label-mix-roll.php` | Label Mixcode untuk **Label Roll**: halaman 57×40mm per stiker, barcode vertikal + panggil & judul |
| `src/template/cetak-label-mix-br.php` | Label Mixcode untuk **Barcode Roll**: halaman 57×25mm per stiker, barcode horizontal + panggil |
| `src/template/cetak-label-mix-tj121.php` | Label Mixcode untuk **Tom & Jerry 121**: grid A4 2×5 (99×57mm) seperti bawaan, disenterkan (margin kiri 4,5mm agar sisa simetris) |
| `src/template/cetak-label-mix-gc121.php` | Label Mixcode untuk **Golden Cock 121**: grid A4 2×4 (101×68mm) seperti bawaan, disenterkan (margin kiri 2,25mm) |
| `src/template/_mixcode_functions.php` | Partial bersama: pecah no. panggil (`sliceCallNumber`), judul penuh/potong, barcode vertikal |
| `src/Module/LabelMixcode/` | Modul menu **Pengaturan > Label Mixcode** (Routes + Controller + View) |
| `patches/01-mixlabel-controller.diff` | Whitelist 3 template + baca opsi Mixcode di `EksemplarLabelController.php` |
| `patches/02-dropdown.diff` | 3 model Mix di dropdown cetak label + dropdown nama label klasifikasi (mode mix) di dua halaman eksemplar |
| `seeds/labelmixcode_menu.sql` | Entri sidebar "Label Mixcode" (tabel `c_menus`, induk Pengaturan Katalog). Sidebar dibaca dari DB, bukan `navigation.php` |
| `seeds/mixcode_warna.sql` | Palet 10 warna DDC (disetujui, gaya Scribd/Material) untuk `master_kelas_besar` |
| `seeds/labelmixcode_permissions.sql` | Permission `label-mixcode/*` + grant grup admin (wajib logout+login ulang sesudahnya) |
| `tests/test_render.php` | Uji render mandiri (`php tests/test_render.php`) — sudah lolos |
| `tests/proof_render.php` | Uji render paket asli (butuh `php-legacy` + vendor): `php-legacy tests/proof_render.php mix-left pdf > tests/proof/out.pdf` |
| `tests/proof/` | Bukti render: `label-mix-*.pdf/html` (harness CLI) + `print-mix-*.pdf/doc` (**dari aplikasi berjalan**, data DB asli: eksemplar B00T19–B00T21) |

## Menjalankan Inlislite lokal (yang dipakai untuk tes ini)

Lingkungan ini tidak punya MySQL server di 3306 dan `php` standar tidak
punya ekstensi gd/mysqli/intl, jadi dipakai:

- **MariaDB sendiri** di `tmp/mysql-data` (port **3307**, `--skip-grant-tables`,
  bind 127.0.0.1; PID tercatat saat start — matikan via PID itu, jangan
  `pkill` sembarangan agar tidak membunuh server lain):
  ```bash
  nohup mariadbd --datadir=$PWD/tmp/mysql-data \
    --socket=$PWD/tmp/mysql-run/mysql.sock --port=3307 \
    --bind-address=127.0.0.1 --skip-grant-tables > tmp/mysql-run/mariadb.log 2>&1 &
  ```
- **Database** `inlislite_v33` dari `inlislite_v33.sql`, plus 3 data uji
  (katalog BIB-TEST-001..003 → eksemplar ID 13,14,15 / barcode B00T19–B00T21).
- **Web server**: `php-legacy -S 127.0.0.1:8080 -t insilite/www/inlislitev33/public/`
  (php-legacy 8.3 punya gd+mysqli+intl).
- **`.env` lokal** (untracked git, boleh beda dengan server asli):
  `app.baseURL=http://localhost:8080/`, `database.default/data.hostname=127.0.0.1`,
  port `3307`. Kembalikan ke semula bila deploy.
- **Cache basi**: hapus `writable/cache/routes_list` bila berisi path
  `C:\laragon\...` (hasil copy dari Windows) agar namespace module terbaca.
- **Login lokal**: `admin` / `admin123` (hash Myth/Auth =
  `password_hash(base64(sha384(pass)), BCRYPT)` — hash mentah tidak bisa).
  Login form butuh hCaptcha; untuk tes curl, bypass sementara di
  `Auth.php` (`if (false && ...)`), **sudah dikembalikan seperti semula** —
  jangan lupa ini bila mengetes via curl.

Alur coba plugin di browser: login → **Katalog > pilih katalog > tab
Eksemplar** → centang eksemplar → Pilih Aksi **Cetak Label** → Jenis Kertas
**Kertas A4** → Model **Mix-Left / Mix-Right / Mix-Both** → Format PDF/Word.

## Status pemasangan lokal (sudah terpasang)

Paket ini **sudah dipasang** ke `insilite/www/inlislitev33/` pada 20 Sep 2026:

- 4 file template tersalin ke `app/Modules/SubModule/Eksemplar/Views/template/`
- `patches/01-whitelist.diff` + `02-dropdown.diff` sudah di-apply
  (terlihat di `git diff`: `$allowedTemplates` + dropdown model Mix-Left/Right/Both)

## Cara tes di browser (di komputer kamu)

Butuh PHP dengan ekstensi `gd + mysqli + intl` + MySQL/MariaDB
(di Laragon/XAMPP biasanya sudah ada semua).

1. Jalankan Inlislite seperti biasa (Apache/Nginx + MySQL, database
   `inlislite_v33` sudah ter-import) lalu login.
2. Buka **Katalog > pilih katalog > tab Eksemplar**, centang beberapa
   eksemplar.
3. **Pilih Aksi: Cetak Label > Proses**, lalu Jenis Kertas: **Kertas A4**,
   Model: **Mix-Left / Mix-Right / Mix-Both**, Format: PDF (atau Word).
4. Hasil: label gaya SLiMS — barcode vertikal di samping + no. panggil
   + header nama perpustakaan berlatar warna kelas.

> Langkah 1–2 dari instalasi manual (copy + `git apply`) **tidak perlu
> diulang** — paket ini sudah terpasang di working tree lokal
> (lihat "Status pemasangan lokal" di atas). Langkah di bawah hanya
> dokumentasi bila ingin memasang ulang di tempat lain:

1. Salin template + modul:
   ```bash
   cp tmp/label_mixcode_color_inlislite/src/template/cetak-label-a4-mix-*.php \
      app/Modules/SubModule/Eksemplar/Views/template/
   cp tmp/label_mixcode_color_inlislite/src/template/_mixcode_functions.php \
      app/Modules/SubModule/Eksemplar/Views/template/
   cp -r tmp/label_mixcode_color_inlislite/src/Module/LabelMixcode \
      app/Modules/SubModule/Administrasi/PengaturanKatalog/
   ```
2. Terapkan patch:
   ```bash
   git apply tmp/label_mixcode_color_inlislite/patches/01-mixlabel-controller.diff
   git apply tmp/label_mixcode_color_inlislite/patches/02-dropdown.diff
   ```
3. Hapus cache discovery agar modul+route baru terbaca (dan setiap
   tambah modul baru): hapus file `writable/cache/routes_list`.
4. Jalankan SQL: `seeds/mixcode_warna.sql` (palet 10 warna DDC),
   `seeds/labelmixcode_permissions.sql` (permission + grant admin), dan
   `seeds/labelmixcode_menu.sql` (entri sidebar; cek ID induk
   Pengaturan Katalog bila bukan 158), lalu **logout + login ulang**
   (matriks permission session dibangun ulang saat login; tanpanya
   tombol Simpan mental ke dashboard dan menu tak tampil).

> Catatan teknis: nama kolom `master_kelas_besar` di database ini
> lowercase (`kdKelas`, `warna`), tapi MySQL mencocokkan case-insensitive
> sehingga query jalan. Namun key hasil PHP mengikuti **tulisan di query**:
> `SELECT *` → key lowercase, `SELECT KdKelas, Warna` → key sesuai tulisan.
> Selalu pakai SELECT eksplisit + key yang sama persis, atau label cetak
> jatuh ke warna default diam-diam (pernah kejadian).
5. Buka **Pengaturan > Pengaturan Katalog > Label Mixcode** untuk
   warna & tampilan; cetak via **Katalog > Eksemplar > Cetak Label >
   Model Mix-Left / Mix-Right / Mix-Both** (PDF/Word).

## Pemetaan fitur SLiMS → Inlislite

| SLiMS (`lbc_*`) | Inlislite (paket ini) |
|---|---|
| Template `left / right / both` | `cetak-label-a4-mix-left / -right / -both` |
| `lbc_color` 0XX..9XX via color picker | Kolom `warna` tabel `master_kelas_besar` (diambil controller sebagai `Warna1`); ubah via menu Administrasi > Pengaturan Katalog > Master Kelas Besar, atau lihat `seeds/mixcode_warna.sql` |
| `sliceCallNumber()` | `mixcode_call_lines()` di `_mixcode_functions.php` (hasil setara) |
| `callNumberColor()` digit pertama | Digit pertama `DeweyNo` → `Warna1` oleh controller (fallback `#FFFF66` bila kelas tak dikenal) |
| `chunk` (label per baris) | `$mixPerRow` di tiap template (left/right = 2, both = 1) |
| `pageBreakAt` | `$mixPerPage` (left/right = 8, both = 4 — diset agar pas 1 halaman A4 fisik, sudah diverifikasi via TCPDF asli) |
| Judul 5 karakter + `...` | `mixcode_title_slice()` (default `$mixTitleLen = 5`) |
| Barcode via JsBarcode (client-side) | `get_barcode_png_vertical()` bawaan Inlislite (server-side PNG, aman untuk TCPDF & Word) |
| `library_name` di header warna | `NamaPerpustakaan` dari `settingparameters` (sudah disediakan controller) |
| Halaman settings Vue + color picker SLiMS | Menu **Pengaturan > Label Mixcode**: kelola klasifikasi warna (tambah/ubah nama/ubah warna/hapus per baris; kolom Rentang otomatis `000 – 099.999` untuk kelas utama) + **Template Bawaan** (dropdown full semua model label seperti di halaman eksemplar) + judul penuh/potong + font + tinggi barcode + teks header (opsi di `settingparameters` prefix Mixcode). Template bawaan otomatis terisi saat panel Cetak Label dibuka (via endpoint `label-mixcode/default-template`). Dropdown Model selalu berisi daftar nama label klasifikasi; Jenis Kertas tampil penuh dan layout mixcode menyesuaikan (A4 = 2 per baris, lainnya 1 per baris). Satu keluarga digit pertama ikut warna baris yang terakhir disimpan. Baris tambah: kode 1–3 digit angka yang belum ada. |
| Antrean cetak (session, maks 50) | Tidak diporting — Inlislite sudah menyeleksi via checkbox + POST; pilih ≤ 50 eksemplar per cetakan |

## Yang sengaja tidak diporting

- **Mode QRCode** (`codeType = Qrcode`): template ini memakai barcode CODE128
  vertikal seperti default plugin (`codeType = Barcode`). Varian QR Inlislite
  sudah ada (`cetak-label-a4-4-qrcode`).
- **Autoprint / margin halaman browser** (`marginPage`, `autoprint`): tidak
  relevan untuk output PDF/Word server-side.
- Ukuran kotak label (`widthBox/heightBox`) tidak dijadikan pengaturan:
  layout tabel TCPDF dihitung pas 1 halaman A4 (verifikasi render),
  mengubahnya perlu hitung ulang `mixPerPage`.
