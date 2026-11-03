<?php
/**
 * Konfigurasi FacilityWatch.
 * Nilai bisa di-override lewat environment variable (DB_HOST, DB_NAME, dst).
 */
return [
    'app_name' => 'FacilityWatch',
    'db' => [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'port'    => getenv('DB_PORT') ?: '3306',
        'name'    => getenv('DB_NAME') ?: 'facilitywatch',
        'user'    => getenv('DB_USER') ?: 'root',
        'pass'    => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'upload_dir'   => __DIR__ . '/../public/uploads',
    'upload_url'   => '/uploads',
    'upload_max'   => 3 * 1024 * 1024, // 3 MB
    'per_page'     => 10,
];
