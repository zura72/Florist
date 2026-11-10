<?php
// Salin berkas ini menjadi config.php lalu isi nilainya. config.php JANGAN di-commit / dibagikan.
return [
    'db' => [
        'host'    => 'localhost',          // dari panel hosting vendor
        'port'    => 3306,
        'name'    => 'kembang_subuh',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    'midtrans' => [
        'is_production' => false,          // sandbox dulu
        'server_key'    => '',             // SB-Mid-server-xxxx (rahasia, hanya di server)
        'client_key'    => '',             // SB-Mid-client-xxxx (boleh tampil di browser)
    ],
    'shop' => [
        'name'         => 'Kembang Subuh',
        'base_url'     => '',              // mis. https://kembangsubuh.id (untuk callback finish); kosong = otomatis
        // Ongkir per daerah: lihat ks_shipping_zones() di bootstrap.php (bisa ditimpa dengan 'shipping_zones' => [...])
        'timezone'     => 'Asia/Jakarta',
        'demo_payments' => false,       // true hanya untuk uji lokal tanpa kunci Midtrans
    ],
];
