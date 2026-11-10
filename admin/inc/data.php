<?php
// Kueri untuk halaman Ringkasan dan admin/api.php. Semua kueri memakai prepared statement.
declare(strict_types=1);
if (realpath((string)(get_included_files()[0] ?? '')) === __FILE__) { http_response_code(404); exit; }   // hanya untuk di-include
require_once __DIR__ . '/ui.php';   // ADM_BULAN untuk label tanggal

const ADM_RANGES = [
    'today' => 'Hari ini',
    '7d'    => '7 hari',
    '30d'   => '30 hari',
    'month' => 'Bulan ini',
];

/**
 * Periode terpilih dan periode pembanding dengan lama waktu berjalan yang sama.
 * @return array{key:string,label:string,from:DateTimeImmutable,to:DateTimeImmutable,prev_from:DateTimeImmutable,prev_to:DateTimeImmutable,unit:string,compare:string}
 */
function adm_range(string $key): array {
    if (!isset(ADM_RANGES[$key])) $key = '7d';
    $now   = new DateTimeImmutable('now');
    $today = $now->setTime(0, 0);
    switch ($key) {
        case 'today':
            $from = $today; $prevFrom = $today->modify('-1 day'); $compare = 'kemarin';
            break;
        case '30d':
            $from = $today->modify('-29 days'); $prevFrom = $from->modify('-30 days'); $compare = '30 hari sebelumnya';
            break;
        case 'month':
            $from = $today->modify('first day of this month'); $prevFrom = $from->modify('-1 month'); $compare = 'bulan lalu';
            break;
        default:
            $from = $today->modify('-6 days'); $prevFrom = $from->modify('-7 days'); $compare = '7 hari sebelumnya';
    }
    $elapsed = $now->getTimestamp() - $from->getTimestamp();
    $prevTo  = $prevFrom->modify('+' . $elapsed . ' seconds');
    if ($prevTo > $from) $prevTo = $from->modify('-1 second');
    return [
        'key' => $key, 'label' => ADM_RANGES[$key], 'compare' => $compare,
        'from' => $from, 'to' => $now, 'prev_from' => $prevFrom, 'prev_to' => $prevTo,
        'unit' => $key === 'today' ? 'hour' : 'day',
    ];
}

function adm_sqlts(DateTimeImmutable $d): string {
    return $d->format('Y-m-d H:i:s');
}

function adm_totals(DateTimeImmutable $from, DateTimeImmutable $to): array {
    $st = ks_db()->prepare(
        "SELECT COUNT(*) AS orders,
                COALESCE(SUM(payment_status = 'paid'), 0) AS paid_orders,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END), 0) AS revenue
           FROM orders WHERE created_at BETWEEN ? AND ?");
    $st->execute([adm_sqlts($from), adm_sqlts($to)]);
    $r = $st->fetch() ?: [];
    $orders = (int)($r['orders'] ?? 0); $paid = (int)($r['paid_orders'] ?? 0); $rev = (int)($r['revenue'] ?? 0);
    return ['orders' => $orders, 'paid_orders' => $paid, 'revenue' => $rev, 'aov' => $paid > 0 ? (int)round($rev / $paid) : null];
}

/** Keadaan saat ini (tidak tergantung periode). */
function adm_snapshot(): array {
    $r = ks_db()->query(
        "SELECT COALESCE(SUM(payment_status = 'pending'), 0) AS pending,
                COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN total ELSE 0 END), 0) AS pending_total,
                COALESCE(SUM(payment_status = 'paid' AND order_status = 'baru'), 0) AS to_arrange,
                COALESCE(SUM(payment_status = 'paid' AND order_status = 'baru' AND delivery_date <= CURDATE() + INTERVAL 1 DAY), 0) AS to_arrange_soon
           FROM orders")->fetch() ?: [];
    return array_map('intval', $r);
}

/** Deret pendapatan (lunas) per jam / per hari, dengan periode pembanding sejajar per indeks. */
function adm_series(array $range): array {
    $db = ks_db();
    $hour = $range['unit'] === 'hour';
    $bucket = $hour ? 'HOUR(created_at)' : 'DATE(created_at)';
    $sql = "SELECT $bucket AS b, SUM(total) AS revenue, COUNT(*) AS orders
              FROM orders WHERE payment_status = 'paid' AND created_at BETWEEN ? AND ?
             GROUP BY $bucket";

    $fetch = function (DateTimeImmutable $from, DateTimeImmutable $to) use ($db, $sql): array {
        $st = $db->prepare($sql);
        $st->execute([adm_sqlts($from), adm_sqlts($to)]);
        $map = [];
        foreach ($st as $r) $map[(string)$r['b']] = ['revenue' => (int)$r['revenue'], 'orders' => (int)$r['orders']];
        return $map;
    };
    $cur  = $fetch($range['from'], $range['to']);
    // Garis pembanding memakai periode sebelumnya utuh agar bentuknya terbaca.
    $prevEnd = $hour ? $range['prev_from']->setTime(23, 59, 59) : $range['from']->modify('-1 second');
    $prev = $fetch($range['prev_from'], $prevEnd);

    $points = [];
    if ($hour) {
        $last = (int)$range['to']->format('G');
        for ($h = 0; $h <= max($last, 0); $h++) {
            $k = (string)$h;
            $points[] = [
                'key' => sprintf('%02d:00', $h), 'label' => sprintf('%02d.00', $h),
                'revenue' => $cur[$k]['revenue'] ?? 0, 'orders' => $cur[$k]['orders'] ?? 0,
                'prev' => $prev[$k]['revenue'] ?? 0,
            ];
        }
    } else {
        $d = $range['from']; $p = $range['prev_from']; $end = $range['to']->format('Y-m-d');
        while ($d->format('Y-m-d') <= $end) {
            $k = $d->format('Y-m-d'); $pk = $p->format('Y-m-d');
            $points[] = [
                'key' => $k, 'label' => (int)$d->format('j') . ' ' . ADM_BULAN[(int)$d->format('n')],
                'revenue' => $cur[$k]['revenue'] ?? 0, 'orders' => $cur[$k]['orders'] ?? 0,
                'prev' => ($p <= $prevEnd) ? ($prev[$pk]['revenue'] ?? 0) : null,
                'prev_label' => (int)$p->format('j') . ' ' . ADM_BULAN[(int)$p->format('n')],
            ];
            $d = $d->modify('+1 day'); $p = $p->modify('+1 day');
        }
    }
    return $points;
}

function adm_top_products(array $range, int $limit = 5): array {
    $st = ks_db()->prepare(
        "SELECT oi.product_id, MAX(oi.name) AS name, SUM(oi.qty) AS qty, SUM(oi.qty * oi.price) AS revenue
           FROM order_items oi JOIN orders o ON o.id = oi.order_id
          WHERE o.payment_status = 'paid' AND o.created_at BETWEEN ? AND ?
          GROUP BY oi.product_id
          ORDER BY qty DESC, revenue DESC
          LIMIT " . max(1, min(20, $limit)));
    $st->execute([adm_sqlts($range['from']), adm_sqlts($range['to'])]);
    return $st->fetchAll();
}

const ADM_ZONE_NONE = 'Tanpa daerah (data lama)';

/**
 * Ongkir per daerah antar untuk pesanan lunas di periode terpilih.
 * Pendapatan = total pesanan (termasuk ongkir), sama dengan definisi KPI Pendapatan.
 * @return array{rows:list<array{zone:string,label:string,orders:int,fees:int,revenue:int}>,fees:int,orders:int,revenue:int}
 */
function adm_zone_report(array $range): array {
    $st = ks_db()->prepare(
        "SELECT shipping_zone AS zone, COUNT(*) AS orders,
                COALESCE(SUM(shipping_fee), 0) AS fees, COALESCE(SUM(total), 0) AS revenue
           FROM orders
          WHERE payment_status = 'paid' AND created_at BETWEEN ? AND ?
          GROUP BY shipping_zone
          ORDER BY fees DESC, orders DESC, shipping_zone");
    $st->execute([adm_sqlts($range['from']), adm_sqlts($range['to'])]);
    $rows = []; $fees = 0; $orders = 0; $revenue = 0;
    foreach ($st as $r) {
        $zone = (string)$r['zone'];
        $row = [
            'zone' => $zone, 'label' => $zone !== '' ? $zone : ADM_ZONE_NONE,
            'orders' => (int)$r['orders'], 'fees' => (int)$r['fees'], 'revenue' => (int)$r['revenue'],
        ];
        $fees += $row['fees']; $orders += $row['orders']; $revenue += $row['revenue'];
        $rows[] = $row;
    }
    return ['rows' => $rows, 'fees' => $fees, 'orders' => $orders, 'revenue' => $revenue];
}

/**
 * Pilihan saringan daerah: label daerah yang berlaku sekarang (urutan pengaturan), lalu label lama
 * yang masih tersimpan di pesanan. Kunci '-' = pesanan tanpa daerah.
 * @return array<string,string> nilai => teks
 */
function adm_zone_options(): array {
    $opts = [];
    if (function_exists('ks_shipping_zones')) {
        foreach (ks_shipping_zones() as $z) {
            $label = is_array($z) ? trim((string)($z['label'] ?? '')) : '';
            if ($label !== '') $opts[$label] = $label;
        }
    }
    $hasNone = false; $old = [];
    foreach (ks_db()->query("SELECT DISTINCT shipping_zone FROM orders ORDER BY shipping_zone") as $r) {
        $label = (string)$r['shipping_zone'];
        if ($label === '') { $hasNone = true; continue; }
        if (!isset($opts[$label])) $old[$label] = $label . ' (lama)';
    }
    $opts += $old;
    if ($hasNone) $opts['-'] = ADM_ZONE_NONE;
    return $opts;
}

function adm_status_breakdown(array $range): array {
    $db = ks_db();
    $args = [adm_sqlts($range['from']), adm_sqlts($range['to'])];
    $st = $db->prepare("SELECT order_status AS s, COUNT(*) AS n FROM orders WHERE payment_status = 'paid' AND created_at BETWEEN ? AND ? GROUP BY order_status");
    $st->execute($args);
    $order = [];
    foreach ($st as $r) $order[$r['s']] = (int)$r['n'];
    $st = $db->prepare("SELECT payment_status AS s, COUNT(*) AS n FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY payment_status");
    $st->execute($args);
    $pay = [];
    foreach ($st as $r) $pay[$r['s']] = (int)$r['n'];
    return ['order' => $order, 'pay' => $pay];
}

function adm_schedule(): array {
    $st = ks_db()->query(
        "SELECT id, order_code, recipient_name, delivery_date, delivery_slot, order_status, payment_status
           FROM orders
          WHERE delivery_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 1 DAY
            AND order_status <> 'batal' AND payment_status IN ('paid', 'pending')
          ORDER BY delivery_date, delivery_slot, id");
    $out = [];
    foreach ($st as $r) {
        $slot = $r['delivery_slot'] !== '' ? $r['delivery_slot'] : 'Tanpa jam';
        $out[$r['delivery_date']][$slot][] = $r;
    }
    return $out;
}

function adm_latest_orders(int $limit = 8): array {
    return ks_db()->query(
        "SELECT id, order_code, customer_name, total, payment_status, order_status, created_at, delivery_date
           FROM orders ORDER BY created_at DESC, id DESC LIMIT " . max(1, min(50, $limit)))->fetchAll();
}

/** Perubahan persen; null bila pembanding terlalu kecil untuk dibandingkan secara jujur. */
function adm_delta(?int $cur, ?int $prev, int $minBase = 1): ?float {
    if ($cur === null || $prev === null || $prev < $minBase) return null;
    return ($cur - $prev) / $prev * 100;
}
