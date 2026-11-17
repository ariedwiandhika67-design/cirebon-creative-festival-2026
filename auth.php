<?php
/**
 * auth.php — Session guard & helper bersama
 * Cirebon Creative Festival 2026
 *
 * Dipakai oleh: admin/dashboard.php, umkm/dashboard.php, logout.php
 */

declare(strict_types=1);

define('CCF_APP', true);

// Samakan zona waktu tampilan dengan zona waktu festival (WIB)
date_default_timezone_set('Asia/Jakarta');

// ---------------------------------------------------------------
// Session
// ---------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // 'secure' => true, // aktifkan saat situs sudah memakai HTTPS
    ]);
    session_start();
}

// Halaman dashboard berisi data pribadi — jangan di-cache browser
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ---------------------------------------------------------------
// Base URL aplikasi (mendukung subfolder maupun virtual host)
// ---------------------------------------------------------------
$authDir    = str_replace('\\', '/', __DIR__);
$docRootRaw = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
$docRoot    = $docRootRaw !== '' ? str_replace('\\', '/', (string) realpath($docRootRaw)) : '';
$basePath   = '';

if ($docRoot !== '' && str_starts_with($authDir, $docRoot)) {
    $basePath = substr($authDir, strlen($docRoot));

    if ($basePath !== '' && $basePath[0] !== '/') {
        $basePath = '/' . $basePath;
    }
}

define('APP_BASE', rtrim($basePath, '/'));

// ---------------------------------------------------------------
// Helper umum
// ---------------------------------------------------------------
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return APP_BASE . '/' . ltrim($path, '/');
}

/**
 * URL absolut (dengan skema + host) untuk tautan di email/WhatsApp.
 */
function absolute_url(string $path = ''): string
{
    $https     = (string) ($_SERVER['HTTPS'] ?? '');
    $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $scheme    = ($https !== '' && $https !== 'off') || $forwarded === 'https' ? 'https' : 'http';
    $host      = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

    return $scheme . '://' . $host . url($path);
}

/**
 * Ambil nilai string dari $_GET/$_POST dengan aman.
 * Array atau tipe lain akan dikembalikan sebagai nilai default.
 */
function input_string(array $source, string $key, string $default = ''): string
{
    $value = $source[$key] ?? $default;

    return is_string($value) ? $value : $default;
}

function redirect(string $location, int $status = 303): never
{
    header('Location: ' . $location, true, $status);
    exit;
}

function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;

    if (!is_array($user) || !isset($user['id'], $user['role'])) {
        return null;
    }

    if (!in_array($user['role'], ['admin', 'umkm'], true)) {
        return null;
    }

    return $user;
}

function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        redirect(url('login.php'), 302);
    }

    return $user;
}

function dashboard_url(string $role): string
{
    return $role === 'admin'
        ? url('admin/dashboard.php')
        : url('umkm/dashboard.php');
}

function require_role(string $role): array
{
    $user = require_login();

    if ($user['role'] !== $role) {
        redirect(dashboard_url((string) $user['role']), 302);
    }

    return $user;
}

// ---------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token)
        && $token !== ''
        && hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token);
}

// ---------------------------------------------------------------
// Flash message
// ---------------------------------------------------------------
function flash_set(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);

    return is_string($message) ? $message : null;
}

// ---------------------------------------------------------------
// Format & UI helper
// ---------------------------------------------------------------
function format_tanggal(?string $datetime, bool $withTime = false): string
{
    if ($datetime === null || $datetime === '') {
        return '-';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return $datetime;
    }

    $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    $result = date('d', $timestamp) . ' ' . $bulan[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);

    if ($withTime) {
        $result .= ' · ' . date('H:i', $timestamp) . ' WIB';
    }

    return $result;
}

function status_badge_classes(string $status): string
{
    return match ($status) {
        'Diterima' => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
        'Ditolak'  => 'bg-red-100 text-red-700 ring-red-200',
        default    => 'bg-amber-100 text-amber-800 ring-amber-200',
    };
}

function uploaded_image_url(?string $relativePath): ?string
{
    if ($relativePath === null || $relativePath === '') {
        return null;
    }

    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

    // Hanya izinkan file dari folder uploads
    if (!str_starts_with($relativePath, 'uploads/')) {
        return null;
    }

    if (!is_file(__DIR__ . '/' . $relativePath)) {
        return null;
    }

    return url($relativePath);
}
