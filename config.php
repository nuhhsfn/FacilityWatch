<?php
// Konfigurasi via environment variable (atau ubah default di bawah).
return [
    'db_host'    => getenv('DB_HOST') ?: '127.0.0.1',
    'db_name'    => getenv('DB_NAME') ?: 'facilitywatch',
    'db_user'    => getenv('DB_USER') ?: 'root',
    'db_pass'    => getenv('DB_PASS') ?: '',
    'admin_user' => getenv('ADMIN_USER') ?: 'admin',
    'admin_pass' => getenv('ADMIN_PASS') ?: 'admin123', // WAJIB diganti di produksi
];
