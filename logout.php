<?php
/**
 * logout.php — Hentikan session dan kembali ke halaman login.
 * Hanya menerima POST + CSRF agar tidak bisa dipicu lewat tautan/GET.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('login.php'), 302);
}

if (!verify_csrf(input_string($_POST, 'csrf_token'))) {
    redirect(url('login.php'), 302);
}

// Kosongkan session
$_SESSION = [];

// Hapus cookie session
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'],
        'domain'   => $params['domain'],
        'secure'   => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

session_destroy();

redirect(url('login.php?logout=1'));
