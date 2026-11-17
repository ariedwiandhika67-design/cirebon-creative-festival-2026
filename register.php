<?php
/**
 * register.php — Pendaftaran akun & profil UMKM
 * Cirebon Creative Festival 2026
 *
 * Alur:
 *   1. Validasi input (termasuk CSRF, kecocokan password, email unik)
 *   2. Simpan ke tabel `users` + `umkm` dalam satu transaksi
 *   3. Redirect (PRG) ke halaman sukses agar tidak terjadi double submit
 */

declare(strict_types=1);

// ---------------------------------------------------------------
// Session & CSRF
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

const KATEGORI_OPTIONS = ['Kuliner', 'Fashion & Kriya', 'Kerajinan Tangan', 'Lainnya'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ---------------------------------------------------------------
// Helper
// ---------------------------------------------------------------
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function field_class(string $field, array $errors): string
{
    $base = 'block w-full rounded-xl border bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:ring-4';

    return isset($errors[$field])
        ? $base . ' border-red-300 focus:border-red-400 focus:ring-red-100'
        : $base . ' border-slate-300 focus:border-amber-400 focus:ring-amber-100';
}

function field_aria(string $field, array $errors): string
{
    return isset($errors[$field])
        ? 'aria-invalid="true" aria-describedby="err-' . e($field) . '"'
        : '';
}

// ---------------------------------------------------------------
// State
// ---------------------------------------------------------------
$errors  = [];
$success = null;

$old = [
    'nama_lengkap'    => '',
    'email'           => '',
    'nama_umkm'       => '',
    'kategori_produk' => '',
    'no_telepon'      => '',
    'alamat'          => '',
];

// Pesan sukses setelah redirect (PRG)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['status'] ?? '') === 'sukses') {
    $success = $_SESSION['flash_registration'] ?? ['nama' => '', 'email' => ''];
    unset($_SESSION['flash_registration']);
}

// ---------------------------------------------------------------
// Proses form
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = null;

    $old['nama_lengkap']    = trim((string) ($_POST['nama_lengkap'] ?? ''));
    $old['email']           = strtolower(trim((string) ($_POST['email'] ?? '')));
    $old['nama_umkm']       = trim((string) ($_POST['nama_umkm'] ?? ''));
    $old['kategori_produk'] = trim((string) ($_POST['kategori_produk'] ?? ''));
    $old['no_telepon']      = trim((string) ($_POST['no_telepon'] ?? ''));
    $old['alamat']          = trim((string) ($_POST['alamat'] ?? ''));

    $password   = (string) ($_POST['password'] ?? '');
    $konfirmasi = (string) ($_POST['konfirmasi_password'] ?? '');

    // --- CSRF ---
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $errors['general'] = 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.';
    }

    // --- Nama lengkap ---
    if ($old['nama_lengkap'] === '') {
        $errors['nama_lengkap'] = 'Nama lengkap wajib diisi.';
    } elseif (mb_strlen($old['nama_lengkap']) < 3) {
        $errors['nama_lengkap'] = 'Nama lengkap minimal 3 karakter.';
    } elseif (mb_strlen($old['nama_lengkap']) > 100) {
        $errors['nama_lengkap'] = 'Nama lengkap maksimal 100 karakter.';
    }

    // --- Email ---
    if ($old['email'] === '') {
        $errors['email'] = 'Email wajib diisi.';
    } elseif (mb_strlen($old['email']) > 150) {
        $errors['email'] = 'Email maksimal 150 karakter.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid.';
    }

    // --- Password ---
    if ($password === '') {
        $errors['password'] = 'Password wajib diisi.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password minimal 8 karakter.';
    } elseif (strlen($password) > 72) {
        $errors['password'] = 'Password maksimal 72 karakter.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password harus mengandung huruf dan angka.';
    }

    // --- Konfirmasi password ---
    if ($konfirmasi === '') {
        $errors['konfirmasi_password'] = 'Konfirmasi password wajib diisi.';
    } elseif ($password !== $konfirmasi) {
        $errors['konfirmasi_password'] = 'Konfirmasi password tidak cocok.';
    }

    // --- Nama UMKM ---
    if ($old['nama_umkm'] === '') {
        $errors['nama_umkm'] = 'Nama UMKM wajib diisi.';
    } elseif (mb_strlen($old['nama_umkm']) < 3) {
        $errors['nama_umkm'] = 'Nama UMKM minimal 3 karakter.';
    } elseif (mb_strlen($old['nama_umkm']) > 150) {
        $errors['nama_umkm'] = 'Nama UMKM maksimal 150 karakter.';
    }

    // --- Kategori ---
    if ($old['kategori_produk'] === '') {
        $errors['kategori_produk'] = 'Pilih kategori produk.';
    } elseif (!in_array($old['kategori_produk'], KATEGORI_OPTIONS, true)) {
        $errors['kategori_produk'] = 'Kategori produk tidak valid.';
    }

    // --- Nomor telepon ---
    $phoneDigits = preg_replace('/\D+/', '', $old['no_telepon']);
    if ($old['no_telepon'] === '') {
        $errors['no_telepon'] = 'Nomor telepon / WhatsApp wajib diisi.';
    } elseif (!preg_match('/^[0-9+\s().-]+$/', $old['no_telepon'])) {
        $errors['no_telepon'] = 'Nomor telepon mengandung karakter yang tidak valid.';
    } elseif (strlen((string) $phoneDigits) < 9 || strlen((string) $phoneDigits) > 15) {
        $errors['no_telepon'] = 'Nomor telepon harus terdiri dari 9–15 digit.';
    }

    // --- Alamat ---
    if ($old['alamat'] === '') {
        $errors['alamat'] = 'Alamat UMKM wajib diisi.';
    } elseif (mb_strlen($old['alamat']) < 5) {
        $errors['alamat'] = 'Alamat UMKM minimal 5 karakter.';
    } elseif (mb_strlen($old['alamat']) > 500) {
        $errors['alamat'] = 'Alamat UMKM maksimal 500 karakter.';
    }

    // --- Simpan ke database ---
    if (!$errors) {
        require_once __DIR__ . '/koneksi.php';

        try {
            // Cek email (pelanggaran UNIQUE tetap ditangkap di catch untuk kondisi race)
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$old['email']]);

            if ($stmt->fetch()) {
                $errors['email'] = 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.';
            } else {
                $pdo->beginTransaction();

                // 1) Akun login
                $stmt = $pdo->prepare(
                    'INSERT INTO users (nama_lengkap, email, password, role)
                     VALUES (:nama_lengkap, :email, :password, :role)'
                );
                $stmt->execute([
                    ':nama_lengkap' => $old['nama_lengkap'],
                    ':email'        => $old['email'],
                    ':password'     => password_hash($password, PASSWORD_DEFAULT),
                    ':role'         => 'umkm',
                ]);

                $userId = (int) $pdo->lastInsertId();

                // 2) Profil UMKM
                $stmt = $pdo->prepare(
                    'INSERT INTO umkm (user_id, nama_umkm, kategori_produk, no_telepon, alamat)
                     VALUES (:user_id, :nama_umkm, :kategori_produk, :no_telepon, :alamat)'
                );
                $stmt->execute([
                    ':user_id'         => $userId,
                    ':nama_umkm'       => $old['nama_umkm'],
                    ':kategori_produk' => $old['kategori_produk'],
                    ':no_telepon'      => $old['no_telepon'],
                    ':alamat'          => $old['alamat'],
                ]);

                $pdo->commit();

                // PRG: hindari resubmit saat refresh
                session_regenerate_id(true);
                $_SESSION['flash_registration'] = [
                    'nama'  => $old['nama_lengkap'],
                    'email' => $old['email'],
                ];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header('Location: register.php?status=sukses', true, 303);
                exit;
            }
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('[REGISTER] ' . $e->getMessage());

            if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
                $errors['email'] = 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.';
            } else {
                $errors['general'] = 'Terjadi kesalahan pada server. Silakan coba beberapa saat lagi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Tenant — Cirebon Creative Festival 2026</title>
    <meta name="description" content="Daftarkan akun dan profil UMKM kamu untuk menjadi tenant Cirebon Creative Festival 2026.">
    <meta name="theme-color" content="#020617">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='16' fill='%23FBBF24'/%3E%3Ctext x='32' y='44' font-family='Arial,sans-serif' font-size='30' font-weight='bold' text-anchor='middle' fill='%23020617'%3EC%3C/text%3E%3C/svg%3E">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        serif: ['"Instrument Serif"', 'Georgia', 'serif'],
                    },
                },
            },
        };
    </script>

    <style>
        .hero-grid {
            background-image:
                linear-gradient(to right, rgba(255, 255, 255, 0.055) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.055) 1px, transparent 1px);
            background-size: 64px 64px;
            -webkit-mask-image: radial-gradient(ellipse at center, #000 25%, transparent 78%);
            mask-image: radial-gradient(ellipse at center, #000 25%, transparent 78%);
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
        }
    </style>
</head>
<body class="min-h-screen bg-stone-50 font-sans text-slate-900 antialiased selection:bg-amber-300 selection:text-slate-950">

<a href="#form-register" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-amber-400 focus:px-4 focus:py-2 focus:text-sm focus:font-bold focus:text-slate-950">
    Lewati ke formulir pendaftaran
</a>

<div class="grid min-h-screen lg:grid-cols-[0.92fr_1.08fr]">

    <!-- ============================= PANEL BRAND ============================= -->
    <aside class="relative hidden overflow-hidden bg-slate-950 px-10 py-12 lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col lg:justify-between xl:px-14">
        <div class="hero-grid absolute inset-0" aria-hidden="true"></div>
        <div class="absolute -left-24 top-1/3 h-80 w-80 rounded-full bg-amber-500/20 blur-[110px]" aria-hidden="true"></div>
        <div class="absolute -bottom-24 right-0 h-72 w-72 rounded-full bg-orange-600/10 blur-[110px]" aria-hidden="true"></div>

        <div class="relative">
            <a href="index.php" class="inline-flex items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-gradient-to-br from-amber-300 via-amber-400 to-orange-500 text-sm font-extrabold tracking-tight text-slate-950 shadow-lg shadow-amber-500/20">
                    CCF
                </span>
                <span class="flex flex-col leading-none">
                    <span class="text-sm font-bold tracking-tight text-white">Cirebon Creative</span>
                    <span class="mt-1 text-[10px] font-semibold uppercase tracking-[0.28em] text-amber-300/80">Festival 2026</span>
                </span>
            </a>
        </div>

        <div class="relative max-w-md">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3.5 py-1.5 text-xs font-semibold text-slate-200 backdrop-blur">
                <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                Pendaftaran tenant 2026 dibuka
            </span>

            <h1 class="mt-6 text-4xl font-extrabold leading-[1.08] tracking-tight text-white xl:text-5xl">
                Bawa produk terbaikmu ke
                <span class="font-serif font-normal italic text-amber-300">panggung Cirebon.</span>
            </h1>

            <p class="mt-5 text-base leading-relaxed text-slate-300">
                Satu akun untuk mendaftarkan UMKM, mengikuti kurasi, dan terhubung dengan buyer serta mitra usaha.
            </p>

            <ul class="mt-8 space-y-4">
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full bg-amber-400/10 text-amber-300 ring-1 ring-inset ring-amber-400/20">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-bold text-white">Kurasi produk</p>
                        <p class="mt-0.5 text-sm leading-relaxed text-slate-400">Tim kurator menilai kualitas dan kesiapan produkmu.</p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full bg-amber-400/10 text-amber-300 ring-1 ring-inset ring-amber-400/20">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-bold text-white">Akses pasar</p>
                        <p class="mt-0.5 text-sm leading-relaxed text-slate-400">Temu buyer, business matching, dan promosi digital.</p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full bg-amber-400/10 text-amber-300 ring-1 ring-inset ring-amber-400/20">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-bold text-white">Workshop praktis</p>
                        <p class="mt-0.5 text-sm leading-relaxed text-slate-400">Bekal ilmu untuk mengembangkan usaha ke level berikutnya.</p>
                    </div>
                </li>
            </ul>
        </div>

        <div class="relative rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur">
            <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-amber-300">Simpan tanggalnya</p>
            <p class="mt-2 font-serif text-2xl italic text-white">12–13 Desember 2026</p>
            <p class="mt-1 text-sm text-slate-400">Kota Cirebon, Jawa Barat</p>
        </div>
    </aside>

    <!-- ============================= KONTEN FORM ============================= -->
    <main class="flex min-h-screen flex-col">

        <!-- Header mobile -->
        <div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-4 sm:px-6 lg:hidden">
            <a href="index.php" class="inline-flex items-center gap-2.5 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-gradient-to-br from-amber-300 via-amber-400 to-orange-500 text-xs font-extrabold text-slate-950">CCF</span>
                <span class="text-sm font-bold tracking-tight text-slate-900">Cirebon Creative</span>
            </a>
            <a href="index.php" class="text-sm font-semibold text-slate-600 transition hover:text-slate-900">Beranda</a>
        </div>

        <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col justify-center px-4 py-10 sm:px-6 lg:px-10 lg:py-14">

            <a href="index.php" class="hidden items-center gap-1.5 text-sm font-semibold text-slate-500 transition hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 lg:inline-flex">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
                Kembali ke beranda
            </a>

            <?php if ($success !== null): ?>

                <!-- ===== Sukses ===== -->
                <div class="mt-8 rounded-3xl border border-emerald-200 bg-white p-8 text-center shadow-xl shadow-emerald-500/5 sm:p-10">
                    <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-emerald-600">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                        </svg>
                    </span>

                    <h1 class="mt-6 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">
                        Pendaftaran berhasil!
                    </h1>

                    <p class="mt-3 text-sm leading-relaxed text-slate-600 sm:text-base">
                        <?php if ($success['nama'] !== ''): ?>
                            Akun UMKM <strong class="font-bold text-slate-900"><?= e($success['nama']) ?></strong> sudah dibuat dan siap digunakan.
                        <?php else: ?>
                            Akun UMKM kamu sudah berhasil dibuat dan siap digunakan.
                        <?php endif; ?>
                        Silakan masuk untuk melanjutkan pendaftaran produk festival.
                    </p>

                    <?php if ($success['email'] !== ''): ?>
                        <p class="mt-3 text-sm text-slate-500">
                            Email terdaftar:
                            <span class="font-semibold text-slate-700"><?= e($success['email']) ?></span>
                        </p>
                    <?php endif; ?>

                    <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                        <a href="login.php?daftar=sukses" class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-400 px-6 py-3.5 text-sm font-bold text-slate-950 shadow-lg shadow-amber-500/25 transition hover:-translate-y-0.5 hover:bg-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2">
                            Masuk ke Akun
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12l-7.5 7.5M21 12H3"/>
                            </svg>
                        </a>
                        <a href="index.php" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-6 py-3.5 text-sm font-bold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2">
                            Kembali ke Beranda
                        </a>
                    </div>

                    <p class="mt-6 text-xs leading-relaxed text-slate-500">
                        Gunakan email dan password yang tadi kamu daftarkan untuk masuk.
                    </p>
                </div>

            <?php else: ?>

                <!-- ===== Heading ===== -->
                <div class="mt-8 lg:mt-10">
                    <p class="inline-flex items-center gap-3 text-xs font-bold uppercase tracking-[0.25em] text-amber-700">
                        <span class="h-px w-8 bg-amber-500"></span>
                        Daftar Tenant
                    </p>
                    <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                        Buat akun UMKM kamu
                    </h1>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600 sm:text-base">
                        Lengkapi data akun dan profil usaha. Setelah akun dibuat, kamu bisa melanjutkan pendaftaran produk festival.
                    </p>
                </div>

                <!-- ===== Alert error ===== -->
                <?php if ($errors): ?>
                    <div role="alert" class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-4 sm:p-5">
                        <div class="flex items-start gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-red-100 text-red-600">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-red-800">Periksa kembali data kamu</p>
                                <ul class="mt-2 list-disc space-y-1 pl-4 text-sm text-red-700">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= e($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ===== Form ===== -->
                <form id="form-register" method="post" action="register.php" class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5 sm:p-8" autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                    <!-- Section 1: Akun -->
                    <fieldset>
                        <legend class="flex items-center gap-3">
                            <span class="grid h-7 w-7 place-items-center rounded-full bg-amber-100 text-xs font-extrabold text-amber-800">1</span>
                            <span class="text-sm font-bold uppercase tracking-[0.15em] text-slate-700">Informasi Akun</span>
                        </legend>

                        <div class="mt-6 space-y-5">
                            <div>
                                <label for="nama_lengkap" class="block text-sm font-semibold text-slate-800">
                                    Nama Lengkap <span class="text-red-500" aria-hidden="true">*</span>
                                </label>
                                <input id="nama_lengkap" name="nama_lengkap" type="text" required maxlength="100"
                                       autocomplete="name" placeholder="Contoh: Siti Aminah"
                                       value="<?= e($old['nama_lengkap']) ?>"
                                       class="mt-2 <?= field_class('nama_lengkap', $errors) ?>"
                                       <?= field_aria('nama_lengkap', $errors) ?>>
                                <?php if (isset($errors['nama_lengkap'])): ?>
                                    <p id="err-nama_lengkap" class="mt-1.5 text-xs font-medium text-red-600"><?= e($errors['nama_lengkap']) ?></p>
                                <?php endif; ?>
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-semibold text-slate-800">
                                    Email <span class="text-red-500" aria-hidden="true">*</span>
                                </label>
                                <input id="email" name="email" type="email" required maxlength="150"
                                       autocomplete="email" placeholder="nama@email.com"
                                       value="<?= e($old['email']) ?>"
                                       class="mt-2 <?= field_class('email', $errors) ?>"
                                       <?= field_aria('email', $errors) ?>>
                                <p class="mt-1.5 text-xs text-slate-500">Email ini akan digunakan untuk masuk ke akun.</p>
                                <?php if (isset($errors['email'])): ?>
                                    <p id="err-email" class="mt-1.5 text-xs font-medium text-red-600"><?= e($errors['email']) ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="password" class="block text-sm font-semibold text-slate-800">
                                        Password <span class="text-red-500" aria-hidden="true">*</span>
                                    </label>
                                    <div class="relative mt-2">
                                        <input id="password" name="password" type="password" required minlength="8" maxlength="72"
                                               autocomplete="new-password" placeholder="Min. 8 karakter"
                                               class="<?= field_class('password', $errors) ?> pr-12"
                                               <?= field_aria('password', $errors) ?>>
                                        <button type="button" data-toggle-password="password" aria-label="Tampilkan password"
                                                class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-xl text-slate-400 transition hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-400">
                                            <svg class="eye-open h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                            <svg class="eye-off hidden h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <p class="mt-1.5 text-xs text-slate-500">Minimal 8 karakter, kombinasi huruf dan angka.</p>
                                    <?php if (isset($errors['password'])): ?>
                                        <p id="err-password" class="mt-1.5 text-xs font-medium text-red-600"><?= e($errors['password']) ?></p>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label for="konfirmasi_password" class="block text-sm font-semibold text-slate-800">
                                        Konfirmasi Password <span class="text-red-500" aria-hidden="true">*</span>
                                    </label>
                                    <div class="relative mt-2">
                                        <input id="konfirmasi_password" name="konfirmasi_password" type="password" required minlength="8" maxlength="72"
                                               autocomplete="new-password" placeholder="Ulangi password"
                                               class="<?= field_class('konfirmasi_password', $errors) ?> pr-12"
                                               <?= field_aria('konfirmasi_password', $errors) ?>>
                                        <button type="button" data-toggle-password="konfirmasi_password" aria-label="Tampilkan konfirmasi password"
                                                class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-xl text-slate-400 transition hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-400">
                                            <svg class="eye-open h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                            <svg class="eye-off hidden h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <?php if (isset($errors['konfirmasi_password'])): ?>
                                        <p id="err-konfirmasi_password" class="mt-1.5 text-xs font-medium text-red-600"><?= e($errors['konfirmasi_password']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- Section 2: UMKM -->
                    <fieldset class="mt-8 border-t border-slate-200 pt-8">
                        <legend class="flex items-center gap-3">
                            <span class="grid h-7 w-7 place-items-center rounded-full bg-amber-100 text-xs font-extrabold text-amber-800">2</span>
                            <span class="text-sm font-bold uppercase tracking-[0.15em] text-slate-700">Profil UMKM</span>
                        </legend>

                        <div class="mt-6 space-y-5">
                            <div>
                                <label for="nama_umkm" class="block text-sm font-semibold text-slate-800">
                                    Nama UMKM <span class="text-red-500" aria-hidden="true">*</span>
                                </label>
                                <input id="nama_umkm" name="nama_umkm" type="text" required maxlength="150"
                                       autocomplete="organization" placeholder="Contoh: Batik Trusmi Asri"
                                       value="<?= e($old['nama_umkm']) ?>"
                                       class="mt-2 <?= field_class('nama_umkm', $errors) ?>"
                                       <?= field_aria('nama_umkm', $errors) ?>>
                                <?php if (isset($errors['nama_umkm'])): ?>
                                    <p id="err-nama_umkm" class="mt-1.5 text-xs font-medium text-red-600"><?= e($errors['nama_umkm']) ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="kategori_produk" class="block text-sm font-semibold text-slate-800">
                                        Kategori Produk <span class="text-red-500" aria-hidden="true">*</span>
                                    </label>
                                    <div class="relative mt-2">
                                        <select id="kategori_produk" name="kategori_produk" required
                                                class="<?= field_class('kategori_produk', $errors) ?> appearance-none pr-10"
                                                <?= field_aria('kategori_produk', $errors) ?>>
                                            <option value="" disabled <?= $old['kategori_produk'] === '' ? 'selected' : '' ?>>Pilih kategori</option>
                                            <?php foreach (KATEGORI_OPTIONS as $kategori): ?>
                                                <option value="<?= e($kategori) ?>" <?= $old['kategori_produk'] === $kategori ? 'selected' : '' ?>>
                                                    <?= e($kategori) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <svg class="pointer-events-none absolute right-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                        </svg>
                                    </div>
                                    <?php if (isset($errors['kategori_produk'])): ?>
                                        <p id="err-kategori_produk" class="mt-1.5 text-xs font-medium text-red-600"><?= e($errors['kategori_produk']) ?></p>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label for="no_telepon" class="block text-sm font-semibold text-slate-800">
                                        No. Telepon / WhatsApp <span class="text-red-500" aria-hidden="true">*</span>
                                    </label>
                                    <input id="no_telepon" name="no_telepon" type="tel" required maxlength="20"
                                           autocomplete="tel" placeholder="08xxxxxxxxxx"
                                           value="<?= e($old['no_telepon']) ?>"
                                           class="mt-2 <?= field_class('no_telepon', $errors) ?>"
                                           <?= field_aria('no_telepon', $errors) ?>>
                                    <?php if (isset($errors['no_telepon'])): ?>
                                        <p id="err-no_telepon" class="mt-1.5 text-xs font-medium text-red-600"><?= e($errors['no_telepon']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div>
                                <label for="alamat" class="block text-sm font-semibold text-slate-800">
                                    Alamat UMKM <span class="text-red-500" aria-hidden="true">*</span>
                                </label>
                                <textarea id="alamat" name="alamat" required rows="3" maxlength="500"
                                          autocomplete="street-address" placeholder="Nama jalan, nomor, kelurahan, kecamatan, kota"
                                          class="mt-2 <?= field_class('alamat', $errors) ?> resize-y"
                                          <?= field_aria('alamat', $errors) ?>><?= e($old['alamat']) ?></textarea>
                                <?php if (isset($errors['alamat'])): ?>
                                    <p id="err-alamat" class="mt-1.5 text-xs font-medium text-red-600"><?= e($errors['alamat']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </fieldset>

                    <div class="mt-8 border-t border-slate-200 pt-6">
                        <button id="submit-btn" type="submit"
                                class="group flex w-full items-center justify-center gap-2 rounded-xl bg-amber-400 px-6 py-3.5 text-sm font-bold text-slate-950 shadow-lg shadow-amber-500/25 transition hover:-translate-y-0.5 hover:bg-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2">
                            <span id="submit-label">Daftar Sekarang</span>
                            <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12l-7.5 7.5M21 12H3"/>
                            </svg>
                        </button>
                        <p class="mt-3 text-center text-xs leading-relaxed text-slate-500">
                            Dengan mendaftar, data kamu digunakan untuk keperluan seleksi dan komunikasi festival.
                        </p>
                    </div>
                </form>

                <p class="mt-6 text-center text-sm text-slate-600">
                    Sudah punya akun?
                    <a href="login.php" class="font-bold text-amber-700 underline-offset-4 transition hover:text-amber-800 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                        Masuk di sini
                    </a>
                </p>

            <?php endif; ?>
        </div>

        <div class="border-t border-slate-200 px-4 py-5 text-center text-xs text-slate-500 sm:px-6">
            &copy; 2026 Cirebon Creative Festival. Seluruh hak cipta dilindungi.
        </div>
    </main>
</div>

<script>
    (function () {
        'use strict';

        /* Tampilkan / sembunyikan password */
        document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById(button.getAttribute('data-toggle-password'));
                if (!input) return;

                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');

                var eyeOpen = button.querySelector('.eye-open');
                var eyeOff = button.querySelector('.eye-off');
                if (eyeOpen) eyeOpen.classList.toggle('hidden', show);
                if (eyeOff) eyeOff.classList.toggle('hidden', !show);
            });
        });

        /* Cegah double submit */
        var form = document.getElementById('form-register');
        var submitBtn = document.getElementById('submit-btn');
        var submitLabel = document.getElementById('submit-label');

        if (form && submitBtn && submitLabel) {
            form.addEventListener('submit', function () {
                if (!form.checkValidity()) return;

                submitBtn.disabled = true;
                submitBtn.classList.add('cursor-not-allowed', 'opacity-70');
                submitLabel.textContent = 'Memproses...';
            });
        }
    })();
</script>

<?php require __DIR__ . '/partials/chatbot.php'; ?>

</body>
</html>
