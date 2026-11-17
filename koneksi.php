<?php
/**
 * koneksi.php
 * Koneksi database terpusat untuk Cirebon Creative Festival 2026.
 *
 * Cara pakai:
 *   require_once __DIR__ . '/koneksi.php';
 *   $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
 *   $stmt->execute([':email' => $email]);
 *
 * Catatan: jangan pernah menampilkan $e->getMessage() ke pengguna.
 */

declare(strict_types=1);

// Zona waktu aplikasi & database (WIB)
date_default_timezone_set('Asia/Jakarta');

// ---------------------------------------------------------------
// Konfigurasi database (sesuaikan untuk server produksi)
// ---------------------------------------------------------------
const DB_HOST    = 'localhost';
const DB_PORT    = 3306;
const DB_NAME    = 'db_umkm_festival';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

// true = tampilkan detail error (hanya untuk development)
const DB_DEBUG   = false;

// ---------------------------------------------------------------
// Membuat koneksi PDO
// ---------------------------------------------------------------
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    DB_HOST,
    DB_PORT,
    DB_NAME,
    DB_CHARSET
);

$options = [
    // Lempar exception saat query gagal, jangan diam-diam return false
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Hasil fetch default berupa array asosiatif
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Gunakan prepared statement asli MySQL (lebih aman dari SQL injection)
    PDO::ATTR_EMULATE_PREPARES   => false,
    // Jangan ubah tipe data secara otomatis
    PDO::ATTR_STRINGIFY_FETCHES  => false,
    // Koneksi persisten dimatikan agar tidak menumpuk koneksi
    PDO::ATTR_PERSISTENT         => false,
    // Timeout koneksi (detik)
    PDO::ATTR_TIMEOUT            => 5,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // Samakan zona waktu session database dengan WIB (UTC+7)
    try {
        $pdo->exec("SET time_zone = '+07:00'");
    } catch (PDOException $e) {
        error_log('[DB TIMEZONE] ' . $e->getMessage());
    }
} catch (PDOException $e) {
    // Catat detail asli ke log server (aman, tidak terlihat pengguna)
    error_log('[DB ERROR] ' . $e->getMessage());

    http_response_code(500);

    // Endpoint API membutuhkan respons JSON, bukan teks biasa.
    if (defined('CCF_API_JSON') && CCF_API_JSON) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok'    => false,
            'reply' => 'Maaf, layanan database sedang tidak tersedia. Silakan coba beberapa saat lagi.',
            'error' => 'database_unavailable',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    header('Content-Type: text/plain; charset=UTF-8');

    if (DB_DEBUG) {
        // Mode development: tampilkan pesan asli untuk memudahkan debugging
        exit('Koneksi database gagal: ' . $e->getMessage());
    }

    // Mode produksi: pesan generik
    exit('Maaf, layanan sedang tidak tersedia. Silakan coba beberapa saat lagi.');
}
