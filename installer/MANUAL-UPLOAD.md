# Upload Manual (tanpa installer, tanpa SSH)

Deploy dengan tangan kosong: cPanel File Manager + phpMyAdmin saja.
Cocok bila SSH dimatikan hosting atau kamu mau kontrol penuh tiap langkah.

Artefak yang dipakai sama dengan auto-installer — hasilkan dulu:

```bash
./installer/build.sh   # output: installer/dist/
```

Isi `dist/`: `inlis.zip.000` + `inlis.zip.001` (>100MB, split),
`slims.zip`, `sql-inlis.sql`, `sql-slims-schema.sql`,
`sql-slims-sample.sql`.

## 0. Aturan file per skenario

| Skenario | File yang diupload |
|---|---|
| A. 2 web / 1 hosting | semua isi `dist/` |
| B. 1 web / 1 hosting | `inlis.zip.*` + `sql-inlis.sql` saja (atau `slims.zip` + 2 sql slims saja) |
| C. 2 web / 2 hosting | per hosting seperti skenario B |

## 1. Buat database (cPanel → MySQL Databases)

Skenario A butuh 2 DB + 2 user (masing-masing ALL PRIVILEGES).
Skenario B/C cukup 1 DB + 1 user. Catat nama DB, user, password.

## 2. Upload + ekstrak (cPanel → File Manager)

1. Upload file zip ke `public_html/`.
   File > ~50MB sering gagal via browser → pakai FTP (FileZilla).
2. `inlis.zip` ter-split (`.000`/`.001`): gabung dulu.
   Tanpa SSH/Terminal ini tidak bisa di File Manager —
   solusinya upload via FTP sebagai **satu file utuh**:
   di lokal `cat inlis.zip.* > inlis.zip`, lalu upload file utuhnya via FTP.
3. Ekstrak (File Manager → kanan → Extract):
   - `inlis.zip` → `public_html/inlis/` (hasil wajib ada `public_html/inlis/public/index.php`, `vendor/autoload.php`, `public/uploads/`)
   - `slims.zip` → `public_html/slims/` (hasil wajib ada `public_html/slims/index.php`, `install/`)
4. Hapus file zip dari server setelah ekstrak.

## 3. Permission

File Manager → klik kanan → Change Permissions (atau 755 semua folder ini):

- `public_html/inlis/writable`
- `public_html/slims/files`, `images`, `repository`, `config`

## 4. INLISLite (lewati bila mode slims saja)

1. Di `public_html/inlis/`: salin `env.sample` menjadi `.env`, edit:
   ```env
   CI_ENVIRONMENT = production
   app.baseURL = 'https://inlis.domain.id/'
   database.default.hostname = localhost
   database.default.database = NAMA_DB_INLIS
   database.default.username = USER_DB_INLIS
   database.default.password = PASSWORD_DB_INLIS
   database.default.DBDriver = MySQLi
   ```
   Samakan blok `database.data.*`.
2. phpMyAdmin → pilih DB inlis → Import → `sql-inlis.sql` (±9MB, 145 tabel).
   File ini tanpa `CREATE DATABASE`/`USE`, jadi DB harus dipilih dulu.
   Error "unknown collation" → file `dist/` sudah versi konversi
   (`utf8mb4_unicode_ci`); bila masih error, hubungi support hosting.
3. Buka `https://inlis.domain.id/` → harus tampil Beranda.

## 5. SLiMS (lewati bila mode inlis saja)

Pakai wizard bawaan (paling aman, tanpa edit config manual):

1. Pastikan `config/` writable (langkah 3).
2. Buka `https://slims.domain.id/install/` → isi host `localhost`,
   port `3306`, nama DB + user + password → centang sample data
   bila mau isi contoh → buat akun admin → catat.
3. Selesai → **hapus/rename folder `install/`** (wajib):
   File Manager → rename jadi `install.DELETED`.
4. Ganti mode error bila perlu: salin `config/env.sample.php`
   jadi `config/env.php`, isi `$env = 'production';`.

## 6. Docroot + PHP + SSL

1. Subdomain/addon domain, docroot:
   INLISLite → `public_html/inlis/public`, SLiMS → `public_html/slims`.
2. MultiPHP Manager → PHP **8.2** untuk domain + subdomain.
3. SSL/TLS Status → Run AutoSSL untuk semua host.

## 7. Checklist

- [ ] `inlis.domain` tampil, `/login` 200, login admin bisa
- [ ] `slims.domain` tampil (OPAC), `?p=login` 200, login admin bisa
- [ ] Folder `slims/install/` sudah dihapus
- [ ] Zip + file SQL sudah dihapus dari server
- [ ] HTTPS aktif semua host
