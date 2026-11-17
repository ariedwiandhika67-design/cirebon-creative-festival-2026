<?php
/**
 * login.php — Masuk ke akun Cirebon Creative Festival 2026
 *
 * Alur:
 *   1. Validasi CSRF + input
 *   2. Cari user berdasarkan email (PDO prepared statement)
 *   3. Verifikasi password dengan password_verify()
 *   4. Redirect sesuai role: admin → admin/dashboard.php, umkm → umkm/dashboard.php
 */

declare(strict_types=1);

// ---------------------------------------------------------------
// Session & security headers
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

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

const MAX_LOGIN_ATTEMPTS = 5;
const LOGIN_LOCK_SECONDS = 60;

// Hash dummy agar waktu eksekusi tetap mirip saat email tidak ditemukan
const DUMMY_PASSWORD_HASH = '$2y$10$C9EtcThHn3XFKugJBr0SyejCacx.mbfBbHAIN50a0uQxawq7lUTZ2';

// ---------------------------------------------------------------
// Helper
// ---------------------------------------------------------------
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function dashboard_path(string $role): string
{
    return $role === 'admin' ? 'admin/dashboard.php' : 'umkm/dashboard.php';
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
        ? 'aria-invalid="true" aria-describedby="login-errors"'
        : '';
}

// ---------------------------------------------------------------
// State
// ---------------------------------------------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$notice = null;
$email  = '';

if (($_GET['logout'] ?? '') === '1') {
    $notice = 'Kamu telah keluar dari akun. Sampai jumpa!';
} elseif (($_GET['daftar'] ?? '') === 'sukses') {
    $notice = 'Akun berhasil dibuat. Silakan masuk dengan email dan password kamu.';
}

// Sudah login? Langsung arahkan ke dashboard sesuai role.
if (isset($_SESSION['user']['role']) && in_array($_SESSION['user']['role'], ['admin', 'umkm'], true)) {
    header('Location: ' . dashboard_path($_SESSION['user']['role']), true, 303);
    exit;
}

// ---------------------------------------------------------------
// Throttle percobaan login (berbasis session)
// ---------------------------------------------------------------
$lockedUntil  = (int) ($_SESSION['login_locked_until'] ?? 0);
$lockRemaining = max(0, $lockedUntil - time());

if ($lockedUntil > 0 && $lockRemaining === 0) {
    unset($_SESSION['login_locked_until'], $_SESSION['login_attempts']);
}

$isLocked = $lockRemaining > 0;

if ($isLocked) {
    $errors['general'] = 'Terlalu banyak percobaan masuk. Coba lagi dalam ' . $lockRemaining . ' detik.';
}

// ---------------------------------------------------------------
// Proses login
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLocked) {
    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $token    = (string) ($_POST['csrf_token'] ?? '');

    // --- CSRF ---
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $errors['general'] = 'Sesi keamanan tidak valid. Muat ulang halaman lalu coba lagi.';
    }

    // --- Validasi dasar ---
    if ($email === '') {
        $errors['email'] = 'Email wajib diisi.';
    } elseif (mb_strlen($email) > 150) {
        $errors['email'] = 'Email maksimal 150 karakter.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid.';
    }

    if ($password === '') {
        $errors['password'] = 'Password wajib diisi.';
    }

    // --- Cek kredensial ---
    if (!$errors) {
        require_once __DIR__ . '/koneksi.php';

        try {
            $stmt = $pdo->prepare(
                'SELECT id, nama_lengkap, email, password, role
                 FROM users
                 WHERE email = ?
                 LIMIT 1'
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Tetap jalankan password_verify walau email tidak ditemukan
            $hashToCheck = $user['password'] ?? DUMMY_PASSWORD_HASH;
            $passwordOk  = password_verify($password, $hashToCheck);

            if (!$user || !$passwordOk) {
                // Catat percobaan gagal
                $attempts = (int) ($_SESSION['login_attempts'] ?? 0) + 1;

                if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                    $_SESSION['login_locked_until'] = time() + LOGIN_LOCK_SECONDS;
                    $_SESSION['login_attempts']     = 0;
                    $errors['general'] = 'Terlalu banyak percobaan masuk. Coba lagi dalam ' . LOGIN_LOCK_SECONDS . ' detik.';
                } else {
                    $_SESSION['login_attempts'] = $attempts;
                    $errors['general'] = 'Email atau password salah. Periksa kembali data kamu.';
                }
            } else {
                $role = (string) $user['role'];

                if (!in_array($role, ['admin', 'umkm'], true)) {
                    $errors['general'] = 'Akun ini tidak memiliki akses yang valid. Hubungi admin festival.';
                } else {
                    // Login berhasil
                    session_regenerate_id(true);

                    $_SESSION['user'] = [
                        'id'           => (int) $user['id'],
                        'nama_lengkap' => (string) $user['nama_lengkap'],
                        'email'        => (string) $user['email'],
                        'role'         => $role,
                    ];

                    // Kompatibilitas dengan endpoint/komponen yang membaca $_SESSION['role'].
                    $_SESSION['role'] = $role;

                    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header('Location: ' . dashboard_path($role), true, 303);
                    exit;
                }
            }
        } catch (Throwable $e) {
            error_log('[LOGIN] ' . $e->getMessage());
            $errors['general'] = 'Terjadi kesalahan pada server. Silakan coba beberapa saat lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — Cirebon Creative Festival 2026</title>
    <meta name="description" content="Masuk ke akun UMKM atau admin Cirebon Creative Festival 2026.">
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
<body class="min-h-screen bg-slate-950 font-sans text-slate-900 antialiased selection:bg-amber-300 selection:text-slate-950">

<a href="#form-login" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-amber-400 focus:px-4 focus:py-2 focus:text-sm focus:font-bold focus:text-slate-950">
    Lewati ke formulir masuk
</a>

<!-- Background dekoratif -->
<div class="hero-grid pointer-events-none fixed inset-0" aria-hidden="true"></div>
<div class="pointer-events-none fixed -left-32 top-1/4 h-96 w-96 rounded-full bg-amber-500/20 blur-[120px]" aria-hidden="true"></div>
<div class="pointer-events-none fixed -bottom-32 -right-24 h-96 w-96 rounded-full bg-orange-600/10 blur-[120px]" aria-hidden="true"></div>

<div class="relative flex min-h-screen flex-col">

    <!-- ============================= HEADER ============================= -->
    <header class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-5 sm:px-6 lg:px-8">
        <a href="index.php" class="inline-flex items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-amber-300 via-amber-400 to-orange-500 text-sm font-extrabold tracking-tight text-slate-950 shadow-lg shadow-amber-500/20">
                CCF
            </span>
            <span class="flex flex-col leading-none">
                <span class="text-sm font-bold tracking-tight text-white">Cirebon Creative</span>
                <span class="mt-1 text-[10px] font-semibold uppercase tracking-[0.28em] text-amber-300/80">Festival 2026</span>
            </span>
        </a>

        <a href="index.php" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-300 transition hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
            </svg>
            Beranda
        </a>
    </header>

    <!-- ============================= KONTEN ============================= -->
    <main class="flex flex-1 items-center justify-center px-4 py-8 sm:px-6 sm:py-12">
        <div class="w-full max-w-md">

            <div class="text-center">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3.5 py-1.5 text-xs font-semibold text-slate-200 backdrop-blur">
                    <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                    Portal Tenant &amp; Admin
                </span>

                <h1 class="mt-5 text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                    Masuk ke akunmu
                </h1>
                <p class="mx-auto mt-3 max-w-sm text-sm leading-relaxed text-slate-300 sm:text-base">
                    Kelola profil UMKM, pantau status pendaftaran, dan siapkan produk terbaikmu.
                </p>
            </div>

            <div class="mt-8 rounded-3xl border border-white/10 bg-white p-6 shadow-2xl shadow-black/40 sm:p-8">

                <?php if ($notice !== null): ?>
                    <div role="status" class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                        <div class="flex items-start gap-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                </svg>
                            </span>
                            <p class="pt-1 text-sm font-medium text-emerald-800"><?= e($notice) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($errors): ?>
                    <div id="login-errors" role="alert" class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4">
                        <div class="flex items-start gap-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-red-100 text-red-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-red-800">Tidak dapat masuk</p>
                                <ul class="mt-2 list-disc space-y-1 pl-4 text-sm text-red-700">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= e($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form id="form-login" method="post" action="login.php" class="space-y-5" autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-800">Email</label>
                        <input id="email" name="email" type="email" required maxlength="150"
                               autocomplete="email" inputmode="email" placeholder="nama@email.com"
                               value="<?= e($email) ?>"
                               class="mt-2 <?= field_class('email', $errors) ?>"
                               <?= field_aria('email', $errors) ?>>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-slate-800">Password</label>
                        <div class="relative mt-2">
                            <input id="password" name="password" type="password" required
                                   autocomplete="current-password" placeholder="Masukkan password"
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
                    </div>

                    <button id="login-btn" type="submit" <?= $isLocked ? 'disabled' : '' ?>
                            class="group flex w-full items-center justify-center gap-2 rounded-xl bg-amber-400 px-6 py-3.5 text-sm font-bold text-slate-950 shadow-lg shadow-amber-500/25 transition hover:-translate-y-0.5 hover:bg-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0 disabled:hover:bg-amber-400">
                        <span id="login-label">Masuk</span>
                        <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12l-7.5 7.5M21 12H3"/>
                        </svg>
                    </button>
                </form>

                <div class="mt-6 border-t border-slate-100 pt-6 text-center">
                    <p class="text-sm text-slate-600">
                        Belum punya akun?
                        <a href="register.php" class="font-bold text-amber-700 underline-offset-4 transition hover:text-amber-800 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                            Daftar sebagai tenant
                        </a>
                    </p>
                </div>
            </div>

            <p class="mt-6 text-center text-xs leading-relaxed text-slate-400">
                Dengan masuk, data kamu digunakan untuk keperluan festival.<br class="hidden sm:block">
                Butuh bantuan? Hubungi <a href="mailto:hello@ccf2026.id" class="font-semibold text-slate-300 underline-offset-4 hover:text-amber-300 hover:underline">hello@ccf2026.id</a>
            </p>
        </div>
    </main>

    <!-- ============================= FOOTER ============================= -->
    <footer class="px-4 py-6 text-center text-xs text-slate-400 sm:px-6">
        &copy; 2026 Cirebon Creative Festival. Seluruh hak cipta dilindungi.
        <span class="mx-1.5 text-slate-600" aria-hidden="true">·</span>
        <a href="index.php" class="font-semibold text-slate-300 underline-offset-4 transition hover:text-amber-300 hover:underline">Beranda</a>
    </footer>
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
        var form = document.getElementById('form-login');
        var loginBtn = document.getElementById('login-btn');
        var loginLabel = document.getElementById('login-label');

        if (form && loginBtn && loginLabel) {
            form.addEventListener('submit', function () {
                if (!form.checkValidity()) return;

                loginBtn.disabled = true;
                loginBtn.classList.add('cursor-not-allowed', 'opacity-60');
                loginLabel.textContent = 'Memproses...';
            });
        }
    })();
</script>

<?php require __DIR__ . '/partials/chatbot.php'; ?>

</body>
</html>
