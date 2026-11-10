<?php
// POST api/checkout.php — buat pesanan dan (bila siap) transaksi Midtrans Snap.
// Semua harga dihitung ulang dari database; harga dari browser tidak pernah dipakai.
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

ks_require_method('POST');

if (!ks_midtrans_ready() && !ks_demo_payments_on()) {
    ks_fail('Pembayaran online belum aktif, silakan pesan via WhatsApp.', 503);
}

$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if (strpos($contentType, 'application/json') !== 0) ks_fail('Format permintaan tidak dikenali.', 415);
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) ks_fail('Permintaan terlalu besar.', 413);

$in = ks_input();
$errors = [];

// --- Data pemesan & penerima -------------------------------------------------
$customerName  = ks_clean_text($in['customer_name'] ?? '');
$customerPhone = ks_normalize_phone($in['customer_phone'] ?? '');
$customerEmail = ks_clean_text($in['customer_email'] ?? '');
$recipientName = ks_clean_text($in['recipient_name'] ?? '');
$recipientPhone = ks_normalize_phone($in['recipient_phone'] ?? '');
$address       = ks_clean_text($in['address'] ?? '', true);
$deliveryDate  = is_string($in['delivery_date'] ?? null) ? $in['delivery_date'] : '';
$slot          = is_string($in['delivery_slot'] ?? null) ? $in['delivery_slot'] : '';
$cardMessage   = ks_clean_text($in['card_message'] ?? '', true);
$zoneCode      = is_string($in['shipping_zone'] ?? null) ? $in['shipping_zone'] : '';

$nameLen = ks_strlen($customerName);
if ($nameLen < 2 || $nameLen > 120) $errors['customer_name'] = 'Isi nama pemesan (2–120 huruf).';
if ($customerPhone === '') $errors['customer_phone'] = 'Nomor WhatsApp belum benar. Contoh: 0812 3456 7890.';
if ($customerEmail !== '' && (ks_strlen($customerEmail) > 160 || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL))) {
    $errors['customer_email'] = 'Alamat email belum benar.';
}
$recLen = ks_strlen($recipientName);
if ($recLen < 2 || $recLen > 120) $errors['recipient_name'] = 'Isi nama penerima (2–120 huruf).';
if ($recipientPhone === '') $errors['recipient_phone'] = 'Nomor penerima belum benar. Contoh: 0812 3456 7890.';
$addrLen = ks_strlen($address);
if ($addrLen < 15 || $addrLen > 500) $errors['address'] = 'Tulis alamat lengkap (jalan, nomor, kelurahan, kecamatan), 15–500 huruf.';
$zones = ks_shipping_zones();
if (!isset($zones[$zoneCode])) {
    $errors['shipping_zone'] = 'Pilih daerah antar.';
} elseif ($zones[$zoneCode]['fee'] === null) {
    $errors['shipping_zone'] = 'Untuk ' . $zones[$zoneCode]['label'] . ', ongkirnya kami hitung dulu. Pesan lewat WhatsApp, ya.';
}
if (ks_strlen($cardMessage) > 160) $errors['card_message'] = 'Tulisan kartu maksimal 160 huruf.';

// --- Tanggal & slot antar ----------------------------------------------------
$minDate = ks_min_delivery_date();
$dateObj = DateTimeImmutable::createFromFormat('!Y-m-d', $deliveryDate, new DateTimeZone('Asia/Jakarta'));
if (!$dateObj || $dateObj->format('Y-m-d') !== $deliveryDate) {
    $errors['delivery_date'] = 'Pilih tanggal antar.';
} elseif ($deliveryDate < $minDate) {
    $errors['delivery_date'] = 'Tanggal ini sudah lewat batas pesan (13.00 WIB). Pilih tanggal ' . $minDate . ' atau sesudahnya.';
} elseif ($deliveryDate > ks_now_wib()->modify('+60 days')->format('Y-m-d')) {
    $errors['delivery_date'] = 'Tanggal antar paling lambat 60 hari dari sekarang.';
}
$slots = ks_delivery_slots();
if (!isset($slots[$slot])) {
    $errors['delivery_slot'] = 'Pilih waktu antar.';
} elseif (!isset($errors['delivery_date']) && !ks_slot_available($deliveryDate, $slot)) {
    $errors['delivery_slot'] = 'Waktu ini sudah terlalu dekat untuk hari ini. Pilih waktu yang lebih sore atau tanggal lain.';
}

// --- Barang ------------------------------------------------------------------
$rawItems = $in['items'] ?? null;
$wanted = [];
if (!is_array($rawItems) || count($rawItems) < 1 || count($rawItems) > 20) {
    $errors['items'] = 'Keranjang harus berisi 1–20 jenis bunga.';
} else {
    foreach ($rawItems as $it) {
        $id  = is_array($it) && is_string($it['id'] ?? null) ? $it['id'] : '';
        $qty = is_array($it) && is_int($it['qty'] ?? null) ? $it['qty'] : 0;
        if (!preg_match('/^[a-z0-9\-]{1,40}$/', $id) || $qty < 1 || $qty > 50) {
            $errors['items'] = 'Ada isi keranjang yang tidak valid. Jumlah tiap bunga 1–50.';
            break;
        }
        $wanted[$id] = ($wanted[$id] ?? 0) + $qty;
        if ($wanted[$id] > 50) { $errors['items'] = 'Jumlah tiap bunga maksimal 50.'; break; }
    }
}

if ($errors) {
    ks_json(['ok' => false, 'error' => 'Ada data yang perlu dibetulkan.', 'fields' => $errors], 422);
}

// --- Simpan pesanan (transaksi) ---------------------------------------------
$pdo = ks_db();
$shipping = max(0, (int) $zones[$zoneCode]['fee']);
$zoneLabel = (string) $zones[$zoneCode]['label'];
$lines = [];
$subtotal = 0;
$orderCode = '';
$orderId = 0;

try {
    $pdo->beginTransaction();

    $ph = implode(',', array_fill(0, count($wanted), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price, stock, is_active FROM products WHERE id IN ($ph) FOR UPDATE");
    $stmt->execute(array_keys($wanted));
    $found = [];
    foreach ($stmt->fetchAll() as $p) $found[$p['id']] = $p;

    $problems = [];
    foreach ($wanted as $id => $qty) {
        $p = $found[$id] ?? null;
        if (!$p || (int) $p['is_active'] !== 1) { $problems[] = ['id' => $id, 'reason' => 'unavailable']; continue; }
        if ((int) $p['stock'] < $qty) {
            $problems[] = ['id' => $id, 'reason' => 'stock', 'name' => $p['name'], 'stock' => max(0, (int) $p['stock'])];
            continue;
        }
        $lines[] = ['id' => $id, 'name' => (string) $p['name'], 'price' => (int) $p['price'], 'qty' => $qty];
        $subtotal += (int) $p['price'] * $qty;
    }
    if ($problems) {
        $pdo->rollBack();
        ks_json([
            'ok' => false,
            'error' => 'Beberapa bunga sudah tidak tersedia atau stoknya kurang. Keranjang sudah kami sesuaikan, cek lagi ya.',
            'items' => $problems,
        ], 409);
    }

    $total = $subtotal + $shipping;
    if ($total > 999999999) {                    // di luar batas kolom INT & wajar untuk satu pesanan
        $pdo->rollBack();
        ks_json(['ok' => false, 'error' => 'Pesanan sebesar ini perlu kami bantu langsung. Chat kami via WhatsApp, ya.',
                 'fields' => ['items' => 'Total pesanan terlalu besar untuk pembayaran online.']], 422);
    }
    $insert = $pdo->prepare(
        'INSERT INTO orders (order_code, customer_name, customer_phone, customer_email, recipient_name, recipient_phone,
            address, delivery_date, delivery_slot, card_message, subtotal, shipping_fee, shipping_zone, total, payment_status, order_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', \'baru\')'
    );
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $orderCode = 'KS-' . ks_now_wib()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        try {
            $insert->execute([
                $orderCode, $customerName, $customerPhone, $customerEmail, $recipientName, $recipientPhone,
                $address, $deliveryDate, $slots[$slot][0], $cardMessage, $subtotal, $shipping, $zoneLabel, $total,
            ]);
            break;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000' || $attempt === 2) throw $e;   // 23000 = kode kembar, coba lagi
        }
    }
    $orderId = (int) $pdo->lastInsertId();

    $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, name, price, qty) VALUES (?, ?, ?, ?, ?)');
    foreach ($lines as $l) $itemStmt->execute([$orderId, $l['id'], $l['name'], $l['price'], $l['qty']]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[ks] checkout.php simpan pesanan: ' . $e->getMessage());
    ks_fail('Pesanan belum bisa disimpan. Coba lagi sebentar atau pesan via WhatsApp.', 500);
}

$total = $subtotal + $shipping;

// --- Pembayaran --------------------------------------------------------------
if (ks_midtrans_ready()) {
    $base = ks_midtrans_base();

    $itemDetails = [];
    foreach ($lines as $l) {
        $itemDetails[] = ['id' => $l['id'], 'price' => $l['price'], 'quantity' => $l['qty'], 'name' => ks_substr($l['name'], 50)];
    }
    if ($shipping > 0) $itemDetails[] = ['id' => 'ongkir', 'price' => $shipping, 'quantity' => 1, 'name' => ks_substr('Ongkos antar ' . $zoneLabel, 50)];

    $sum = 0;
    foreach ($itemDetails as $d) $sum += $d['price'] * $d['quantity'];
    if ($sum !== $total) {                       // tidak boleh terjadi; jaga-jaga
        error_log('[ks] checkout.php jumlah item_details tidak sama dengan total untuk ' . $orderCode);
        ks_fail('Pembayaran belum bisa dibuat. Silakan pesan via WhatsApp.', 500);
    }

    $customer = ['first_name' => ks_substr($customerName, 50), 'phone' => $customerPhone];
    if ($customerEmail !== '') $customer['email'] = $customerEmail;
    $customer['shipping_address'] = [
        'first_name' => ks_substr($recipientName, 50),
        'phone'      => $recipientPhone,
        'address'    => ks_substr(str_replace("\n", ', ', $address), 200),
        'country_code' => 'IDN',
    ];

    $payload = [
        'transaction_details' => ['order_id' => $orderCode, 'gross_amount' => $total],
        'item_details'        => $itemDetails,
        'customer_details'    => $customer,
        'callbacks'           => ['finish' => ks_site_url('pesanan.html?kode=' . rawurlencode($orderCode))],
    ];

    [$status, $resp] = ks_midtrans_request('POST', $base['snap_api'], $payload, 15);
    $token = is_array($resp) && is_string($resp['token'] ?? null) ? $resp['token'] : '';
    if ($status < 200 || $status >= 300 || $token === '' || !preg_match('/^[A-Za-z0-9\-]{10,80}$/', $token)) {
        error_log('[ks] Snap API gagal untuk ' . $orderCode . ' (HTTP ' . $status . '): '
            . substr((string) json_encode($resp['error_messages'] ?? $resp), 0, 500));
        // Pesanan tetap "pending" agar admin bisa menindaklanjuti.
        ks_json([
            'ok' => false,
            'error' => 'Gerbang pembayaran sedang sibuk. Pesananmu tersimpan dengan kode ' . $orderCode
                . '. Coba lagi sebentar atau lanjutkan via WhatsApp.',
            'order_code' => $orderCode,
        ], 502);
    }

    $pdo->prepare('UPDATE orders SET snap_token = ? WHERE id = ?')->execute([$token, $orderId]);

    ks_json([
        'ok'         => true,
        'mode'       => 'midtrans',
        'order_code' => $orderCode,
        'total'      => $total,
        'token'      => $token,
        'client_key' => (string) ks_config('midtrans.client_key'),
        'snap_js'    => $base['snap_js'],
    ]);
}

// Mode uji (sudah dipastikan aktif di awal berkas).
ks_json(['ok' => true, 'mode' => 'demo', 'order_code' => $orderCode, 'total' => $total]);
