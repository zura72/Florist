<?php
// Bootstrap bersama untuk api/ dan admin/. Tanpa Composer, cocok untuk shared hosting.
declare(strict_types=1);

// Berkas ini hanya untuk di-include; bila dibuka langsung lewat HTTP, jawab 404 tanpa isi.
if (realpath((string) (get_included_files()[0] ?? '')) === __FILE__) {
    http_response_code(404);
    exit;
}

// Jangan pernah tampilkan pesan error PHP (path, kueri) ke pengunjung; cukup ke error_log.
ini_set('display_errors', '0');
set_exception_handler(function (Throwable $e): void {
    error_log('[ks] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode(['ok' => false, 'error' => 'Maaf, ada kendala di server. Coba lagi sebentar.']);
    exit;
});

if (!is_file(__DIR__ . '/config.php')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'config.php belum dibuat. Salin api/config.example.php menjadi api/config.php.']);
    exit;
}
$GLOBALS['KS_CONFIG'] = require __DIR__ . '/config.php';
date_default_timezone_set(ks_config('shop.timezone', 'Asia/Jakarta'));

function ks_config(string $path, $default = null) {
    $node = $GLOBALS['KS_CONFIG'];
    foreach (explode('.', $path) as $key) {
        if (!is_array($node) || !array_key_exists($key, $node)) return $default;
        $node = $node[$key];
    }
    return $node;
}

function ks_db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = ks_config('db');
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], (int)($c['port'] ?? 3306), $c['name'], $c['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET time_zone = '+07:00'");
    return $pdo;
}

function ks_json($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ks_fail(string $message, int $status = 400): void {
    ks_json(['ok' => false, 'error' => $message], $status);
}

function ks_input(): array {
    // Batasi 64 KB: Content-Length bisa tidak ada (chunked); badan yang terpotong jadi JSON tidak valid -> [].
    $raw = file_get_contents('php://input', false, null, 0, 65536) ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function ks_require_method(string $method): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) ks_fail('Metode tidak diizinkan.', 405);
}

function ks_midtrans_base(): array {
    $prod = (bool) ks_config('midtrans.is_production', false);
    return [
        'snap_api' => $prod ? 'https://app.midtrans.com/snap/v1/transactions' : 'https://app.sandbox.midtrans.com/snap/v1/transactions',
        'snap_js'  => $prod ? 'https://app.midtrans.com/snap/snap.js'          : 'https://app.sandbox.midtrans.com/snap/snap.js',
        'core_api' => $prod ? 'https://api.midtrans.com/v2'                    : 'https://api.sandbox.midtrans.com/v2',
    ];
}

function ks_midtrans_ready(): bool {
    return ks_config('midtrans.server_key', '') !== '' && ks_config('midtrans.client_key', '') !== '';
}

function ks_client_ip(): string {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/**
 * Mode uji (simulasi bayar) hanya boleh bila: shop.demo_payments === true, kunci Midtrans kosong,
 * DAN permintaan datang dari komputer sendiri (localhost / *.test / *.localhost tanpa proxy).
 * Jadi walau config.php lokal ikut terunggah ke hosting, orang lain tidak bisa "membayar" pesanan secara palsu.
 */
function ks_demo_payments_on(): bool {
    if (ks_config('shop.demo_payments') !== true || ks_midtrans_ready()) return false;
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (!in_array($ip, ['127.0.0.1', '::1'], true)) return false;
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) || !empty($_SERVER['HTTP_FORWARDED'])) return false;
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d{1,5}$/', '', $host);
    return in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)
        || (bool) preg_match('/^[a-z0-9.\-]+\.(?:test|localhost)$/', (string) $host);
}

// ---------------------------------------------------------------------------
// Tambahan untuk checkout & pembayaran (checkout.php, notify.php, order-status.php, demo-pay.php).
// ---------------------------------------------------------------------------

/** Panjang string UTF-8. */
function ks_strlen(string $s): int {
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : (int) preg_match_all('/./us', $s);
}

/** Potong string UTF-8 tanpa merusak karakter. */
function ks_substr(string $s, int $len): string {
    if (function_exists('mb_substr')) return mb_substr($s, 0, $len, 'UTF-8');
    preg_match('/^.{0,' . max(0, $len) . '}/us', $s, $m);
    return $m[0] ?? '';
}

/** Rapikan teks masukan: buang karakter kontrol (baris baru boleh bila $multiline) dan spasi berlebih. */
function ks_clean_text($value, bool $multiline = false): string {
    if (!is_string($value) && !is_int($value)) return '';
    $s = (string) $value;
    if (!preg_match('//u', $s)) return '';                        // bukan UTF-8 valid
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    if ($multiline) {
        $s = preg_replace('/[^\P{C}\n]+/u', '', $s);
        $s = preg_replace('/[ \t]+/u', ' ', (string) $s);
        $s = preg_replace('/ *\n */u', "\n", (string) $s);
        $s = preg_replace('/\n{3,}/u', "\n\n", (string) $s);
    } else {
        $s = preg_replace('/\p{C}+/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', (string) $s);
    }
    return trim((string) $s);
}

/** Nomor HP Indonesia -> format 62xxxxxxxxxx, atau '' bila tidak valid. */
function ks_normalize_phone($value): string {
    if (!is_string($value)) return '';
    $digits = preg_replace('/[\s\-().]/', '', $value);
    if (!preg_match('/^(?:\+?62|0)(8\d{7,12})$/', (string) $digits, $m)) return '';
    return '62' . $m[1];
}

/** Slot antar yang diizinkan: kode => [label, jam mulai]. */
function ks_delivery_slots(): array {
    return [
        'pagi'  => ['Pagi 09.00–12.00', 9],
        'siang' => ['Siang 12.00–15.00', 12],
        'sore'  => ['Sore 15.00–18.00', 15],
    ];
}

function ks_now_wib(): DateTimeImmutable {
    return new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
}

/** Tanggal antar paling awal (Y-m-d): hari ini, atau besok bila sudah lewat 13.00 WIB. */
function ks_min_delivery_date(?DateTimeImmutable $now = null): string {
    $now = $now ?: ks_now_wib();
    return ((int) $now->format('G') >= 13) ? $now->modify('+1 day')->format('Y-m-d') : $now->format('Y-m-d');
}

/** Slot hari ini hanya boleh bila mulainya minimal 2 jam lagi (Sore 15.00 = batas 13.00). */
function ks_slot_available(string $date, string $slot, ?DateTimeImmutable $now = null): bool {
    $slots = ks_delivery_slots();
    if (!isset($slots[$slot])) return false;
    $now = $now ?: ks_now_wib();
    if ($date !== $now->format('Y-m-d')) return true;
    $minutesNow = (int) $now->format('G') * 60 + (int) $now->format('i');
    return $minutesNow < ($slots[$slot][1] - 2) * 60;
}

/** Format kode pesanan: KS-YYYYMMDD-XXXXXXXX (hex huruf besar). */
function ks_valid_order_code($code): bool {
    return is_string($code) && (bool) preg_match('/^KS-\d{8}-[0-9A-F]{8}$/', $code);
}

/** Panggilan HTTP JSON ke Midtrans memakai server key (Basic auth). Mengembalikan [status HTTP, body array|null]. */
function ks_midtrans_request(string $method, string $url, ?array $body = null, int $timeout = 15): array {
    if (!function_exists('curl_init')) {
        error_log('[ks] ekstensi cURL tidak tersedia');
        return [0, null];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode((string) ks_config('midtrans.server_key', '') . ':'),
        ],
        CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($raw === false) {
        error_log('[ks] Midtrans cURL error: ' . curl_error($ch));
        curl_close($ch);
        return [0, null];
    }
    curl_close($ch);
    $data = json_decode((string) $raw, true);
    return [$status, is_array($data) ? $data : null];
}

/**
 * URL absolut ke berkas di root situs (mis. pesanan.html). Memakai shop.base_url bila diisi;
 * kalau kosong ditebak dari permintaan saat ini (folder induk api/).
 */
function ks_site_url(string $path): string {
    $base = rtrim((string) ks_config('shop.base_url', ''), '/');
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        if (!preg_match('/^[a-z0-9.\-]+(?::\d{1,5})?$/i', $host)) $host = 'localhost';
        $dir = str_replace('\\', '/', dirname(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/api/x.php'))));
        $dir = rtrim($dir === '.' ? '' : $dir, '/');
        $base = ($https ? 'https' : 'http') . '://' . $host . $dir;
    }
    return $base . '/' . ltrim($path, '/');
}

/** Catat payload pembayaran mentah ke payment_logs. */
function ks_payment_log(string $orderCode, string $source, $payload): void {
    try {
        $text = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ks_db()->prepare('INSERT INTO payment_logs (order_code, source, payload) VALUES (?, ?, ?)')
            ->execute([substr($orderCode, 0, 40), substr($source, 0, 20), substr((string) $text, 0, 60000)]);
    } catch (Throwable $e) {
        error_log('[ks] gagal mencatat payment_logs: ' . $e->getMessage());
    }
}

/** Petakan transaction_status Midtrans ke payment_status toko. '' = abaikan. */
function ks_map_midtrans_status(string $transactionStatus, string $fraudStatus = ''): string {
    switch ($transactionStatus) {
        case 'capture':        return ($fraudStatus === '' || $fraudStatus === 'accept') ? 'paid' : 'pending';
        case 'settlement':     return 'paid';
        case 'pending':        return 'pending';
        case 'deny':
        case 'cancel':
        case 'failure':        return 'failed';
        case 'expire':         return 'expired';
        case 'refund':
        case 'partial_refund': return 'refunded';
        default:               return '';
    }
}

/** Perpindahan status yang diizinkan (idempoten; paid tidak pernah turun ke pending/failed). */
function ks_payment_transition_ok(string $from, string $to): bool {
    if ($from === $to) return false;
    $allowed = [
        'pending'  => ['paid', 'failed', 'expired'],
        'failed'   => ['paid'],          // uang tetap masuk walau sebelumnya ditandai gagal
        'expired'  => ['paid'],
        'paid'     => ['refunded'],
        'refunded' => [],
    ];
    return in_array($to, $allowed[$from] ?? [], true);
}

/**
 * Terapkan hasil cek status resmi Core API Midtrans (GET {core_api}/{order_id}/status) ke pesanan.
 * $order butuh kolom id, order_code, total, payment_status. Mengembalikan payment_status akhir.
 */
function ks_apply_midtrans_status(array $order, array $status): string {
    $current = (string) $order['payment_status'];
    if ((string) ($status['order_id'] ?? '') !== (string) $order['order_code']) {
        error_log('[ks] order_id status Midtrans tidak cocok untuk ' . $order['order_code']);
        return $current;
    }
    $gross = (string) ($status['gross_amount'] ?? '');
    if (!preg_match('/^\d+(?:\.0{1,2})?$/', $gross) || (int) $gross !== (int) $order['total']) {
        error_log('[ks] gross_amount tidak cocok untuk ' . $order['order_code'] . ': ' . $gross . ' vs ' . $order['total']);
        return $current;
    }
    $next = ks_map_midtrans_status((string) ($status['transaction_status'] ?? ''), (string) ($status['fraud_status'] ?? ''));
    $paymentType = substr((string) preg_replace('/[^a-z0-9_\-]/i', '', (string) ($status['payment_type'] ?? '')), 0, 40);
    if ($next === '' || !ks_payment_transition_ok($current, $next)) {
        if ($paymentType !== '' && $current === 'pending') {
            ks_db()->prepare("UPDATE orders SET payment_type = ? WHERE id = ? AND payment_type = ''")
                ->execute([$paymentType, $order['id']]);
        }
        return $current;
    }
    $pdo = ks_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE orders SET payment_status = ?, payment_type = IF(? = '', payment_type, ?) WHERE id = ? AND payment_status = ?");
        $stmt->execute([$next, $paymentType, $paymentType, $order['id'], $current]);
        $changed = $stmt->rowCount() === 1;
        if ($changed && $next === 'paid') ks_consume_stock((int) $order['id']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return $changed ? $next : $current;
}

/**
 * Kurangi stok produk sesuai isi pesanan. Dipanggil tepat sekali, di dalam transaksi yang sama
 * dengan perubahan payment_status ke 'paid' (paid hanya bisa dicapai satu kali per pesanan).
 * Stok tidak pernah di bawah 0; pesanan yang sudah dibayar tetap sah walau stok terlanjur habis.
 */
function ks_consume_stock(int $orderId): void {
    ks_db()->prepare(
        'UPDATE products p
           JOIN (SELECT product_id, SUM(qty) AS q FROM order_items WHERE order_id = ? GROUP BY product_id) oi
             ON oi.product_id = p.id
            SET p.stock = GREATEST(p.stock - oi.q, 0)'
    )->execute([$orderId]);
}

/** Daftar daerah antar bawaan; dipakai bila tabel shipping_zones belum ada/kosong atau gagal dibaca. */
function ks_shipping_zone_defaults(): array {
    return [
        'palmerah'  => ['label' => 'Palmerah dan Slipi',                'fee' => 15000],
        'jakbar'    => ['label' => 'Jakarta Barat dan Pusat',           'fee' => 30000],
        'jakarta'   => ['label' => 'Jakarta Selatan, Timur, dan Utara', 'fee' => 45000],
        'bodetabek' => ['label' => 'Depok, Tangerang, dan Bekasi',      'fee' => null],
    ];
}

/** Kode daerah yang sah: huruf kecil, angka, tanda hubung (tidak di awal/akhir), maks. 30 karakter. */
function ks_valid_zone_code($code): bool {
    return is_string($code) && strlen($code) <= 30 && (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $code);
}

/** Ubah nama daerah jadi kode, mis. "Jakarta Selatan, Timur" -> "jakarta-selatan-timur". */
function ks_zone_slug(string $label): string {
    $s = strtolower($label);
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
        if (is_string($t) && $t !== '') $s = strtolower($t);
    }
    $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    $s = rtrim(substr($s, 0, 30), '-');
    return $s;
}

/**
 * Semua baris tabel shipping_zones (termasuk yang nonaktif), urut sort_order.
 * Mengembalikan null bila tabel belum ada atau gagal dibaca (galat dicatat sekali per permintaan).
 */
function ks_shipping_zone_rows(): ?array {
    static $logged = false;
    try {
        $rows = ks_db()->query(
            'SELECT code, label, fee, sort_order, is_active, updated_at FROM shipping_zones ORDER BY sort_order, label'
        )->fetchAll();
    } catch (Throwable $e) {
        if (!$logged) {
            error_log('[ks] shipping_zones tidak bisa dibaca, memakai daftar bawaan: ' . $e->getMessage());
            $logged = true;
        }
        return null;
    }
    return $rows;
}

/**
 * Daerah antar aktif dan ongkirnya: kode => ['label' => ..., 'fee' => int|null].
 * fee null = tidak bisa dibayar online (tanya dulu lewat WhatsApp).
 * Sumber: (1) config 'shop' => ['shipping_zones' => [...]] bila diisi (menimpa dasbor),
 * (2) tabel shipping_zones (diatur di Dasbor > Ongkir), (3) daftar bawaan bila tabel belum ada atau kosong.
 */
function ks_shipping_zones(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $zones = ks_config('shop.shipping_zones');
    if (is_array($zones) && $zones) return $cache = $zones;

    $rows = ks_shipping_zone_rows();
    if (!$rows) return $cache = ks_shipping_zone_defaults();   // tabel belum ada, gagal dibaca, atau masih kosong

    $out = [];
    foreach ($rows as $r) {
        if ((int) $r['is_active'] !== 1) continue;
        $out[(string) $r['code']] = [
            'label' => (string) $r['label'],
            'fee'   => $r['fee'] === null ? null : (int) $r['fee'],
        ];
    }
    return $cache = $out;
}
