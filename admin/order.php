<?php
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/ui.php';

$user = adm_require_login();
$db   = ks_db();
$id   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

function load_order(PDO $db, int $id): ?array {
    if ($id <= 0) return null;
    $st = $db->prepare('SELECT * FROM orders WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

$order = load_order($db, $id);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    adm_require_csrf();
    if (!$order) { http_response_code(404); adm_flash('error', 'Pesanan tidak ditemukan.'); adm_redirect('orders.php'); }
    $to   = (string)($_POST['order_status'] ?? '');
    $from = (string)$order['order_status'];
    if (!isset(ADM_STATUS[$to]) || !in_array($to, ADM_FLOW[$from] ?? [], true)) {
        adm_flash('error', 'Perubahan status itu tidak bisa dilakukan.');
    } else {
        // Hanya ubah bila status belum diubah orang lain sejak halaman dimuat.
        $st = $db->prepare('UPDATE orders SET order_status = ? WHERE id = ? AND order_status = ?');
        $st->execute([$to, $id, $from]);
        if ($st->rowCount() === 1) {
            adm_flash('success', 'Status pesanan ' . $order['order_code'] . ' sekarang: ' . ADM_STATUS[$to][0] . '.');
        } else {
            adm_flash('error', 'Status sudah berubah di tempat lain. Periksa lagi sebelum mengubah.');
        }
    }
    adm_redirect('order.php?id=' . $id);
}

if (!$order) {
    http_response_code(404);
    adm_layout_start('Pesanan tidak ditemukan', 'orders', $user);
    echo '<div class="card empty"><svg class="empty-illus" viewBox="0 0 220 150" aria-hidden="true" focusable="false"><use href="#ill-empty-orders"></use></svg>'
       . '<h1 class="page-title">Pesanan tidak ditemukan</h1><p><a class="btn btn-soft" href="orders.php">Kembali ke daftar pesanan</a></p></div>';
    adm_layout_end();
    exit;
}

$st = $db->prepare('SELECT product_id, name, price, qty FROM order_items WHERE order_id = ? ORDER BY id');
$st->execute([$id]);
$items = $st->fetchAll();

$st = $db->prepare('SELECT source, payload, created_at FROM payment_logs WHERE order_code = ? ORDER BY created_at DESC, id DESC LIMIT 20');
$st->execute([$order['order_code']]);
$logs = [];
foreach ($st as $l) {
    $p = json_decode((string)$l['payload'], true);
    $p = is_array($p) ? $p : [];
    $logs[] = [
        'at'      => $l['created_at'],
        'source'  => $l['source'],
        'status'  => is_scalar($p['transaction_status'] ?? $p['result'] ?? null) ? (string)($p['transaction_status'] ?? $p['result']) : '—',
        'type'    => (string)($p['payment_type'] ?? ''),
        'amount'  => isset($p['gross_amount']) ? (float)$p['gross_amount'] : null,
        'fraud'   => (string)($p['fraud_status'] ?? ''),
        'code'    => (string)($p['status_code'] ?? ''),
    ];
}

$status = (string)$order['order_status'];
$next   = ADM_FLOW[$status] ?? [];
$paid   = $order['payment_status'] === 'paid';
$forward = ['baru' => 'dirangkai', 'dirangkai' => 'diantar', 'diantar' => 'selesai'];
$actionLabel = [
    'dirangkai' => 'Mulai rangkai', 'diantar' => 'Tandai diantar', 'selesai' => 'Tandai selesai',
    'batal' => 'Batalkan pesanan', 'baru' => 'Kembalikan ke Baru',
];
$steps = ['baru', 'dirangkai', 'diantar', 'selesai'];
$stepIdx = array_search($status, $steps, true);
$srcLabel = ['notification' => 'Notifikasi Midtrans', 'status-check' => 'Cek status', 'demo' => 'Simulasi (mode uji)'];

adm_layout_start('Pesanan ' . $order['order_code'], 'orders', $user);
?>
<header class="page-head">
  <div>
    <a class="back-link" href="orders.php"><?= adm_icon('i-back') ?>Pesanan</a>
    <h1 class="page-title code-title"><?= e($order['order_code']) ?></h1>
    <p class="page-meta">Dipesan <?= e(tgl($order['created_at'], true)) ?> <span aria-hidden="true">·</span> <?= pay_badge($order['payment_status']) ?> <?= status_dot($status) ?></p>
  </div>
  <button type="button" class="btn btn-quiet" data-print><?= adm_icon('i-print') ?><span>Cetak kartu &amp; label</span></button>
</header>

<div class="grid grid-detail">
  <div class="stack-col">
    <section class="card panel" aria-labelledby="flow-title">
      <h2 id="flow-title" class="panel-title">Status pesanan</h2>
      <?php if ($status === 'batal'): ?>
        <p class="chip chip-off">Pesanan dibatalkan</p>
      <?php else: ?>
        <ol class="steps">
          <?php foreach ($steps as $i => $s): $state = $stepIdx === false ? '' : ($i < $stepIdx ? 'done' : ($i === $stepIdx ? 'now' : '')); ?>
            <li class="step <?= $state ? 'step-' . $state : '' ?>"<?= $state === 'now' ? ' aria-current="step"' : '' ?>><span class="petal petal-<?= e(ADM_STATUS[$s][1]) ?>" aria-hidden="true"></span><?= e(ADM_STATUS[$s][0]) ?></li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
      <?php if (!$paid && in_array($status, ['baru', 'dirangkai'], true)): ?>
        <p class="note-warn">Pembayaran belum lunas. Pastikan dana sudah masuk sebelum merangkai.</p>
      <?php elseif ($paid && strncmp((string)$order['payment_type'], 'demo-', 5) === 0): ?>
        <p class="note-warn">Lunas lewat simulasi (mode uji), bukan pembayaran sungguhan. Tidak ada dana yang masuk, jadi jangan dikirim.</p>
      <?php endif; ?>
      <?php if ($next): ?>
      <form method="post" action="order.php?id=<?= (int)$id ?>" class="status-actions">
        <?= adm_csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <?php if (isset($forward[$status]) && in_array($forward[$status], $next, true)): ?>
          <button type="submit" name="order_status" value="<?= e($forward[$status]) ?>" class="btn btn-primary"><?= e($actionLabel[$forward[$status]]) ?></button>
        <?php endif; ?>
        <?php foreach ($next as $to): if (($forward[$status] ?? null) === $to) continue; ?>
          <button type="submit" name="order_status" value="<?= e($to) ?>" class="btn <?= $to === 'batal' ? 'btn-danger-quiet' : 'btn-quiet' ?>"
            <?= $to === 'batal' ? 'data-confirm="Batalkan pesanan ' . e($order['order_code']) . '?"' : '' ?>>
            <?= e($actionLabel[$to] ?? ('Ubah ke ' . ADM_STATUS[$to][0])) ?></button>
        <?php endforeach; ?>
      </form>
      <?php endif; ?>
    </section>

    <section class="card panel" aria-labelledby="items-title">
      <h2 id="items-title" class="panel-title">Isi pesanan</h2>
      <div class="table-scroll">
        <table class="table table-compact">
          <thead><tr><th scope="col">Produk</th><th scope="col" class="num">Harga</th><th scope="col" class="num">Jml</th><th scope="col" class="num">Subtotal</th></tr></thead>
          <tbody>
          <?php foreach ($items as $it): ?>
            <tr><th scope="row" class="cell-name"><?= e($it['name']) ?></th><td class="num"><?= e(rupiah($it['price'])) ?></td><td class="num"><?= e(angka($it['qty'])) ?></td><td class="num"><?= e(rupiah((int)$it['price'] * (int)$it['qty'])) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$items): ?><tr><td colspan="4" class="muted">Tidak ada item.</td></tr><?php endif; ?>
          </tbody>
          <tfoot>
            <tr><th scope="row" colspan="3">Subtotal</th><td class="num"><?= e(rupiah($order['subtotal'])) ?></td></tr>
            <tr><th scope="row" colspan="3">Ongkos antar<?php if (($order['shipping_zone'] ?? '') !== ''): ?> <span class="muted">· <?= e($order['shipping_zone']) ?></span><?php endif; ?></th><td class="num"><?= e(rupiah($order['shipping_fee'])) ?></td></tr>
            <tr class="row-total"><th scope="row" colspan="3">Total</th><td class="num"><?= e(rupiah($order['total'])) ?></td></tr>
          </tfoot>
        </table>
      </div>
    </section>

    <section class="card panel" aria-labelledby="pay-title">
      <h2 id="pay-title" class="panel-title">Pembayaran</h2>
      <dl class="facts">
        <div><dt>Status</dt><dd><?= pay_badge($order['payment_status']) ?></dd></div>
        <div><dt>Metode</dt><dd><?= $order['payment_type'] !== '' ? e(str_replace('_', ' ', $order['payment_type'])) : '—' ?></dd></div>
        <div><dt>Diperbarui</dt><dd><?= e(tgl($order['updated_at'], true)) ?></dd></div>
      </dl>
      <h3 class="sub-title">Riwayat dari Midtrans</h3>
      <?php if (!$logs): ?>
        <p class="empty-line">Belum ada catatan pembayaran.</p>
      <?php else: ?>
        <ul class="log-list">
          <?php foreach ($logs as $l): ?>
            <li>
              <span class="log-when"><?= e(tgl($l['at'], true)) ?></span>
              <span class="log-what"><strong><?= e($l['status']) ?></strong><?= $l['type'] !== '' ? ' · ' . e(str_replace('_', ' ', $l['type'])) : '' ?><?= $l['amount'] !== null ? ' · ' . e(rupiah($l['amount'])) : '' ?><?= $l['fraud'] !== '' ? ' · fraud: ' . e($l['fraud']) : '' ?></span>
              <span class="log-src muted"><?= e($srcLabel[$l['source']] ?? $l['source']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <div class="stack-col">
    <section class="card panel" aria-labelledby="who-title">
      <h2 id="who-title" class="panel-title">Pemesan</h2>
      <dl class="facts">
        <div><dt>Nama</dt><dd><?= e($order['customer_name']) ?></dd></div>
        <div><dt>No. HP</dt><dd><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $order['customer_phone'])) ?>"><?= e($order['customer_phone']) ?></a></dd></div>
        <div><dt>Email</dt><dd><?= $order['customer_email'] !== '' ? e($order['customer_email']) : '—' ?></dd></div>
      </dl>
    </section>

    <section class="card panel" aria-labelledby="to-title">
      <h2 id="to-title" class="panel-title">Penerima &amp; antar</h2>
      <dl class="facts">
        <div><dt>Penerima</dt><dd><?= e($order['recipient_name']) ?></dd></div>
        <div><dt>No. HP</dt><dd><?php if ($order['recipient_phone'] !== ''): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $order['recipient_phone'])) ?>"><?= e($order['recipient_phone']) ?></a><?php else: ?>—<?php endif; ?></dd></div>
        <div><dt>Tanggal</dt><dd><?= e(tgl_hari($order['delivery_date'])) ?> <?= e(date('Y', strtotime($order['delivery_date']))) ?></dd></div>
        <div><dt>Jam</dt><dd><?= $order['delivery_slot'] !== '' ? e($order['delivery_slot']) : '—' ?></dd></div>
        <div class="facts-wide"><dt>Alamat</dt><dd class="pre"><?= e($order['address']) ?></dd></div>
      </dl>
    </section>

    <section class="card panel print-sheet" aria-labelledby="print-title">
      <h2 id="print-title" class="panel-title">Kartu ucapan &amp; label antar</h2>
      <div class="print-grid">
        <article class="greeting-card">
          <svg class="greeting-sprig" aria-hidden="true"><use href="#sprig"></use></svg>
          <p class="greeting-to">Untuk <?= e($order['recipient_name']) ?></p>
          <p class="greeting-msg"><?= $order['card_message'] !== '' ? nl2br(e($order['card_message'])) : '<span class="muted">(tanpa pesan kartu)</span>' ?></p>
          <p class="greeting-from">Kembang Subuh</p>
        </article>
        <article class="ship-label">
          <p class="ship-head"><svg class="brand-mark" aria-hidden="true"><use href="#logo"></use></svg> Kembang Subuh <span class="ship-code"><?= e($order['order_code']) ?></span></p>
          <p class="ship-to"><span class="ship-k">Kepada</span><strong><?= e($order['recipient_name']) ?></strong><?= $order['recipient_phone'] !== '' ? '<br>' . e($order['recipient_phone']) : '' ?></p>
          <p class="ship-addr pre"><?= e($order['address']) ?></p>
          <p class="ship-when"><span class="ship-k">Antar</span><?= e(tgl_hari($order['delivery_date'])) ?><?= $order['delivery_slot'] !== '' ? ' · ' . e($order['delivery_slot']) : '' ?></p>
          <p class="ship-items"><?= e(implode(', ', array_map(fn($it) => $it['qty'] . '× ' . $it['name'], $items))) ?></p>
        </article>
      </div>
    </section>
  </div>
</div>
<?php adm_layout_end();
