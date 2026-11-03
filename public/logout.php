<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

if (is_post()) {
    csrf_verify();
    unset($_SESSION['user']);
    session_regenerate_id(true);
    flash('success', 'Anda telah keluar.');
}
redirect('/');
