<?php
/**
 * chatbot-config.php — Konfigurasi jembatan PHP → Node.js chatbot service
 * Cirebon Creative Festival 2026
 *
 * File ini TIDAK berisi logika chatbot. Logika utama berada di
 * chatbot-service/server.js (Node.js/Express).
 *
 * Tugas file ini:
 *   1. Menyimpan URL endpoint Node.js.
 *   2. Membuat signed token berisi user ID + role agar Node.js dapat
 *      menampilkan data yang sesuai tanpa mengakses session PHP.
 */

declare(strict_types=1);

if (!defined('CCF_CHATBOT_CONFIG')) {
    define('CCF_CHATBOT_CONFIG', true);
}

// Cegah akses langsung lewat browser.
if (
    PHP_SAPI !== 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)
) {
    http_response_code(403);
    exit('Forbidden');
}

// URL microservice Node.js.
// Produksi: gunakan reverse proxy, misalnya https://domain.com/chatbot/api/chat
if (!defined('CHATBOT_SERVICE_URL')) {
    define(
        'CHATBOT_SERVICE_URL',
        getenv('CHATBOT_SERVICE_URL') ?: 'http://localhost:3000/api/chat'
    );
}

// Endpoint uji koneksi provider AI (dipakai halaman admin).
if (!defined('CHATBOT_CONFIG_TEST_URL')) {
    define(
        'CHATBOT_CONFIG_TEST_URL',
        getenv('CHATBOT_CONFIG_TEST_URL') ?: 'http://localhost:3000/api/chatbot-config/test'
    );
}

// HARUS sama dengan CHATBOT_SHARED_SECRET di chatbot-service/.env
// Ganti dengan string acak yang kuat untuk produksi.
if (!defined('CHATBOT_SHARED_SECRET')) {
    define(
        'CHATBOT_SHARED_SECRET',
        getenv('CHATBOT_SHARED_SECRET') ?: 'ccf-chatbot-dev-secret-change-me'
    );
}

// Masa berlaku token (detik). Default 8 jam.
if (!defined('CHATBOT_TOKEN_TTL')) {
    define('CHATBOT_TOKEN_TTL', 8 * 60 * 60);
}

/**
 * Base64 URL-safe tanpa padding.
 */
function chatbot_base64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

/**
 * Buat signed token untuk Node.js.
 * Token hanya dibuat untuk user login; guest mengirim request tanpa token.
 */
function chatbot_auth_token(?array $user): string
{
    if ($user === null || !isset($user['id'], $user['role'])) {
        return '';
    }

    if (!in_array($user['role'], ['admin', 'umkm'], true)) {
        return '';
    }

    $payload = [
        'uid'  => (int) $user['id'],
        'role' => (string) $user['role'],
        'name' => (string) ($user['nama_lengkap'] ?? ''),
        'exp'  => time() + CHATBOT_TOKEN_TTL,
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return '';
    }

    $encoded   = chatbot_base64url($json);
    $signature = hash_hmac('sha256', $encoded, CHATBOT_SHARED_SECRET);

    return $encoded . '.' . $signature;
}
