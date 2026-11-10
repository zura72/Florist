<?php
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/ui.php';

$user = adm_require_login();
$db   = ks_db();

const PRICE_MIN = 1000;
const PRICE_MAX = 100000000;
const STOCK_MAX = 9999;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    adm_require_csrf();
    $pid   = (string)($_POST['id'] ?? '');
    $price = filter_var(str_replace(['.', ' ', 'Rp'], '', (string)($_POST['price'] ?? '')), FILTER_VALIDATE_INT, ['options' => ['min_range' => PRICE_MIN, 'max_range' => PRICE_MAX]]);
    $stock = filter_var((string)($_POST['stock'] ?? ''), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => STOCK_MAX]]);
    $activ = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;

    $st = $db->prepare('SELECT name FROM products WHERE id = ?');
    $st->execute([$pid]);
    $name = $st->fetchColumn();

    if ($name === false) {
        adm_flash('error', 'Produk tidak ditemukan.');
    } elseif ($price === false) {
        adm_flash('error', 'Harga ' . $name . ' harus angka bulat antara ' . rupiah(PRICE_MIN) . ' dan ' . rupiah(PRICE_MAX) . '.');
    } elseif ($stock === false) {
        adm_flash('error', 'Stok ' . $name . ' harus angka 0 sampai ' . angka(STOCK_MAX) . '.');
    } else {
        $db->prepare('UPDATE products SET price = ?, stock = ?, is_active = ? WHERE id = ?')->execute([$price, $stock, $activ, $pid]);
        adm_flash('success', $name . ' tersimpan.');
    }
    adm_redirect('products.php#p-' . rawurlencode($pid));
}

$products = $db->query(
    "SELECT p.id, p.name, p.category, p.price, p.image, p.stock, p.is_active, p.updated_at,
            COALESCE(s.sold, 0) AS sold30
       FROM products p
       LEFT JOIN (
            SELECT oi.product_id, SUM(oi.qty) AS sold
              FROM order_items oi JOIN orders o ON o.id = oi.order_id
             WHERE o.payment_status = 'paid' AND o.created_at >= NOW() - INTERVAL 30 DAY
             GROUP BY oi.product_id
       ) s ON s.product_id = p.id
      ORDER BY p.is_active DESC, p.category, p.name")->fetchAll();

$active = count(array_filter($products, fn($p) => (int)$p['is_active'] === 1));
$low    = count(array_filter($products, fn($p) => (int)$p['is_active'] === 1 && (int)$p['stock'] <= 3));

adm_layout_start('Produk', 'products', $user);
?>
<header class="page-head">
  <div>
    <h1 class="page-title">Produk</h1>
    <p class="page-meta"><?= e(angka(count($products))) ?> produk <span aria-hidden="true">·</span> <?= e(angka($active)) ?> tampil di toko<?php if ($low): ?> <span aria-hidden="true">·</span> <span class="chip chip-attn"><?= e(angka($low)) ?> stok menipis</span><?php endif; ?></p>
  </div>
</header>

<section class="card panel" aria-label="Daftar produk">
  <?php if (!$products): ?>
    <div class="empty">
      <svg class="empty-illus" viewBox="0 0 220 150" aria-hidden="true" focusable="false"><use href="#ill-empty-pot"></use></svg>
      <p class="empty-title">Belum ada produk.</p>
      <p class="empty-note">Pot ini menunggu buket pertama. Impor db/seed.sql lewat phpMyAdmin.</p>
    </div>
  <?php else: ?>
  <div class="table-scroll">
    <table class="table table-cards table-products">
      <thead><tr>
        <th scope="col">Produk</th><th scope="col">Harga (Rp)</th><th scope="col">Stok</th><th scope="col">Tampil</th>
        <th scope="col" class="num">Terjual 30 hr</th><th scope="col"><span class="sr-only">Simpan</span></th>
      </tr></thead>
      <tbody>
      <?php foreach ($products as $p): $fid = 'p-' . $p['id']; $isOn = (int)$p['is_active'] === 1; $stock = (int)$p['stock']; ?>
        <tr id="<?= e($fid) ?>" class="<?= $isOn ? '' : 'row-off' ?>" data-row>
          <td data-label="Produk">
            <div class="prod">
              <?php if ($p['image'] !== ''): ?><img class="prod-img" src="../<?= e(preg_replace('#^assets/([^/]+)\.jpg$#', 'assets/webp/$1-320.webp', $p['image'])) ?>" alt="" width="48" height="48" loading="lazy" decoding="async"><?php endif; ?>
              <span><strong class="prod-name"><?= e($p['name']) ?></strong><span class="sub"><?= e(ucfirst($p['category'])) ?> · <?= e($p['id']) ?></span></span>
            </div>
          </td>
          <td data-label="Harga (Rp)">
            <label class="sr-only" for="<?= e($fid) ?>-price">Harga <?= e($p['name']) ?></label>
            <input class="input-num" id="<?= e($fid) ?>-price" form="<?= e($fid) ?>-form" name="price" type="number" inputmode="numeric"
                   min="<?= PRICE_MIN ?>" max="<?= PRICE_MAX ?>" step="1000" required value="<?= (int)$p['price'] ?>">
          </td>
          <td data-label="Stok">
            <label class="sr-only" for="<?= e($fid) ?>-stock">Stok <?= e($p['name']) ?></label>
            <input class="input-num input-stock<?= $isOn && $stock <= 3 ? ' is-low' : '' ?>" id="<?= e($fid) ?>-stock" form="<?= e($fid) ?>-form" name="stock" type="number" inputmode="numeric"
                   min="0" max="<?= STOCK_MAX ?>" step="1" required value="<?= $stock ?>">
          </td>
          <td data-label="Tampil">
            <label class="switch">
              <input type="checkbox" form="<?= e($fid) ?>-form" name="is_active" value="1"<?= $isOn ? ' checked' : '' ?>>
              <span class="switch-track" aria-hidden="true"></span>
              <span class="sr-only">Tampilkan <?= e($p['name']) ?> di toko</span>
            </label>
          </td>
          <td data-label="Terjual 30 hr" class="num"><?= e(angka($p['sold30'])) ?></td>
          <td class="cell-save">
            <form id="<?= e($fid) ?>-form" method="post" action="products.php">
              <?= adm_csrf_field() ?>
              <input type="hidden" name="id" value="<?= e($p['id']) ?>">
              <button type="submit" class="btn btn-soft btn-sm">Simpan</button>
            </form>
            <span class="dirty-note" aria-live="polite"></span>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php adm_layout_end();
