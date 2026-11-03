-- FacilityWatch - Skema database + seed data (MySQL 5.7+ / MariaDB 10.4+)
-- Import: mysql -u root -p < database/schema.sql

CREATE DATABASE IF NOT EXISTS facilitywatch
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE facilitywatch;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS maintenance_tickets;
DROP TABLE IF EXISTS facility_assets;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------
-- users (admin / petugas)
-- ---------------------------------------------------------------
CREATE TABLE users (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username    VARCHAR(50)  NOT NULL,
  password    VARCHAR(255) NOT NULL,
  full_name   VARCHAR(100) NOT NULL,
  role        ENUM('admin','teknisi') NOT NULL DEFAULT 'admin',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- rooms
-- ---------------------------------------------------------------
CREATE TABLE rooms (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  room_code    VARCHAR(20)  NOT NULL,
  room_name    VARCHAR(100) NOT NULL,
  floor        INT NOT NULL DEFAULT 1,
  building     VARCHAR(100) NOT NULL,
  description  TEXT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rooms_code (room_code),
  KEY idx_rooms_building (building),
  KEY idx_rooms_name (room_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- facility_assets
-- ---------------------------------------------------------------
CREATE TABLE facility_assets (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  asset_code  VARCHAR(50)  NOT NULL,
  asset_name  VARCHAR(150) NOT NULL,
  category    ENUM('Furniture','Elektronik','AC','Sanitasi','Peralatan','Lainnya') NOT NULL DEFAULT 'Lainnya',
  room_id     INT UNSIGNED NOT NULL,
  `condition` ENUM('Baik','Perlu Perbaikan','Rusak') NOT NULL DEFAULT 'Baik',
  quantity    INT NOT NULL DEFAULT 1,
  image_url   VARCHAR(255) NULL,
  notes       TEXT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_assets_code (asset_code),
  KEY idx_assets_room (room_id),
  KEY idx_assets_category (category),
  KEY idx_assets_condition (`condition`),
  KEY idx_assets_name (asset_name),
  CONSTRAINT fk_assets_room FOREIGN KEY (room_id) REFERENCES rooms (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- maintenance_tickets
-- ---------------------------------------------------------------
CREATE TABLE maintenance_tickets (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_number      VARCHAR(30)  NOT NULL,
  facility_asset_id  INT UNSIGNED NOT NULL,
  reporter_name      VARCHAR(100) NOT NULL,
  reporter_role      ENUM('Guru','Siswa','Staf','Lainnya') NOT NULL DEFAULT 'Lainnya',
  issue_description  TEXT NOT NULL,
  priority           ENUM('Rendah','Sedang','Tinggi','Darurat') NOT NULL DEFAULT 'Sedang',
  image_url          VARCHAR(255) NULL,
  maintenance_status ENUM('Dilaporkan','Ditinjau','Dalam Perbaikan','Selesai','Ditolak') NOT NULL DEFAULT 'Dilaporkan',
  assigned_to        VARCHAR(100) NULL,
  resolution_notes   TEXT NULL,
  reported_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at        TIMESTAMP NULL DEFAULT NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tickets_number (ticket_number),
  KEY idx_tickets_asset (facility_asset_id),
  KEY idx_tickets_status (maintenance_status),
  KEY idx_tickets_priority (priority),
  KEY idx_tickets_reported (reported_at),
  CONSTRAINT fk_tickets_asset FOREIGN KEY (facility_asset_id) REFERENCES facility_assets (id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===============================================================
-- SEED DATA
-- ===============================================================

-- Login admin:  admin / admin123  (ganti setelah instalasi!)
INSERT INTO users (username, password, full_name, role) VALUES
('admin', '$2y$10$xm9WAtDoQm2mRnsfE4w.l.mkBYUQ0ZW3HJ0eNLX3.yF3a.m/zcapG', 'Administrator Sarpras', 'admin');

INSERT INTO rooms (room_code, room_name, floor, building, description) VALUES
('R-101',   'Kelas X IPA 1',        1, 'Gedung A', 'Ruang kelas reguler kapasitas 36 siswa.'),
('R-201',   'Kelas XI IPS 2',       2, 'Gedung A', 'Ruang kelas lantai dua, menghadap lapangan.'),
('LAB-KOM', 'Laboratorium Komputer',2, 'Gedung B', 'Lab komputer 30 unit untuk praktik TIK dan ujian berbasis komputer.'),
('PERP',    'Perpustakaan',         1, 'Gedung C', 'Perpustakaan sekolah dengan area baca dan katalog digital.'),
('WC-A1',   'Toilet Putra Gedung A',1, 'Gedung A', 'Toilet siswa putra lantai satu.');

INSERT INTO facility_assets (asset_code, asset_name, category, room_id, `condition`, quantity, notes) VALUES
('AST-001', 'Meja Siswa',            'Furniture',  1, 'Baik',            36, NULL),
('AST-002', 'Kursi Siswa',           'Furniture',  1, 'Perlu Perbaikan', 36, 'Beberapa kaki kursi mulai goyang.'),
('AST-003', 'Proyektor LCD',         'Elektronik', 1, 'Rusak',            1, 'Lampu proyektor tidak menyala.'),
('AST-004', 'AC Split 2 PK',         'AC',         1, 'Baik',             2, 'Servis terakhir Agustus 2026.'),
('AST-005', 'Papan Tulis Putih',     'Peralatan',  2, 'Baik',             1, NULL),
('AST-006', 'AC Split 1.5 PK',       'AC',         2, 'Perlu Perbaikan',  1, 'Kurang dingin.'),
('AST-007', 'Kipas Angin Dinding',   'Elektronik', 2, 'Baik',             2, NULL),
('AST-008', 'Komputer Desktop',      'Elektronik', 3, 'Baik',            30, 'Core i5, RAM 8 GB.'),
('AST-009', 'Router Wi-Fi',          'Elektronik', 3, 'Rusak',            1, 'Port LAN mati.'),
('AST-010', 'AC Cassette 3 PK',      'AC',         3, 'Baik',             2, NULL),
('AST-011', 'Rak Buku Besi',         'Furniture',  4, 'Baik',            20, NULL),
('AST-012', 'Komputer Katalog',      'Elektronik', 4, 'Perlu Perbaikan',  2, 'Layar sering berkedip.'),
('AST-013', 'Kran Wastafel',         'Sanitasi',   5, 'Rusak',            4, 'Air bocor di sambungan.'),
('AST-014', 'Kloset Duduk',          'Sanitasi',   5, 'Baik',             3, NULL),
('AST-015', 'Lampu LED Panel',       'Elektronik', 5, 'Perlu Perbaikan',  6, 'Dua lampu redup.');

INSERT INTO maintenance_tickets
 (ticket_number, facility_asset_id, reporter_name, reporter_role, issue_description, priority,
  maintenance_status, assigned_to, resolution_notes, reported_at, resolved_at) VALUES
('MTC-20261028-001', 3,  'Bu Rina Wulandari', 'Guru',  'Proyektor tidak menyala sama sekali saat dipakai mengajar.', 'Tinggi',
  'Dalam Perbaikan', 'Pak Joko', NULL, '2026-10-28 08:15:00', NULL),
('MTC-20261029-001', 2,  'Dimas Pratama',     'Siswa', 'Kaki beberapa kursi patah dan berbahaya diduduki.', 'Sedang',
  'Ditinjau', NULL, NULL, '2026-10-29 10:30:00', NULL),
('MTC-20261030-001', 9,  'Pak Hendra',        'Staf',  'Router Wi-Fi lab komputer mati total, ujian online terganggu.', 'Darurat',
  'Dilaporkan', NULL, NULL, '2026-10-30 07:45:00', NULL),
('MTC-20261030-002', 4,  'Bu Sari',           'Guru',  'AC meneteskan air ke meja siswa.', 'Sedang',
  'Selesai', 'Pak Joko', 'Saluran pembuangan dibersihkan dan freon dicek.', '2026-10-30 09:00:00', '2026-10-31 14:20:00'),
('MTC-20261031-001', 15, 'Andi Saputra',      'Siswa', 'Lampu toilet berkedip.', 'Rendah',
  'Ditolak', NULL, 'Duplikat laporan, sudah dalam jadwal penggantian lampu.', '2026-10-31 11:10:00', NULL);
