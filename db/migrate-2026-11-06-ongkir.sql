-- Jalankan sekali (tab SQL phpMyAdmin) kalau tabel orders sudah dibuat sebelum ongkir per daerah.
ALTER TABLE orders ADD COLUMN shipping_zone VARCHAR(60) NOT NULL DEFAULT '' AFTER shipping_fee;
