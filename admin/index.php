<?php
/*
 * Ringkasan — pertanyaan layar: "Bagaimana toko hari ini, dan apa yang harus dikerjakan?"
 * Catatan desain (penyimpangan dari pola dashboard-design):
 * - Warna mengikuti merek: mawar = uang/aksi, abu = pembanding. Hijau tidak dipakai (khusus WhatsApp),
 *   jadi kenaikan ditulis ▲ dengan warna tinta, penurunan pendapatan memakai merah bahaya.
 * - "Menunggu pembayaran" dan "Perlu dirangkai" adalah keadaan saat ini, bukan per periode.
 */
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/ui.php';
require __DIR__ . '/inc/data.php';

$user  = adm_require_login();
$range = adm_range((string)($_GET['range'] ?? '7d'));
$cur   = adm_totals($range['from'], $range['to']);
$prev  = adm_totals($range['prev_from'], $range['prev_to']);
$snap  = adm_snapshot();
$top   = adm_top_products($range);
$brk   = adm_status_breakdown($range);
$sched = adm_schedule();
$latest = adm_latest_orders(8);
$series = adm_series($range);
$zones  = adm_zone_report($range);

/** Baris pembanding untuk kartu KPI. $sense: 'up' (naik = baik) atau 'neutral'. */
function kpi_delta(?int $cur, ?int $prev, string $compare, bool $money, string $sense = 'neutral'): string {
    if ($cur === null) return '<p class="kpi-delta">—</p>';
    $fmt = fn($v) => $money ? rupiah($v) : angka($v);
    if ($prev === null || $prev < ($money ? 1 : 5)) {
        $was = $prev === null ? '—' : $fmt($prev);
        return '<p class="kpi-delta"><span class="delta delta-flat">sebelumnya ' . e($was) . '</span> <span class="kpi-base">' . e($compare) . '</span></p>';
    }
    $d = ($cur - $prev) / $prev * 100;
    $abs = abs($d);
    $txt = $abs < 0.1 ? '0%' : str_replace('.', ',', (string)round($abs, $abs < 10 ? 1 : 0)) . '%';
    if ($abs < 0.1) { $cls = 'delta-flat'; $arrow = '•'; }
    elseif ($d > 0) { $cls = $sense === 'up' ? 'delta-up' : 'delta-flat'; $arrow = '▲'; }
    else            { $cls = $sense === 'up' ? 'delta-down' : 'delta-flat'; $arrow = '▼'; }
    $title = 'Periode pembanding: ' . $fmt($prev);
    return '<p class="kpi-delta" title="' . e($title) . '"><span class="delta ' . $cls . '"><span aria-hidden="true">' . $arrow . '</span> '
         . ($d < 0 ? '<span class="sr-only">turun </span>' : '<span class="sr-only">naik </span>') . e($txt) . '</span> <span class="kpi-base">vs ' . e($compare) . '</span></p>';
}

$nowTs = time();
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$unitLabel = $range['unit'] === 'hour' ? 'per jam' : 'per hari';

adm_layout_start('Ringkasan', 'index', $user);
?>
<header class="page-head">
  <div>
    <h1 class="page-title">Ringkasan</h1>
    <p class="page-meta"><?= e(tgl_hari($today)) ?> <span aria-hidden="true">·</span> <span class="live-dot" aria-hidden="true"></span>diperbarui <?= e(date('H.i', $nowTs)) ?></p>
  </div>
  <nav class="seg" aria-label="Periode">
    <?php foreach (ADM_RANGES as $k => $label): ?>
      <a href="index.php?range=<?= e($k) ?>"<?= $k === $range['key'] ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
</header>

<section class="kpis" aria-label="Angka utama">
  <article class="card kpi">
    <div class="kpi-top"><h2 class="kpi-label">Pendapatan</h2><span class="kpi-icon"><?= adm_icon('i-money') ?></span></div>
    <p class="kpi-value" data-countup="<?= (int)$cur['revenue'] ?>" data-format="rupiah"><?= e(rupiah($cur['revenue'])) ?></p>
    <?= kpi_delta($cur['revenue'], $prev['revenue'], $range['compare'], true, 'up') ?>
  </article>
  <article class="card kpi">
    <div class="kpi-top"><h2 class="kpi-label">Pesanan</h2><span class="kpi-icon"><?= adm_icon('i-orders') ?></span></div>
    <p class="kpi-value" data-countup="<?= (int)$cur['orders'] ?>"><?= e(angka($cur['orders'])) ?></p>
    <?= kpi_delta($cur['orders'], $prev['orders'], $range['compare'], false) ?>
    <p class="kpi-fact"><?= e(angka($cur['paid_orders'])) ?> lunas</p>
  </article>
  <article class="card kpi">
    <div class="kpi-top"><h2 class="kpi-label" title="Pendapatan dibagi jumlah pesanan lunas">Rata-rata pesanan</h2><span class="kpi-icon"><?= adm_icon('i-avg') ?></span></div>
    <p class="kpi-value"<?= $cur['aov'] !== null ? ' data-countup="' . (int)$cur['aov'] . '" data-format="rupiah"' : '' ?>><?= $cur['aov'] !== null ? e(rupiah($cur['aov'])) : '—' ?></p>
    <?= kpi_delta($cur['aov'], $prev['aov'], $range['compare'], true) ?>
  </article>
  <a class="card kpi kpi-link<?= $snap['pending'] > 0 ? ' kpi-warn' : '' ?>" href="orders.php?payment_status=pending">
    <div class="kpi-top"><h2 class="kpi-label">Menunggu pembayaran</h2><span class="kpi-icon"><?= adm_icon('i-clock') ?></span></div>
    <p class="kpi-value" data-countup="<?= (int)$snap['pending'] ?>"><?= e(angka($snap['pending'])) ?></p>
    <p class="kpi-delta"><span class="kpi-base">saat ini · <?= e(rupiah($snap['pending_total'])) ?></span></p>
  </a>
  <a class="card kpi kpi-link<?= $snap['to_arrange'] > 0 ? ' kpi-attn' : '' ?>" href="orders.php?payment_status=paid&amp;order_status=baru">
    <div class="kpi-top"><h2 class="kpi-label">Perlu dirangkai</h2><span class="kpi-icon"><?= adm_icon('i-flower') ?></span></div>
    <p class="kpi-value" data-countup="<?= (int)$snap['to_arrange'] ?>"><?= e(angka($snap['to_arrange'])) ?></p>
    <p class="kpi-delta"><?php if ($snap['to_arrange_soon'] > 0): ?><span class="chip chip-attn"><?= e(angka($snap['to_arrange_soon'])) ?> antar s.d. besok</span><?php else: ?><span class="kpi-base">saat ini</span><?php endif; ?></p>
  </a>
</section>

<div class="grid grid-main">
  <section class="card panel panel-chart" aria-labelledby="chart-title">
    <div class="panel-head">
      <h2 id="chart-title" class="panel-title">Pendapatan <?= e($unitLabel) ?></h2>
      <ul class="legend" aria-label="Keterangan grafik">
        <li><span class="key key-cur" aria-hidden="true"></span><?= e($range['label']) ?></li>
        <li><span class="key key-prev" aria-hidden="true"></span><?= e(ucfirst($range['compare'])) ?></li>
      </ul>
    </div>
    <div class="chart" data-chart="api.php?range=<?= e($range['key']) ?>" data-unit="<?= e($range['unit']) ?>">
      <div class="skeleton skeleton-chart" aria-hidden="true"></div>
      <p class="chart-status" role="status" aria-live="polite">Memuat grafik…</p>
    </div>
    <details class="chart-table">
      <summary>Lihat sebagai tabel</summary>
      <div class="table-scroll">
        <table class="table table-compact">
          <caption class="sr-only">Pendapatan lunas <?= e($unitLabel) ?>, <?= e($range['label']) ?></caption>
          <thead><tr><th scope="col"><?= $range['unit'] === 'hour' ? 'Jam' : 'Tanggal' ?></th><th scope="col" class="num">Pesanan lunas</th><th scope="col" class="num">Pendapatan</th><th scope="col" class="num"><?= e(ucfirst($range['compare'])) ?></th></tr></thead>
          <tbody>
          <?php foreach ($series as $p): ?>
            <tr><th scope="row"><?= e($p['label']) ?></th><td class="num"><?= e(angka($p['orders'])) ?></td><td class="num"><?= e(rupiah($p['revenue'])) ?></td><td class="num"><?= $p['prev'] === null ? '—' : e(rupiah($p['prev'])) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
  </section>

  <section class="card panel" aria-labelledby="sched-title">
    <div class="panel-head">
      <h2 id="sched-title" class="panel-title">Jadwal antar</h2>
      <?= adm_icon('i-truck', 'icon icon-muted') ?>
    </div>
    <?php foreach ([$today => 'Hari ini', $tomorrow => 'Besok'] as $day => $dayLabel):
        $slots = $sched[$day] ?? []; $count = array_sum(array_map('count', $slots)); ?>
      <div class="sched-day">
        <h3 class="sched-date"><?= e($dayLabel) ?> <span class="muted">· <?= e(tgl_hari($day)) ?></span><span class="count-pill"><?= e(angka($count)) ?></span></h3>
        <?php if (!$slots): ?>
          <p class="empty-line">Belum ada antaran.</p>
        <?php else: foreach ($slots as $slot => $rows): ?>
          <div class="slot">
            <p class="slot-time"><?= e($slot) ?></p>
            <ul class="slot-list">
              <?php foreach ($rows as $r): ?>
                <li><a href="order.php?id=<?= (int)$r['id'] ?>">
                  <?= status_dot($r['order_status']) ?>
                  <span class="slot-name"><?= e($r['recipient_name']) ?></span>
                  <?php if ($r['payment_status'] !== 'paid'): ?><span class="badge badge-warn">Belum lunas</span><?php endif; ?>
                </a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; endif; ?>
      </div>
    <?php endforeach; ?>
  </section>
</div>

<div class="grid grid-tri">
  <section class="card panel" aria-labelledby="top-title">
    <div class="panel-head"><h2 id="top-title" class="panel-title">Produk terlaris</h2><span class="panel-note"><?= e($range['label']) ?> · terjual</span></div>
    <?php if (!$top): ?>
      <p class="empty-line">Belum ada penjualan lunas di periode ini.</p>
    <?php else: $maxQty = max(array_map(fn($r) => (int)$r['qty'], $top)); ?>
      <ol class="rank">
        <?php foreach ($top as $i => $r): $pct = $maxQty > 0 ? round((int)$r['qty'] / $maxQty * 100, 1) : 0; ?>
          <li class="rank-row">
            <span class="rank-n"><?= $i + 1 ?></span>
            <span class="rank-name"><?= e($r['name']) ?></span>
            <span class="rank-val"><strong><?= e(angka($r['qty'])) ?></strong> <span class="muted">· <?= e(rupiah($r['revenue'])) ?></span></span>
            <span class="rank-bar" aria-hidden="true"><span style="width:<?= e($pct) ?>%"></span></span>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </section>

  <section class="card panel" aria-labelledby="status-title">
    <div class="panel-head"><h2 id="status-title" class="panel-title">Status pesanan</h2><span class="panel-note"><?= e($range['label']) ?></span></div>
    <?php
      $groups = [
        ['Pesanan lunas', $brk['order'], ADM_STATUS, 'order_status'],
        ['Pembayaran', $brk['pay'], ADM_PAY, 'payment_status'],
      ];
      foreach ($groups as [$gLabel, $counts, $labels, $param]):
        $total = array_sum($counts); ?>
      <div class="breakdown">
        <h3 class="breakdown-title"><?= e($gLabel) ?> <span class="muted">· <?= e(angka($total)) ?></span></h3>
        <?php if ($total === 0): ?>
          <p class="empty-line">—</p>
        <?php else: ?>
          <div class="stack" aria-hidden="true">
            <?php foreach ($labels as $k => [$lab, $tone]): if (empty($counts[$k])) continue; ?>
              <span class="stack-seg tone-<?= e($tone) ?>" style="flex-grow:<?= (int)$counts[$k] ?>"></span>
            <?php endforeach; ?>
          </div>
          <ul class="breakdown-list">
            <?php foreach ($labels as $k => [$lab, $tone]): $n = (int)($counts[$k] ?? 0); if ($n === 0) continue; ?>
              <li><a href="orders.php?<?= e($param) ?>=<?= e($k) ?>">
                <span class="status"><span class="petal petal-<?= e($tone) ?>" aria-hidden="true"></span><?= e($lab) ?></span>
                <span class="num"><?= e(angka($n)) ?> <span class="muted"><?= e(str_replace('.', ',', (string)round($n / $total * 100))) ?>%</span></span>
              </a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="card panel" aria-labelledby="zone-title">
    <div class="panel-head"><h2 id="zone-title" class="panel-title">Ongkir per daerah</h2><span class="panel-note"><?= e($range['label']) ?> · pesanan lunas</span></div>
    <?php if (!$zones['rows']): ?>
      <p class="empty-line">Belum ada pesanan lunas di periode ini.</p>
    <?php else: $maxFee = max(array_map(fn($z) => $z['fees'], $zones['rows'])); ?>
      <p class="zone-total"><span class="zone-total-k">Total ongkir</span> <strong class="zone-total-v"><?= e(rupiah($zones['fees'])) ?></strong> <span class="muted">dari <?= e(angka($zones['orders'])) ?> pesanan lunas</span></p>
      <ol class="rank rank-zone">
        <?php foreach ($zones['rows'] as $i => $z): $pct = $maxFee > 0 ? round($z['fees'] / $maxFee * 100, 1) : 0; ?>
          <li class="rank-row<?= $z['zone'] === '' ? ' is-legacy' : '' ?>">
            <span class="rank-n"><?= $i + 1 ?></span>
            <span class="rank-main">
              <span class="rank-name"><?= e($z['label']) ?></span>
              <span class="rank-sub"><?= e(angka($z['orders'])) ?> pesanan <span aria-hidden="true">·</span> pendapatan <?= e(rupiah($z['revenue'])) ?></span>
            </span>
            <span class="rank-val"><span class="sr-only">Ongkir terkumpul </span><strong><?= e(rupiah($z['fees'])) ?></strong></span>
            <span class="rank-bar" aria-hidden="true"><span style="width:<?= e($pct) ?>%"></span></span>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </section>
</div>

<section class="card panel" aria-labelledby="latest-title">
  <div class="panel-head"><h2 id="latest-title" class="panel-title">Pesanan terbaru</h2><a class="link-more" href="orders.php">Semua pesanan →</a></div>
  <?php if (!$latest): ?>
    <p class="empty-line">Belum ada pesanan.</p>
  <?php else: ?>
  <div class="table-scroll">
    <table class="table table-cards">
      <thead><tr><th scope="col">Kode</th><th scope="col">Pemesan</th><th scope="col">Dipesan</th><th scope="col">Antar</th><th scope="col">Bayar</th><th scope="col">Status</th><th scope="col" class="num">Total</th></tr></thead>
      <tbody>
      <?php foreach ($latest as $o): ?>
        <tr>
          <td data-label="Kode"><a class="code-link" href="order.php?id=<?= (int)$o['id'] ?>"><?= e($o['order_code']) ?></a></td>
          <td data-label="Pemesan"><?= e($o['customer_name']) ?></td>
          <td data-label="Dipesan"><?= e(tgl($o['created_at'], true)) ?></td>
          <td data-label="Antar"><?= e(tgl($o['delivery_date'])) ?></td>
          <td data-label="Bayar"><?= pay_badge($o['payment_status']) ?></td>
          <td data-label="Status"><?= status_dot($o['order_status']) ?></td>
          <td data-label="Total" class="num"><?= e(rupiah($o['total'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php adm_layout_end();
