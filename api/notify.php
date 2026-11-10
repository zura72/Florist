<?php
// POST api/notify.php — Payment Notification URL (HTTP notification) dari Midtrans.
// Alur: cek signature_key -> konfirmasi ulang ke Core API (GET /status) -> perbarui pesanan secara idempoten.
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

ks_require_method('POST');

if (!ks_midtrans_ready()) ks_fail('Tidak ditemukan.', 404);

$raw = (string) file_get_contents('php://input', false, null, 0, 65536);
$n = json_decode($raw, true);
if (!is_array($n)) ks_fail('Payload tidak valid.', 400);

$orderCode   = is_string($n['order_id'] ?? null) ? $n['order_id'] : '';
$statusCode  = is_string($n['status_code'] ?? null) ? $n['status_code'] : '';
$grossAmount = is_string($n['gross_amount'] ?? null) ? $n['gross_amount'] : '';
$signature   = is_string($n['signature_key'] ?? null) ? $n['signature_key'] : '';

$expected = hash('sha512', $orderCode . $statusCode . $grossAmount . (string) ks_config('midtrans.server_key'));
if ($signature === '' || !hash_equals($expected, strtolower($signature))) {
    error_log('[ks] notify.php signature tidak valid dari ' . ks_client_ip());
    ks_fail('Signature tidak valid.', 403);
}

ks_payment_log($orderCode, 'notification', $raw);

if (!ks_valid_order_code($orderCode)) {
    // Signature benar tetapi bukan pesanan situs ini (mis. tes dari dashboard). Balas 200 agar tidak diulang.
    ks_json(['ok' => true, 'ignored' => true]);
}

try {
    $stmt = ks_db()->prepare('SELECT id, order_code, total, payment_status FROM orders WHERE order_code = ?');
    $stmt->execute([$orderCode]);
    $order = $stmt->fetch();
} catch (Throwable $e) {
    error_log('[ks] notify.php baca pesanan: ' . $e->getMessage());
    ks_fail('Kesalahan server.', 500);   // Midtrans akan mengulang
}
if (!$order) ks_json(['ok' => true, 'ignored' => true]);

// Jangan percaya isi notifikasi begitu saja: tanya status resmi ke Midtrans.
[$http, $status] = ks_midtrans_request('GET', ks_midtrans_base()['core_api'] . '/' . rawurlencode($orderCode) . '/status', null, 10);
if ($http !== 200 || !is_array($status)) {
    error_log('[ks] notify.php cek status gagal untuk ' . $orderCode . ' (HTTP ' . $http . ')');
    ks_fail('Status belum bisa dikonfirmasi.', 502);   // Midtrans akan mengulang
}
ks_payment_log($orderCode, 'status-check', $status);

try {
    $final = ks_apply_midtrans_status($order, $status);
} catch (Throwable $e) {
    error_log('[ks] notify.php perbarui pesanan: ' . $e->getMessage());
    ks_fail('Kesalahan server.', 500);
}

ks_json(['ok' => true, 'payment_status' => $final]);
