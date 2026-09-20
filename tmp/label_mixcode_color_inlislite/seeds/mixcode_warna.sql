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
