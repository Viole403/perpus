# Tes lokal native (Arch, tanpa podman)

Stack: `php-legacy` 8.3 + MariaDB 12.3 sistem, dua server `php -S`.

## Install (sekali saja, user yang jalankan)

```bash
sudo pacman -S --needed php-legacy php-legacy-apache php-legacy-gd composer mariadb
sudo mariadb-install-db --user=mysql --basedir=/usr --datadir=/var/lib/mysql
sudo systemctl enable --now mariadb
sudo sed -i -E 's/^;extension=(mysqli|pdo_mysql|gd|intl|gettext|iconv)$/extension=\1/' /etc/php-legacy/php.ini
sudo mariadb -e "CREATE DATABASE IF NOT EXISTS senayan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS inlislite_v33 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'slims'@'localhost' IDENTIFIED BY 'slims123'; CREATE USER IF NOT EXISTS 'slims'@'127.0.0.1' IDENTIFIED BY 'slims123'; CREATE USER IF NOT EXISTS 'inlis'@'localhost' IDENTIFIED BY 'inlis123'; CREATE USER IF NOT EXISTS 'inlis'@'127.0.0.1' IDENTIFIED BY 'inlis123'; GRANT ALL PRIVILEGES ON senayan.* TO 'slims'@'localhost'; GRANT ALL PRIVILEGES ON senayan.* TO 'slims'@'127.0.0.1'; GRANT ALL PRIVILEGES ON inlislite_v33.* TO 'inlis'@'localhost'; GRANT ALL PRIVILEGES ON inlislite_v33.* TO 'inlis'@'127.0.0.1'; FLUSH PRIVILEGES;"
```

## Import database (sekali saja)

```bash
mysql --protocol=tcp -h127.0.0.1 -uslims -pslims123 senayan < slims9_bulian/install/senayan.sql
mysql --protocol=tcp -h127.0.0.1 -uslims -pslims123 senayan < slims9_bulian/install/sampledata.sql
mysql --protocol=tcp -h127.0.0.1 -uinlis -pinlis123 inlislite_v33 < local/inlislite_v33-mariadb.sql
```

`local/inlislite_v33-mariadb.sql` = dump asli dengan `utf8mb4_0900_ai_ci` diganti
`utf8mb4_unicode_ci` agar cocok dengan MariaDB.

## Config (sudah tersalin, referensi: `local/`)

- `INLISLIteV33/.env` ← `local/inlis-native.env` (DB `inlis`/`inlis123`, baseURL `:8080`)
- `slims9_bulian/config/database.php` ← `local/slims-database.php`
- `slims9_bulian/config/env.php` ← `local/slims-env.php` (`development`)
- `local/router-inlis.php` = router `php -S` (ponytail: dev-only; produksi pakai Apache + `public/.htaccess`)

`composer install` SLiMS sudah jalan (`slims9_bulian/vendor/` ada).
INLISLite pakai `vendor/` dari bundle Laragon (`composer.json` identik).

## Jalankan tiap sesi tes

```bash
php-legacy -S 127.0.0.1:8881 -t slims9_bulian
php-legacy -S 127.0.0.1:8080 -t INLISLIteV33/public local/router-inlis.php
```

Wajib `-t INLISLIteV33/public` agar aset statis terserve langsung.
Tanpanya CSS/JS 404 dan halaman tampil polos.

`INLISLIteV33/public/uploads/` tidak ikut git (gitignore). Disalin dari
bundle Laragon (`insilite/www/inlislitev33/public/uploads/`, ±6 MB) agar
banner/logo tampil.

## URL + akun lokal

| App | URL | Admin |
|---|---|---|
| SLiMS 9 OPAC | http://127.0.0.1:8881/ | `admin` / `admin123` (direset lokal) |
| SLiMS login | http://127.0.0.1:8881/index.php?p=login | sama |
| INLISLite | http://127.0.0.1:8080/ | `admin` / `admin123` (direset lokal) |
| INLISLite login | http://127.0.0.1:8080/login | sama |

Password `admin123` hanya untuk tes lokal. Jangan bawa ke server produksi.
