<?php
// GET api/order-status.php?kode=KS-YYYYMMDD-XXXXXXXX — status pesanan untuk halaman pesanan.html.
// Hanya data yang aman ditampilkan: tanpa nomor HP, alamat, atau email.
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

ks_require_method('GET');

$code = $_GET['kode'] ?? '';
if (!ks_valid_order_code($code)) ks_fail('Kode pesanan tidak valid.', 400);

function ks_load_order(string $code) {
    $stmt = ks_db()->prepare(
        'SELECT id, order_code, recipient_name, delivery_date, delivery_slot, subtotal, shipping_fee, shipping_zone, total,
                payment_status, payment_type, snap_token, order_status, created_at
         FROM orders WHERE order_code = ?'
    );
    $stmt->execute([$code]);
    return $stmt->fetch();
}

try {
    $order = ks_load_order($code);
    if (!$order) ks_fail('Pesanan tidak ditemukan.', 404);

    // Cadangan bila notifikasi Midtrans belum sampai (mis. situs lokal): cek status langsung,
    // paling sering sekali per 15 detik per pesanan.
    if ($order['payment_status'] === 'pending' && ks_midtrans_ready() && $order['snap_token'] !== '') {
        $last = ks_db()->prepare(
            "SELECT COUNT(*) FROM payment_logs WHERE order_code = ? AND source = 'status-check'
               AND created_at > (NOW() - INTERVAL 15 SECOND)"
        );
        $last->execute([$code]);
        if ((int) $last->fetchColumn() === 0) {
            [$http, $status] = ks_midtrans_request('GET', ks_midtrans_base()['core_api'] . '/' . rawurlencode($code) . '/status', null, 8);
            ks_payment_log($code, 'status-check', is_array($status) ? $status : ['http' => $http]);
            if ($http === 200 && is_array($status) && (string) ($status['status_code'] ?? '') !== '404') {
                ks_apply_midtrans_status($order, $status);
                $order = ks_load_order($code);
            }
        }
    }

    $items = ks_db()->prepare('SELECT name, qty FROM order_items WHERE order_id = ? ORDER BY id');
    $items->execute([$order['id']]);
    $itemRows = array_map(static fn(array $r): array => ['name' => (string) $r['name'], 'qty' => (int) $r['qty']], $items->fetchAll());
} catch (Throwable $e) {
    error_log('[ks] order-status.php: ' . $e->getMessage());
    ks_fail('Status pesanan belum bisa dimuat. Coba lagi sebentar.', 500);
}

$first = preg_split('/\s+/u', trim((string) $order['recipient_name']))[0] ?? '';

$pay = null;
if ($order['payment_status'] === 'pending') {
    if (ks_midtrans_ready() && $order['snap_token'] !== '') {
        $pay = [
            'mode'       => 'midtrans',
            'token'      => $order['snap_token'],
            'client_key' => (string) ks_config('midtrans.client_key'),
            'snap_js'    => ks_midtrans_base()['snap_js'],
        ];
    } elseif (ks_demo_payments_on()) {
        $pay = ['mode' => 'demo'];
    }
}

ks_json([
    'ok'             => true,
    'order_code'     => $order['order_code'],
    'payment_status' => $order['payment_status'],
    'payment_type'   => $order['payment_type'],
    'order_status'   => $order['order_status'],
    'subtotal'       => (int) $order['subtotal'],
    'shipping_fee'   => (int) $order['shipping_fee'],
    'shipping_zone'  => (string) $order['shipping_zone'],
    'total'          => (int) $order['total'],
    'items'          => $itemRows,
    'delivery_date'  => $order['delivery_date'],
    'delivery_slot'  => $order['delivery_slot'],
    'recipient'      => ks_substr((string) $first, 40),
    'created_at'     => $order['created_at'],
    'pay'            => $pay,
]);
