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
