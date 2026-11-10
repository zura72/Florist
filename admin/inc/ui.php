<?php
// Helper tampilan dan kerangka halaman Dasbor Kembang Subuh.
declare(strict_types=1);

if (realpath((string)(get_included_files()[0] ?? '')) === __FILE__) { http_response_code(404); exit; }   // hanya untuk di-include

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rupiah($n): string {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function angka($n): string {
    return number_format((float)$n, 0, ',', '.');
}

const ADM_BULAN = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const ADM_HARI  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

function tgl(?string $s, bool $withTime = false): string {
    if (!$s) return '—';
    $t = strtotime($s);
    if ($t === false) return '—';
    $out = (int)date('j', $t) . ' ' . ADM_BULAN[(int)date('n', $t)] . ' ' . date('Y', $t);
    return $withTime ? $out . ', ' . date('H.i', $t) : $out;
}

function tgl_hari(string $s): string {
    $t = strtotime($s);
    return ADM_HARI[(int)date('w', $t)] . ', ' . (int)date('j', $t) . ' ' . ADM_BULAN[(int)date('n', $t)];
}

const ADM_PAY = [
    'pending'  => ['Menunggu bayar', 'warn'],
    'paid'     => ['Lunas', 'ok'],
    'failed'   => ['Gagal', 'bad'],
    'expired'  => ['Kedaluwarsa', 'bad'],
    'refunded' => ['Dikembalikan', 'mute'],
];

const ADM_STATUS = [
    'baru'      => ['Baru', 'rose'],
    'dirangkai' => ['Dirangkai', 'amber'],
    'diantar'   => ['Diantar', 'blue'],
    'selesai'   => ['Selesai', 'mute'],
    'batal'     => ['Batal', 'off'],
];

/** Alur status pesanan yang diizinkan (termasuk koreksi satu langkah mundur). */
const ADM_FLOW = [
    'baru'      => ['dirangkai', 'batal'],
    'dirangkai' => ['diantar', 'baru', 'batal'],
    'diantar'   => ['selesai', 'dirangkai'],
    'selesai'   => ['diantar'],
    'batal'     => ['baru'],
];

function pay_badge(string $s): string {
    [$label, $tone] = ADM_PAY[$s] ?? [$s, 'mute'];
    return '<span class="badge badge-' . e($tone) . '">' . e($label) . '</span>';
}

function status_dot(string $s): string {
    [$label, $tone] = ADM_STATUS[$s] ?? [$s, 'mute'];
    return '<span class="status"><span class="petal petal-' . e($tone) . '" aria-hidden="true"></span>' . e($label) . '</span>';
}

function adm_icon(string $id, string $cls = 'icon'): string {
    return '<svg class="' . e($cls) . '" aria-hidden="true" focusable="false"><use href="#' . e($id) . '"></use></svg>';
}

function adm_sprite(): string {
    return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" class="sprite" aria-hidden="true" focusable="false">
  <symbol id="logo" viewBox="0 0 64 44"><g fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"><g transform="translate(32 38)"><path d="M0 0C-6.5-9-6.5-22 0-34C6.5-22 6.5-9 0 0Z" transform="rotate(-64)"/><path d="M0 0C-6.5-9-6.5-22 0-34C6.5-22 6.5-9 0 0Z" transform="rotate(-32)"/><path d="M0 0C-6.5-9-6.5-22 0-34C6.5-22 6.5-9 0 0Z"/><path d="M0 0C-6.5-9-6.5-22 0-34C6.5-22 6.5-9 0 0Z" transform="rotate(32)"/><path d="M0 0C-6.5-9-6.5-22 0-34C6.5-22 6.5-9 0 0Z" transform="rotate(64)"/></g><path d="M2 38H62"/></g></symbol>
  <symbol id="i-home" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 13h6v7H4zM14 4h6v16h-6zM4 4h6v5H4z"/></g></symbol>
  <symbol id="i-orders" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h3"/></g></symbol>
  <symbol id="i-flower" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="2.2"/><path d="M12 5.8C11 3 13 3 12 2.5M9.8 8C7 8 6.5 6 7.5 5.5s3 .8 2.3 2.5M14.2 8c2.8 0 3.3-2 2.3-2.5s-3 .8-2.3 2.5M12 10.2V21M12 16c2-3 5-3 6-3 0 2-2 4-6 4M12 18c-2-2-4-3-6-3 0 2 2 4 6 4"/></g></symbol>
  <symbol id="i-pin" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.3"/></g></symbol>
  <symbol id="i-store" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9l1.5-5h13L20 9M4 9v11h16V9M4 9c0 1.7 1.3 3 3 3s3-1.3 3-3c0 1.7 1.3 3 2 3s2-1.3 2-3c0 1.7 1.3 3 3 3s3-1.3 3-3"/><path d="M10 20v-5h4v5"/></g></symbol>
  <symbol id="i-logout" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4H5v16h5M15 8l4 4-4 4M19 12H9"/></g></symbol>
  <symbol id="i-print" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 9V3h10v6M7 17H4v-8h16v8h-3M7 14h10v7H7z"/></g></symbol>
  <symbol id="i-back" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></g></symbol>
  <symbol id="i-money" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></g></symbol>
  <symbol id="i-clock" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></g></symbol>
  <symbol id="i-avg" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16M7 16V10M12 16V5M17 16v-4"/></g></symbol>
  <symbol id="i-truck" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h11v10H3zM14 11h4l3 3v3h-7z"/><circle cx="7.5" cy="18" r="1.8"/><circle cx="17.5" cy="18" r="1.8"/></g></symbol>
  <symbol id="ill-empty-orders" viewBox="0 0 220 150"><ellipse class="ie-ground" cx="104" cy="138" rx="72" ry="7"/><g class="ie-sprig"><path class="ie-stem" d="M104 70C102 52 96 38 86 28M104 70c2-16 8-28 20-36"/><path class="ie-leaf" d="M95 46c-9-1-15-7-16-15 9 1 15 7 16 15ZM113 42c8-3 12-10 12-17-8 2-12 8-12 17Z"/><path class="ie-petal" d="M86 28c-5-3-6-9-2-13 4 2 5 8 2 13ZM124 34c1-6 6-9 11-8-1 5-6 8-11 8Z"/></g><path class="ie-vase" d="M84 76h40c3 18 4 36-3 56H87c-7-20-6-38-3-56Z"/><path class="ie-rim" d="M80 68h48v9H80z"/><path class="ie-band" d="M86 98h36"/><g class="ie-lens"><circle class="ie-glass" cx="168" cy="62" r="17"/><path class="ie-handle" d="M180 75l14 14"/><path class="ie-shine" d="M159 56c2-4 6-6 10-6"/></g><path class="ie-fly" d="M40 52c-4-4-3-10 2-12 3 4 2 9-2 12Z"/><circle class="ie-dot" cx="56" cy="90" r="2.5"/><circle class="ie-dot" cx="150" cy="112" r="2"/></symbol>
  <symbol id="ill-empty-pot" viewBox="0 0 220 150"><ellipse class="ie-ground" cx="110" cy="138" rx="66" ry="7"/><g class="ie-sprout"><path class="ie-stem" d="M110 84V50"/><path class="ie-leaf" d="M110 64c-14-1-22-9-23-20 13 1 21 8 23 20ZM110 56c12-3 19-11 19-21-11 2-18 9-19 21Z"/></g><path class="ie-vase" d="M78 88h64l-9 46H87Z"/><path class="ie-rim" d="M72 80h76v10H72z"/><path class="ie-soil" d="M80 88h60"/><path class="ie-fly" d="M162 46c-4-4-3-10 2-12 3 4 2 9-2 12Z"/><circle class="ie-dot" cx="58" cy="70" r="2.5"/></symbol>
  <symbol id="sprig" viewBox="0 0 120 64"><g fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 58C30 50 58 40 82 22c10-8 20-14 32-16"/><path d="M30 50c-4-10 0-18 8-22-1 9-3 16-8 22zM30 50c10 2 18-1 22-8-9-1-16 2-22 8zM56 38c-3-11 2-19 10-22 0 9-3 16-10 22zM56 38c10 1 18-3 21-11-9 0-16 4-21 11zM82 22c-1-9 4-15 11-17-1 8-5 13-11 17z"/></g><circle cx="114" cy="6" r="4" fill="currentColor"/></symbol>
</svg>
SVG;
}

function adm_head(string $title, string $bodyClass = ''): void {
    ?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Dasbor Kembang Subuh</title>
<link rel="icon" href="../assets/logo/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
<link rel="stylesheet" href="../design/tokens.css">
<link rel="stylesheet" href="assets/admin.css">
<?php foreach ($GLOBALS['ADM_EXTRA_CSS'] ?? [] as $css): ?><link rel="stylesheet" href="<?= e($css) ?>">
<?php endforeach; ?>
<?php foreach ($GLOBALS['ADM_EXTRA_JS'] ?? [] as $js): ?><script src="<?= e($js) ?>" defer></script>
<?php endforeach; ?>
<script src="assets/admin.js" defer></script>
</head>
<body class="<?= e($bodyClass) ?>">
<?= adm_sprite() ?>
<?php
}

/** Buka kerangka halaman: sidebar (desktop), bilah atas + navigasi bawah (ponsel). */
function adm_layout_start(string $title, string $active, array $user): void {
    adm_head($title, 'adm');
    $nav = [
        'index'    => ['index.php', 'Ringkasan', 'i-home'],
        'orders'   => ['orders.php', 'Pesanan', 'i-orders'],
        'products' => ['products.php', 'Produk', 'i-flower'],
        'shipping' => ['shipping.php', 'Ongkir', 'i-pin'],
    ];
    $navCols = count($nav) + 1;   // + "Lihat toko"; menentukan jumlah kolom navigasi bawah di ponsel
    $logout = '<form method="post" action="logout.php" class="logout-form">' . adm_csrf_field()
            . '<button type="submit" class="btn btn-quiet btn-sm">' . adm_icon('i-logout') . '<span>Keluar</span></button></form>';
    ?>
<a class="skip-link" href="#main">Lewati ke konten</a>
<header class="topbar">
  <a class="brand" href="index.php"><svg class="brand-mark" aria-hidden="true"><use href="#logo"></use></svg><span class="brand-name">Kembang Subuh</span></a>
  <?= $logout ?>
</header>
<aside class="side">
  <a class="brand side-brand" href="index.php">
    <svg class="brand-mark" aria-hidden="true"><use href="#logo"></use></svg>
    <span><span class="brand-name">Kembang Subuh</span><span class="brand-sub">Dasbor toko</span></span>
  </a>
  <nav class="side-nav" aria-label="Menu utama" style="grid-template-columns: repeat(<?= (int)$navCols ?>, minmax(0, 1fr))">
    <?php foreach ($nav as $key => [$href, $label, $icon]): ?>
      <a href="<?= e($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= adm_icon($icon) ?><span><?= e($label) ?></span></a>
    <?php endforeach; ?>
    <a href="../index.html" class="nav-store"><?= adm_icon('i-store') ?><span>Lihat toko</span></a>
  </nav>
  <div class="side-foot">
    <svg class="sprig" aria-hidden="true"><use href="#sprig"></use></svg>
    <p class="side-user">Masuk sebagai <strong><?= e($user['username']) ?></strong></p>
    <?= $logout ?>
  </div>
</aside>
<main id="main" class="main" tabindex="-1">
<?php
    $flash = adm_take_flash();
    if ($flash) {
        $role = $flash['type'] === 'error' ? 'alert' : 'status';
        echo '<div class="flash flash-' . e($flash['type']) . '" role="' . $role . '">' . e($flash['text']) . '</div>';
    }
}

function adm_layout_end(): void {
    echo "</main>\n</body>\n</html>\n";
}
