-- Produk awal (sama dengan kartu di index.html). Aman diimpor ulang.
INSERT INTO products (id, name, category, price, image) VALUES
('pastel','Buket Pastel Lembut','campur',295000,'assets/bridal-bouquet-unsplash.jpg'),
('blush','Buket 50 Mawar Blush','mawar',850000,'assets/bouquet-de-roses-roses.jpg'),
('warna','Buket Tulip Warna-warni','tulip',425000,'assets/tulip-bouquet-vienna-unsplash.jpg'),
('peony','Vas Peony Merah Muda','vas',650000,'assets/bouquet-of-peonies-12.jpg'),
('tulip-pink','Keranjang Tulip Pink','tulip',385000,'assets/a-bouquet-of-pink-tulips-in-a.jpg'),
('ceria','Buket Ceria Matahari','campur',395000,'assets/dfc-0794-bright-bouquet-of-pink-roses.jpg'),
('pengantin','Buket Pengantin Klasik','pengantin',650000,'assets/22-bridal-bouquet-unsplash.jpg'),
('anyelir','Rangkaian Mawar Anyelir','vas',450000,'assets/rose-and-carnation-flower-bouquet-01.jpg'),
('jingga','Buket Tulip Jingga','tulip',345000,'assets/a-bouquet-of-orange-tulips-on-a.jpg'),
('vas-pastel','Rangkaian Vas Pastel','vas',525000,'assets/pastel-artificial-flower-bouquet-in-white-vase.jpg'),
('hijau','Buket Pengantin Hijau Putih','pengantin',750000,'assets/wedding-flowers.jpg'),
('tulip-merah','Keranjang Tulip Merah','tulip',385000,'assets/a-bouquet-of-red-tulips-in-a.jpg')
ON DUPLICATE KEY UPDATE name=VALUES(name), category=VALUES(category), price=VALUES(price);

-- Daerah antar awal (sama dengan daftar "Ongkos antar" di index.html). Aman diimpor ulang:
-- kalau baris sudah ada, nama, ongkir, dan urutan dikembalikan ke nilai awal ini.
INSERT INTO shipping_zones (code, label, fee, sort_order, is_active) VALUES
('palmerah','Palmerah dan Slipi',15000,10,1),
('jakbar','Jakarta Barat dan Pusat',30000,20,1),
('jakarta','Jakarta Selatan, Timur, dan Utara',45000,30,1),
('bodetabek','Depok, Tangerang, dan Bekasi',NULL,40,1)
ON DUPLICATE KEY UPDATE label=VALUES(label), fee=VALUES(fee), sort_order=VALUES(sort_order);
