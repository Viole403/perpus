-- Menu Pengaturan > Captcha + permission (idempoten, aman dijalankan ulang).
-- Induk "Pengaturan Umum" dicari by controller agar tahan beda ID.
-- Setelah ini: user wajib LOGOUT + LOGIN ulang (matriks permission
-- session dibangun ulang saat login).

-- Entri sidebar di bawah Pengaturan Umum.
INSERT INTO c_menus
  (name, parent, controller, slug, icon, type, category_id, sort, active)
SELECT 'Captcha', id, 'pengaturan-captcha', 'pengaturan-captcha',
       'fas fa-shield-alt', 'menu', 1, 61, 1
FROM c_menus
WHERE controller = 'pengaturan-umum' AND category_id = 1
  AND NOT EXISTS
  (SELECT 1 FROM c_menus m2 WHERE m2.controller = 'pengaturan-captcha' AND m2.category_id = 1)
LIMIT 1;

INSERT INTO auth_permissions (name, route, menu, description, category)
SELECT 'pengaturan-captcha/access', 'pengaturan-captcha', 'Captcha',
       'Permission for menu Captcha', NULL
FROM DUAL WHERE NOT EXISTS
  (SELECT 1 FROM auth_permissions WHERE name = 'pengaturan-captcha/access');

INSERT INTO auth_permissions (name, route, menu, description, category)
SELECT 'pengaturan-captcha/index', 'pengaturan-captcha', 'Captcha',
       'Permission for menu Captcha', NULL
FROM DUAL WHERE NOT EXISTS
  (SELECT 1 FROM auth_permissions WHERE name = 'pengaturan-captcha/index');

INSERT INTO auth_permissions (name, route, menu, description, category)
SELECT 'pengaturan-captcha/save', 'pengaturan-captcha', 'Captcha',
       'Permission for menu Captcha', NULL
FROM DUAL WHERE NOT EXISTS
  (SELECT 1 FROM auth_permissions WHERE name = 'pengaturan-captcha/save');

INSERT INTO auth_groups_permissions (group_id, permission_id, category, branch_id)
SELECT 1, id, 0, 0 FROM auth_permissions
WHERE name IN ('pengaturan-captcha/access', 'pengaturan-captcha/index',
               'pengaturan-captcha/save')
  AND NOT EXISTS
  (SELECT 1 FROM auth_groups_permissions g
   WHERE g.group_id = 1 AND g.permission_id = auth_permissions.id);
