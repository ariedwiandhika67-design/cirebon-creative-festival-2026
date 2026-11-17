<?php
/**
 * umkm/form-pendaftaran.php — Form pendaftaran tenant UMKM
 * Cirebon Creative Festival 2026
 *
 * Alur:
 *   1. Guard session + role umkm
 *   2. Pastikan profil UMKM ada dan belum punya pendaftaran aktif
 *   3. Validasi deskripsi + foto produk
 *   4. Simpan ke tabel pendaftaran, lalu kembali ke dashboard
 */

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

$user = require_role('umkm');

require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../whatsapp.php';

// ---------------------------------------------------------------
// Akun & profil UMKM
// ---------------------------------------------------------------
$stmt = $pdo->prepare(
    'SELECT id, nama_lengkap, email, role
     FROM users
     WHERE id = ? AND role = ?
     LIMIT 1'
);
$stmt->execute([(int) $user['id'], 'umkm']);
$account = $stmt->fetch();

if (!$account) {
    unset($_SESSION['user']);
    redirect(url('login.php'), 302);
}

$stmt = $pdo->prepare('SELECT * FROM umkm WHERE user_id = ? LIMIT 1');
$stmt->execute([(int) $account['id']]);
$umkm = $stmt->fetch();

if (!$umkm) {
    flash_set('error', 'Profil UMKM tidak ditemukan. Hubungi admin festival sebelum mendaftar.');
    redirect(url('umkm/dashboard.php'));
}

$umkmId = (int) $umkm['id'];

// ---------------------------------------------------------------
// Cegah pendaftaran ganda saat masih ada pengajuan aktif
// ---------------------------------------------------------------
$stmt = $pdo->prepare(
    'SELECT id, deskripsi_produk, status, catatan_admin, created_at
     FROM pendaftaran
     WHERE umkm_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT 1'
);
$stmt->execute([$umkmId]);
$latest = $stmt->fetch();

if ($latest && in_array((string) $latest['status'], ['Menunggu', 'Diterima'], true)) {
    flash_set('error', 'Kamu masih memiliki pendaftaran aktif. Tunggu hasil kurasi sebelum mengirim pendaftaran baru.');
    redirect(url('umkm/dashboard.php'));
}

$isResubmission = $latest && $latest['status'] === 'Ditolak';

$errors          = [];
$oldDeskripsi    = $isResubmission ? (string) $latest['deskripsi_produk'] : '';
$uploadedRelative = null;

// ---------------------------------------------------------------
// Proses form
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(input_string($_POST, 'csrf_token'))) {
        $errors['general'] = 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        // Cek ulang status terbaru untuk mencegah pengajuan ganda
        // (misalnya admin menerima pendaftaran saat form masih terbuka).
        $stmt = $pdo->prepare(
            'SELECT status
             FROM pendaftaran
             WHERE umkm_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([$umkmId]);
        $activeStatus = $stmt->fetchColumn();

        if ($activeStatus !== false && in_array((string) $activeStatus, ['Menunggu', 'Diterima'], true)) {
            flash_set('error', 'Kamu masih memiliki pendaftaran aktif. Tunggu hasil kurasi sebelum mengirim pendaftaran baru.');
            redirect(url('umkm/dashboard.php'));
        }

        $oldDeskripsi = trim(input_string($_POST, 'deskripsi_produk'));

        // ---------------- Deskripsi ----------------
        if (mb_strlen($oldDeskripsi) < 10) {
            $errors['deskripsi_produk'] = 'Deskripsi produk minimal 10 karakter.';
        } elseif (mb_strlen($oldDeskripsi) > 2000) {
            $errors['deskripsi_produk'] = 'Deskripsi produk maksimal 2000 karakter.';
        }

        // ---------------- Foto produk ----------------
        $file = $_FILES['foto_produk'] ?? null;

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors['foto_produk'] = 'Foto produk wajib diunggah.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['foto_produk'] = match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran foto melebihi batas server.',
                UPLOAD_ERR_PARTIAL                         => 'Foto hanya terunggah sebagian. Coba lagi.',
                default                                    => 'Foto gagal diunggah. Silakan coba lagi.',
            };
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors['foto_produk'] = 'Ukuran foto maksimal 2 MB.';
        } else {
            $tmpName = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';

            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                $errors['foto_produk'] = 'Berkas upload tidak valid.';
            } else {
                $mime = '';

                if (class_exists('finfo')) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime  = (string) $finfo->file($tmpName);
                } elseif (function_exists('mime_content_type')) {
                    $mime = (string) mime_content_type($tmpName);
                }

                $allowedMime = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                ];

                if (!isset($allowedMime[$mime])) {
                    $errors['foto_produk'] = 'Format foto harus JPG, PNG, atau WEBP.';
                } elseif (@getimagesize($tmpName) === false) {
                    $errors['foto_produk'] = 'Berkas yang diunggah bukan gambar yang valid.';
                }
            }
        }

        // ---------------- Simpan file + data ----------------
        if (!$errors) {
            $uploadDir = __DIR__ . '/../uploads/produk';

            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                $errors['general'] = 'Folder penyimpanan foto tidak tersedia. Hubungi admin festival.';
            } else {
                $filename     = bin2hex(random_bytes(16)) . '.' . $allowedMime[$mime];
                $destination  = $uploadDir . '/' . $filename;
                $relativePath = 'uploads/produk/' . $filename;

                if (!move_uploaded_file($tmpName, $destination)) {
                    $errors['general'] = 'Foto gagal disimpan di server. Silakan coba lagi.';
                } else {
                    try {
                        $stmt = $pdo->prepare(
                            'INSERT INTO pendaftaran (umkm_id, deskripsi_produk, foto_produk)
                             VALUES (?, ?, ?)'
                        );
                        $stmt->execute([$umkmId, $oldDeskripsi, $relativePath]);
                        $pendaftaranId = (int) $pdo->lastInsertId();

                        // Notifikasi ke admin. Kegagalan WA tidak boleh
                        // menggagalkan pendaftaran yang sudah tersimpan.
                        $adminMessage = "Pendaftaran tenant baru CCF 2026\n\n"
                            . "Nama UMKM: {$umkm['nama_umkm']}\n"
                            . "Pemilik: {$account['nama_lengkap']}\n"
                            . "Kategori: {$umkm['kategori_produk']}\n"
                            . "WhatsApp: {$umkm['no_telepon']}\n"
                            . 'ID: #CCF-' . str_pad((string) $pendaftaranId, 4, '0', STR_PAD_LEFT) . "\n"
                            . 'Deskripsi: ' . mb_substr($oldDeskripsi, 0, 180)
                            . (mb_strlen($oldDeskripsi) > 180 ? '...' : '') . "\n\n"
                            . "Tinjau di dashboard admin:\n"
                            . absolute_url('admin/dashboard.php') . '#pendaftaran';

                        try {
                            $waResult = sendWhatsAppNotification(WA_ADMIN_NUMBER, $adminMessage);
                            if (($waResult['ok'] ?? false) !== true && empty($waResult['skipped'])) {
                                error_log('[UMKM FORM WA] ' . ($waResult['error'] ?? 'unknown error'));
                            }
                        } catch (Throwable $e) {
                            // Notifikasi bersifat best-effort; jangan gagalkan pendaftaran.
                            error_log('[UMKM FORM WA] ' . $e->getMessage());
                        }

                        flash_set(
                            'success',
                            'Pendaftaran produk berhasil dikirim. Tim kurator akan meninjau dalam 3–5 hari kerja.'
                        );

                        redirect(url('umkm/dashboard.php'));
                    } catch (Throwable $e) {
                        @unlink($destination);
                        error_log('[UMKM FORM] ' . $e->getMessage());
                        $errors['general'] = 'Pendaftaran gagal disimpan. Silakan coba lagi beberapa saat lagi.';
                    }
                }
            }
        }
    }
}

$flashError = flash_get('error');

$pageTitle = $isResubmission ? 'Kirim Ulang Pendaftaran' : 'Form Pendaftaran Tenant';
$navItems  = [
    ['label' => 'Dashboard',   'href' => url('umkm/dashboard.php'), 'active' => false],
    ['label' => 'Profil',      'href' => url('umkm/dashboard.php') . '#profil', 'active' => false],
    ['label' => 'Pendaftaran', 'href' => url('umkm/form-pendaftaran.php'), 'active' => true],
];

require __DIR__ . '/../partials/dashboard-header.php';
?>

<main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">

    <!-- ============================= HEADING ============================= -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a href="<?= e(url('umkm/dashboard.php')) ?>"
               class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 transition hover:text-amber-700">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
                Kembali ke dashboard
            </a>

            <p class="mt-5 inline-flex items-center gap-3 text-xs font-bold uppercase tracking-[0.25em] text-amber-700">
                <span class="h-px w-8 bg-amber-500"></span>
                Pendaftaran Tenant
            </p>
            <h1 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">
                <?= $isResubmission ? 'Perbaiki & kirim ulang pendaftaran' : 'Daftarkan produk unggulanmu' ?>
            </h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">
                Lengkapi deskripsi dan foto produk terbaikmu. Tim kurator akan meninjau pengajuan dalam 3–5 hari kerja.
            </p>
        </div>

        <span class="inline-flex w-fit items-center gap-2 rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-600 shadow-sm">
            <span class="h-2 w-2 rounded-full bg-amber-500"></span>
            Festival 2026 &middot; <?= e((string) $umkm['nama_umkm']) ?>
        </span>
    </div>

    <!-- ============================= FLASH ============================= -->
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

    <?php if ($isResubmission): ?>
        <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-5">
            <p class="text-sm font-bold text-red-800">Pendaftaran sebelumnya ditolak</p>
            <?php if (!empty($latest['catatan_admin'])): ?>
                <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-red-700"><?= e((string) $latest['catatan_admin']) ?></p>
            <?php else: ?>
                <p class="mt-2 text-sm leading-relaxed text-red-700">Silakan perbaiki data produk dan kirim ulang pendaftaran.</p>
            <?php endif; ?>
            <p class="mt-2 text-xs text-red-600">Setelah diperbaiki, gunakan formulir di bawah untuk mengirim ulang.</p>
        </div>
    <?php endif; ?>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1.35fr_0.65fr]">

        <!-- ============================= FORM ============================= -->
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <?php if ($errors): ?>
                <div role="alert" class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4">
                    <p class="text-sm font-bold text-red-800">Periksa kembali data berikut:</p>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-red-700">
                        <?php foreach ($errors as $message): ?>
                            <li><?= e($message) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('umkm/form-pendaftaran.php')) ?>" enctype="multipart/form-data" class="space-y-7">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <!-- Deskripsi -->
                <div>
                    <div class="flex items-start gap-3">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-amber-100 text-xs font-extrabold text-amber-800">01</span>
                        <div>
                            <label for="deskripsi_produk" class="block text-sm font-bold text-slate-800">
                                Deskripsi Produk
                            </label>
                            <p id="deskripsi-hint" class="mt-1 text-xs leading-relaxed text-slate-500">
                                Minimal 10 karakter. Jelaskan bahan, keunikan, ukuran, dan kisaran harga.
                            </p>
                        </div>
                    </div>

                    <textarea id="deskripsi_produk" name="deskripsi_produk" rows="6" required minlength="10" maxlength="2000"
                              aria-describedby="deskripsi-hint<?= isset($errors['deskripsi_produk']) ? ' deskripsi-error' : '' ?>"
                              aria-invalid="<?= isset($errors['deskripsi_produk']) ? 'true' : 'false' ?>"
                              placeholder="Contoh: Batik tulis khas Cirebon dengan motif mega mendung, pewarna alami, tersedia dalam ukuran 2 x 1,5 meter. Kisaran harga Rp350.000–Rp750.000."
                              class="mt-3 block w-full rounded-xl border bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:ring-4 <?= isset($errors['deskripsi_produk']) ? 'border-red-300 focus:border-red-400 focus:ring-red-100' : 'border-slate-300 focus:border-amber-400 focus:ring-amber-100' ?>"><?= e($oldDeskripsi) ?></textarea>

                    <div class="mt-1.5 flex items-center justify-between gap-3">
                        <p class="text-xs text-slate-500">Tulis dengan jelas agar kurator mudah menilai produkmu.</p>
                        <p class="shrink-0 text-xs tabular-nums text-slate-500"><?= mb_strlen($oldDeskripsi) ?>/2000</p>
                    </div>
                    <?php if (isset($errors['deskripsi_produk'])): ?>
                        <p id="deskripsi-error" class="mt-1 text-xs font-medium text-red-600"><?= e($errors['deskripsi_produk']) ?></p>
                    <?php endif; ?>
                </div>

                <!-- Foto -->
                <div class="border-t border-slate-100 pt-6">
                    <div class="flex items-start gap-3">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-amber-100 text-xs font-extrabold text-amber-800">02</span>
                        <div>
                            <label for="foto_produk" class="block text-sm font-bold text-slate-800">
                                Foto Produk
                            </label>
                            <p id="foto-hint" class="mt-1 text-xs leading-relaxed text-slate-500">
                                JPG, PNG, atau WEBP. Ukuran maksimal 2 MB. Gunakan foto asli dengan pencahayaan terang.
                            </p>
                        </div>
                    </div>

                    <input id="foto_produk" name="foto_produk" type="file" required
                           accept="image/jpeg,image/png,image/webp"
                           aria-describedby="foto-hint<?= isset($errors['foto_produk']) ? ' foto-error' : '' ?>"
                           aria-invalid="<?= isset($errors['foto_produk']) ? 'true' : 'false' ?>"
                           class="mt-3 block w-full cursor-pointer rounded-xl border border-slate-300 bg-white text-sm text-slate-600 shadow-sm file:mr-4 file:cursor-pointer file:rounded-l-xl file:border-0 file:bg-amber-100 file:px-4 file:py-3 file:text-sm file:font-bold file:text-amber-800 transition hover:file:bg-amber-200 focus:outline-none focus:ring-4 focus:ring-amber-100">

                    <?php if (isset($errors['foto_produk'])): ?>
                        <p id="foto-error" class="mt-1.5 text-xs font-medium text-red-600"><?= e($errors['foto_produk']) ?></p>
                    <?php endif; ?>

                    <div id="foto-preview-wrap" class="mt-4 hidden">
                        <p class="mb-2 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Pratinjau</p>
                        <img id="foto-preview" src="" alt="Pratinjau foto produk"
                             class="h-44 w-full max-w-sm rounded-xl object-cover ring-1 ring-slate-200">
                    </div>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <p class="max-w-md text-xs leading-relaxed text-slate-500">
                        Dengan mengirim, kamu menyatakan data dan foto produk adalah milik sendiri.
                    </p>
                    <button type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2">
                        <?= $isResubmission ? 'Kirim Ulang Pendaftaran' : 'Kirim Pendaftaran' ?>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                        </svg>
                    </button>
                </div>
            </form>
        </section>

        <!-- ============================= ASIDE ============================= -->
        <aside class="space-y-6">
            <div class="rounded-3xl bg-slate-950 p-6 text-white shadow-sm sm:p-8">
                <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-amber-300">Tips Kurasi</p>
                <h2 class="mt-3 font-serif text-2xl leading-tight text-white">Buat produkmu menonjol.</h2>
                <ul class="mt-5 space-y-3 text-sm leading-relaxed text-slate-300">
                    <li class="flex gap-2.5">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span>
                        Gunakan foto asli dengan cahaya terang dan latar bersih.
                    </li>
                    <li class="flex gap-2.5">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span>
                        Tulis keunikan produk, bahan, dan kisaran harga.
                    </li>
                    <li class="flex gap-2.5">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span>
                        Pastikan nomor WhatsApp aktif untuk dihubungi kurator.
                    </li>
                </ul>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-slate-500">Yang Dinilai Kurator</p>
                <ul class="mt-4 space-y-3 text-sm leading-relaxed text-slate-600">
                    <li class="flex gap-2.5">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"></span>
                        Kualitas dan keunikan produk.
                    </li>
                    <li class="flex gap-2.5">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"></span>
                        Kesiapan produk untuk dipamerkan.
                    </li>
                    <li class="flex gap-2.5">
                        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"></span>
                        Kelengkapan informasi produk.
                    </li>
                </ul>
                <p class="mt-5 border-t border-slate-100 pt-4 text-xs leading-relaxed text-slate-500">
                    Butuh bantuan? Hubungi
                    <a href="mailto:hello@ccf2026.id" class="font-bold text-slate-700 underline underline-offset-4">hello@ccf2026.id</a>.
                </p>
            </div>
        </aside>
    </div>
</main>

<script>
    (function () {
        const input = document.getElementById('foto_produk');
        const preview = document.getElementById('foto-preview');
        const wrap = document.getElementById('foto-preview-wrap');

        if (!input || !preview || !wrap) {
            return;
        }

        let objectUrl = null;

        input.addEventListener('change', function () {
            const file = input.files && input.files[0];

            if (objectUrl !== null) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }

            if (!file || !file.type.startsWith('image/')) {
                wrap.classList.add('hidden');
                preview.removeAttribute('src');
                return;
            }

            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            wrap.classList.remove('hidden');
        });
    })();
</script>

<?php require __DIR__ . '/../partials/dashboard-footer.php'; ?>
