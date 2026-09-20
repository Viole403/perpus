-- sql-mixcode.sql — digabung otomatis oleh installer/build.sh dari:
--   1. seeds/fix-datatable-ispopuler.sql
--   2. seeds/mixcode_warna.sql
--   3. seeds/labelmixcode_permissions.sql
--   4. seeds/labelmixcode_menu.sql
-- (JANGAN edit file ini langsung; ubah sumbernya lalu rebuild.)
-- Aman dijalankan ulang: INSERT memakai WHERE NOT EXISTS.
-- CATATAN: statement ALTER di bawah gagal dengan error 1060 bila kolom
-- sudah ada (mis. install ulang) — itu normal, abaikan; statement
-- berikutnya tetap jalan. Di installer.php error 1060 otomatis diabaikan,
-- di install.sh/cli pakai `mysql --force`.
-- Perbaikan DataTables "Ajax error" (Unknown column 'a.ISPopuler')
-- Tabel katalog di seed inlislite_v33.sql belum punya kolom ISPopuler
-- padahal Api/Katalog + Home (koleksi populer) + list.php memakainya.
-- Jalankan sekali di database inlislite_v33:
ALTER TABLE catalogs ADD COLUMN ISPopuler tinyint(1) NULL DEFAULT 0 AFTER IsOPAC;
-- Palet warna 10 kelas utama DDC untuk Label Mixcode Warna.
-- Disetujui user (gaya "Scribd", turunan Material Design).
-- Diterapkan ke master_kelas_besar (kolom warna) — sumber warna yang
-- dibaca EksemplarLabelController sebagai $label['Warna1'].
-- Bisa diubah lagi lewat menu Pengaturan > Label Mixcode.
UPDATE master_kelas_besar SET warna = '#FFC107' WHERE kdKelas LIKE '0%';
UPDATE master_kelas_besar SET warna = '#8E24AA' WHERE kdKelas LIKE '1%';
UPDATE master_kelas_besar SET warna = '#2E7D32' WHERE kdKelas LIKE '2%';
UPDATE master_kelas_besar SET warna = '#D32F2F' WHERE kdKelas LIKE '3%';
UPDATE master_kelas_besar SET warna = '#CE93D8' WHERE kdKelas LIKE '4%';
UPDATE master_kelas_besar SET warna = '#FB8C00' WHERE kdKelas LIKE '5%';
UPDATE master_kelas_besar SET warna = '#66BB6A' WHERE kdKelas LIKE '6%';
UPDATE master_kelas_besar SET warna = '#1976D2' WHERE kdKelas LIKE '7%';
UPDATE master_kelas_besar SET warna = '#64B5F6' WHERE kdKelas LIKE '8%';
UPDATE master_kelas_besar SET warna = '#795548' WHERE kdKelas LIKE '9%';
-- Permission menu Pengaturan > Label Mixcode.
-- Jalankan sekali di database inlislite_v33 (sesudah modul terpasang).
-- Ganti 1 pada auth_groups_permissions dengan ID group yang sesuai
-- bila bukan grup admin. Setelah ini, user harus LOGOUT + LOGIN ulang
-- agar matriks permission di session dibangun ulang (mode profiling).

INSERT INTO auth_permissions (name, route, menu, description, category)
SELECT 'label-mixcode/access', 'label-mixcode', 'Label Mixcode',
       'Permission for menu Label Mixcode', NULL
FROM DUAL WHERE NOT EXISTS
  (SELECT 1 FROM auth_permissions WHERE name = 'label-mixcode/access');

INSERT INTO auth_permissions (name, route, menu, description, category)
SELECT 'label-mixcode/index', 'label-mixcode', 'Label Mixcode',
       'Permission for menu Label Mixcode', NULL
FROM DUAL WHERE NOT EXISTS
  (SELECT 1 FROM auth_permissions WHERE name = 'label-mixcode/index');

INSERT INTO auth_permissions (name, route, menu, description, category)
SELECT 'label-mixcode/save', 'label-mixcode', 'Label Mixcode',
       'Permission for menu Label Mixcode', NULL
FROM DUAL WHERE NOT EXISTS
  (SELECT 1 FROM auth_permissions WHERE name = 'label-mixcode/save');

INSERT INTO auth_permissions (name, route, menu, description, category)
SELECT 'label-mixcode/default-template', 'label-mixcode', 'Label Mixcode',
       'Permission for menu Label Mixcode', NULL
FROM DUAL WHERE NOT EXISTS
  (SELECT 1 FROM auth_permissions WHERE name = 'label-mixcode/default-template');

INSERT INTO auth_groups_permissions (group_id, permission_id, category, branch_id)
SELECT 1, id, 0, 0 FROM auth_permissions
WHERE name IN ('label-mixcode/access', 'label-mixcode/index',
               'label-mixcode/save', 'label-mixcode/default-template')
  AND NOT EXISTS
  (SELECT 1 FROM auth_groups_permissions g
   WHERE g.group_id = 1 AND g.permission_id = auth_permissions.id);
-- Entri sidebar "Label Mixcode" di bawah Pengaturan Katalog.
-- Sidebar Inlislite (partial/sidebar.php) dibaca dari tabel c_menus
-- (category_id = 1), BUKAN dari navigation.php. Item hanya tampil bila
-- permission '<controller>/access' di-grant ke grup user
-- (lihat seeds/labelmixcode_permissions.sql).
-- Aman dijalankan ulang. Induk Pengaturan Katalog = id 158
-- (sesuaikan bila ID berbeda di database lain).

INSERT INTO c_menus
  (name, parent, controller, slug, icon, type, category_id, sort, active)
SELECT 'Label Mixcode', 158, 'label-mixcode', 'label-mixcode',
       'fas fa-tags', 'menu', 1, 51, 1
FROM DUAL WHERE NOT EXISTS
  (SELECT 1 FROM c_menus WHERE controller = 'label-mixcode' AND category_id = 1);
