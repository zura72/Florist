<?php
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/ui.php';

adm_boot();
$next = adm_safe_next((string)($_GET['next'] ?? $_POST['next'] ?? 'index.php'));
if (adm_user()) adm_redirect($next);

$error = '';
$username = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!adm_csrf_valid()) {
        $error = 'Formulir kedaluwarsa. Silakan coba lagi.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if ($username === '' || $password === '' || strlen($username) > 60 || strlen($password) > 200) {
            $error = 'Nama pengguna atau kata sandi salah.';
        } else {
            $res = adm_attempt_login($username, $password);
            if ($res === 'ok') adm_redirect($next);
            $error = $res === 'locked'
                ? 'Terlalu banyak percobaan gagal. Coba lagi dalam 15 menit.'
                : 'Nama pengguna atau kata sandi salah.';
        }
    }
    http_response_code($error !== '' && str_contains($error, 'Terlalu') ? 429 : 401);
}
$flash = adm_take_flash();

adm_head('Masuk', 'login-body');
?>
<main class="login" id="main">
  <section class="login-art" aria-hidden="true">
    <svg class="login-illus" viewBox="0 0 320 360" focusable="false">
      <g fill="none" stroke-linecap="round" stroke-linejoin="round">
        <!-- tangkai -->
        <path class="ill-stem ill-draw" pathLength="1" d="M160 330C158 280 150 230 128 180M160 330c4-52 10-104 34-150M160 330c0-60 0-120 2-176M160 330c-12-40-36-80-70-104M160 330c14-38 40-74 76-96"/>
        <!-- daun -->
        <path class="ill-leaf ill-draw" pathLength="1" d="M146 262c-24-6-38-24-40-44 22 4 36 20 40 44zM176 250c22-10 32-30 30-50-20 8-30 26-30 50zM112 238c-18 2-34-6-42-20 16-4 32 4 42 20zM210 226c16 0 30-10 36-24-16-2-30 8-36 24z"/>
        <!-- bunga (grup luar memosisikan, grup dalam yang bergerak) -->
        <g transform="translate(162 140)"><g class="ill-bloom ill-b1">
          <path d="M0 0C-12-14-12-34 0-48C12-34 12-14 0 0Z"/><path d="M0 0C-12-14-12-34 0-48C12-34 12-14 0 0Z" transform="rotate(72)"/>
          <path d="M0 0C-12-14-12-34 0-48C12-34 12-14 0 0Z" transform="rotate(144)"/><path d="M0 0C-12-14-12-34 0-48C12-34 12-14 0 0Z" transform="rotate(216)"/>
          <path d="M0 0C-12-14-12-34 0-48C12-34 12-14 0 0Z" transform="rotate(288)"/><circle r="7"/>
        </g></g>
        <g transform="translate(124 176) rotate(-18) scale(.72)"><g class="ill-bloom ill-soft ill-b2">
          <path d="M0 0C-12-14-12-34 0-48C12-34 12-14 0 0Z"/><path d="M0 0C-12-14-12-34 0-48C12-34 12-14 0 0Z" transform="rotate(72)"/>
          <path d="M0 0C-12-14-12-34 0-48C12-34 12-14 0 0Z" transform="rotate(144)"/><path d="M0 0C-12-14-12-34 0-48C12-34 12-14 0 0Z" transform="rotate(216)"/>
          <path d="M0 0C-12-14-12-34 0-48C12-34 12-14 0 0Z" transform="rotate(288)"/><circle r="7"/>
        </g></g>
        <g transform="translate(198 172) rotate(20) scale(.64)"><g class="ill-bloom ill-soft ill-b3">
          <path d="M0 0C-12-14-12-34 0-48C12-34 12-14 0 0Z"/><path d="M0 0C-12-14-12-34 0-48C12-34 12-14 0 0Z" transform="rotate(72)"/>
          <path d="M0 0C-12-14-12-34 0-48C12-34 12-14 0 0Z" transform="rotate(144)"/><path d="M0 0C-12-14-12-34 0-48C12-34 12-14 0 0Z" transform="rotate(216)"/>
          <path d="M0 0C-12-14-12-34 0-48C12-34 12-14 0 0Z" transform="rotate(288)"/><circle r="7"/>
        </g></g>
        <path class="ill-bud ill-draw" pathLength="1" d="M88 226c-8-8-8-20 0-28 8 8 8 20 0 28zM238 232c-2-12 4-22 14-24 2 10-4 20-14 24z"/>
        <!-- pita -->
        <path class="ill-ribbon ill-draw" pathLength="1" d="M146 300h28M160 300l-18 26M160 300l16 28"/>
      </g>
    </svg>
    <p class="login-quote">Setiap pesanan, satu senyum yang diantar.</p>
  </section>

  <section class="login-panel">
    <div class="login-card">
      <a class="brand" href="../index.html"><svg class="brand-mark" aria-hidden="true"><use href="#logo"></use></svg><span class="brand-name">Kembang Subuh</span></a>
      <h1 class="login-title">Masuk ke dasbor</h1>
      <p class="login-lead">Pantau pesanan, jadwal antar, dan stok buket hari ini.</p>

      <?php if ($flash): ?><div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['text']) ?></div><?php endif; ?>
      <div class="form-error" id="login-error" role="alert"><?= $error !== '' ? e($error) : '' ?></div>

      <form method="post" action="login.php" class="login-form" novalidate<?= $error !== '' ? ' aria-describedby="login-error"' : '' ?>>
        <?= adm_csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="field">
          <label for="username">Nama pengguna</label>
          <input id="username" name="username" type="text" autocomplete="username" autocapitalize="none" spellcheck="false"
                 maxlength="60" required value="<?= e($username) ?>"<?= $error !== '' ? ' aria-invalid="true"' : '' ?> autofocus>
        </div>
        <div class="field">
          <label for="password">Kata sandi</label>
          <div class="pw-wrap">
            <input id="password" name="password" type="password" autocomplete="current-password" maxlength="200" required<?= $error !== '' ? ' aria-invalid="true"' : '' ?>>
            <button type="button" class="pw-toggle" data-pw-toggle aria-controls="password" aria-pressed="false">Lihat</button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Masuk</button>
      </form>
      <p class="login-foot"><a href="../index.html">← Kembali ke toko</a></p>
    </div>
  </section>
</main>
</body>
</html>
