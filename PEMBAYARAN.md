# Pembayaran online (Midtrans Snap)

Checkout Kembang Subuh sudah disiapkan untuk Midtrans Snap (QRIS, GoPay/ShopeePay, Virtual Account bank, kartu kredit).
Selama kunci Midtrans di `api/config.php` masih kosong, situs berjalan di **mode uji**: pembeli melihat lembar
"Mode uji — gateway belum dihubungkan" dan pembayaran hanya disimulasikan (tidak ada uang yang ditarik).

## Berkas yang terlibat

| Berkas | Fungsi |
| --- | --- |
| `checkout.html`, `checkout.css`, `checkout.js` | Halaman checkout: keranjang, data pengiriman, tombol "Bayar sekarang" |
| `pesanan.html`, `pesanan.js` | Halaman status pesanan (`pesanan.html?kode=KS-...`), cek status otomatis tiap 4 detik |
| `api/products.php` | Daftar produk aktif + harga resmi, ongkir, tanggal antar paling awal |
| `api/checkout.php` | Validasi pesanan, hitung ulang harga dari database, simpan pesanan, minta token Snap |
| `api/notify.php` | **Payment Notification URL** untuk Midtrans (cek signature + konfirmasi status ke Midtrans) |
| `api/order-status.php` | Status pesanan untuk pembeli (tanpa nomor HP, alamat, email) |
| `api/demo-pay.php` | Simulasi bayar. Hanya hidup bila `demo_payments` = `true` **dan** kunci Midtrans kosong |

## 1. Ambil kunci sandbox Midtrans

1. Daftar / masuk di <https://dashboard.midtrans.com>.
2. Pastikan lingkungan di kiri atas adalah **Sandbox**.
3. Buka **Settings → Access Keys**. Salin **Server Key** (`SB-Mid-server-...`) dan **Client Key** (`SB-Mid-client-...`).

Server Key itu rahasia: hanya boleh ada di `api/config.php` di server. Jangan dikirim lewat chat atau di-commit.

## 2. Isi `api/config.php`

Bila belum ada, salin `api/config.example.php` menjadi `api/config.php`, lalu isi:

```php
'midtrans' => [
    'is_production' => false,                 // sandbox
    'server_key'    => 'SB-Mid-server-xxxx',
    'client_key'    => 'SB-Mid-client-xxxx',
],
'shop' => [
    'base_url'      => 'https://domain-anda.id',   // tanpa garis miring di akhir
    'demo_payments' => false,                       // matikan simulasi
    // ...
],
```

Ongkir dihitung per daerah antar (sama dengan daftar "Ongkos antar" di beranda): Palmerah dan Slipi Rp 15.000, Jakarta Barat dan Pusat Rp 30.000, Jakarta Selatan/Timur/Utara Rp 45.000, Depok/Tangerang/Bekasi tanya dulu lewat WhatsApp (tidak bisa dibayar online). Daftarnya ada di `ks_shipping_zones()` di `api/bootstrap.php`; bisa ditimpa di config dengan `'shipping_zones' => ['kode' => ['label' => '...', 'fee' => 15000]]` (`'fee' => null` = tanya dulu). Kalau mengubah harga, ubah juga daftar di `index.html`.

Database yang dibuat sebelum 6 Nov 2026 perlu kolom baru: jalankan `db/migrate-2026-11-06-ongkir.sql` sekali di tab SQL phpMyAdmin.

Begitu kedua kunci terisi, checkout otomatis memakai Midtrans Snap dan `api/demo-pay.php` mati (404).

## 3. Atur URL di dashboard Midtrans

Di **Settings → Payment → Notification URL** (Sandbox):

- **Payment Notification URL**: `https://<domain>/api/notify.php`
- **Finish Redirect URL**: `https://<domain>/pesanan.html`
- **Unfinish / Error Redirect URL**: `https://<domain>/pesanan.html`

Situs juga mengirim `callbacks.finish` = `https://<domain>/pesanan.html?kode=<kode pesanan>` di setiap transaksi,
jadi pembeli selalu kembali ke halaman pesanannya sendiri.

Notification URL harus bisa diakses dari internet (bukan `localhost`). Saat uji di laptop, halaman pesanan tetap
bisa memperbarui status karena `order-status.php` menanyakan status langsung ke Midtrans (paling sering 1x per 15 detik).

## 4. Uji di sandbox

1. Pesan dari `index.html`, lanjut ke checkout, klik **Bayar sekarang**. Jendela Snap terbuka.
2. Pilih metode lalu pakai data uji Midtrans (daftar lengkap: <https://docs.midtrans.com/docs/testing-payment-on-sandbox>):
   - **Kartu kredit** berhasil: `4811 1111 1111 1114`, CVV `123`, kedaluwarsa bulan/tahun apa saja di masa depan, OTP `112233`.
   - **Kartu ditolak**: `4911 1111 1111 1113`.
   - **QRIS / GoPay / ShopeePay**: salin URL gambar QR, tempel di <https://simulator.sandbox.midtrans.com> (menu QRIS / GoPay), lalu bayar.
   - **Virtual Account**: salin nomor VA, bayar di simulator yang sama (menu Virtual Account sesuai bank).
3. Halaman `pesanan.html?kode=...` berubah menjadi "Pembayaran berhasil" dalam beberapa detik.
4. Cek di phpMyAdmin: `orders.payment_status` = `paid`, dan ada baris di `payment_logs` (`notification`, `status-check`).

## 5. Cara kerja keamanan (ringkas)

- Harga dan total **selalu dihitung ulang dari tabel `products`**; harga dari browser diabaikan.
- Kode pesanan: `KS-YYYYMMDD-XXXXXXXX` (8 hex acak dari `random_bytes`).
- `notify.php` menolak (403) notifikasi yang `signature_key`-nya tidak sama dengan
  `sha512(order_id + status_code + gross_amount + server_key)`, lalu tetap **menanyakan ulang status ke Midtrans**
  sebelum mengubah pesanan, dan memastikan `gross_amount` sama dengan `orders.total`.
- Status tidak pernah turun dari `paid` ke `pending`/`failed` (idempoten). Semua payload mentah tercatat di `payment_logs`.
- Halaman status pembeli tidak menampilkan nomor HP, alamat, atau email.

## 6. Pindah ke produksi

1. Selesaikan aktivasi akun Midtrans (dokumen usaha) sampai mode **Production** aktif.
2. Ambil Server Key & Client Key **Production** (tanpa awalan `SB-`).
3. Di `api/config.php`: `is_production => true`, isi kedua kunci produksi.
4. Ulangi langkah 3 (Notification / Finish URL) di dashboard **Production**.
5. Lakukan satu transaksi nyata bernilai kecil, cek status `paid` di admin.

## Checklist sebelum online

- [ ] Situs memakai **HTTPS** (sertifikat aktif, `http://` dialihkan ke `https://`).
- [ ] `shop.demo_payments` = **`false`** (wajib! Mode uji membolehkan siapa saja menandai pesanan "lunas" bila kunci kosong).
- [ ] `shop.base_url` diisi domain HTTPS yang benar.
- [ ] Server Key & Client Key terisi dan sesuai lingkungan (sandbox vs production).
- [ ] Notification URL di dashboard = `https://<domain>/api/notify.php`.
- [ ] `api/config.php` tidak bisa dibuka dari browser (berkas PHP, hanya mengembalikan halaman kosong) dan tidak ikut dibagikan.
- [ ] Ekstensi PHP **cURL** dan **pdo_mysql** aktif di hosting.
- [ ] Uji satu pesanan sampai `paid`, satu pesanan gagal, dan cek baris `payment_logs`.
