<?php
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/ui.php';
require __DIR__ . '/inc/data.php';

$user = adm_require_login();
$db   = ks_db();

const PER_PAGE = 20;

$f = [
    'q'              => trim(mb_substr((string)($_GET['q'] ?? ''), 0, 80)),
    'payment_status' => (string)($_GET['payment_status'] ?? ''),
    'order_status'   => (string)($_GET['order_status'] ?? ''),
    'antar'          => (string)($_GET['antar'] ?? ''),
    'dibuat'         => (string)($_GET['dibuat'] ?? ''),
    'daerah'         => trim(mb_substr((string)($_GET['daerah'] ?? ''), 0, 80)),
];
$zoneOpts = adm_zone_options();   // nilai => teks; '-' = pesanan tanpa daerah (data lama)
if (!isset(ADM_PAY[$f['payment_status']])) $f['payment_status'] = '';
if (!isset(ADM_STATUS[$f['order_status']])) $f['order_status'] = '';
if (!isset($zoneOpts[$f['daerah']])) $f['daerah'] = '';
foreach (['antar', 'dibuat'] as $k) {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $f[$k]);
    if (!$d || $d->format('Y-m-d') !== $f[$k]) $f[$k] = '';
}

$where = []; $args = [];
if ($f['q'] !== '') {
    $like = '%' . addcslashes($f['q'], '%_\\') . '%';
    $where[] = '(order_code LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ? OR recipient_name LIKE ? OR recipient_phone LIKE ?)';
    array_push($args, $like, $like, $like, $like, $like);
}
if ($f['payment_status'] !== '') { $where[] = 'payment_status = ?'; $args[] = $f['payment_status']; }
if ($f['order_status'] !== '')   { $where[] = 'order_status = ?';   $args[] = $f['order_status']; }
if ($f['antar'] !== '')          { $where[] = 'delivery_date = ?';  $args[] = $f['antar']; }
if ($f['daerah'] !== '')         { $where[] = 'shipping_zone = ?';  $args[] = $f['daerah'] === '-' ? '' : $f['daerah']; }
if ($f['dibuat'] !== '')       { $where[] = 'created_at >= ? AND created_at < ? + INTERVAL 1 DAY'; array_push($args, $f['dibuat'] . ' 00:00:00', $f['dibuat']); }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$st = $db->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END), 0) AS paid_sum FROM orders $whereSql");
$st->execute($args);
$agg   = $st->fetch() ?: ['n' => 0, 'paid_sum' => 0];
$total = (int)$agg['n'];
$pages = max(1, (int)ceil($total / PER_PAGE));
$page  = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * PER_PAGE;

$st = $db->prepare(
    "SELECT o.id, o.order_code, o.customer_name, o.customer_phone, o.recipient_name, o.delivery_date, o.delivery_slot,
            o.shipping_zone, o.shipping_fee, o.total, o.payment_status, o.order_status, o.created_at,
            (SELECT COALESCE(SUM(qty), 0) FROM order_items oi WHERE oi.order_id = o.id) AS items
       FROM orders o $whereSql
      ORDER BY o.created_at DESC, o.id DESC
      LIMIT " . PER_PAGE . " OFFSET " . (int)$offset);
$st->execute($args);
$rows = $st->fetchAll();

$active = array_filter($f, fn($v) => $v !== '');
function page_url(array $f, int $p): string {
    $q = array_filter($f, fn($v) => $v !== '');
    if ($p > 1) $q['page'] = $p;
    return 'orders.php' . ($q ? '?' . http_build_query($q) : '');
}

adm_layout_start('Pesanan', 'orders', $user);
?>
<header class="page-head">
  <div>
    <h1 class="page-title">Pesanan</h1>
    <p class="page-meta" role="status"><?= e(angka($total)) ?> pesanan<?= $active ? ' cocok' : '' ?> <span aria-hidden="true">·</span> lunas <?= e(rupiah($agg['paid_sum'])) ?></p>
  </div>
</header>

<form class="card filters" method="get" action="orders.php" role="search" aria-label="Saring pesanan">
  <div class="field field-grow">
    <label for="f-q">Cari</label>
    <input id="f-q" name="q" type="search" value="<?= e($f['q']) ?>" placeholder="Kode, nama, atau nomor HP" maxlength="80">
  </div>
  <div class="field">
    <label for="f-pay">Pembayaran</label>
    <select id="f-pay" name="payment_status">
      <option value="">Semua</option>
      <?php foreach (ADM_PAY as $k => [$lab]): ?><option value="<?= e($k) ?>"<?= $f['payment_status'] === $k ? ' selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="f-status">Status</label>
    <select id="f-status" name="order_status">
      <option value="">Semua</option>
      <?php foreach (ADM_STATUS as $k => [$lab]): ?><option value="<?= e($k) ?>"<?= $f['order_status'] === $k ? ' selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="f-zone">Daerah</label>
    <select id="f-zone" name="daerah">
      <option value="">Semua daerah</option>
      <?php foreach ($zoneOpts as $k => $lab): ?><option value="<?= e($k) ?>"<?= $f['daerah'] === (string)$k ? ' selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="f-antar">Tanggal antar</label>
    <input id="f-antar" name="antar" type="date" value="<?= e($f['antar']) ?>">
  </div>
  <div class="field">
    <label for="f-dibuat">Tanggal pesan</label>
    <input id="f-dibuat" name="dibuat" type="date" value="<?= e($f['dibuat']) ?>">
  </div>
  <div class="filter-actions">
    <button type="submit" class="btn btn-primary">Terapkan</button>
    <?php if ($active): ?><a class="btn btn-quiet" href="orders.php">Atur ulang</a><?php endif; ?>
  </div>
</form>

<section class="card panel" aria-label="Daftar pesanan">
  <?php if (!$rows): ?>
    <div class="empty">
      <svg class="empty-illus" viewBox="0 0 220 150" aria-hidden="true" focusable="false"><use href="#ill-empty-orders"></use></svg>
      <p class="empty-title"><?= $active ? 'Tidak ada pesanan yang cocok.' : 'Belum ada pesanan.' ?></p>
      <p class="empty-note"><?= $active ? 'Vasnya masih kosong untuk saringan ini. Coba ubah kata kunci atau tanggal.' : 'Pesanan baru dari toko akan muncul di sini.' ?></p>
      <?php if ($active): ?><p><a class="btn btn-soft" href="orders.php">Hapus saringan</a></p><?php endif; ?>
    </div>
  <?php else: ?>
  <div class="table-scroll">
    <table class="table table-cards">
      <caption class="sr-only">Pesanan, halaman <?= $page ?> dari <?= $pages ?></caption>
      <thead><tr>
        <th scope="col">Kode</th><th scope="col">Pemesan</th><th scope="col">Penerima</th><th scope="col">Antar</th><th scope="col">Daerah</th>
        <th scope="col" class="num">Item</th><th scope="col">Bayar</th><th scope="col">Status</th><th scope="col" class="num">Total</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $o): ?>
        <tr>
          <td data-label="Kode"><a class="code-link" href="order.php?id=<?= (int)$o['id'] ?>"><?= e($o['order_code']) ?></a><span class="sub"><?= e(tgl($o['created_at'], true)) ?></span></td>
          <td data-label="Pemesan"><?= e($o['customer_name']) ?><span class="sub"><?= e($o['customer_phone']) ?></span></td>
          <td data-label="Penerima"><?= e($o['recipient_name']) ?></td>
          <td data-label="Antar"><?= e(tgl($o['delivery_date'])) ?><?php if ($o['delivery_slot'] !== ''): ?><span class="sub"><?= e($o['delivery_slot']) ?></span><?php endif; ?></td>
          <td data-label="Daerah" class="cell-zone"><?php if ($o['shipping_zone'] !== ''): ?><span class="zone-name"><?= e($o['shipping_zone']) ?></span><?php else: ?><span class="muted">—</span><?php endif; ?><span class="sub">ongkir <?= e(rupiah($o['shipping_fee'])) ?></span></td>
          <td data-label="Item" class="num"><?= e(angka($o['items'])) ?></td>
          <td data-label="Bayar"><?= pay_badge($o['payment_status']) ?></td>
          <td data-label="Status"><?= status_dot($o['order_status']) ?></td>
          <td data-label="Total" class="num"><strong><?= e(rupiah($o['total'])) ?></strong></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Halaman">
    <?php if ($page > 1): ?><a class="btn btn-quiet btn-sm" href="<?= e(page_url($f, $page - 1)) ?>" rel="prev">← Sebelumnya</a><?php else: ?><span class="btn btn-quiet btn-sm is-disabled" aria-disabled="true">← Sebelumnya</span><?php endif; ?>
    <span class="pager-info">Halaman <?= $page ?> dari <?= $pages ?></span>
    <?php if ($page < $pages): ?><a class="btn btn-quiet btn-sm" href="<?= e(page_url($f, $page + 1)) ?>" rel="next">Berikutnya →</a><?php else: ?><span class="btn btn-quiet btn-sm is-disabled" aria-disabled="true">Berikutnya →</span><?php endif; ?>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</section>
<?php adm_layout_end();
