# Design system Kembang Subuh

Gaya: toko bunga online yang bersih dan terang, mengikuti referensi pilihan pemilik: Three Bouquets, Voletta Flowers, FlowerAdvisor, dan Delovery. Latar putih, huruf sans modern, foto buket besar, kartu produk, dan tombol WhatsApp yang selalu terlihat.

Sumber kebenaran: `design/tokens.json`. Berkas `design/tokens.css` dibuat otomatis dan tidak boleh diedit langsung.

Setelah mengubah token, buat ulang CSS-nya dari root proyek:

```bash
node "C:/Users/HP/.claude/skills/design-system/scripts/generate-tokens.cjs" --config design/tokens.json -o design/tokens.css
```

Periksa nilai yang masih ditulis langsung:

```bash
node "C:/Users/HP/.claude/skills/design-system/scripts/validate-tokens.cjs" --dir . --ignore .taste-skill --ignore graphify-out --ignore assets --ignore .claude --ignore design
```

## Logo

Logo ini template. Lima kelopak yang terbit di atas garis cakrawala menggabungkan bunga dan fajar.

| Berkas | Isi | Cara memakai |
|---|---|---|
| `assets/logo/logo-mark.svg` | Lambang saja | Warnanya mengikuti `currentColor` |
| `assets/logo/logo-template.svg` | Lambang dan nama toko | Ganti teks di elemen `<text>` |
| `assets/logo/favicon.svg` | Lambang di kotak putih | Ikon tab browser |

- Nama pada `logo-template.svg` berupa teks yang bisa diedit. Komputer tanpa huruf Plus Jakarta Sans akan menampilkannya dengan huruf lain, jadi ubah teks menjadi outline sebelum dicetak.
- Di halaman, lambang dipakai lewat `<symbol id="logo">` di `index.html`. Kalau lambangnya diganti, ganti juga symbol itu.

## Tiga lapis token

| Lapis | Contoh | Dipakai untuk |
|---|---|---|
| Primitif | `--primitive-color-rose-600` | Nilai mentah. Jangan dipakai langsung di `styles.css`. |
| Semantik | `--color-accent`, `--space-16`, `--font-size-body` | Arti sebuah nilai. |
| Komponen | `--button-wa-bg`, `--category-size` | Keputusan khusus satu komponen. |

Generator menulis nilai akhir ke setiap lapis, bukan rantai `var()`. Karena itu, perubahan primitif baru berlaku setelah CSS dibuat ulang.

## Warna dan kontras

Dihitung dengan rumus WCAG.

| Teks atau garis | Di atas | Kontras |
|---|---|---|
| `--color-ink` | `--color-bg` | 16,82:1 |
| `--color-ink` | `--color-surface` | 15,71:1 |
| `--color-muted` | `--color-bg` | 6,16:1 |
| `--color-muted` | `--color-surface` | 5,75:1 |
| `--color-muted` | `--color-surface-hover` | 5,36:1 |
| `--color-on-accent` | `--color-accent` | 5,36:1 |
| `--color-on-accent` | `--color-accent-hover` | 7,12:1 |
| `--color-accent` | `--color-bg` | 5,36:1 |
| `--button-soft-fg` | `--button-soft-bg` | 5,89:1 |
| `--button-wa-fg` | `--button-wa-bg` | 5,29:1 |
| `--button-wa-fg` | `--button-wa-bg-hover` | 7,18:1 |
| `--field-line` | `--field-bg` | 3,22:1 |
| `--color-danger` | `--color-bg` | 6,54:1 |

Merah muda dipakai untuk aksi utama. Hijau hanya untuk WhatsApp. Teks banner berada di atas foto yang digelapkan dengan `brightness(.48)`.

## Huruf

Satu keluarga: Plus Jakarta Sans, berat 400, 500, 600, dan 700.

| Token | Ukuran | Dipakai untuk |
|---|---|---|
| `--font-size-small` | 14 px | Label, keterangan produk, menu |
| `--font-size-body` | 16 px | Teks utama, nama produk |
| `--font-size-lead` | 20 px | Pembuka hero, logo |
| `--font-size-head` | 26–36 px | Judul bagian |
| `--font-size-title` | 36–60 px | Judul halaman |

## Jarak dan sudut

- Skala jarak: `--space-2` sampai `--space-96`. Angka pada nama token sama dengan nilai pikselnya.
- `--space-page` adalah margin halaman, `--space-section` adalah jarak antarbagian, `--size-content` adalah lebar maksimum isi.
- Sudut: `--radius-control` 10 px untuk tombol dan kolom, `--radius-card` 16 px untuk kartu, `--radius-panel` 24 px untuk hero dan banner, `--radius-pill` untuk pencarian, filter, dan lencana.

## State komponen

### Tombol (`.btn`)

| Varian | Dipakai di | Default | Hover |
|---|---|---|---|
| `.btn` | Lihat koleksi | `--button-bg` | `--button-bg-hover` |
| `.btn-soft` | Tambah | `--button-soft-bg` | berubah penuh ke `--button-bg` |
| `.btn-quiet` | Tanya via WhatsApp | `--button-quiet-bg` | `--color-tint-strong` |
| `.btn-wa` | Tombol WhatsApp hijau penuh | `--button-wa-bg` | `--button-wa-bg-hover` |
| `.btn-pay` | Bayar sekarang (keranjang) | `--button-bg` | `--button-bg-hover` |
| `.btn-line-wa` | Kirim pesanan ke WhatsApp (keranjang) | garis `--color-whatsapp` | garis `--color-whatsapp-hover` |

Semua varian mengecil ke skala .98 saat ditekan, dan punya garis fokus 2 px. Tombol kirim nonaktif saat keranjang kosong: opacity `--button-disabled-opacity`, `aria-disabled="true"`, dan klik diabaikan.

### Filter (`.pill`)

| Default | Hover | Terpilih |
|---|---|---|
| `--color-surface-hover` | `--color-tint-strong` | Latar `--color-ink`, teks `--color-bg`, `aria-pressed="true"` |

### Kartu produk (`.card`)

| Default | Hover | Tersaring |
|---|---|---|
| Foto 4:5, lencana "Kirim hari ini", nama, keterangan, harga, tombol Tambah | Foto membesar 1,04 kali | Disembunyikan, jumlah produk ikut berubah |

Tanpa JavaScript, tombol Tambah menjadi tautan WhatsApp untuk produk itu.

### Keranjang (`dialog.drawer`)

| Keadaan | Tampilan |
|---|---|
| Kosong | Ilustrasi keranjang, pesan "Keranjang masih kosong", tombol bayar dan kirim nonaktif |
| Berisi | Daftar produk dengan tombol tambah dan kurang 44 × 44 px, subtotal, tombol "Bayar sekarang", dan pesan WhatsApp yang tersusun otomatis |
| Ditutup | Tombol silang, tombol Esc, atau klik di luar panel |

### Pencarian dan hasil kosong

Pencarian menyaring kartu saat mengetik. Kalau tidak ada yang cocok, muncul pesan yang mengarahkan ke WhatsApp.

## Gerak

| Token | Nilai | Dipakai untuk |
|---|---|---|
| `--duration-press` | .15s | Tombol ditekan |
| `--duration-color` | .2s | Warna tombol dan tautan |
| `--duration-move` | .3s | Foto kategori membesar |
| `--duration-reveal` | .6s | Elemen muncul saat digulir, foto kartu membesar, keranjang masuk |
| `--duration-pop` | .45s | Angka keranjang melompat, foto selesai dimuat |
| `--duration-shimmer` | 1.4s | Kilau kerangka foto, kelopak di kartu |
| `--duration-fall` | 11s | Kelopak jatuh di hero (tiap kelopak 9–15 dtk) |
| `--duration-page` | .26s | Transisi antarhalaman |
| `--duration-step` | .5s | Satu ruas sulur Cara pesan |
| `--duration-draw` | 1.2s | Pemisah terbuka, mobil antar masuk |
| `--duration-rotate` | 5s | Jeda pesan promo |
| `--duration-sway` | 6s | Goyangan untaian footer |
| `--duration-drift` | 16s | Satu putaran kupu-kupu |
| `--ease-out` | `cubic-bezier(.2, .8, .2, 1)` | Hampir semua gerak |
| `--ease-spring` | `cubic-bezier(.34, 1.56, .64, 1)` | Kelopak preloader, angka keranjang |

Kartu dan blok "mekar" saat masuk layar: skala .96 ke 1 dengan fade, jeda 60 ms per kolom. Elemen yang sudah terlewati (misalnya setelah lompat ke `#tanya`) langsung tampil. Tanpa JavaScript semua isi langsung terlihat, karena gaya tersembunyi hanya berlaku di bawah `html.js`.

## Lapisan bunga (`floral.css`, `floral.js`)

Hiasan saja. Toko tetap berjalan kalau kedua berkas ini gagal dimuat. Semua ilustrasi berupa SVG buatan tangan di `<svg>` simbol pada awal `index.html`, selalu `aria-hidden="true"`.

| Simbol | Isi | Dipakai di |
|---|---|---|
| `fl-petal`, `fl-bloom-solid` | Satu kelopak, bunga lima kelopak polos | Preloader, kelopak jatuh, ledakan kelopak, riak tombol |
| `fl-rose`, `fl-tulip`, `fl-blossom`, `fl-leaf`, `fl-sprig` | Bahan dasar | Dirangkai di simbol lain |
| `fl-spray` | Rangkaian mawar, tulip, dan ranting | Sudut hero, bagian Pesanan khusus |
| `fl-vine` | Sulur bergelombang | Pemisah antarbagian |
| `fl-bouquet` | Buket berbungkus kertas | Bagian Cara pesan |
| `fl-basket` | Keranjang kosong | Keranjang belum berisi |
| pola `fl-garland`, `fl-edge` | Untaian dan tepi kelopak | Atas footer, tepi kiri keranjang |
| `fl-wing` | Sayap kupu-kupu | Kupu-kupu di hero |
| `fl-ring` | Cincin delapan kelopak | Di belakang foto kategori |
| `fl-pin` | Penanda peta | Daftar ongkos antar |
| `fl-corner` | Rangkaian sudut (mawar, ranting) | Sudut kiri atas ubin Pesanan khusus |

Ilustrasi yang bagiannya bergerak sendiri-sendiri ditulis langsung di halaman (bukan satu `<use>`), karena isi `<use>` tidak bisa dianimasikan per tempat: vinyet alasan belanja (`.usp-art`), sulur dan bunga langkah Cara pesan (`.step`), adegan mobil antar (`.antar-scene`), ikon bunga FAQ (`.faq-ico`), hasil pencarian kosong (`.search-art`), ranting di samping judul bagian (`.h-sprig`).

Warna ilustrasi lewat kelas `f-*` yang membaca token `--color-petal-light`, `--color-petal`, `--color-bloom`, `--color-leaf`, `--color-leaf-deep`, `--color-pollen`. Ukurannya lewat token komponen `--spray-size`, `--illustration-*`, `--divider-*`, `--garland-height`, `--drawer-edge`, `--preload-size`.

| Efek | Aturan |
|---|---|
| Preloader | Kelopak mekar di sekitar logo, hanya kunjungan pertama per sesi (`sessionStorage` `ks-preloaded`), paling lama 700 ms, tidak pernah menyembunyikan foto atau judul hero |
| Kerangka foto | Kilau merah muda di foto produk dan kategori sampai foto dimuat, lalu foto memudar masuk. Ukuran dari atribut `width`/`height`, jadi tidak ada geseran tata letak |
| Kelopak jatuh | Hanya di hero, 6 kelopak di ponsel dan 10 di layar lebar, mulai setelah `load`, berhenti saat tab tersembunyi atau hero di luar layar |
| Hover kartu | Foto membesar 1,04 kali, tiga kelopak melayang dari sudut (hanya perangkat dengan hover) |
| Tambah ke keranjang | Sembilan kelopak memancar dari tombol, angka keranjang melompat |
| Tombol ditekan | Bunga lembut mekar dari titik tekan |
| Header | Setelah promo terlewati, header naik sedikit dan mendapat bayangan |
| Keranjang | Masuk dari kanan dengan tepi kelopak, keluar ke kanan sebelum ditutup |

| Kupu-kupu | Melayang pelan di dekat buket hero (`--duration-drift`), sayap mengepak; hanya bergerak saat terlihat (`data-live` → `.is-live`) |
| Ranting judul | Membuka dari sudut kiri bawah saat judul masuk layar |
| Cincin kategori | Kelopak mekar di sekitar foto saat hover atau fokus |
| Alasan belanja | Ranting membuka dan bunga berputar mekar saat masuk layar; bunga berputar saat hover |
| Ubin Pesanan khusus | Rangkaian sudut mekar dari pojok saat masuk layar, sedikit berputar saat hover |
| Cara pesan | Bunga mekar di tiap langkah berurutan, sulur menggambar diri ke langkah berikut (`stroke-dashoffset`, `--duration-step`). Di ponsel jalurnya vertikal: batang tumbuh ke bawah |
| Ongkos antar | Mobil masuk dari kiri, matahari terbit di balik gedung, roda berputar dan angin bergaris selama terlihat; penanda peta jatuh dan bunganya mekar |
| Pemisah | Sulur terbuka dari tengah (`clip-path`), garis tipis tumbuh ke luar |
| FAQ | Bunga kecil membesar dan berputar, tanda tambah menjadi kurang. Buka/tutup halus lewat `::details-content` + `interpolate-size` bila didukung, selain itu langsung |
| Footer | Untaian bergoyang pelan (`--duration-sway`) selama terlihat |
| Promo | Tiga pesan bergantian memudar setiap `--duration-rotate`; berhenti saat hover/fokus, di luar layar, atau tab tersembunyi. Tombol jeda/putar 44 px di kanan bar (`.promo-toggle`, `aria-pressed`) memenuhi WCAG 2.2.2 untuk keyboard dan sentuh. Tinggi bar (min. 44 px) dan ruang tombol sudah disiapkan sejak cat pertama, jadi tidak ada geser |
| Pencarian kosong | Tulip muncul, kelopak jatuh, kaca pembesar mencari tiga kali; tombol "Lihat semua bunga" dan WhatsApp |
| Keranjang | Baris masuk berurutan saat laci dibuka, baris yang dihapus bergeser keluar, baris di bawahnya naik (FLIP), angka jumlah melompat |

Animasi masuk memakai `animation-fill-mode: backwards` yang dipicu kelas `.in` dari `main.js`, sehingga keadaan akhir adalah gaya biasa: tanpa JavaScript gambar langsung lengkap, dan efek hover tetap bekerja setelahnya.

### Transisi halaman

- Antarhalaman (beranda, checkout, status pesanan): `@view-transition { navigation: auto; }`. Halaman lama memudar, halaman baru naik `--page-rise` sambil muncul dalam `--duration-page`. Header bernama `ks-header`, logo `ks-logo`. Aturannya ada di `styles.css`, yang juga dimuat checkout dan status pesanan.
- Menyaring koleksi (filter, pencarian, tombol reset): `document.startViewTransition` di `main.js`. Setiap kartu yang tampil mendapat `view-transition-name: ks-card-<id>` sementara, jadi kartu meluncur ke tempat barunya. Halaman sendiri (`root`) berganti langsung lewat kelas `html.ks-filtering`. Tautan yang ikut menggulir ke `#koleksi` (menu, kategori, footer) tidak memakai transisi. Pencarian menunggu jeda ketik 140 ms sebelum transisi dimulai, dan `::view-transition` diberi `pointer-events: none` agar pil tetap bisa diklik selama kartu meluncur.
- Checkout dan status pesanan hanya menamai `.co-head` (`ks-header`) dan `.co-brand` (`ks-logo`); animasi `root` tidak didefinisikan ulang di `checkout.css`. Dasbor admin memakai pudar sendiri tanpa naik (`admin.css`), sidebar `adm-sidebar` dan bilah atas `adm-topbar` diam.
- Browser tanpa dukungan atau mode kurangi gerakan: langsung berganti.

Mode kurangi gerakan: preloader, kelopak, ledakan, riak, dan kupu-kupu tidak ada sama sekali. Semua ilustrasi baru langsung tampil lengkap tanpa gerak, cincin kategori hanya memudar, promo tidak berganti (dua pesan tampil sebaris seperti tanpa JavaScript), FAQ dan transisi halaman langsung. Kerangka foto menjadi warna polos tanpa kilau, entri hanya fade tanpa skala, header tidak bergerak, keranjang hanya fade.

## Keranjang dan pembayaran

Keranjang disimpan di `localStorage` dengan kunci `ks-cart-v1` setiap kali berubah dan dipulihkan saat halaman dibuka:

```json
{"items":{"pastel":{"name":"Buket Pastel Lembut","price":295000,"qty":1}},"kartu":"","penerima":"","alamat":""}
```

Kunci produk sama dengan `data-id` kartu dan `id` di `db/seed.sql`. Saat dipulihkan, nama dan harga selalu diambil dari halaman. Tombol utama "Bayar sekarang" (`.btn-pay`, merah muda) membuka `checkout.html`; tombol garis hijau "Atau kirim pesanan ke WhatsApp" (`.btn-line-wa`) tetap ada sebagai pilihan kedua. Keduanya `aria-disabled="true"` dan redup saat keranjang kosong.

Di bawah alasan belanja ada strip kepercayaan "Pembayaran aman via QRIS, e-wallet, transfer bank, kartu" dengan ikon garis satu warna, tanpa logo merek.

## Pengecualian yang disengaja

- Beberapa aturan taste-skill sengaja tidak diikuti karena pemilik memilih gaya referensi: kartu produk berulang, kategori berikon, lencana, dan huruf Plus Jakarta Sans.
- Validator masih melaporkan tiga breakpoint: 560, 820, dan 1080 px. CSS tidak bisa memakai `var()` di dalam `@media`.
- Warna bayangan pada header dan keranjang ditulis langsung, karena hanya dipakai di dua tempat itu.

## Foto

Setiap JPG di `assets/` punya varian WebP 320/640/960/1280 px di `assets/webp/` (tidak lebih lebar dari aslinya). `index.html` memakai `srcset` + `sizes` sesuai lebar tampil; JPG tetap di `src` sebagai cadangan. Checkout dan dasbor memakai varian 320 px untuk thumbnail.

Setelah menambah atau mengganti foto, buat ulang variannya lalu tambahkan `srcset` pada `<img>` baru:

```bash
python tools/make-webp.py
```

Hasil 2026-11-06: total foto yang diunduh beranda turun dari 2,25 MB menjadi 0,40 MB di 1440 px.
