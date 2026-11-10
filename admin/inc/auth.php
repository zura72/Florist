<?php
// Sesi, keamanan, CSRF, dan login untuk Dasbor Kembang Subuh.
declare(strict_types=1);

if (realpath((string)(get_included_files()[0] ?? '')) === __FILE__) { http_response_code(404); exit; }   // hanya untuk di-include

require_once __DIR__ . '/../../api/bootstrap.php';

const ADM_IDLE_SECONDS   = 7200;   // keluar otomatis setelah 2 jam tanpa aktivitas
const ADM_MAX_FAILS      = 5;      // gagal login maksimal per IP ...
const ADM_FAIL_WINDOW    = 15;     // ... dalam 15 menit

ini_set('display_errors', '0');

set_exception_handler(function (Throwable $e): void {
    error_log('[ks-admin] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Terjadi kendala</title><link rel="stylesheet" href="assets/admin.css">'
       . '<main class="error-page"><h1>Maaf, ada kendala di server.</h1><p>Coba muat ulang halaman sebentar lagi.</p>'
       . '<p><a class="btn btn-soft" href="index.php">Kembali ke Ringkasan</a></p></main>';
    exit;
});

function adm_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') return true;
    return (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function adm_security_headers(): void {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store, max-age=0');
    header('X-Robots-Tag: noindex, nofollow');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
         . "font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; object-src 'none'; "
         . "base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    if (adm_is_https()) header('Strict-Transport-Security: max-age=31536000');
}

function adm_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'))), '/') . '/';
    session_name('ks_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $dir,
        'secure'   => adm_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();

    $now = time();
    if (isset($_SESSION['admin_id'], $_SESSION['last_seen']) && $now - (int)$_SESSION['last_seen'] > ADM_IDLE_SECONDS) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['flash'] = ['type' => 'info', 'text' => 'Sesi berakhir karena lama tidak aktif. Silakan masuk lagi.'];
    }
    if (isset($_SESSION['admin_id'])) $_SESSION['last_seen'] = $now;
}

function adm_boot(): void {
    adm_security_headers();
    adm_session_start();
}

/* ---------- CSRF ---------- */

function adm_csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function adm_csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(adm_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function adm_csrf_valid(): bool {
    $sent = (string)($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    return $sent !== '' && !empty($_SESSION['csrf']) && hash_equals((string)$_SESSION['csrf'], $sent);
}

/** Hentikan permintaan POST yang token CSRF-nya salah. */
function adm_require_csrf(): void {
    if (!adm_csrf_valid()) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>Permintaan ditolak</title><link rel="stylesheet" href="assets/admin.css">'
           . '<main class="error-page"><h1>Permintaan ditolak.</h1><p>Formulir sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.</p>'
           . '<p><a class="btn btn-soft" href="index.php">Kembali</a></p></main>';
        exit;
    }
}

/* ---------- Flash ---------- */

function adm_flash(string $type, string $text): void {
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
}

function adm_take_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($f) ? $f : null;
}

/* ---------- Login ---------- */

function adm_user(): ?array {
    if (empty($_SESSION['admin_id'])) return null;
    return ['id' => (int)$_SESSION['admin_id'], 'username' => (string)($_SESSION['admin_name'] ?? '')];
}

function adm_safe_next(string $next): string {
    return preg_match('/^[a-z]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/', $next) ? $next : 'index.php';
}

function adm_redirect(string $to): void {
    header('Location: ' . $to, true, 303);
    exit;
}

/** Halaman HTML: alihkan ke login. Dipakai di awal setiap halaman admin. */
function adm_require_login(): array {
    adm_boot();
    $u = adm_user();
    if ($u) return $u;
    $self = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    $qs   = (string)($_SERVER['QUERY_STRING'] ?? '');
    $next = adm_safe_next($self . ($qs !== '' ? '?' . $qs : ''));
    adm_redirect('login.php' . ($next !== 'index.php' ? '?next=' . rawurlencode($next) : ''));
}

function adm_login_locked(string $ip): bool {
    $st = ks_db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL ' . ADM_FAIL_WINDOW . ' MINUTE)');
    $st->execute([$ip]);
    return (int)$st->fetchColumn() >= ADM_MAX_FAILS;
}

/** @return string 'ok' | 'fail' | 'locked' */
function adm_attempt_login(string $username, string $password): string {
    $db = ks_db();
    $ip = ks_client_ip();
    if (mt_rand(1, 50) === 1) $db->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    if (adm_login_locked($ip)) return 'locked';
    // Catat percobaan SEBELUM memeriksa sandi, lalu hitung ulang: permintaan paralel ikut terhitung,
    // jadi batas 5 kali tidak bisa ditembus dengan mengirim banyak tebakan sekaligus.
    $db->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
    $st = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL ' . ADM_FAIL_WINDOW . ' MINUTE)');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() > ADM_MAX_FAILS) return 'locked';

    $st = $db->prepare('SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1');
    $st->execute([$username]);
    $row = $st->fetch();
    // Tetap jalankan password_verify walau pengguna tidak ada, agar waktu respons serupa.
    $hash = $row ? (string)$row['password_hash'] : password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
    $ok   = password_verify($password, $hash) && $row;

    if (!$ok) return adm_login_locked($ip) ? 'locked' : 'fail';

    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        $db->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }
    $db->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
    $db->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?')->execute([$row['id']]);

    session_regenerate_id(true);
    $_SESSION = [
        'admin_id'   => (int)$row['id'],
        'admin_name' => (string)$row['username'],
        'last_seen'  => time(),
        'csrf'       => bin2hex(random_bytes(32)),
    ];
    return 'ok';
}

function adm_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}
