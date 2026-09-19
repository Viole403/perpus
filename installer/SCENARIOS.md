# Skenario deploy (semua otomatis, satu codebase)

Mode didukung semua skrip (`installer.php`, `install.sh`,
`customer-install.sh`, `deploy-customer.sh`).

| Skenario | 1 web / 1 hosting | 2 web / 1 hosting | 2 web / 2 hosting |
|---|---|---|---|
| `mode` | `inlis` atau `slims` | `both` | `inlis` di H1 + `slims` di H2 |
| DB | 1 | 2 | 1 per hosting |
| Artefak | app itu saja | semua | app itu saja per hosting |
| Docroot | inlis→`.../inlis/public`, slims→`.../slims` | sama | sama per hosting |

Docroot tidak berubah antar skenario: INLISLite selalu
`<target>/inlis/public`, SLiMS selalu `<target>/slims`.

## A. 2 web / 1 hosting (mode both)

Web (`installer.php`): upload `installer.php` + isi `dist/`,
buat 2 DB via cPanel, form → mode both → Install → Finish.

SSH (`deploy-fleet.sh` / manual): satu baris per hosting.

```bash
./installer/deploy-customer.sh \
  --host H --ssh-user U --cpanel-user U \
  --mode both --target public_html \
  --inlis-url https://inlis.DOM --slims-url https://slims.DOM \
  --admin-user admin --admin-pass 'XXX'
```

## B. 1 web / 1 hosting

Sama, mode `inlis` (atau `slims`), URL+DB app lain tidak
diperlukan dan tidak dibuat.

```bash
./installer/deploy-customer.sh \
  --host H --ssh-user U --cpanel-user U \
  --mode inlis --target public_html \
  --inlis-url https://inlis.DOM \
  --admin-user admin --admin-pass 'XXX'
```

Web: upload `installer.php` + `inlis.zip*` + `sql-inlis.sql`
saja, pilih mode inlis.

## C. 2 web / 2 hosting

Dua deploy mode tunggal: `inlis` ke H1, `slims` ke H2.
`deploy-fleet.sh` memetakan otomatis dari kolom `mode` di TSV
(lihat `customers.tsv.example`).

```bash
./installer/deploy-fleet.sh customers.tsv
```

## Pasca-install (semua skenario)

Atur docroot subdomain/addon + SSL. `slims/install/` sudah
dihapus installer. Staging dihapus otomatis (SSH) / tombol
Finish (web).
