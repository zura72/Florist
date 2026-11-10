-- Jalankan SEKALI (tab SQL phpMyAdmin) pada database yang dibuat sebelum ongkir bisa diatur di dasbor.
-- Membuat tabel shipping_zones dan mengisi 4 daerah awal. Aman dijalankan ulang: baris yang sudah ada
-- (termasuk yang sudah diubah lewat Dasbor > Ongkir) tidak ditimpa.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS shipping_zones (
  code        VARCHAR(30)  NOT NULL PRIMARY KEY,
  label       VARCHAR(60)  NOT NULL,
  fee         INT UNSIGNED NULL DEFAULT NULL,
  sort_order  INT          NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO shipping_zones (code, label, fee, sort_order, is_active) VALUES
('palmerah','Palmerah dan Slipi',15000,10,1),
('jakbar','Jakarta Barat dan Pusat',30000,20,1),
('jakarta','Jakarta Selatan, Timur, dan Utara',45000,30,1),
('bodetabek','Depok, Tangerang, dan Bekasi',NULL,40,1);
