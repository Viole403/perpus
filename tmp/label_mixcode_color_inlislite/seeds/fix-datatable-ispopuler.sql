-- Perbaikan DataTables "Ajax error" (Unknown column 'a.ISPopuler')
-- Tabel katalog di seed inlislite_v33.sql belum punya kolom ISPopuler
-- padahal Api/Katalog + Home (koleksi populer) + list.php memakainya.
-- Jalankan sekali di database inlislite_v33:
ALTER TABLE catalogs ADD COLUMN ISPopuler tinyint(1) NULL DEFAULT 0 AFTER IsOPAC;
