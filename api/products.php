<?php
// GET api/products.php — daftar produk aktif dengan harga resmi dari database.
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

ks_require_method('GET');

try {
    $rows = ks_db()->query(
        'SELECT id, name, category, price, image, stock FROM products WHERE is_active = 1 ORDER BY name'
    )->fetchAll();
} catch (Throwable $e) {
    error_log('[ks] products.php: ' . $e->getMessage());
    ks_fail('Daftar bunga sedang tidak bisa dimuat. Coba lagi sebentar, ya.', 500);
}

$products = array_map(static function (array $r): array {
    return [
        'id'       => (string) $r['id'],
        'name'     => (string) $r['name'],
        'category' => (string) $r['category'],
        'price'    => (int) $r['price'],
        'image'    => (string) $r['image'],
        'stock'    => max(0, (int) $r['stock']),
    ];
}, $rows);

// Daerah antar aktif (Dasbor > Ongkir), urut seperti di dasbor. fee null = "Tanya dulu".
$zones = [];
foreach (ks_shipping_zones() as $code => $z) {
    $zones[] = ['code' => (string) $code, 'label' => (string) $z['label'], 'fee' => $z['fee'] === null ? null : (int) $z['fee']];
}

$slots = [];
foreach (ks_delivery_slots() as $code => $s) $slots[] = ['code' => $code, 'label' => $s[0], 'start' => $s[1]];

ks_json([
    'ok'           => true,
    'products'     => $products,
    'shipping_zones' => $zones,
    'min_date'     => ks_min_delivery_date(),
    'slots'        => $slots,
    'payment_mode' => ks_midtrans_ready() ? 'midtrans' : (ks_demo_payments_on() ? 'demo' : 'off'),
]);
