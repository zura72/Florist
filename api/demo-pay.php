<?php
// POST api/demo-pay.php — simulasi pembayaran untuk uji lokal.
// Hanya aktif bila ks_demo_payments_on(): shop.demo_payments === true, kunci Midtrans kosong,
// dan permintaan dari komputer sendiri (localhost). Selain itu 404.
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (!ks_demo_payments_on()) ks_fail('Tidak ditemukan.', 404);

ks_require_method('POST');

$in = ks_input();
$code   = $in['kode'] ?? '';
$result = $in['result'] ?? '';
$method = is_string($in['method'] ?? null) ? $in['method'] : '';
$methods = ['qris', 'gopay', 'shopeepay', 'bank_transfer', 'credit_card'];

if (!ks_valid_order_code($code)) ks_fail('Kode pesanan tidak valid.', 400);
if ($result !== 'paid' && $result !== 'failed') ks_fail('Hasil simulasi tidak dikenal.', 400);
if (!in_array($method, $methods, true)) $method = 'qris';

$pdo = ks_db();
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        "UPDATE orders SET payment_status = ?, payment_type = ? WHERE order_code = ? AND payment_status = 'pending'"
    );
    $stmt->execute([$result, 'demo-' . $method, $code]);
    $changed = $stmt->rowCount() === 1;

    $q = $pdo->prepare('SELECT id, payment_status FROM orders WHERE order_code = ?');
    $q->execute([$code]);
    $row = $q->fetch();
    if ($changed && $result === 'paid' && $row) ks_consume_stock((int) $row['id']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[ks] demo-pay.php: ' . $e->getMessage());
    ks_fail('Simulasi belum bisa diproses. Coba lagi.', 500);
}

if (!$row) ks_fail('Pesanan tidak ditemukan.', 404);
if ($changed) ks_payment_log($code, 'demo', ['result' => $result, 'method' => $method, 'ip' => ks_client_ip()]);
if (!$changed) ks_fail('Pesanan ini sudah tidak menunggu pembayaran.', 409);

ks_json(['ok' => true, 'payment_status' => $row['payment_status']]);
