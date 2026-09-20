-- Seed tambahan Lokasi Ruang (locations) agar form Tambah Eksemplar bisa dipakai.
-- Kondisi awal: Pusat (ID 1) hanya punya 3 ruang, Pusling01 (ID 5) tidak punya sama sekali.
-- Aman dijalankan ulang (INSERT ... WHERE NOT EXISTS per Code).
-- Jalankan di database inlislite_v33.

-- ── Ruang Perpustakaan Pusat (LocationLibrary_id = 1) ──
INSERT INTO locations (Code, Name, Description, LocationLibrary_id, active)
SELECT '0104', 'Ruang Sirkulasi', 'Layanan peminjaman dan pengembalian', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM locations WHERE Code = '0104');

INSERT INTO locations (Code, Name, Description, LocationLibrary_id, active)
SELECT '0105', 'Ruang Baca Anak', 'Layanan anak dan keluarga', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM locations WHERE Code = '0105');

INSERT INTO locations (Code, Name, Description, LocationLibrary_id, active)
SELECT '0106', 'Ruang Multimedia', 'Koleksi digital dan audiovisual', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM locations WHERE Code = '0106');

INSERT INTO locations (Code, Name, Description, LocationLibrary_id, active)
SELECT '0107', 'Ruang Arsip dan Koleksi Khusus', 'Arsip dan koleksi khusus', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM locations WHERE Code = '0107');

-- ── Ruang Perpustakaan Keliling 01 (LocationLibrary_id = 5) ──
INSERT INTO locations (Code, Name, Description, LocationLibrary_id, active)
SELECT '0501', 'Ruang Layanan Keliling', 'Layanan di armada keliling', 5, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM locations WHERE Code = '0501');

INSERT INTO locations (Code, Name, Description, LocationLibrary_id, active)
SELECT '0502', 'Gudang Koleksi Pusling', 'Penyimpanan koleksi keliling', 5, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM locations WHERE Code = '0502');
