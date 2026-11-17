<?php
/**
 * whatsapp.php — Integrasi WhatsApp Gateway (Fonnte / Wablas)
 * Cirebon Creative Festival 2026
 *
 * Cara pakai:
 *   require_once __DIR__ . '/whatsapp.php';
 *   sendWhatsAppNotification('08123456789', 'Halo dari CCF 2026');
 *
 * Konfigurasi token:
 *   1. Fonnte  : daftar di https://fonnte.com, hubungkan perangkat WhatsApp,
 *                lalu salin token dari menu Device.
 *   2. Wablas  : daftar di https://wablas.com, hubungkan perangkat,
 *                lalu salin token dari menu Device/API.
 *
 * Isi token dengan salah satu cara berikut:
 *   A. Environment variable (disarankan untuk produksi):
 *        WA_API_TOKEN=xxxxxxxxxxxxxxxx
 *        WA_ADMIN_NUMBER=6281234567890
 *        WA_PROVIDER=fonnte
 *   B. Isi langsung variabel $waToken / $waAdminNumber di bawah (khusus lokal).
 *
 * Jangan commit token asli ke repository publik.
 */

declare(strict_types=1);

// Cegah file ini diakses langsung lewat browser.
if (
    PHP_SAPI !== 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)
) {
    http_response_code(403);
    exit('Forbidden');
}

// ---------------------------------------------------------------
// KONFIGURASI
// ---------------------------------------------------------------
$waToken = getenv('WA_API_TOKEN');
if (!is_string($waToken) || trim($waToken) === '') {
    // Fallback lokal. Untuk produksi, gunakan environment variable
    // WA_API_TOKEN agar token tidak tersimpan di dalam source code.
    //
    // PENTING: token asli diletakkan di sini sebagai NILAI variabel.
    // Jangan mengganti nama konstanta 'WA_API_TOKEN' di bawah.
    $waToken = 'kd3aMWoUYhtzmuAV3uoj';
}
define('WA_API_TOKEN', trim($waToken));

$waAdminNumber = getenv('WA_ADMIN_NUMBER');
if (!is_string($waAdminNumber) || trim($waAdminNumber) === '') {
    // Nomor admin penerima notifikasi pendaftaran baru.
    // Format lokal (08...) akan dinormalisasi otomatis ke 628...
    $waAdminNumber = '088220345460';
}
define('WA_ADMIN_NUMBER', trim($waAdminNumber));

$waProvider = strtolower(trim((string) getenv('WA_PROVIDER')));
if (!in_array($waProvider, ['fonnte', 'wablas'], true)) {
    $waProvider = 'fonnte';
}
define('WA_PROVIDER', $waProvider);

$waApiUrl = trim((string) getenv('WA_API_URL'));
if ($waApiUrl === '') {
    // Untuk Wablas, sesuaikan dengan domain akun Anda,
    // contoh: https://pati.wablas.com/api/send-message
    $waApiUrl = WA_PROVIDER === 'wablas'
        ? 'https://api.wablas.com/api/send-message'
        : 'https://api.fonnte.com/send';
}
define('WA_API_URL', $waApiUrl);

$waEnabled = getenv('WA_ENABLED');
define(
    'WA_ENABLED',
    $waEnabled === false || trim((string) $waEnabled) === ''
        ? true
        : filter_var($waEnabled, FILTER_VALIDATE_BOOLEAN)
);

// Timeout dibuat pendek agar proses pendaftaran tidak terlalu lama menunggu.
define('WA_TIMEOUT', max(3, (int) (getenv('WA_TIMEOUT') ?: 10)));
define('WA_DEBUG', filter_var(getenv('WA_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN));

// ---------------------------------------------------------------
// Helper nomor telepon
// ---------------------------------------------------------------
/**
 * Normalisasi nomor Indonesia ke format internasional tanpa tanda +.
 * Contoh: 0812-3456-7890 -> 6281234567890
 */
function normalize_whatsapp_number(string $number): string
{
    $digits = (string) preg_replace('/\D+/', '', $number);

    if ($digits === '') {
        return '';
    }

    // 0062... -> 62...
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }

    // 08... -> 628...
    if (str_starts_with($digits, '0')) {
        $digits = '62' . substr($digits, 1);
    } elseif (str_starts_with($digits, '8')) {
        // 8... -> 628...
        $digits = '62' . $digits;
    }

    // Panjang nomor internasional yang wajar.
    if (strlen($digits) < 9 || strlen($digits) > 15) {
        return '';
    }

    return $digits;
}

// ---------------------------------------------------------------
// Fungsi utama
// ---------------------------------------------------------------
/**
 * Kirim notifikasi WhatsApp melalui Fonnte atau Wablas.
 *
 * @return array{
 *     ok: bool,
 *     skipped: bool,
 *     http_code: int,
 *     response: mixed,
 *     error: string|null
 * }
 */
function sendWhatsAppNotification(string $target, string $message): array
{
    $result = [
        'ok'        => false,
        'skipped'   => false,
        'http_code' => 0,
        'response'  => null,
        'error'     => null,
    ];

    if (!WA_ENABLED) {
        $result['skipped'] = true;
        $result['error']   = 'Notifikasi WhatsApp dinonaktifkan.';
        return $result;
    }

    if (WA_API_TOKEN === '') {
        $result['skipped'] = true;
        $result['error']   = 'WA_API_TOKEN belum diisi.';
        return $result;
    }

    $phone = normalize_whatsapp_number($target);
    if ($phone === '') {
        $result['skipped'] = true;
        $result['error']   = 'Nomor tujuan WhatsApp tidak valid atau kosong.';
        return $result;
    }

    $message = trim($message);
    if ($message === '') {
        $result['skipped'] = true;
        $result['error']   = 'Pesan WhatsApp kosong.';
        return $result;
    }

    if (!function_exists('curl_init')) {
        $result['error'] = 'Ekstensi PHP cURL tidak aktif.';
        error_log('[WA] ' . $result['error']);
        return $result;
    }

    // Fonnte memakai field "target"; Wablas memakai "phone".
    $payload = WA_PROVIDER === 'wablas'
        ? ['phone' => $phone, 'message' => $message]
        : ['target' => $phone, 'message' => $message];

    $ch = curl_init(WA_API_URL);
    if ($ch === false) {
        $result['error'] = 'Gagal menginisialisasi cURL.';
        error_log('[WA] ' . $result['error']);
        return $result;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => WA_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => [
            'Authorization: ' . WA_API_TOKEN,
            'Accept: application/json',
        ],
    ]);

    $rawResponse = curl_exec($ch);
    $curlError   = curl_error($ch);
    $httpCode    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result['http_code'] = $httpCode;

    if ($rawResponse === false) {
        $result['error'] = $curlError !== '' ? $curlError : 'Permintaan cURL gagal.';
        error_log('[WA] Gagal mengirim ke ' . $phone . ': ' . $result['error']);
        return $result;
    }

    // Sebagian gateway mengirim BOM UTF-8 di awal respons JSON.
    $rawBody = (string) $rawResponse;
    if (str_starts_with($rawBody, "\xEF\xBB\xBF")) {
        $rawBody = substr($rawBody, 3);
    }

    $decoded = json_decode($rawBody, true);
    $result['response'] = is_array($decoded) ? $decoded : $rawBody;

    $providerOk = false;
    if (is_array($decoded)) {
        $status = $decoded['status'] ?? null;

        $providerOk = $status === true
            || $status === 1
            || $status === 'true'
            || $status === 'success'
            || $status === 'ok'
            || ($decoded['success'] ?? false) === true;
    }

    $result['ok'] = $httpCode >= 200 && $httpCode < 300 && $providerOk;

    if (!$result['ok']) {
        $result['error'] = $curlError !== ''
            ? $curlError
            : 'Gateway menolak permintaan (HTTP ' . $httpCode . ').';

        error_log(
            '[WA] Gagal mengirim ke ' . $phone
            . ' (HTTP ' . $httpCode . '): '
            . substr((string) $rawResponse, 0, 500)
        );
    } elseif (WA_DEBUG) {
        error_log('[WA] Terkirim ke ' . $phone . ' (HTTP ' . $httpCode . ').');
    }

    return $result;
}
