<?php
// JSON untuk grafik Ringkasan. Hanya GET, wajib login.
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/data.php';

adm_boot();
header('Content-Security-Policy: default-src \'none\'; frame-ancestors \'none\'');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    ks_fail('Metode tidak diizinkan.', 405);
}
if (!adm_user()) ks_fail('Sesi berakhir. Silakan masuk lagi.', 401);
session_write_close();

try {
    $range  = adm_range((string)($_GET['range'] ?? '7d'));
    $points = adm_series($range);
    $cur    = adm_totals($range['from'], $range['to']);
    $prev   = adm_totals($range['prev_from'], $range['prev_to']);
    $zones  = adm_zone_report($range);
} catch (Throwable $e) {
    error_log('[ks-admin api] ' . $e->getMessage());
    ks_fail('Data belum bisa dimuat.', 500);
}

ks_json([
    'ok'    => true,
    'range' => [
        'key' => $range['key'], 'label' => $range['label'], 'compare' => $range['compare'], 'unit' => $range['unit'],
        'from' => $range['from']->format(DATE_ATOM), 'to' => $range['to']->format(DATE_ATOM),
        'prev_from' => $range['prev_from']->format(DATE_ATOM), 'prev_to' => $range['prev_to']->format(DATE_ATOM),
    ],
    'totals'  => ['current' => $cur, 'previous' => $prev],
    'points'  => $points,
    // Ongkir dari pesanan lunas di periode ini, total dan per daerah antar.
    'shipping' => [
        'total'  => $zones['fees'],
        'orders' => $zones['orders'],
        'zones'  => array_map(fn($z) => ['label' => $z['label'], 'orders' => $z['orders'], 'fees' => $z['fees'], 'revenue' => $z['revenue']], $zones['rows']),
    ],
]);
