<?php
// Buat / ganti password admin. Jalankan dari terminal: php tools/create-admin.php <username> <password>
// Di shared hosting tanpa SSH: jalankan lokal ke DB hosting, atau buat hash dengan php -r "echo password_hash('...', PASSWORD_DEFAULT);" lalu INSERT lewat phpMyAdmin.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../api/bootstrap.php';
[$self, $user, $pass] = array_pad($argv, 3, null);
if (!$user || !$pass || strlen($pass) < 10) { fwrite(STDERR, "Pakai: php tools/create-admin.php <username> <password min 10 karakter>\n"); exit(1); }
$st = ks_db()->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)');
$st->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);
echo "Admin '$user' siap.\n";
