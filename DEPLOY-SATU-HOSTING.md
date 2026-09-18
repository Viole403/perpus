# Deploy INLISLite V3 + SLiMS 9 Bulian dalam 1 Hosting cPanel (ArenHost)

## 1. Hasil analisa

| Aspek | INLISLite V3 (`INLISLIteV33/`) | SLiMS 9 Bulian (`slims9_bulian/`) |
|---|---|---|
| Framework | CodeIgniter 4 (`codeigniter4/framework ^4.7`) | Native SLiMS (PSR-0 `Slims/`, `simbio2/`) |
| PHP | `composer.json`: `^7.3 \|\| ^8.0`, tapi `public/index.php` menolak < **8.2** → pakai **PHP 8.2** | `README`: **PHP >= 8.1** → PHP 8.2 cocok untuk keduanya |
| Ekstensi wajib | `intl`, `mbstring`, `mysqlnd`, `json`, `xml`, `curl` | `gd`, `gettext`, `mbstring` (+ umum: `curl`, `xml`, `zip`, `mysqlnd`) |
| Docroot | Harus ke **`public/`** (front controller `public/index.php`) | **Root repo itu sendiri** (`index.php` di root) |
| Database | 1 DB MySQL (`inlislite_v33`), config via `.env` | 1 DB MySQL, config via `config/database.php` + installer web `install/` |
| File SQL bawaan | **Ada: `inlislite_v33.sql` (±9 MB, dump Navicat dari MySQL 8.0, 145 tabel, tanpa statement `CREATE DATABASE`/`USE`, tanpa `DEFINER`/view/trigger)** di root workspace | Ada: `install/senayan.sql` + installer web otomatis |
| Cache/Search eksternal | `predis/predis` ada, tapi default `.env` (`is_opac_cache=0` dsb.) **mati** → jangan dinyalakan di shared hosting | Engine ES/Solr default **mati** (`enable=false`) → jangan dinyalakan |
| Ukuran kode (tanpa `.git`, tanpa `vendor/`) | ±104 MB | ±207 MB |
| `vendor/` | **Sudah ada** (`INLISLIteV33/vendor/`, dari bundle Laragon; `composer.json` identik) → **jangan `composer install` ulang** di hosting (hemat RAM, hindari blokir security-advisory dompdf) | Kecil → `composer install --no-dev` di SSH, atau upload `vendor/` dari lokal |

**Kesimpulan: BISA satu hosting.** Keduanya PHP-MySQL standar, tanpa root access, tanpa proses background. Konflik satu-satunya adalah **versi PHP** (samakan ke 8.2) dan **docroot berbeda** (solusi: subdomain/addon domain dengan docroot masing-masing).

## 2. Pilih skema (sesuaikan paket)

| Paket | Domain | Skema yang bisa dipakai |
|---|---|---|
| Student (1 domain, 1 GB) | 1 | Hanya Opsi A. Storage sangat mepet (lihat §3). |
| Competent (1 domain, 2 GB) | 1 | Opsi A. Minimum yang disarankan. |
| Expert (2 domain, 5 GB) | 2 | Opsi A **atau** Opsi B. Paling aman. |

> Catatan: "1 Domain" = batas *addon domain*. Subdomain dari domain utama umumnya tetap bisa dibuat di semua paket (konfirmasi ke support ArenHost jika ragu).

### Opsi A — 1 domain + 2 subdomain (semua paket, disarankan)

```text
domain.id            → public_html/landing/      (halaman info/link, opsional)
slims.domain.id      → public_html/slims/        (seluruh isi slims9_bulian)
inlis.domain.id      → public_html/inlis/public/ (docroot; kode lengkap di public_html/inlis/)
```

### Opsi B — 2 domain (khusus Expert)

```text
domain1.id → public_html/slims/        (SLiMS)
domain2.id → public_html/inlis/public/ (INLISLite, docroot ke /public)
```

## 3. Cek kecukupan paket dulu

- Kode saja ±311 MB, setelah `composer install` keduanya perkiraan **±500–700 MB**. Sisa untuk upload (PDF/repositori) menipis cepat.
- Student 1 GB: bisa naik-tayang tapi rawan penuh dalam hitungan bulan → **jangan untuk produksi berisi file digital**.
- Competent 2 GB: minimum layak. Expert 5 GB: disarankan.
- RAM 512 MB–1,5 GB: `composer install` di server bisa terbunuh (OOM). Solusi: build `vendor/` di lokal lalu upload (lihat §5 langkah 4b).

## 4. Persiapan di lokal (sebelum sentuh cPanel)

1. File dump **`inlislite_v33.sql`** sudah ada di root workspace (±9 MB, Navicat dump dari MySQL 8.0.3). Pastikan ikut di-upload ke server (atau import dari lokal via phpMyAdmin setelah DB dibuat).
2. **INLISLite — pakai `vendor/` yang sudah jadi** (terbukti di tes lokal, `composer.json` identik): salin `insilite/www/inlislitev33/vendor/` ke `INLISLIteV33/vendor/`. Jangan `composer install` ulang di server (boros RAM, rawan OOM, dan composer baru bisa menolak dompdf karena security advisory).
   **SLiMS** — `composer install --no-dev` di lokal (dep kecil), atau via Terminal cPanel bila RAM cukup.
3. Buat 2 arsip zip **tanpa folder `.git/`**:
   - `inlis.zip` = isi `INLISLIteV33/` (termasuk `vendor/` dan **`public/uploads/`**, tanpa `.git/`)
   - `slims.zip` = isi `slims9_bulian/` (termasuk `vendor/`, tanpa `.git/`)

   > **Pelajaran dari tes lokal:** `public/uploads/` (±6 MB: banner, logo cabang, dsb.) masuk `.gitignore` sehingga tidak ikut repo — tanpa folder ini INLISLite tampil polos tanpa gambar. Ambil dari bundle Laragon (`insilite/www/inlislitev33/public/uploads/`) bila kosong.
4. Catat kredensial yang akan dibuat (DB name/user/pass, admin).

## 5. Langkah deploy di cPanel

### Langkah 0 — PHP 8.2 + ekstensi

1. **Software → MultiPHP Manager**: set PHP **8.2** untuk domain + kedua subdomain.
2. **Software → Select PHP Extensions** (atau PHP Selector): aktifkan `intl`, `mbstring`, `gd`, `gettext`, `curl`, `xml`, `zip`, `mysqlnd`, `fileinfo`, `iconv`. Simpan.

### Langkah 1 — Database (2 buah)

**Databases → MySQL Databases**, buat:

| # | Database | User | Untuk |
|---|---|---|---|
| 1 | `user_inlisdb` | `user_inlisuser` | INLISLite |
| 2 | `user_slimsdb` | `user_slimsuser` | SLiMS |

Centang **ALL PRIVILEGES** untuk tiap user ke DB-nya. Simpan password di catatan.

### Langkah 2 — Subdomain + docroot

**Domains → Subdomains**, buat (Opsi A):

| Subdomain | Document Root (isi manual) |
|---|---|
| `slims.domain.id` | `public_html/slims` |
| `inlis.domain.id` | `public_html/inlis/public` |

(cPanel otomatis membuat folder; untuk `inlis` pastikan docroot menunjuk ke subfolder `public`.)

Opsi B: **Domains → Create/Addon Domains**, docroot sama seperti tabel di atas.

### Langkah 3 — Upload + ekstrak

1. **Files → File Manager → public_html**: upload `slims.zip`, ekstrak ke `public_html/slims/`.
2. Upload `inlis.zip`, ekstrak ke `public_html/inlis/` (sehingga ada `public_html/inlis/public/index.php`).
   > Wajib: `inlis.zip` harus berisi `vendor/` DAN `public/uploads/` (lihat §4 — tanpa keduanya tampilan polos tanpa CSS/gambar).
3. Upload juga `inlislite_v33.sql` ke `public_html/` (untuk import via SSH) atau simpan di lokal (untuk import via phpMyAdmin). Hapus dari server setelah import sukses.
4. Hapus file zip dari server.

### Langkah 4 — Permission folder writable

Di File Manager (klik kanan → Change Permissions) atau SSH:

```bash
chmod 755 ~/public_html/inlis/writable ~/public_html/slims/files ~/public_html/slims/images ~/public_html/slims/repository ~/public_html/slims/config
```

Isi `writable/` (INLISLite) dan `files/`, `images/`, `repository/` (SLiMS) harus writable oleh web server.

### Langkah 5 — Konfigurasi INLISLite (`inlis.domain.id`)

1. Di `public_html/inlis/`, salin `env.sample` menjadi **`.env`** (File Manager → Copy, rename diawali titik).
2. Edit `.env`:
   ```env
   CI_ENVIRONMENT = production
   app.baseURL = 'https://inlis.domain.id/'
   database.default.hostname = localhost
   database.default.database = user_inlisdb
   database.default.username = user_inlisuser
   database.default.password = ISI_PASSWORD_DB_1
   database.default.DBDriver = MySQLi
   ```
   Samakan juga blok `database.data.*`.
3. **Import DB**: **Databases → phpMyAdmin** → pilih `user_inlisdb` → **Import** → file `inlislite_v33.sql` (±9 MB, 145 tabel) → Go. File ini **tidak berisi statement `CREATE DATABASE`/`USE`**, jadi wajib pilih database-nya dulu sebelum import. Gagal/timeout → coba via SSH: `mysql -u user_inlisuser -p user_inlisdb < inlislite_v33.sql`.
   > Kompatibilitas: dump berasal dari MySQL 8.0 dan memakai collation `utf8mb4_0900_ai_ci`. Jika import error "unknown collation", MySQL/MariaDB di hosting terlalu lama → hubungi support ArenHost untuk upgrade, atau convert collation ke `utf8mb4_general_ci` sebelum import.
4. Buka `https://inlis.domain.id/`. Blank/500 → cek `public_html/inlis/writable/logs/`.

> Jika belum build `vendor/` di lokal (§4): buka **Terminal** cPanel di `public_html/inlis` lalu `composer install --no-dev --prefer-dist --optimize-autoloader`. Gagal/OOM → wajib upload `vendor/` dari lokal.

### Langkah 6 — Instalasi SLiMS (`slims.domain.id`)

Wizard installer SLiMS mengerjakan semuanya sendiri (cek ekstensi → tes koneksi DB → import `senayan.sql` → tulis `config/database.php` + `config/env.php` → buat akun admin). Jadi **tidak perlu salin config manual**.

1. Pastikan folder `config/` writable (sudah di langkah 4).
2. Buka `https://slims.domain.id/install/`:
   - Isi host `localhost`, port `3306`, nama DB `user_slimsdb`, user + password DB.
   - Centang sample data bila mau isi contoh (terbukti jalan di tes lokal).
   - Buat username + password admin → catat.
3. **Setelah sukses: hapus atau rename folder `install/`** (wajib keamanan):
   ```bash
   mv install install.DELETED
   ```
4. Bila nanti perlu ubah DB: edit `config/database.php` (lihat `config/database.sample.php` untuk format). Bila ganti mode error: edit `config/env.php` (`$env = 'production';`).

> `config/url.php`: biarkan `'base' => ''` untuk subdomain; `'force_https' => true` jika SSL aktif. File ini opsional (dibuat dari `url.sample.php` bila perlu).

### Langkah 7 — SSL + HTTPS

**Security → SSL/TLS Status** → **Run AutoSSL** untuk domain + kedua subdomain (atau Let's Encrypt). Setelah aktif, pastikan `app.baseURL` (INLISLite) dan `force_https` (SLiMS) memakai `https://`.

### Langkah 8 — Landing page utama (opsional)

Isi `public_html/landing/` (atau root `public_html/` jika domain utama tidak dipakai aplikasi) dengan `index.html` berisi link ke kedua subdomain.

## 6. Troubleshooting

| Gejala | Penyebab umum → aksi |
|---|---|
| INLISLite: "PHP version must be 8.2" | Domain/subdomain masih PHP lama → MultiPHP Manager → 8.2 |
| INLISLite blank/500 | Permission `writable/`; kredensial DB di `.env`; log di `writable/logs/` |
| INLISLite polos tanpa CSS/JS | `app.baseURL` tidak sama persis dengan URL akses (cek `https://` + garis miring akhir) — atau folder `public/uploads/` tidak ikut ter-upload (lihat §4 langkah 3) |
| SLiMS redirect ke `install/` terus | `config/database.php` belum ada/kredensial salah (atau folder `config/` tidak writable saat wizard jalan) |
| SLiMS halaman rusak di subfolder | `config/url.php` → `'base'` sesuaikan; kosongkan dulu untuk subdomain |
| Composer mati terbunuh (killed/OOM) | Build `vendor/` di lokal lalu upload (§4) |
| Storage penuh tiba-tiba | Repository PDF + backup menumpuk → upgrade ke Expert, bersihkan `files/backup` |

## 7. Checklist selesai

- [ ] PHP 8.2 + ekstensi aktif untuk semua domain/subdomain
- [ ] 2 database + user terbuat, import sukses (INLISLite via phpMyAdmin, SLiMS via wizard)
- [ ] `inlis.domain.id` tampil, login admin bisa
- [ ] `slims.domain.id` tampil (OPAC), login admin bisa
- [ ] Folder `slims/install/` dihapus/diganti nama
- [ ] SSL aktif di semua host, akses HTTPS dipaksa
- [ ] Backup terjadwal (cPanel → Backup Wizard / JetBackup bila tersedia)

## 9. Cara otomatis (installer web, disarankan)

Folder `installer/` berisi auto-installer yang mengerjakan Langkah 3–6
sekaligus, sudah diuji end-to-end lokal (extract → import chunked →
config → admin → kedua app HTTP 200 + aset OK). Lihat `installer/README.md`.

Ringkasnya:

1. Di repo ini jalankan `installer/build.sh` → hasilkan `installer/dist/`
   (`inlis.zip.000/.001`, `slims.zip`, `sql-inlis.sql`,
   `sql-slims-schema.sql`, `sql-slims-sample.sql`).
2. Upload `installer/installer.php` + isi `dist/` ke satu folder di hosting
   (mis. `public_html/pasang/`), buka via browser.
3. Buat 2 database + user manual di cPanel (Langkah 1), isi kredensial di
   form installer (mode `both` untuk satu hosting, atau `inlis`/`slims`
   saja untuk dua hosting terpisah).
4. Klik **Pasang** → installer: cek server → gabung+ekstrak zip → import
   SQL chunked (resume otomatis) → tulis `.env` + `config/` → reset admin.
5. Hapus folder installer dari server setelah selesai.

Catatan: SSH di paket ini opsional (hanya untuk `install.sh`/troubleshoot);
jalur utama cukup browser + File Manager.

## 8. Batasan yang disadari (tidak diutak-atik)

- Elasticsearch/Sphinx (SLiMS) dan Redis (INLISLite) **tetap mati** — butuh server sendiri, tidak cocok di paket ini.
- Chat server SLiMS (`chatserver.php`, port 9300) **tidak dijalankan** — pastikan `chat_system enabled=false` (default).
- Cron/scheduler tidak dikonfigurasi di panduan ini; tambahkan bila butuh (cPanel → Cron Jobs).
