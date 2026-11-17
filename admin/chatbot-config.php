<?php
/**
 * admin/chatbot-config.php — Konfigurasi AI Chatbot
 * Cirebon Creative Festival 2026
 *
 * Halaman admin untuk:
 *   - Memilih provider AI (Gemini, OpenAI, Custom/Local).
 *   - Menyimpan API key, endpoint, model, dan status aktif.
 *   - Menguji koneksi provider melalui service Node.js.
 *
 * Perubahan disimpan ke tabel chatbot_configurations. Service Node.js
 * membaca baris aktif dari tabel tersebut setiap kali membutuhkan
 * fallback AI, sehingga perubahan langsung berlaku tanpa restart.
 */

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

$user = require_role('admin');

require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../chatbot-config.php';

$providerDefaults = [
    'gemini' => [
        'label'    => 'Google Gemini',
        'endpoint' => 'https://generativelanguage.googleapis.com/v1beta',
        'model'    => 'models/gemini-2.5-flash',
    ],
    'openai' => [
        'label'    => 'OpenAI',
        'endpoint' => 'https://api.openai.com/v1/chat/completions',
        'model'    => 'gpt-4o-mini',
    ],
    'custom' => [
        'label'    => 'Custom / Local (OpenAI-compatible)',
        'endpoint' => 'http://localhost:11434/v1/chat/completions',
        'model'    => 'llama3.1',
    ],
];

$allowedProviders = array_keys($providerDefaults);
$pageUrl = url('admin/chatbot-config.php');

/**
 * Ambil konfigurasi yang sedang diedit. Prioritaskan baris aktif.
 */
function fetch_chatbot_config(PDO $pdo): ?array
{
    $stmt = $pdo->query(
        'SELECT id, provider_name, api_key, endpoint_url, model_name, is_active,
                updated_by, created_at, updated_at
         FROM chatbot_configurations
         ORDER BY is_active DESC, updated_at DESC, id ASC
         LIMIT 1'
    );

    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

/**
 * Kirim JSON ke service Node.js dan kembalikan array respons.
 * Dipakai untuk tombol "Uji Koneksi" agar API key tidak pernah
 * dikirim langsung dari browser ke provider AI.
 */
function chatbot_config_post_json(string $url, array $payload, string $token): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($body === false) {
        return ['ok' => false, 'message' => 'Payload konfigurasi tidak dapat diproses.'];
    }

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log('[CHATBOT CONFIG TEST] cURL: ' . $curlError);

            return [
                'ok'      => false,
                'message' => 'Layanan chatbot tidak dapat dihubungi. Pastikan Node.js berjalan di port 3000.',
            ];
        }

        $data = json_decode((string) $response, true);

        if (!is_array($data)) {
            error_log('[CHATBOT CONFIG TEST] Respons bukan JSON: ' . mb_substr((string) $response, 0, 300));

            return [
                'ok'      => false,
                'message' => 'Respons layanan chatbot tidak valid (HTTP ' . $httpCode . ').',
            ];
        }

        $data['http_code'] = $httpCode;

        return $data;
    }

    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", $headers),
            'content'       => $body,
            'timeout'       => 25,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        error_log('[CHATBOT CONFIG TEST] stream: gagal menghubungi layanan.');

        return [
            'ok'      => false,
            'message' => 'Layanan chatbot tidak dapat dihubungi. Pastikan Node.js berjalan di port 3000.',
        ];
    }

    $data = json_decode((string) $response, true);

    if (!is_array($data)) {
        return ['ok' => false, 'message' => 'Respons layanan chatbot tidak valid.'];
    }

    return $data;
}

$configTableError = null;
$config = null;

try {
    $config = fetch_chatbot_config($pdo);
} catch (PDOException $e) {
    error_log('[CHATBOT CONFIG] ' . $e->getMessage());
    $configTableError = 'Tabel chatbot_configurations belum tersedia. Jalankan database.sql atau migrasi tabel terlebih dahulu.';
}

// ---------------------------------------------------------------
// Aksi POST: simpan konfigurasi atau uji koneksi
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input_string($_POST, 'action', '');

    if (!verify_csrf(input_string($_POST, 'csrf_token', ''))) {
        flash_set('error', 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
        redirect($pageUrl);
    }

    if (!in_array($action, ['save_config', 'test_connection'], true)) {
        flash_set('error', 'Aksi yang dikirim tidak valid.');
        redirect($pageUrl);
    }

    $provider = input_string($_POST, 'provider_name', 'gemini');

    if (!in_array($provider, $allowedProviders, true)) {
        flash_set('error', 'Provider AI tidak valid.');
        redirect($pageUrl);
    }

    $endpoint         = trim(input_string($_POST, 'endpoint_url', ''));
    $model            = trim(input_string($_POST, 'model_name', ''));
    $apiKeyInput      = trim(input_string($_POST, 'api_key', ''));
    $originalProvider = input_string($_POST, 'original_provider', $provider);
    $clearApiKey      = isset($_POST['clear_api_key']);
    $isActive         = isset($_POST['is_active']) ? 1 : 0;
    $defaults         = $providerDefaults[$provider];

    if ($endpoint === '') {
        $endpoint = $defaults['endpoint'];
    }

    if ($model === '') {
        $model = $defaults['model'];
    }

    if (mb_strlen($endpoint) > 500) {
        flash_set('error', 'URL endpoint maksimal 500 karakter.');
        redirect($pageUrl);
    }

    if (mb_strlen($model) > 150) {
        flash_set('error', 'Nama model maksimal 150 karakter.');
        redirect($pageUrl);
    }

    if (mb_strlen($apiKeyInput) > 500) {
        flash_set('error', 'API key maksimal 500 karakter.');
        redirect($pageUrl);
    }

    $scheme = strtolower((string) parse_url($endpoint, PHP_URL_SCHEME));

    if (
        filter_var($endpoint, FILTER_VALIDATE_URL) === false
        || !in_array($scheme, ['http', 'https'], true)
    ) {
        flash_set('error', 'URL endpoint tidak valid. Gunakan URL lengkap dengan http:// atau https://.');
        redirect($pageUrl);
    }

    // Jangan membawa API key lama ke provider yang berbeda.
    $providerChanged = $originalProvider !== $provider;
    $existingKey = trim((string) ($config['api_key'] ?? ''));
    $effectiveKey = ($clearApiKey || $providerChanged)
        ? ''
        : ($apiKeyInput !== '' ? $apiKeyInput : $existingKey);

    if (
        $action === 'save_config'
        && $isActive === 1
        && in_array($provider, ['gemini', 'openai'], true)
        && $effectiveKey === ''
    ) {
        flash_set('error', 'API key wajib diisi saat mengaktifkan provider Gemini atau OpenAI.');
        redirect($pageUrl);
    }

    if ($action === 'test_connection') {
        if (in_array($provider, ['gemini', 'openai'], true) && $effectiveKey === '') {
            flash_set('error', 'Isi API key terlebih dahulu untuk menguji provider ini.');
            redirect($pageUrl);
        }

        $token = chatbot_auth_token($user);

        if ($token === '') {
            flash_set('error', 'Token admin untuk layanan chatbot tidak dapat dibuat.');
            redirect($pageUrl);
        }

        $result = chatbot_config_post_json(
            CHATBOT_CONFIG_TEST_URL,
            [
                'provider_name' => $provider,
                'api_key'       => $effectiveKey,
                'endpoint_url'  => $endpoint,
                'model_name'    => $model,
            ],
            $token
        );

        if (($result['ok'] ?? false) === true) {
            $latency = isset($result['latency_ms']) ? (int) $result['latency_ms'] : 0;
            $sample  = trim((string) ($result['sample'] ?? ''));
            $message = 'Koneksi berhasil ke ' . $defaults['label'] . '.';

            if ($latency > 0) {
                $message .= ' Latensi ' . $latency . ' ms.';
            }

            if ($sample !== '') {
                $message .= ' Respons uji: "' . mb_substr($sample, 0, 120) . '"';
            }

            flash_set('success', $message);
        } else {
            $message = trim((string) ($result['message'] ?? $result['reply'] ?? ''));
            flash_set('error', 'Uji koneksi gagal: ' . ($message !== '' ? $message : 'Provider tidak merespons.'));
        }

        redirect($pageUrl);
    }

    try {
        $pdo->beginTransaction();

        if ($config !== null) {
            $configId = (int) $config['id'];

            $stmt = $pdo->prepare(
                'UPDATE chatbot_configurations
                 SET provider_name = ?, api_key = ?, endpoint_url = ?, model_name = ?,
                     is_active = ?, updated_by = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $provider,
                $effectiveKey !== '' ? $effectiveKey : null,
                $endpoint,
                $model,
                $isActive,
                (int) $user['id'],
                $configId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO chatbot_configurations
                    (provider_name, api_key, endpoint_url, model_name, is_active, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $provider,
                $effectiveKey !== '' ? $effectiveKey : null,
                $endpoint,
                $model,
                $isActive,
                (int) $user['id'],
            ]);
            $configId = (int) $pdo->lastInsertId();
        }

        // Hanya konfigurasi yang sedang diedit yang boleh aktif.
        $deactivate = $pdo->prepare('UPDATE chatbot_configurations SET is_active = 0 WHERE id <> ?');
        $deactivate->execute([$configId]);

        $pdo->commit();

        error_log(sprintf(
            '[CHATBOT CONFIG] Admin #%d (%s) menyimpan provider=%s model=%s active=%d.',
            (int) $user['id'],
            (string) ($user['email'] ?? '-'),
            $provider,
            $model,
            $isActive
        ));

        flash_set('success', 'Konfigurasi AI Chatbot berhasil disimpan. Perubahan langsung dipakai oleh layanan Node.js.');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('[CHATBOT CONFIG SAVE] ' . $e->getMessage());
        flash_set('error', 'Konfigurasi gagal disimpan. Pastikan tabel chatbot_configurations sudah dibuat.');
    }

    redirect($pageUrl);
}

$flashSuccess = flash_get('success');
$flashError   = flash_get('error');

if ($flashError === null && $configTableError !== null) {
    $flashError = $configTableError;
}

$provider = (string) ($config['provider_name'] ?? 'gemini');
if (!in_array($provider, $allowedProviders, true)) {
    $provider = 'gemini';
}

$endpoint = trim((string) ($config['endpoint_url'] ?? ''));
if ($endpoint === '') {
    $endpoint = $providerDefaults[$provider]['endpoint'];
}

$model = trim((string) ($config['model_name'] ?? ''));
if ($model === '') {
    $model = $providerDefaults[$provider]['model'];
}

$isActive      = (int) ($config['is_active'] ?? 0) === 1;
$hasApiKey     = trim((string) ($config['api_key'] ?? '')) !== '';
$lastUpdated   = $config['updated_at'] ?? null;
$providerLabel = $providerDefaults[$provider]['label'];

$providerDefaultsJson = json_encode(
    array_map(
        static fn(array $item): array => [
            'endpoint' => $item['endpoint'],
            'model'    => $item['model'],
        ],
        $providerDefaults
    ),
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
);

$pageTitle = 'Konfigurasi AI Chatbot';
$navItems = [
    ['label' => 'Dashboard',      'href' => url('admin/dashboard.php'), 'active' => false],
    ['label' => 'Pendaftaran',    'href' => url('admin/dashboard.php') . '#pendaftaran', 'active' => false],
    ['label' => 'Manajemen User', 'href' => url('admin/users.php'), 'active' => false],
    ['label' => 'Chatbot AI',     'href' => $pageUrl, 'active' => true],
];

require __DIR__ . '/../partials/dashboard-header.php';
?>

<main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">

    <!-- ============================= HEADING ============================= -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a href="<?= e(url('admin/dashboard.php')) ?>"
               class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 transition hover:text-amber-700">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
                Kembali ke dashboard
            </a>

            <p class="mt-5 inline-flex items-center gap-3 text-xs font-bold uppercase tracking-[0.25em] text-amber-700">
                <span class="h-px w-8 bg-amber-500"></span>
                Pengaturan Chatbot
            </p>
            <h1 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">
                Konfigurasi AI Chatbot
            </h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">
                Hubungkan Tanya CCF 2026 ke provider AI pilihan Anda. Konfigurasi aktif dibaca langsung
                oleh service Node.js setiap ada pertanyaan yang tidak dikenali aturan chatbot.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex w-fit items-center gap-2 rounded-full border px-3.5 py-1.5 text-xs font-semibold shadow-sm <?= $isActive ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600' ?>">
                <span class="h-2 w-2 rounded-full <?= $isActive ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span>
                <?= $isActive ? 'AI Aktif' : 'AI Nonaktif' ?>
            </span>
            <span class="inline-flex w-fit items-center rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-600 shadow-sm">
                <?= e($providerLabel) ?>
            </span>
        </div>
    </div>

    <!-- ============================= FLASH ============================= -->
    <?php if ($flashSuccess !== null): ?>
        <div role="status" class="mt-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                </svg>
            </span>
            <p class="pt-1 text-sm font-medium text-emerald-800"><?= e($flashSuccess) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($flashError !== null): ?>
        <div role="alert" class="mt-6 flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-red-100 text-red-600">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                </svg>
            </span>
            <p class="pt-1 text-sm font-medium text-red-800"><?= e($flashError) ?></p>
        </div>
    <?php endif; ?>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">

        <!-- ============================= FORM ============================= -->
        <section class="lg:col-span-2">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-4 sm:px-6">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-slate-900 text-amber-300">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c.251.011.502.028.75.05m-.75-.05a48.5 48.5 0 00-3.478.397m12.728 0a48.667 48.667 0 00-3.478-.397m3.478.397v5.714a2.25 2.25 0 00.659 1.591L19 14.5m-9.25-6.75v5.714a2.25 2.25 0 01-.659 1.591L5 19.25m14 0l-4.091-4.091a2.25 2.25 0 00-.659-1.591V7.854m0 0a48.667 48.667 0 00-3.478-.397m3.478.397a48.5 48.5 0 01-3.478.397"/>
                            </svg>
                        </span>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Pengaturan Provider</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Simpan kredensial dan model yang dipakai layanan Node.js.</p>
                        </div>
                    </div>
                </div>

                <form method="post" action="<?= e($pageUrl) ?>" autocomplete="off" class="space-y-5 p-5 sm:p-6">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="original_provider" value="<?= e($provider) ?>">

                    <div>
                        <label for="provider_name" class="block text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">
                            AI Provider
                        </label>
                        <select id="provider_name" name="provider_name" required
                                class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100">
                            <?php foreach ($providerDefaults as $providerKey => $providerItem): ?>
                                <option value="<?= e($providerKey) ?>" <?= $provider === $providerKey ? 'selected' : '' ?>>
                                    <?= e($providerItem['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1.5 text-xs text-slate-500">
                            Gemini dan OpenAI memakai API resmi. Custom/Local cocok untuk Ollama, LM Studio, atau endpoint OpenAI-compatible.
                        </p>
                    </div>

                    <div>
                        <label for="api_key" class="block text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">
                            API Key
                        </label>
                        <input id="api_key" name="api_key" type="password" maxlength="500" value=""
                               autocomplete="new-password" spellcheck="false"
                               placeholder="<?= $hasApiKey ? '•••••••••••• (tersimpan)' : 'Masukkan API key provider' ?>"
                               class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-amber-400 focus:ring-4 focus:ring-amber-100">

                        <?php if ($hasApiKey): ?>
                            <div class="mt-2 flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                                </svg>
                                <p class="text-xs leading-relaxed text-emerald-800">
                                    API key sudah tersimpan dan tidak ditampilkan. Biarkan kolom kosong untuk mempertahankannya.
                                </p>
                            </div>

                            <label class="mt-2 inline-flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-600">
                                <input type="checkbox" name="clear_api_key" value="1"
                                       class="h-4 w-4 rounded border-slate-300 text-red-600 focus:ring-red-500">
                                Hapus API key tersimpan
                            </label>
                        <?php endif; ?>

                        <p class="mt-1.5 text-xs text-slate-500">
                            Jika mengganti provider, isi API key baru. API key lama tidak digunakan untuk provider berbeda.
                        </p>
                    </div>

                    <div>
                        <label for="endpoint_url" class="block text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">
                            URL Endpoint
                        </label>
                        <input id="endpoint_url" name="endpoint_url" type="url" maxlength="500"
                               value="<?= e($endpoint) ?>" required
                               class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-amber-400 focus:ring-4 focus:ring-amber-100">
                        <p class="mt-1.5 text-xs text-slate-500">
                            Gemini: base URL <code class="rounded bg-slate-100 px-1 py-0.5 text-[11px]">/v1beta</code>.
                            OpenAI/Custom: URL <code class="rounded bg-slate-100 px-1 py-0.5 text-[11px]">/chat/completions</code> lengkap.
                        </p>
                    </div>

                    <div>
                        <label for="model_name" class="block text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">
                            Nama Model
                        </label>
                        <input id="model_name" name="model_name" type="text" maxlength="150"
                               value="<?= e($model) ?>" required spellcheck="false"
                               class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-amber-400 focus:ring-4 focus:ring-amber-100">
                        <p class="mt-1.5 text-xs text-slate-500">
                            Contoh: <code class="rounded bg-slate-100 px-1 py-0.5 text-[11px]">models/gemini-2.5-flash</code>,
                            <code class="rounded bg-slate-100 px-1 py-0.5 text-[11px]">gpt-4o-mini</code>,
                            <code class="rounded bg-slate-100 px-1 py-0.5 text-[11px]">llama3.1</code>.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input type="checkbox" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>
                                   class="mt-0.5 h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                            <span>
                                <span class="block text-sm font-bold text-slate-900">Aktifkan AI provider</span>
                                <span class="mt-0.5 block text-xs leading-relaxed text-slate-500">
                                    Jika aktif, pertanyaan yang tidak dikenali aturan chatbot akan dijawab oleh provider AI.
                                    Jika nonaktif, chatbot tetap memakai jawaban rule-based.
                                </span>
                            </span>
                        </label>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-slate-500">Uji koneksi tidak menyimpan konfigurasi.</p>
                        <div class="flex flex-col gap-2 sm:flex-row-reverse">
                            <button type="submit" name="action" value="save_config"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-500 focus-visible:ring-offset-2">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                </svg>
                                Simpan Konfigurasi
                            </button>
                            <button type="submit" name="action" value="test_connection"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm transition hover:border-amber-300 hover:bg-amber-50 hover:text-amber-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12 18.75h.008v.008H12v-.008z"/>
                                </svg>
                                Uji Koneksi
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <!-- ============================= INFO ============================= -->
        <aside class="space-y-4">

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Status Saat Ini</p>
                <div class="mt-3 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-lg font-extrabold tracking-tight text-slate-900">
                            <?= $isActive ? 'AI Aktif' : 'AI Nonaktif' ?>
                        </p>
                        <p class="mt-1 text-xs text-slate-500"><?= e($providerLabel) ?></p>
                    </div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wide ring-1 ring-inset <?= $isActive ? 'bg-emerald-100 text-emerald-700 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-slate-200' ?>">
                        <?= $isActive ? 'ON' : 'OFF' ?>
                    </span>
                </div>

                <dl class="mt-4 space-y-3 border-t border-slate-100 pt-4 text-xs">
                    <div>
                        <dt class="font-semibold text-slate-500">Model</dt>
                        <dd class="mt-0.5 break-all font-mono text-slate-800"><?= e($model) ?></dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Endpoint</dt>
                        <dd class="mt-0.5 break-all font-mono text-slate-800"><?= e($endpoint) ?></dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">API Key</dt>
                        <dd class="mt-0.5 font-semibold text-slate-800">
                            <?= $hasApiKey ? 'Tersimpan (disembunyikan)' : 'Belum diatur' ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500">Terakhir diperbarui</dt>
                        <dd class="mt-0.5 text-slate-800">
                            <?= e($lastUpdated !== null ? format_tanggal((string) $lastUpdated, true) : '-') ?>
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-amber-800">Nilai Default</p>
                <ul class="mt-3 space-y-3 text-xs leading-relaxed text-amber-900">
                    <?php foreach ($providerDefaults as $providerItem): ?>
                        <li>
                            <p class="font-bold"><?= e($providerItem['label']) ?></p>
                            <p class="mt-0.5 break-all font-mono text-[11px]"><?= e($providerItem['endpoint']) ?></p>
                            <p class="break-all font-mono text-[11px]"><?= e($providerItem['model']) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-bold text-slate-900">Catatan keamanan</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                            API key disimpan di database dan hanya ditampilkan dalam bentuk tersembunyi.
                            Gunakan HTTPS di produksi, batasi akses admin, dan pertimbangkan enkripsi
                            atau secret manager untuk deployment publik.
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Cara Kerja</p>
                <ol class="mt-3 space-y-2 text-xs leading-relaxed text-slate-600">
                    <li><span class="font-bold text-slate-900">1.</span> Simpan konfigurasi di halaman ini.</li>
                    <li><span class="font-bold text-slate-900">2.</span> Node.js membaca baris aktif dari tabel <code class="rounded bg-slate-100 px-1 py-0.5 text-[11px]">chatbot_configurations</code> setiap ada pertanyaan fallback.</li>
                    <li><span class="font-bold text-slate-900">3.</span> Perubahan langsung berlaku tanpa restart service.</li>
                    <li><span class="font-bold text-slate-900">4.</span> Jika AI gagal, chatbot kembali ke jawaban rule-based.</li>
                </ol>
            </div>

        </aside>
    </div>
</main>

<script>
(() => {
    const provider = document.getElementById('provider_name');
    const endpoint = document.getElementById('endpoint_url');
    const model = document.getElementById('model_name');
    const defaults = <?= $providerDefaultsJson ?: '{}' ?>;

    if (!provider || !endpoint || !model) {
        return;
    }

    const knownEndpoints = Object.values(defaults).map((item) => item.endpoint);
    const knownModels = Object.values(defaults).map((item) => item.model);

    provider.addEventListener('change', () => {
        const selected = defaults[provider.value];

        if (!selected) {
            return;
        }

        const currentEndpoint = endpoint.value.trim();
        const currentModel = model.value.trim();

        if (currentEndpoint === '' || knownEndpoints.includes(currentEndpoint)) {
            endpoint.value = selected.endpoint;
        }

        if (currentModel === '' || knownModels.includes(currentModel)) {
            model.value = selected.model;
        }
    });
})();
</script>

<?php require __DIR__ . '/../partials/dashboard-footer.php'; ?>
