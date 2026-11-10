<?php
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';

adm_boot();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    adm_redirect(adm_user() ? 'index.php' : 'login.php');
}
adm_require_csrf();
adm_logout();
// Sesi baru kosong hanya untuk pesan "sudah keluar".
adm_session_start();
adm_flash('info', 'Kamu sudah keluar. Sampai jumpa!');
adm_redirect('login.php');
