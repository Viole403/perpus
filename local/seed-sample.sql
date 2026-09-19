-- =====================================================================
-- SEED SAMPLE INLISLite V3 (data contoh untuk testing)
-- =====================================================================
-- Isi: 10 katalog tersebar di kelas Dewey 000-900 (pas untuk uji
--       Barcode Warna per klasifikasi) + 2 eksemplar per katalog
--       = 10 katalog, 20 eksemplar. Barcode: SEED-00001..SEED-00020
--
-- Idempoten: aman dijalankan berulang (baris SEED lama dihapus dulu).
--
-- Cara pakai (lokal):
--   mysql -h 127.0.0.1 -P 3333 -u root e2e_inlis < local/seed-sample.sql
-- Cara pakai (hosting via phpMyAdmin): buka DB inlislite -> Import file ini.
-- =====================================================================

DELETE FROM `collections` WHERE `NomorBarcode` LIKE 'SEED-%';
DELETE FROM `catalogs` WHERE `BIBID` LIKE 'SEED-%';

INSERT INTO `catalogs`
  (`ID`,`ControlNumber`,`BIBID`,`Title`,`Author`,`Edition`,`Publisher`,`PublishLocation`,`PublishYear`,`Publikasi`,`Subject`,`PhysicalDescription`,`ISBN`,`CallNumber`,`Languages`,`DeweyNo`,`IsOPAC`,`Branch_id`,`Location_id`,`Worksheet_id`,`CreateBy`,`CreateDate`,`CreateTerminal`,`active`)
VALUES
  (90001,'SEED-CTRL-001','SEED-0001','Pengantar Ilmu Komputer dan Informatika','Abdul Kadir','Edisi 3','Andi Offset','Yogyakarta','2019','Yogyakarta : Andi Offset, 2019','Komputer; Informatika','xii, 340 hlm ; 23 cm','978-979-29-0001-1','004 Abd p','Indonesia','004',1,1,466,1,1,NOW(),'seed',1),
  (90002,'SEED-CTRL-002','SEED-0002','Psikologi Perkembangan Anak Usia Dini','Hurlock, Elizabeth B.','Edisi 5','Erlangga','Jakarta','2018','Jakarta : Erlangga, 2018','Psikologi anak; Perkembangan','viii, 256 hlm ; 21 cm','978-602-00-0002-2','155 Hur p','Indonesia','155',1,1,466,1,1,NOW(),'seed',1),
  (90003,'SEED-CTRL-003','SEED-0003','Fiqih Ibadah Sehari-hari','Sulaiman Rasyid','Cetakan 10','Sinar Baru','Bandung','2020','Bandung : Sinar Baru, 2020','Fiqih; Ibadah','x, 212 hlm ; 20 cm','978-979-00-0003-3','297 Sul f','Indonesia','297',1,1,466,1,1,NOW(),'seed',1),
  (90004,'SEED-CTRL-004','SEED-0004','Ekonomi Mikro: Teori dan Aplikasi','Sukirno, Sadono','Edisi 4','Rajawali Pers','Jakarta','2021','Jakarta : Rajawali Pers, 2021','Ekonomi mikro','xiv, 410 hlm ; 24 cm','978-979-00-0004-4','330 Suk e','Indonesia','330',1,1,466,1,1,NOW(),'seed',1),
  (90005,'SEED-CTRL-005','SEED-0005','Tata Bahasa Inggris Praktis','Azar, Betty Schrampfer','Edisi 2','Binarupa Aksara','Jakarta','2017','Jakarta : Binarupa Aksara, 2017','Bahasa Inggris; Tata bahasa','xii, 388 hlm ; 25 cm','978-979-00-0005-5','410 Aza t','Indonesia','410',1,1,466,1,1,NOW(),'seed',1),
  (90006,'SEED-CTRL-006','SEED-0006','Matematika Diskrit dan Aplikasinya','Rosen, Kenneth H.','Edisi 7','Salemba Teknika','Jakarta','2019','Jakarta : Salemba Teknika, 2019','Matematika diskrit','xviii, 520 hlm ; 26 cm','978-979-00-0006-6','510 Ros m','Indonesia','510',1,1,466,1,1,NOW(),'seed',1),
  (90007,'SEED-CTRL-007','SEED-0007','Ilmu Kesehatan Masyarakat','Notoatmodjo, Soekidjo','Edisi Revisi','Rineka Cipta','Jakarta','2018','Jakarta : Rineka Cipta, 2018','Kesehatan masyarakat','x, 280 hlm ; 21 cm','978-979-00-0007-7','613 Not i','Indonesia','613',1,1,466,1,1,NOW(),'seed',1),
  (90008,'SEED-CTRL-008','SEED-0008','Desain Grafis dengan Inkscape','Wahana Komputer','Cetakan 3','Andi Offset','Yogyakarta','2020','Yogyakarta : Andi Offset, 2020','Desain grafis; Inkscape','viii, 196 hlm ; 23 cm','978-979-00-0008-8','741 Wah d','Indonesia','741',1,1,466,1,1,NOW(),'seed',1),
  (90009,'SEED-CTRL-009','SEED-0009','Laskar Pelangi','Hirata, Andrea','Cetakan 25','Bentang Pustaka','Yogyakarta','2021','Yogyakarta : Bentang Pustaka, 2021','Novel Indonesia','x, 529 hlm ; 20 cm','978-979-00-0009-9','813 Hir l','Indonesia','813',1,1,466,1,1,NOW(),'seed',1),
  (90010,'SEED-CTRL-010','SEED-0010','Sejarah Indonesia Modern','Ricklefs, M. C.','Edisi 3','Gadjah Mada University Press','Yogyakarta','2019','Yogyakarta : UGM Press, 2019','Sejarah Indonesia','xx, 610 hlm ; 24 cm','978-979-00-0010-5','959 Ric s','Indonesia','959',1,1,466,1,1,NOW(),'seed',1);

INSERT INTO `collections`
  (`ID`,`NomorBarcode`,`NoInduk`,`TanggalPengadaan`,`CallNumber`,`Branch_id`,`Catalog_id`,`Location_id`,`Rule_id`,`Category_id`,`Media_id`,`Source_id`,`Status_id`,`Location_Library_id`,`CreateBy`,`CreateDate`,`CreateTerminal`,`ISOPAC`,`active`)
VALUES
  (90001,'SEED-00001','IND-90001',NOW(),'004 Abd p',1,90001,466,1,7,2,1,1,1,1,NOW(),'seed',1,1),
  (90002,'SEED-00002','IND-90002',NOW(),'004 Abd p C.2',1,90001,466,1,7,2,1,1,1,1,NOW(),'seed',1,1),
  (90003,'SEED-00003','IND-90003',NOW(),'155 Hur p',1,90002,466,1,7,2,1,1,1,1,NOW(),'seed',1,1),
  (90004,'SEED-00004','IND-90004',NOW(),'155 Hur p C.2',1,90002,466,1,7,2,1,1,1,1,NOW(),'seed',1,1),
  (90005,'SEED-00005','IND-90005',NOW(),'297 Sul f',1,90003,468,2,7,2,1,1,1,1,NOW(),'seed',1,1),
  (90006,'SEED-00006','IND-90006',NOW(),'297 Sul f C.2',1,90003,468,2,7,2,1,1,1,1,NOW(),'seed',1,1),
  (90007,'SEED-00007','IND-90007',NOW(),'330 Suk e',1,90004,466,1,7,2,1,1,1,1,NOW(),'seed',1,1),
  (90008,'SEED-00008','IND-90008',NOW(),'330 Suk e C.2',1,90004,466,1,7,2,1,1,1,1,NOW(),'seed',1,1),
  (90009,'SEED-00009','IND-90009',NOW(),'410 Aza t',1,90005,466,1,7,2,1,1,1,1,NOW(),'seed',1,1),
  (90010,'SEED-00010','IND-90010',NOW(),'410 Aza t C.2',1,90005,466,1,7,2,1,1,1,1,NOW(),'seed',1,1),
  (90011,'SEED-00011','IND-90011',NOW(),'510 Ros m',1,90006,466,1,7,2,1,1,1,1,NOW(),'seed',1,1),
  (90012,'SEED-00012','IND-90012',NOW(),'510 Ros m C.2',1,90006,466,1,7,2,1,1,1,1,NOW(),'seed',1,1),
  (90013,'SEED-00013','IND-90013',NOW(),'613 Not i',1,90007,466,1,7,2,1,1,1,1,NOW(),'seed',1,1),
  (90014,'SEED-00014','IND-90014',NOW(),'613 Not i C.2',1,90007,466,1,7,2,1,1,1,1,NOW(),'seed',1,1),
  (90015,'SEED-00015','IND-90015',NOW(),'741 Wah d',1,90008,466,1,7,2,1,1,1,1,NOW(),'seed',1,1),
  (90016,'SEED-00016','IND-90016',NOW(),'741 Wah d C.2',1,90008,466,1,7,2,1,1,1,1,NOW(),'seed',1,1),
  (90017,'SEED-00017','IND-90017',NOW(),'813 Hir l',1,90009,466,1,7,2,1,1,1,1,NOW(),'seed',1,1),
  (90018,'SEED-00018','IND-90018',NOW(),'813 Hir l C.2',1,90009,466,1,7,2,1,1,1,1,NOW(),'seed',1,1),
  (90019,'SEED-00019','IND-90019',NOW(),'959 Ric s',1,90010,466,1,7,2,1,1,1,1,NOW(),'seed',1,1),
  (90020,'SEED-00020','IND-90020',NOW(),'959 Ric s C.2',1,90010,466,1,7,2,1,1,1,1,NOW(),'seed',1,1);

-- Verifikasi cepat (jalankan manual bila perlu):
-- SELECT COUNT(*) AS katalog_seed FROM catalogs WHERE BIBID LIKE 'SEED-%';
-- SELECT COUNT(*) AS eksemplar_seed FROM collections WHERE NomorBarcode LIKE 'SEED-%';
