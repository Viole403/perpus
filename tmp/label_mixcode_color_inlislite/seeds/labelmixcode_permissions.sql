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
