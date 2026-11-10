<?php
// Dasbor > Ongkir: atur daerah antar dan ongkirnya (tabel shipping_zones). Tidak ada hapus permanen; nonaktifkan saja.
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/ui.php';

$user = adm_require_login();
$db   = ks_db();

const ZONE_FEE_MAX   = 10000000;
const ZONE_SORT_MAX  = 9999;
const ZONE_LABEL_MIN = 2;
const ZONE_LABEL_MAX = 60;

/** Rapikan & periksa isian daerah. Mengembalikan [data, pesan galat|null]. */
function zone_input(array $src): array {
    $label = ks_clean_text($src['label'] ?? '');
    $ask   = isset($src['ask']) && $src['ask'] === '1';
    $feeRaw = trim(str_replace(['.', ' ', 'Rp', 'rp'], '', (string)($src['fee'] ?? '')));
    $sort  = filter_var(trim((string)($src['sort_order'] ?? '')), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => ZONE_SORT_MAX]]);
    $data  = ['label' => $label, 'fee' => null, 'sort_order' => $sort === false ? 0 : $sort, 'is_active' => isset($src['is_active']) && $src['is_active'] === '1' ? 1 : 0];

    $len = ks_strlen($label);
    if ($len < ZONE_LABEL_MIN || $len > ZONE_LABEL_MAX) {
        return [$data, 'Nama daerah wajib diisi, ' . ZONE_LABEL_MIN . '–' . ZONE_LABEL_MAX . ' karakter.'];
    }
    if (!$ask) {
        $fee = filter_var($feeRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => ZONE_FEE_MAX]]);
        if ($feeRaw === '' || $fee === false) {
            return [$data, 'Ongkir ' . $label . ' harus angka bulat ' . rupiah(0) . ' sampai ' . rupiah(ZONE_FEE_MAX) . ', atau centang "Tanya dulu".'];
        }
        $data['fee'] = $fee;
    }
    if ($sort === false) {
        return [$data, 'Urutan ' . $label . ' harus angka 0 sampai ' . angka(ZONE_SORT_MAX) . '.'];
    }
    return [$data, null];
}

function zone_code_taken(PDO $db, string $code): bool {
    $st = $db->prepare('SELECT 1 FROM shipping_zones WHERE code = ?');
    $st->execute([$code]);
    return $st->fetchColumn() !== false;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    adm_require_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'update') {
        $code = (string)($_POST['code'] ?? '');
        $st = $db->prepare('SELECT label, is_active FROM shipping_zones WHERE code = ?');
        $st->execute([$code]);
        $row = ks_valid_zone_code($code) ? $st->fetch() : false;
        [$d, $err] = zone_input($_POST);

        if (!$row) {
            adm_flash('error', 'Daerah antar tidak ditemukan.');
            adm_redirect('shipping.php');
        }
        if (!$err && (int)$row['is_active'] === 1 && $d['is_active'] === 0) {
            $others = (int)$db->query('SELECT COUNT(*) FROM shipping_zones WHERE is_active = 1')->fetchColumn() - 1;
            if ($others < 1) $err = 'Minimal satu daerah antar harus aktif, supaya pembeli tetap bisa checkout.';
        }
        if ($err) {
            adm_flash('error', $err);
        } else {
            $db->prepare('UPDATE shipping_zones SET label = ?, fee = ?, sort_order = ?, is_active = ? WHERE code = ?')
               ->execute([$d['label'], $d['fee'], $d['sort_order'], $d['is_active'], $code]);
            adm_flash('success', $d['label'] . ' tersimpan.');
        }
        adm_redirect('shipping.php#z-' . rawurlencode($code));
    }

    if ($action === 'add') {
        [$d, $err] = zone_input($_POST);
        $codeIn = strtolower(trim((string)($_POST['code'] ?? '')));
        $code = $codeIn;
        if (!$err) {
            if ($codeIn !== '') {
                if (!ks_valid_zone_code($codeIn)) {
                    $err = 'Kode hanya boleh huruf kecil, angka, dan tanda hubung (maks. 30 karakter), mis. jakarta-timur.';
                } elseif (zone_code_taken($db, $codeIn)) {
                    $err = 'Kode "' . $codeIn . '" sudah dipakai daerah lain. Pilih kode lain atau kosongkan.';
                }
            } else {
                $base = ks_zone_slug($d['label']);
                if ($base === '') $base = 'daerah';
                $code = $base;
                for ($n = 2; zone_code_taken($db, $code); $n++) {
                    $suffix = '-' . $n;
                    $code = rtrim(substr($base, 0, 30 - strlen($suffix)), '-') . $suffix;
                }
            }
        }
        if (!$err) {
            try {
                $db->prepare('INSERT INTO shipping_zones (code, label, fee, sort_order, is_active) VALUES (?, ?, ?, ?, ?)')
                   ->execute([$code, $d['label'], $d['fee'], $d['sort_order'], $d['is_active']]);
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') throw $e;
                $err = 'Kode "' . $code . '" baru saja dipakai. Coba simpan lagi.';
            }
        }
        if ($err) {
            $_SESSION['zone_old'] = [
                'label' => ks_substr($d['label'], ZONE_LABEL_MAX), 'code' => substr($codeIn, 0, 30),
                'fee' => substr((string)($_POST['fee'] ?? ''), 0, 12), 'ask' => isset($_POST['ask']),
                'sort_order' => substr((string)($_POST['sort_order'] ?? ''), 0, 5), 'is_active' => $d['is_active'] === 1,
            ];
            adm_flash('error', $err);
            adm_redirect('shipping.php#tambah');
        }
        adm_flash('success', $d['label'] . ' ditambahkan' . ($d['is_active'] ? ' dan tampil di toko.' : ' (belum tampil di toko).'));
        adm_redirect('shipping.php#z-' . rawurlencode($code));
    }

    adm_flash('error', 'Permintaan tidak dikenal.');
    adm_redirect('shipping.php');
}

$rows       = ks_shipping_zone_rows();          // null = tabel belum ada
$tableReady = $rows !== null;
$rows       = $rows ?? [];
$override   = is_array(ks_config('shop.shipping_zones')) && ks_config('shop.shipping_zones');
$activeN    = count(array_filter($rows, fn($r) => (int)$r['is_active'] === 1));
$nextSort   = $rows ? (int)max(array_map(fn($r) => (int)$r['sort_order'], $rows)) + 10 : 10;
$nextSort   = min($nextSort, ZONE_SORT_MAX);

$old = $_SESSION['zone_old'] ?? null;
unset($_SESSION['zone_old']);
$old = is_array($old) ? $old : ['label' => '', 'code' => '', 'fee' => '', 'ask' => false, 'sort_order' => (string)$nextSort, 'is_active' => true];

$GLOBALS['ADM_EXTRA_CSS'] = ['assets/shipping.css'];
$GLOBALS['ADM_EXTRA_JS']  = ['assets/shipping.js'];
adm_layout_start('Ongkir & daerah', 'shipping', $user);
?>
<header class="page-head">
  <div>
    <h1 class="page-title">Ongkir &amp; daerah antar</h1>
    <p class="page-meta">
      <?php if ($tableReady): ?>
        <?= e(angka(count($rows))) ?> daerah <span aria-hidden="true">·</span> <?= e(angka($activeN)) ?> tampil di toko dan checkout
      <?php else: ?>
        Memakai daftar bawaan
      <?php endif; ?>
    </p>
  </div>
</header>

<?php if ($override): ?>
  <p class="note-warn zone-note" role="note">Daftar ongkir sedang diatur dari <code>api/config.php</code> (<code>shop.shipping_zones</code>), jadi perubahan di halaman ini belum dipakai toko. Hapus pengaturan itu agar daftar di bawah yang berlaku.</p>
<?php endif; ?>

<?php if (!$tableReady): ?>
<section class="card panel" aria-label="Daerah antar">
  <div class="empty">
    <svg class="empty-illus" viewBox="0 0 220 150" aria-hidden="true" focusable="false"><use href="#ill-empty-pot"></use></svg>
    <p class="empty-title">Tabel ongkir belum dibuat.</p>
    <p class="empty-note">Jalankan <code>db/migrate-2026-11-06-zones.sql</code> sekali di tab SQL phpMyAdmin. Sementara itu toko memakai 4 daerah bawaan.</p>
  </div>
</section>
<?php else: ?>
<section class="card panel" aria-labelledby="zones-title">
  <div class="panel-head">
    <h2 id="zones-title" class="panel-title">Daftar daerah</h2>
    <span class="panel-note">Urutan kecil tampil lebih dulu</span>
  </div>
  <?php if (!$rows): ?>
    <div class="empty">
      <svg class="empty-illus" viewBox="0 0 220 150" aria-hidden="true" focusable="false"><use href="#ill-empty-pot"></use></svg>
      <p class="empty-title">Belum ada daerah antar.</p>
      <p class="empty-note">Selama daftar ini kosong, toko memakai 4 daerah bawaan. Tambahkan daerah pertama di bawah.</p>
    </div>
  <?php else: ?>
  <div class="table-scroll">
    <table class="table table-cards table-zones">
      <thead><tr>
        <th scope="col">Daerah</th><th scope="col">Ongkir (Rp)</th><th scope="col">Urutan</th><th scope="col">Aktif</th>
        <th scope="col"><span class="sr-only">Simpan</span></th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $z):
          $fid = 'z-' . $z['code']; $isOn = (int)$z['is_active'] === 1; $ask = $z['fee'] === null; ?>
        <tr id="<?= e($fid) ?>" class="<?= $isOn ? '' : 'row-off' ?>" data-row>
          <td data-label="Daerah" class="cell-zone">
            <label class="sr-only" for="<?= e($fid) ?>-label">Nama daerah</label>
            <input class="zone-text" id="<?= e($fid) ?>-label" form="<?= e($fid) ?>-form" name="label" type="text"
                   minlength="<?= ZONE_LABEL_MIN ?>" maxlength="<?= ZONE_LABEL_MAX ?>" required value="<?= e($z['label']) ?>" autocomplete="off">
            <span class="sub">Kode <?= e($z['code']) ?><?= $isOn ? '' : ' · nonaktif' ?></span>
          </td>
          <td data-label="Ongkir (Rp)">
            <div class="fee-cell">
              <label class="sr-only" for="<?= e($fid) ?>-fee">Ongkir <?= e($z['label']) ?> dalam Rupiah</label>
              <input class="input-num" id="<?= e($fid) ?>-fee" form="<?= e($fid) ?>-form" name="fee" type="number" inputmode="numeric"
                     min="0" max="<?= ZONE_FEE_MAX ?>" step="500" placeholder="mis. 25000" value="<?= $ask ? '' : (int)$z['fee'] ?>">
              <label class="ask-check">
                <input type="checkbox" form="<?= e($fid) ?>-form" name="ask" value="1" data-ask="<?= e($fid) ?>-fee"<?= $ask ? ' checked' : '' ?>>
                <span>Tanya dulu (via WhatsApp)</span>
              </label>
            </div>
          </td>
          <td data-label="Urutan">
            <label class="sr-only" for="<?= e($fid) ?>-sort">Urutan <?= e($z['label']) ?></label>
            <input class="input-num input-sort" id="<?= e($fid) ?>-sort" form="<?= e($fid) ?>-form" name="sort_order" type="number" inputmode="numeric"
                   min="0" max="<?= ZONE_SORT_MAX ?>" step="1" required value="<?= (int)$z['sort_order'] ?>">
          </td>
          <td data-label="Aktif">
            <label class="switch">
              <input type="checkbox" form="<?= e($fid) ?>-form" name="is_active" value="1"<?= $isOn ? ' checked' : '' ?>>
              <span class="switch-track" aria-hidden="true"></span>
              <span class="sr-only">Tampilkan <?= e($z['label']) ?> di toko dan checkout</span>
            </label>
          </td>
          <td class="cell-save">
            <form id="<?= e($fid) ?>-form" method="post" action="shipping.php">
              <?= adm_csrf_field() ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="code" value="<?= e($z['code']) ?>">
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
  <p class="panel-note zone-help">Daerah tidak bisa dihapus; matikan <strong>Aktif</strong> agar tidak muncul di toko dan checkout. Pesanan lama tetap menyimpan nama daerah dan ongkirnya. <strong>Tanya dulu</strong> berarti ongkir dihitung lewat WhatsApp dan daerah itu tidak bisa dibayar online.</p>
</section>

<section class="card panel" id="tambah" aria-labelledby="add-title">
  <h2 id="add-title" class="panel-title">Tambah daerah</h2>
  <form class="zone-add" method="post" action="shipping.php#tambah">
    <?= adm_csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="field field-label">
      <label for="new-label">Nama daerah</label>
      <input id="new-label" name="label" type="text" minlength="<?= ZONE_LABEL_MIN ?>" maxlength="<?= ZONE_LABEL_MAX ?>" required
             placeholder="mis. Jakarta Timur" value="<?= e($old['label']) ?>" autocomplete="off">
    </div>
    <div class="field">
      <label for="new-code">Kode <span class="field-opt">(opsional)</span></label>
      <input id="new-code" name="code" type="text" maxlength="30" pattern="[a-z0-9]+(-[a-z0-9]+)*" aria-describedby="new-code-hint"
             placeholder="otomatis dari nama" value="<?= e($old['code']) ?>" autocomplete="off" spellcheck="false">
      <span class="field-hint" id="new-code-hint">Huruf kecil, angka, tanda hubung. Tidak bisa diubah setelah disimpan.</span>
    </div>
    <div class="field">
      <label for="new-fee">Ongkir (Rp)</label>
      <input id="new-fee" name="fee" type="number" inputmode="numeric" min="0" max="<?= ZONE_FEE_MAX ?>" step="500" placeholder="mis. 25000"
             value="<?= e($old['ask'] ? '' : $old['fee']) ?>">
      <label class="ask-check">
        <input type="checkbox" name="ask" value="1" data-ask="new-fee"<?= $old['ask'] ? ' checked' : '' ?>>
        <span>Tanya dulu (via WhatsApp)</span>
      </label>
    </div>
    <div class="field field-sort">
      <label for="new-sort">Urutan</label>
      <input id="new-sort" name="sort_order" type="number" inputmode="numeric" min="0" max="<?= ZONE_SORT_MAX ?>" step="1" required value="<?= e($old['sort_order']) ?>">
    </div>
    <div class="field field-active">
      <span class="field-label-text" id="new-active-label">Aktif</span>
      <label class="switch">
        <input type="checkbox" name="is_active" value="1" aria-labelledby="new-active-label"<?= $old['is_active'] ? ' checked' : '' ?>>
        <span class="switch-track" aria-hidden="true"></span>
      </label>
    </div>
    <div class="zone-add-actions">
      <button type="submit" class="btn btn-primary">Tambah daerah</button>
    </div>
  </form>
</section>
<?php endif; ?>
<?php adm_layout_end();
