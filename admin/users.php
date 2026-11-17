<?php
/**
 * admin/users.php — Manajemen User
 * Cirebon Creative Festival 2026
 *
 * Halaman untuk melihat dan menghapus akun terdaftar.
 * Penghapusan memakai POST + CSRF, prepared statement, dan
 * memanfaatkan ON DELETE CASCADE pada tabel umkm/pendaftaran.
 * File foto produk ikut dibersihkan dan aksi dicatat ke error log.
 */

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

$user = require_role('admin');

require_once __DIR__ . '/../koneksi.php';

// ---------------------------------------------------------------
// Filter pencarian & role
// ---------------------------------------------------------------
$allowedRoleFilters = ['Semua', 'admin', 'umkm'];

$search = trim(input_string($_GET, 'q', ''));
if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}

$roleFilter = input_string($_GET, 'role', 'Semua');
if (!in_array($roleFilter, $allowedRoleFilters, true)) {
    $roleFilter = 'Semua';
}

$hasFilter = $search !== '' || $roleFilter !== 'Semua';

// Pertahankan filter setelah redirect agar admin tidak kehilangan konteks.
$returnQuery = [];
if ($search !== '') {
    $returnQuery['q'] = $search;
}
if ($roleFilter !== 'Semua') {
    $returnQuery['role'] = $roleFilter;
}
$returnUrl = url('admin/users.php')
    . ($returnQuery !== [] ? '?' . http_build_query($returnQuery, '', '&', PHP_QUERY_RFC3986) : '');

// ---------------------------------------------------------------
// Aksi hapus akun (POST + CSRF)
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input_string($_POST, 'action', '');

    if ($action !== 'delete_user') {
        flash_set('error', 'Aksi yang dikirim tidak valid.');
        redirect($returnUrl);
    }

    if (!verify_csrf(input_string($_POST, 'csrf_token', ''))) {
        flash_set('error', 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
        redirect($returnUrl);
    }

    $deleteId = filter_var(
        input_string($_POST, 'delete_id', ''),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($deleteId === false) {
        flash_set('error', 'ID akun tidak valid.');
        redirect($returnUrl);
    }

    try {
        $targetStmt = $pdo->prepare(
            'SELECT id, nama_lengkap, email, role
             FROM users
             WHERE id = ?
             LIMIT 1'
        );
        $targetStmt->execute([$deleteId]);
        $target = $targetStmt->fetch();

        if (!$target) {
            flash_set('error', 'Akun tidak ditemukan atau sudah dihapus.');
            redirect($returnUrl);
        }

        // Jangan sampai admin menghapus akun yang sedang dipakai.
        if ((int) $target['id'] === (int) $user['id']) {
            flash_set('error', 'Anda tidak dapat menghapus akun sendiri.');
            redirect($returnUrl);
        }

        // Jangan sampai sistem kehilangan seluruh akun admin.
        if ($target['role'] === 'admin') {
            $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

            if ($adminCount <= 1) {
                flash_set('error', 'Akun admin terakhir tidak dapat dihapus.');
                redirect($returnUrl);
            }
        }

        // Simpan path foto sebelum record dihapus agar file upload tidak menjadi orphan.
        $photoStmt = $pdo->prepare(
            'SELECT p.foto_produk
             FROM pendaftaran p
             INNER JOIN umkm m ON m.id = p.umkm_id
             WHERE m.user_id = ?'
        );
        $photoStmt->execute([$deleteId]);
        $photoPaths = $photoStmt->fetchAll(PDO::FETCH_COLUMN);

        // ON DELETE CASCADE akan menghapus umkm dan pendaftaran terkait.
        $deleteStmt = $pdo->prepare(
            'DELETE FROM users
             WHERE id = ? AND id <> ?
             LIMIT 1'
        );
        $deleteStmt->execute([$deleteId, (int) $user['id']]);

        if ($deleteStmt->rowCount() !== 1) {
            flash_set('error', 'Akun gagal dihapus. Silakan coba lagi.');
            redirect($returnUrl);
        }

        // Audit log untuk aksi destruktif.
        error_log(sprintf(
            '[USER DELETE] Admin #%d (%s) menghapus user #%d (%s, role=%s).',
            (int) $user['id'],
            (string) ($user['email'] ?? '-'),
            (int) $target['id'],
            (string) $target['email'],
            (string) $target['role']
        ));

        // Bersihkan file foto produk setelah record DB berhasil dihapus.
        $uploadRoot = realpath(__DIR__ . '/../uploads');

        if ($uploadRoot !== false) {
            $uploadRoot = rtrim(str_replace('\\', '/', $uploadRoot), '/');

            foreach ($photoPaths as $photoPath) {
                $relative = ltrim(str_replace('\\', '/', (string) $photoPath), '/');

                if ($relative === '' || !str_starts_with($relative, 'uploads/')) {
                    continue;
                }

                $absolute = realpath(__DIR__ . '/../' . $relative);

                if ($absolute === false || !is_file($absolute)) {
                    continue;
                }

                $absolute = str_replace('\\', '/', $absolute);

                // Pastikan file benar-benar berada di dalam folder uploads.
                if (!str_starts_with($absolute, $uploadRoot . '/')) {
                    continue;
                }

                if (!@unlink($absolute)) {
                    error_log('[USER DELETE FILE] Gagal menghapus file: ' . $absolute);
                }
            }
        }

        $message = 'Akun "' . (string) $target['nama_lengkap'] . '" (' . (string) $target['email'] . ') berhasil dihapus.';

        if ($target['role'] === 'umkm') {
            $message .= ' Profil UMKM, data pendaftaran, dan foto produknya (jika ada) ikut terhapus.';
        }

        flash_set('success', $message);
        redirect($returnUrl);
    } catch (PDOException $e) {
        error_log('[USER DELETE] ' . $e->getMessage());
        flash_set('error', 'Terjadi kesalahan saat menghapus akun. Silakan coba lagi.');
        redirect($returnUrl);
    }
}

// ---------------------------------------------------------------
// Statistik akun
// ---------------------------------------------------------------
$roleCounts = ['admin' => 0, 'umkm' => 0];
foreach ($pdo->query('SELECT role, COUNT(*) AS total FROM users GROUP BY role') as $row) {
    if (isset($roleCounts[$row['role']])) {
        $roleCounts[$row['role']] = (int) $row['total'];
    }
}
$totalUsers = array_sum($roleCounts);

// ---------------------------------------------------------------
// Daftar pengguna
// ---------------------------------------------------------------
$sql = 'SELECT u.id, u.nama_lengkap, u.email, u.role, u.created_at,
               m.nama_umkm, m.no_telepon
        FROM users u
        LEFT JOIN umkm m ON m.user_id = u.id';

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = '(u.nama_lengkap LIKE ? OR u.email LIKE ? OR m.nama_umkm LIKE ? OR m.no_telepon LIKE ?)';
    $like    = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($roleFilter !== 'Semua') {
    $where[]  = 'u.role = ?';
    $params[] = $roleFilter;
}

if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY u.created_at DESC, u.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$resultCount = count($users);

$flashSuccess = flash_get('success');
$flashError   = flash_get('error');

$pageTitle = 'Manajemen User';
$navItems  = [
    ['label' => 'Dashboard',       'href' => url('admin/dashboard.php'), 'active' => false],
    ['label' => 'Pendaftaran',     'href' => url('admin/dashboard.php') . '#pendaftaran', 'active' => false],
    ['label' => 'Manajemen User',  'href' => url('admin/users.php'), 'active' => true],
    ['label' => 'Chatbot AI',      'href' => url('admin/chatbot-config.php'), 'active' => false],
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
                Manajemen User
            </p>
            <h1 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">
                Akun terdaftar
            </h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">
                Pantau dan kelola seluruh akun admin dan UMKM yang terdaftar di Cirebon Creative Festival 2026.
            </p>
        </div>

        <span class="inline-flex w-fit items-center gap-2 rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-600 shadow-sm">
            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
            <?= $totalUsers ?> akun terdaftar
        </span>
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
    <div class="mt-8 grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Akun</p>
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                    </svg>
                </span>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900"><?= $totalUsers ?></p>
            <p class="mt-1 text-xs text-slate-500">Admin dan UMKM</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Admin</p>
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-slate-900 text-white">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                    </svg>
                </span>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900"><?= $roleCounts['admin'] ?></p>
            <p class="mt-1 text-xs text-slate-500">Pengelola festival</p>
        </div>

        <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-800">UMKM</p>
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-amber-100 text-amber-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72l1.189-1.19A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72M6.75 18h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .414.336.75.75.75z"/>
                    </svg>
                </span>
            </div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight text-amber-950"><?= $roleCounts['umkm'] ?></p>
            <p class="mt-1 text-xs text-amber-800">Tenant terdaftar</p>
        </div>
    </div>

    <!-- ============================= FILTER ============================= -->
    <form method="get" action="<?= e(url('admin/users.php')) ?>"
          class="mt-8 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="q" class="block text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">
                    Cari pengguna
                </label>
                <div class="relative mt-2">
                    <span class="pointer-events-none absolute inset-y-0 left-0 grid w-10 place-items-center text-slate-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                        </svg>
                    </span>
                    <input id="q" name="q" type="search" value="<?= e($search) ?>" maxlength="100"
                           placeholder="Nama, email, UMKM, atau nomor telepon..."
                           class="block w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-500 focus:border-amber-400 focus:ring-4 focus:ring-amber-100">
                </div>
            </div>

            <div class="sm:w-48">
                <label for="role" class="block text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500">
                    Role
                </label>
                <select id="role" name="role"
                        class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100">
                    <option value="Semua" <?= $roleFilter === 'Semua' ? 'selected' : '' ?>>Semua role</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="umkm" <?= $roleFilter === 'umkm' ? 'selected' : '' ?>>UMKM</option>
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-500 focus-visible:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                    </svg>
                    Terapkan
                </button>
                <?php if ($hasFilter): ?>
                    <a href="<?= e(url('admin/users.php')) ?>"
                       class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2">
                        Reset
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <div class="mt-4 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4">
        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-amber-100 text-amber-700">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376A2.25 2.25 0 004.5 19.5h15a2.25 2.25 0 001.803-3.624L13.803 4.5a2.25 2.25 0 00-3.606 0L2.697 15.876a2.25 2.25 0 00-.001 1.25z"/>
            </svg>
        </span>
        <p class="text-xs leading-relaxed text-amber-800">
            <strong class="font-bold">Perhatian:</strong> menghapus akun UMKM juga menghapus profil UMKM,
            seluruh data pendaftaran, dan foto produknya secara permanen. Akun yang sedang digunakan
            tidak dapat dihapus.
        </p>
    </div>

    <!-- ============================= DAFTAR USER ============================= -->
    <div class="mt-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs text-slate-500">
            Menampilkan <span class="font-semibold text-slate-700"><?= $resultCount ?></span>
            akun<?= $hasFilter ? ' yang cocok dengan filter' : ' terdaftar' ?>.
        </p>
        <p class="text-xs text-slate-500">Terakhir diperbarui: <?= e(format_tanggal(date('Y-m-d H:i:s'), true)) ?></p>
    </div>

    <?php if ($users): ?>
        <p class="mt-2 text-xs text-slate-500 lg:hidden">Geser tabel ke kanan untuk melihat semua kolom.</p>
    <?php endif; ?>

    <?php if (!$users): ?>
        <div class="mt-4 rounded-3xl border border-dashed border-slate-300 bg-white/70 p-10 text-center sm:p-14">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 text-slate-400">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                </svg>
            </span>
            <h2 class="mt-4 text-base font-bold text-slate-900">
                <?= $hasFilter ? 'Tidak ada akun yang cocok' : 'Belum ada akun terdaftar' ?>
            </h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500">
                <?= $hasFilter
                    ? 'Coba ubah kata kunci pencarian atau reset filter role untuk melihat semua akun.'
                    : 'Akun admin dan UMKM akan muncul di sini setelah proses registrasi.' ?>
            </p>
            <?php if ($hasFilter): ?>
                <a href="<?= e(url('admin/users.php')) ?>"
                   class="mt-5 inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">
                    Reset filter
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                    <caption class="sr-only">Daftar seluruh akun pengguna Cirebon Creative Festival 2026</caption>
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="w-16 px-5 py-3.5 text-xs font-bold uppercase tracking-wider text-slate-500">No</th>
                            <th scope="col" class="min-w-[220px] px-5 py-3.5 text-xs font-bold uppercase tracking-wider text-slate-500">Nama</th>
                            <th scope="col" class="min-w-[220px] px-5 py-3.5 text-xs font-bold uppercase tracking-wider text-slate-500">Email</th>
                            <th scope="col" class="whitespace-nowrap px-5 py-3.5 text-xs font-bold uppercase tracking-wider text-slate-500">No. Telepon</th>
                            <th scope="col" class="px-5 py-3.5 text-xs font-bold uppercase tracking-wider text-slate-500">Role</th>
                            <th scope="col" class="whitespace-nowrap px-5 py-3.5 text-xs font-bold uppercase tracking-wider text-slate-500">Tanggal Daftar</th>
                            <th scope="col" class="w-28 px-5 py-3.5 text-right text-xs font-bold uppercase tracking-wider text-slate-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($users as $index => $row): ?>
                            <?php
                            $isAdmin  = $row['role'] === 'admin';
                            $isSelf   = (int) $row['id'] === (int) $user['id'];
                            $initial  = mb_strtoupper(mb_substr((string) $row['nama_lengkap'], 0, 1));
                            $roleName = $isAdmin ? 'Admin' : 'UMKM';
                            ?>
                            <tr class="transition hover:bg-slate-50/80">
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500">
                                    <?= $index + 1 ?>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-bold text-slate-600 ring-1 ring-inset ring-slate-200" aria-hidden="true">
                                            <?= e($initial !== '' ? $initial : '?') ?>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-slate-900"><?= e((string) $row['nama_lengkap']) ?></p>
                                            <?php if (!empty($row['nama_umkm'])): ?>
                                                <p class="mt-0.5 truncate text-xs text-slate-500"><?= e((string) $row['nama_umkm']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <a href="mailto:<?= e((string) $row['email']) ?>"
                                       class="text-sm text-slate-600 underline-offset-4 transition hover:text-amber-700 hover:underline">
                                        <?= e((string) $row['email']) ?>
                                    </a>
                                </td>

                                <td class="whitespace-nowrap px-5 py-4">
                                    <?php if (!empty($row['no_telepon'])): ?>
                                        <a href="tel:<?= e((string) $row['no_telepon']) ?>"
                                           class="text-sm text-slate-600 underline-offset-4 transition hover:text-amber-700 hover:underline">
                                            <?= e((string) $row['no_telepon']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-sm text-slate-500">-</span>
                                    <?php endif; ?>
                                </td>

                                <td class="whitespace-nowrap px-5 py-4">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ring-1 ring-inset <?= $isAdmin ? 'bg-slate-900 text-white ring-slate-900' : 'bg-amber-100 text-amber-800 ring-amber-200' ?>">
                                        <?= e($roleName) ?>
                                    </span>
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500">
                                    <?= e(format_tanggal((string) $row['created_at'])) ?>
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <?php if ($isSelf): ?>
                                        <button type="button" disabled
                                                aria-label="Akun Anda tidak dapat dihapus"
                                                title="Akun yang sedang digunakan tidak dapat dihapus"
                                                class="inline-flex cursor-not-allowed items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-[11px] font-bold text-slate-400">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                                            </svg>
                                            Hapus
                                        </button>
                                    <?php else: ?>
                                        <form method="post" action="<?= e($returnUrl) ?>" class="inline-flex"
                                              onsubmit="return confirm('Hapus akun ini? Profil UMKM, data pendaftaran, dan foto produknya juga akan terhapus. Tindakan ini tidak dapat dibatalkan.');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="delete_id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit"
                                                    aria-label="Hapus akun <?= e((string) $row['nama_lengkap']) ?>"
                                                    class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-[11px] font-bold text-red-700 transition hover:border-red-300 hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-400 focus-visible:ring-offset-2">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0115.916 21.75H8.084A2.25 2.25 0 015.84 19.673L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                                </svg>
                                                Hapus
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-1 border-t border-slate-100 bg-slate-50/60 px-5 py-3 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                <p>Menampilkan <?= $resultCount ?> akun.</p>
                <p>Penghapusan UMKM juga menghapus profil dan pendaftarannya.</p>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../partials/dashboard-footer.php'; ?>
