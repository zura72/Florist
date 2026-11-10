-- Kembang Subuh — skema MySQL/MariaDB. Impor lewat phpMyAdmin (tab Import) ke database yang sudah dibuat.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS products (
  id          VARCHAR(40)  NOT NULL PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  category    VARCHAR(40)  NOT NULL,
  price       INT UNSIGNED NOT NULL,
  image       VARCHAR(255) NOT NULL DEFAULT '',
  stock       INT          NOT NULL DEFAULT 20,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_code      VARCHAR(40)  NOT NULL UNIQUE,          -- dikirim ke Midtrans sebagai order_id, mis. KS-20261106-AB12CD
  customer_name   VARCHAR(120) NOT NULL,
  customer_phone  VARCHAR(30)  NOT NULL,
  customer_email  VARCHAR(160) NOT NULL DEFAULT '',
  recipient_name  VARCHAR(120) NOT NULL,
  recipient_phone VARCHAR(30)  NOT NULL DEFAULT '',
  address         TEXT         NOT NULL,
  delivery_date   DATE         NOT NULL,
  delivery_slot   VARCHAR(40)  NOT NULL DEFAULT '',
  card_message    VARCHAR(200) NOT NULL DEFAULT '',
  subtotal        INT UNSIGNED NOT NULL,
  shipping_fee    INT UNSIGNED NOT NULL DEFAULT 0,
  shipping_zone   VARCHAR(60)  NOT NULL DEFAULT '',          -- daerah antar, mis. Palmerah dan Slipi
  total           INT UNSIGNED NOT NULL,
  -- status pembayaran mengikuti Midtrans: pending | paid | failed | expired | refunded
  payment_status  VARCHAR(20)  NOT NULL DEFAULT 'pending',
  payment_type    VARCHAR(40)  NOT NULL DEFAULT '',
  snap_token      VARCHAR(80)  NOT NULL DEFAULT '',
  -- status pesanan untuk toko: baru | dirangkai | diantar | selesai | batal
  order_status    VARCHAR(20)  NOT NULL DEFAULT 'baru',
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_created (created_at),
  KEY idx_payment (payment_status),
  KEY idx_status (order_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED NOT NULL,
  product_id  VARCHAR(40)  NOT NULL,
  name        VARCHAR(120) NOT NULL,   -- salinan nama & harga saat dipesan
  price       INT UNSIGNED NOT NULL,
  qty         INT UNSIGNED NOT NULL,
  CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  KEY idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_logs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_code  VARCHAR(40)  NOT NULL,
  source      VARCHAR(20)  NOT NULL,   -- notification | status-check
  payload     MEDIUMTEXT   NOT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_code (order_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at TIMESTAMP    NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  ip          VARCHAR(45)  NOT NULL,
  attempted_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daerah antar & ongkir; diatur di Dasbor > Ongkir. fee NULL = "Tanya dulu" (ongkir dihitung lewat WhatsApp, tidak bisa dibayar online).
CREATE TABLE IF NOT EXISTS shipping_zones (
  code        VARCHAR(30)  NOT NULL PRIMARY KEY,      -- huruf kecil, angka, tanda hubung; dikirim checkout sebagai shipping_zone
  label       VARCHAR(60)  NOT NULL,
  fee         INT UNSIGNED NULL DEFAULT NULL,
  sort_order  INT          NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
