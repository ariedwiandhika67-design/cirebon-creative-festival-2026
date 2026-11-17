<?php
/**
 * admin/dashboard.php — Dashboard administrator
 * Cirebon Creative Festival 2026
 *
 * Fitur:
 *   - Statistik UMKM & pendaftaran
 *   - Daftar pendaftaran tenant + filter status
 *   - Aksi terima / tolak beserta catatan admin
 */

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

$user = require_role('admin');

require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../whatsapp.php';

$allowedFilters = ['Semua', 'Menunggu', 'Diterima', 'Ditolak'];

// ---------------------------------------------------------------
// Aksi admin: terima / tolak pendaftaran
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnFilter = input_string($_POST, 'filter', 'Semua');
    if (!in_array($returnFilter, $allowedFilters, true)) {
        $returnFilter = 'Semua';
    }

    $redirectUrl = url('admin/dashboard.php');
    if ($returnFilter !== 'Semua') {
        $redirectUrl .= '?status=' . urlencode($returnFilter);
    }

    if (!verify_csrf(input_string($_POST, 'csrf_token'))) {
        flash_set('error', 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
        redirect($redirectUrl);
    }

    $pendaftaranId = filter_var($_POST['pendaftaran_id'] ?? null, FILTER_VALIDATE_INT);
    $aksi          = input_string($_POST, 'aksi');
    $catatan       = trim(input_string($_POST, 'catatan_admin'));

    if (!$pendaftaranId || !in_array($aksi, ['Diterima', 'Ditolak'], true)) {
        flash_set('error', 'Aksi yang dikirim tidak valid.');
        redirect($redirectUrl);
    }

    if (mb_strlen($catatan) > 1000) {
        flash_set('error', 'Catatan admin maksimal 1000 karakter.');
        redirect($redirectUrl);
    }

    if ($aksi === 'Ditolak' && $catatan === '') {
        flash_set('error', 'Catatan admin wajib diisi saat menolak pendaftaran.');
        redirect($redirectUrl);
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT p.id, p.status, m.nama_umkm, m.no_telepon, u.nama_lengkap
             FROM pendaftaran p
             JOIN umkm m ON m.id = p.umkm_id
             JOIN users u ON u.id = m.user_id
             WHERE p.id = ?
             LIMIT 1'
        );
        $stmt->execute([(int) $pendaftaranId]);
        $pendaftaran = $stmt->fetch();

        if (!$pendaftaran) {
            flash_set('error', 'Pendaftaran tidak ditemukan.');
            redirect($redirectUrl);
        }

        $oldStatus = (string) $pendaftaran['status'];

        $stmt = $pdo->prepare('UPDATE pendaftaran SET status = ?, catatan_admin = ? WHERE id = ?');
        $stmt->execute([
            $aksi,
            $catatan !== '' ? $catatan : null,
            (int) $pendaftaranId,
        ]);

        // Kirim WhatsApp hanya saat status benar-benar berubah.
        // Kegagalan notifikasi tidak membatalkan keputusan admin.
        if ($oldStatus !== $aksi && in_array($aksi, ['Diterima', 'Ditolak'], true)) {
            $statusIntro = $aksi === 'Diterima'
                ? "Selamat! Pendaftaran *{$pendaftaran['nama_umkm']}* diterima sebagai tenant Cirebon Creative Festival 2026."
                : "Mohon maaf, pendaftaran *{$pendaftaran['nama_umkm']}* belum dapat diterima.";

            $catatanLine = $catatan !== ''
                ? "\nCatatan admin:\n{$catatan}\n"
                : '';

            $umkmMessage = "Halo {$pendaftaran['nama_lengkap']},\n\n"
                . $statusIntro . "\n"
                . "Status: *{$aksi}*\n"
                . $catatanLine
                . "\nLihat detail pendaftaran di dashboard UMKM:\n"
                . absolute_url('umkm/dashboard.php') . "\n\n"
                . '— Panitia Cirebon Creative Festival 2026';

            try {
                $waResult = sendWhatsAppNotification((string) $pendaftaran['no_telepon'], $umkmMessage);
                if (($waResult['ok'] ?? false) !== true && empty($waResult['skipped'])) {
                    error_log('[ADMIN WA] ' . ($waResult['error'] ?? 'unknown error'));
                }
            } catch (Throwable $e) {
                // Keputusan admin tetap tersimpan meskipun notifikasi gagal.
                error_log('[ADMIN WA] ' . $e->getMessage());
            }
        }

        flash_set(
            'success',
            $aksi === 'Diterima'
                ? 'Pendaftaran berhasil diterima.'
                : 'Pendaftaran telah ditolak. Catatan admin akan terlihat oleh UMKM.'
        );
    } catch (Throwable $e) {
        error_log('[ADMIN ACTION] ' . $e->getMessage());
        flash_set('error', 'Terjadi kesalahan saat menyimpan keputusan. Silakan coba lagi.');
    }

    redirect($redirectUrl);
}

// ---------------------------------------------------------------
// Data statistik
// ---------------------------------------------------------------
$totalUmkm = (int) $pdo->query('SELECT COUNT(*) FROM umkm')->fetchColumn();

$counts = ['Menunggu' => 0, 'Diterima' => 0, 'Ditolak' => 0];
foreach ($pdo->query('SELECT status, COUNT(*) AS total FROM pendaftaran GROUP BY status') as $row) {
    if (isset($counts[$row['status']])) {
        $counts[$row['status']] = (int) $row['total'];
    }
}

$totalPendaftaran = array_sum($counts);

// ---------------------------------------------------------------
// Filter & daftar pendaftaran
// ---------------------------------------------------------------
$filter = input_string($_GET, 'status', 'Semua');
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'Semua';
}

$sql = 'SELECT p.id, p.deskripsi_produk, p.foto_produk, p.status, p.catatan_admin, p.created_at,
               m.nama_umkm, m.kategori_produk, m.no_telepon,
               u.nama_lengkap, u.email
        FROM pendaftaran p
        JOIN umkm m ON m.id = p.umkm_id
        JOIN users u ON u.id = m.user_id';

$params = [];
if ($filter !== 'Semua') {
    $sql .= ' WHERE p.status = ?';
    $params[] = $filter;
}

$sql .= ' ORDER BY p.created_at DESC, p.id DESC LIMIT 100';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pendaftaranList = $stmt->fetchAll();

$filterOptions = [
    'Semua'    => $totalPendaftaran,
    'Menunggu' => $counts['Menunggu'],
    'Diterima' => $counts['Diterima'],
    'Ditolak'  => $counts['Ditolak'],
];

$flashSuccess = flash_get('success');
$flashError   = flash_get('error');

$pageTitle = 'Dashboard Admin';
$navItems  = [
    ['label' => 'Dashboard',      'href' => url('admin/dashboard.php'), 'active' => true],
    ['label' => 'Pendaftaran',    'href' => url('admin/dashboard.php') . '#pendaftaran', 'active' => false],
    ['label' => 'Manajemen User', 'href' => url('admin/users.php'), 'active' => false],
    ['label' => 'Chatbot AI',     'href' => url('admin/chatbot-config.php'), 'active' => false],
];

require __DIR__ . '/../partials/dashboard-header.php';
?>

<main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">

    <!-- ============================= HEADING ============================= -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="inline-flex items-center gap-3 text-xs font-bold uppercase tracking-[0.25em] text-amber-700">
                <span class="h-px w-8 bg-amber-500"></span>
                Dashboard Admin
            </p>
            <h1 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">
                Selamat datang, <?= e((string) $user['nama_lengkap']) ?>
            </h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">
                Pantau pendaftaran tenant, tinjau produk, dan tentukan status kurasi festival.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="<?= e(url('admin/users.php')) ?>"
               class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm transition hover:border-amber-300 hover:bg-amber-50 hover:text-amber-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                </svg>
                Manajemen User
            </a>
            <a href="<?= e(url('admin/chatbot-config.php')) ?>"
               class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm transition hover:border-amber-300 hover:bg-amber-50 hover:text-amber-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
                </svg>
                Chatbot AI
            </a>
            <span class="inline-flex w-fit items-center gap-2 rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-600 shadow-sm">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                Festival 2026 &middot; Admin
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

    <!-- ============================= STATISTIK ============================= -->
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total UMKM</p>
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72l1.189-1.19A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72M6.75 18h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .414.336.75.75.75z"/>
                    </svg>
                </span>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900"><?= $totalUmkm ?></p>
            <p class="mt-1 text-xs text-slate-500">Profil UMKM terdaftar</p>
        </div>

        <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-800">Menunggu</p>
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-amber-100 text-amber-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-amber-950"><?= $counts['Menunggu'] ?></p>
            <p class="mt-1 text-xs text-amber-800">Perlu ditinjau</p>
        </div>

        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-800">Diterima</p>
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-emerald-100 text-emerald-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                    </svg>
                </span>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-emerald-950"><?= $counts['Diterima'] ?></p>
            <p class="mt-1 text-xs text-emerald-800/80">Tenant terkurasi</p>
        </div>

        <div class="rounded-2xl border border-red-200 bg-red-50/60 p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-red-800">Ditolak</p>
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-red-100 text-red-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </span>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-red-950"><?= $counts['Ditolak'] ?></p>
            <p class="mt-1 text-xs text-red-800/80">Tidak lolos kurasi</p>
        </div>
    </div>

    <!-- ============================= FILTER ============================= -->
    <div id="pendaftaran" class="mt-10 scroll-mt-24">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-bold tracking-tight text-slate-900">Pendaftaran Tenant</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Menampilkan <?= count($pendaftaranList) ?> pendaftaran<?= $filter !== 'Semua' ? ' dengan status ' . e($filter) : '' ?>.
                </p>
            </div>
            <p class="text-xs text-slate-500">Terbaru diperbarui: <?= e(format_tanggal(date('Y-m-d H:i:s'), true)) ?></p>
        </div>

        <div class="mt-5 flex flex-wrap gap-2" role="navigation" aria-label="Filter status pendaftaran">
            <?php foreach ($filterOptions as $label => $count): ?>
                <?php $isActive = $filter === $label; ?>
                <a href="<?= e(url('admin/dashboard.php')) ?><?= $label !== 'Semua' ? '?status=' . urlencode($label) : '' ?>"
                   <?= $isActive ? 'aria-current="page"' : '' ?>
                   class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold ring-1 ring-inset transition <?= $isActive ? 'bg-slate-900 text-white ring-slate-900' : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50' ?>">
                    <?= e($label) ?>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold <?= $isActive ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-600' ?>">
                        <?= $count ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- ============================= DAFTAR ============================= -->
        <?php if (!$pendaftaranList): ?>
            <div class="mt-6 rounded-3xl border border-dashed border-slate-300 bg-white/70 p-10 text-center sm:p-14">
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 text-slate-400">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </span>
                <h3 class="mt-4 text-base font-bold text-slate-900">
                    <?= $filter === 'Semua' ? 'Belum ada pendaftaran' : 'Tidak ada pendaftaran dengan status ini' ?>
                </h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500">
                    <?= $filter === 'Semua'
                        ? 'Pendaftaran produk dari UMKM akan muncul di sini setelah mereka mengirim formulir.'
                        : 'Coba pilih filter lain untuk melihat pendaftaran dengan status berbeda.' ?>
                </p>
                <?php if ($filter !== 'Semua'): ?>
                    <a href="<?= e(url('admin/dashboard.php')) ?>" class="mt-5 inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">
                        Lihat semua pendaftaran
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="mt-6 space-y-4">
                <?php foreach ($pendaftaranList as $item): ?>
                    <?php
                    $fotoUrl = uploaded_image_url((string) $item['foto_produk']);
                    $isPending = $item['status'] === 'Menunggu';
                    ?>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md sm:p-6">
                        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">

                            <div class="flex min-w-0 flex-1 gap-4">
                                <?php if ($fotoUrl !== null): ?>
                                    <a href="<?= e($fotoUrl) ?>" target="_blank" rel="noopener noreferrer" class="shrink-0" title="Lihat foto produk">
                                        <img src="<?= e($fotoUrl) ?>" alt="Foto produk <?= e((string) $item['nama_umkm']) ?>"
                                             class="h-20 w-20 rounded-xl object-cover ring-1 ring-slate-200 transition hover:opacity-90 sm:h-24 sm:w-24"
                                             loading="lazy">
                                    </a>
                                <?php else: ?>
                                    <span class="grid h-20 w-20 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-400 ring-1 ring-slate-200 sm:h-24 sm:w-24">
                                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z"/>
                                        </svg>
                                    </span>
                                <?php endif; ?>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-base font-bold text-slate-900"><?= e((string) $item['nama_umkm']) ?></h3>
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ring-1 ring-inset <?= e(status_badge_classes((string) $item['status'])) ?>">
                                            <?= e((string) $item['status']) ?>
                                        </span>
                                    </div>

                                    <p class="mt-1 text-xs font-medium text-slate-500">
                                        <?= e((string) $item['kategori_produk']) ?>
                                        &middot;
                                        <?= e(format_tanggal((string) $item['created_at'], true)) ?>
                                    </p>

                                    <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-600"><?= e((string) $item['deskripsi_produk']) ?></p>

                                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                        <span class="inline-flex items-center gap-1.5">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0"/>
                                            </svg>
                                            <?= e((string) $item['nama_lengkap']) ?>
                                        </span>
                                        <a href="mailto:<?= e((string) $item['email']) ?>" class="inline-flex items-center gap-1.5 transition hover:text-amber-700">
                                            <?= e((string) $item['email']) ?>
                                        </a>
                                        <a href="tel:<?= e((string) $item['no_telepon']) ?>" class="inline-flex items-center gap-1.5 transition hover:text-amber-700">
                                            <?= e((string) $item['no_telepon']) ?>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <?php if ($isPending): ?>
                                <form method="post" action="<?= e(url('admin/dashboard.php')) ?>" class="w-full space-y-2 lg:w-80 lg:shrink-0">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="pendaftaran_id" value="<?= (int) $item['id'] ?>">
                                    <input type="hidden" name="filter" value="<?= e($filter) ?>">

                                    <label for="catatan-<?= (int) $item['id'] ?>" class="block text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">
                                        Catatan Admin
                                    </label>
                                    <textarea id="catatan-<?= (int) $item['id'] ?>" name="catatan_admin" rows="2" maxlength="1000"
                                              placeholder="Wajib diisi jika menolak..."
                                              class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-amber-400 focus:ring-4 focus:ring-amber-100"></textarea>

                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="submit" name="aksi" value="Diterima"
                                                class="inline-flex items-center justify-center rounded-lg bg-emerald-700 px-3 py-2.5 text-xs font-bold text-white transition hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                                            Terima
                                        </button>
                                        <button type="submit" name="aksi" value="Ditolak"
                                                onclick="return confirm('Tolak pendaftaran ini? Catatan admin akan ditampilkan ke UMKM.');"
                                                class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-white px-3 py-2.5 text-xs font-bold text-red-600 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400 focus-visible:ring-offset-2">
                                            Tolak
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="w-full rounded-xl border border-slate-200 bg-slate-50 p-4 lg:w-80 lg:shrink-0">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Catatan Admin</p>
                                    <?php if (!empty($item['catatan_admin'])): ?>
                                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-700"><?= e((string) $item['catatan_admin']) ?></p>
                                    <?php else: ?>
                                        <p class="mt-2 text-sm text-slate-500">Tidak ada catatan.</p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/../partials/dashboard-footer.php'; ?>
