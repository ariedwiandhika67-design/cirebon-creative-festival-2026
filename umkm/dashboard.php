<?php
/**
 * umkm/dashboard.php — Dashboard tenant UMKM
 * Cirebon Creative Festival 2026
 *
 * Menampilkan:
 *   - Sambutan & profil UMKM
 *   - Status pendaftaran terbaru (Menunggu / Diterima / Ditolak)
 *   - Catatan admin
 *   - CTA ke form pendaftaran jika belum pernah mendaftar / ditolak
 */

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

// Session + role guard (auth.php sudah menjalankan session_start()).
$user = require_role('umkm');

require_once __DIR__ . '/../koneksi.php';

// ---------------------------------------------------------------
// Data akun dari database (bukan hanya dari session)
// ---------------------------------------------------------------
$stmt = $pdo->prepare(
    'SELECT id, nama_lengkap, email, role, created_at
     FROM users
     WHERE id = ? AND role = ?
     LIMIT 1'
);
$stmt->execute([(int) $user['id'], 'umkm']);
$account = $stmt->fetch();

if (!$account) {
    // Akun sudah tidak ada / role berubah — bersihkan session user.
    unset($_SESSION['user']);
    redirect(url('login.php'), 302);
}

// ---------------------------------------------------------------
// Profil UMKM milik user
// ---------------------------------------------------------------
$stmt = $pdo->prepare('SELECT * FROM umkm WHERE user_id = ? LIMIT 1');
$stmt->execute([(int) $account['id']]);
$umkm = $stmt->fetch();

// ---------------------------------------------------------------
// Riwayat & pendaftaran terbaru
// ---------------------------------------------------------------
$registrations = [];
$latest        = null;

if ($umkm) {
    $stmt = $pdo->prepare(
        'SELECT id, deskripsi_produk, foto_produk, status, catatan_admin, created_at
         FROM pendaftaran
         WHERE umkm_id = ?
         ORDER BY created_at DESC, id DESC'
    );
    $stmt->execute([(int) $umkm['id']]);
    $registrations = $stmt->fetchAll();
    $latest        = $registrations[0] ?? null;
}

$flashSuccess = flash_get('success');
$flashError   = flash_get('error');

$latestStatus = $latest['status'] ?? null;
$latestFoto   = $latest ? uploaded_image_url((string) $latest['foto_produk']) : null;

// ---------------------------------------------------------------
// Teks & warna kartu status
// ---------------------------------------------------------------
$heroClass = match ($latestStatus) {
    'Diterima' => 'border-emerald-200 bg-emerald-50',
    'Ditolak'  => 'border-red-200 bg-red-50',
    'Menunggu' => 'border-amber-200 bg-amber-50',
    default    => 'border-amber-200 bg-gradient-to-br from-amber-50 via-white to-white',
};

$heroEyebrowClass = match ($latestStatus) {
    'Diterima' => 'text-emerald-700',
    'Ditolak'  => 'text-red-700',
    'Menunggu' => 'text-amber-800',
    default    => 'text-amber-800',
};

$heroTitle = match ($latestStatus) {
    'Diterima' => 'Selamat, kamu diterima!',
    'Ditolak'  => 'Pendaftaran belum diterima',
    'Menunggu' => 'Pendaftaranmu sedang dikurasi',
    default    => 'Belum ada pendaftaran produk',
};

$heroDescription = match ($latestStatus) {
    'Diterima' => 'Siapkan produk terbaikmu untuk 12–13 Desember 2026. Informasi booth dan teknis pameran akan dikirim melalui email.',
    'Ditolak'  => 'Perbaiki deskripsi atau foto produk sesuai catatan admin, lalu kirim ulang pendaftaran.',
    'Menunggu' => 'Tim kurator sedang meninjau produkmu. Hasil kurasi akan muncul di dashboard ini.',
    default    => 'Kirim deskripsi dan foto produk terbaikmu untuk mengikuti kurasi tenant Cirebon Creative Festival 2026.',
};

$pageTitle = 'Dashboard UMKM';
$navItems  = [
    ['label' => 'Dashboard',   'href' => url('umkm/dashboard.php'), 'active' => true],
    ['label' => 'Profil',      'href' => url('umkm/dashboard.php') . '#profil', 'active' => false],
    ['label' => 'Pendaftaran', 'href' => url('umkm/dashboard.php') . '#pendaftaran', 'active' => false],
];

require __DIR__ . '/../partials/dashboard-header.php';
?>

<main class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">

    <!-- ============================= SAMBUTAN ============================= -->
    <section class="relative overflow-hidden rounded-3xl bg-slate-950 p-6 text-white shadow-xl sm:p-8 lg:p-10">
        <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-amber-400/20 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-24 left-1/3 h-64 w-64 rounded-full bg-orange-500/10 blur-3xl" aria-hidden="true"></div>

        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="inline-flex items-center gap-3 text-xs font-bold uppercase tracking-[0.25em] text-amber-300">
                    <span class="h-px w-8 bg-amber-400"></span>
                    Dashboard UMKM
                </p>
                <h1 class="mt-4 font-serif text-3xl leading-tight text-white sm:text-4xl lg:text-5xl">
                    Halo, <?= e((string) $account['nama_lengkap']) ?>
                </h1>
                <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-300">
                    Kelola pendaftaran produk dan pantau status kurasi tenant festivalmu.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs font-semibold text-slate-200">
                    <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                    12–13 Desember 2026
                </span>
                <?php if ($umkm): ?>
                    <span class="inline-flex items-center gap-2 rounded-full border border-amber-300/20 bg-amber-400/10 px-4 py-2 text-xs font-semibold text-amber-200">
                        <?= e((string) $umkm['kategori_produk']) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </section>

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

    <?php if (!$umkm): ?>
        <div role="alert" class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <p class="text-sm font-bold text-amber-900">Profil UMKM tidak ditemukan</p>
            <p class="mt-1 text-sm leading-relaxed text-amber-800">
                Akunmu belum terhubung dengan profil UMKM. Hubungi admin festival di
                <a href="mailto:admin@ccf2026.id" class="font-bold underline underline-offset-4">admin@ccf2026.id</a>
                agar data usahamu dilengkapi.
            </p>
        </div>
    <?php endif; ?>

    <!-- ============================= STATUS ============================= -->
    <section id="pendaftaran" class="mt-6 scroll-mt-24 rounded-3xl border p-6 shadow-sm sm:p-8 <?= e($heroClass) ?>">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-bold uppercase tracking-[0.25em] <?= e($heroEyebrowClass) ?>">
                    Status Pendaftaran
                </p>
                <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-950 sm:text-3xl">
                    <?= e($heroTitle) ?>
                </h2>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">
                    <?= e($heroDescription) ?>
                </p>

                <?php if ($latest !== null): ?>
                    <div class="mt-5 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wide ring-1 ring-inset <?= e(status_badge_classes((string) $latest['status'])) ?>">
                            <?= e((string) $latest['status']) ?>
                        </span>
                        <span class="text-xs font-medium text-slate-600">
                            Diajukan <?= e(format_tanggal((string) $latest['created_at'], true)) ?>
                        </span>
                    </div>

                    <?php if (!empty($latest['catatan_admin'])): ?>
                        <div class="mt-5 rounded-2xl border border-black/5 bg-white/75 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Catatan Admin</p>
                            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-700"><?= e((string) $latest['catatan_admin']) ?></p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($umkm && (!$latest || $latestStatus === 'Ditolak')): ?>
                    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <a href="<?= e(url('umkm/form-pendaftaran.php')) ?>"
                           class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2">
                            <?= e($latestStatus === 'Ditolak' ? 'Perbaiki & Kirim Ulang' : 'Daftar sebagai Tenant') ?>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                            </svg>
                        </a>
                        <p class="text-xs leading-relaxed text-slate-600">
                            Isi formulir pendaftaran produk untuk mengikuti kurasi tenant.
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($latestFoto !== null): ?>
                <a href="<?= e($latestFoto) ?>" target="_blank" rel="noopener noreferrer"
                   class="group block shrink-0 overflow-hidden rounded-2xl ring-1 ring-black/5"
                   title="Lihat foto produk">
                    <img src="<?= e($latestFoto) ?>" alt="Foto produk terakhir yang diajukan"
                         class="h-40 w-full object-cover transition duration-300 group-hover:scale-[1.02] lg:h-36 lg:w-52"
                         loading="lazy">
                </a>
            <?php endif; ?>
        </div>

        <?php if ($latest !== null): ?>
            <dl class="mt-6 grid gap-4 border-t border-black/5 pt-5 sm:grid-cols-3">
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-600">ID Pendaftaran</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900">#CCF-<?= str_pad((string) $latest['id'], 4, '0', STR_PAD_LEFT) ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-600">Tanggal Pengajuan</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900"><?= e(format_tanggal((string) $latest['created_at'])) ?></dd>
                </div>
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-600">Kategori Usaha</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900"><?= e((string) ($umkm['kategori_produk'] ?? '-')) ?></dd>
                </div>
            </dl>
        <?php endif; ?>
    </section>

    <!-- ============================= PROFIL & TIPS ============================= -->
    <div id="profil" class="mt-6 grid scroll-mt-24 gap-6 lg:grid-cols-[1.25fr_0.75fr]">

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-slate-100 text-slate-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72l1.189-1.19A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72M6.75 18h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .414.336.75.75.75z"/>
                    </svg>
                </span>
                <div>
                    <h2 class="text-lg font-bold tracking-tight text-slate-900">Profil UMKM</h2>
                    <p class="text-xs text-slate-500">Data usaha yang terdaftar di festival.</p>
                </div>
            </div>

            <?php if ($umkm): ?>
                <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Nama UMKM</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900"><?= e((string) $umkm['nama_umkm']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Kategori</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900"><?= e((string) $umkm['kategori_produk']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">No. Telepon / WhatsApp</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">
                            <a href="tel:<?= e((string) $umkm['no_telepon']) ?>" class="transition hover:text-amber-700"><?= e((string) $umkm['no_telepon']) ?></a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Email</dt>
                        <dd class="mt-1 break-all text-sm font-semibold text-slate-900">
                            <a href="mailto:<?= e((string) $account['email']) ?>" class="transition hover:text-amber-700"><?= e((string) $account['email']) ?></a>
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Alamat Usaha</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm leading-relaxed text-slate-700"><?= e((string) $umkm['alamat']) ?></dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">Terdaftar Sejak</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900"><?= e(format_tanggal((string) $umkm['created_at'])) ?></dd>
                    </div>
                </dl>
            <?php else: ?>
                <p class="mt-6 text-sm leading-relaxed text-slate-500">
                    Data profil belum tersedia. Hubungi admin festival untuk melengkapi profil UMKM-mu.
                </p>
            <?php endif; ?>
        </section>

        <aside class="rounded-3xl bg-slate-950 p-6 text-white shadow-sm sm:p-8">
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
            <div class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs leading-relaxed text-slate-300">
                    Festival berlangsung <span class="font-bold text-white">12–13 Desember 2026</span> di Cirebon.
                </p>
            </div>
        </aside>
    </div>

    <!-- ============================= RIWAYAT ============================= -->
    <?php if (count($registrations) > 1): ?>
        <section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight text-slate-900">Riwayat Pendaftaran</h2>
                    <p class="mt-1 text-sm text-slate-500">Semua pengajuan yang pernah kamu kirim.</p>
                </div>
                <span class="text-xs font-semibold text-slate-500"><?= count($registrations) ?> pengajuan</span>
            </div>

            <ul class="mt-4 divide-y divide-slate-100">
                <?php foreach ($registrations as $riwayat): ?>
                    <li class="flex flex-col gap-2 py-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                <?= e(format_tanggal((string) $riwayat['created_at'], true)) ?>
                            </p>
                            <p class="mt-1 line-clamp-2 text-sm leading-relaxed text-slate-700"><?= e((string) $riwayat['deskripsi_produk']) ?></p>
                            <?php if (!empty($riwayat['catatan_admin'])): ?>
                                <p class="mt-1 text-xs text-slate-500">Catatan: <?= e((string) $riwayat['catatan_admin']) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="inline-flex w-fit shrink-0 items-center rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ring-1 ring-inset <?= e(status_badge_classes((string) $riwayat['status'])) ?>">
                            <?= e((string) $riwayat['status']) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../partials/dashboard-footer.php'; ?>
