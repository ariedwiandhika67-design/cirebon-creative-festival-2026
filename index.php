<?php
/**
 * index.php — Landing page Cirebon Creative Festival 2026
 * PHP native + Tailwind CSS (CDN)
 *
 * Halaman ini tidak membutuhkan koneksi database.
 * Semua tautan pendaftaran mengarah ke register.php.
 */

declare(strict_types=1);

// Session ringan untuk widget chatbot yang sadar role dan token CSRF.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

const EVENT_NAME = 'Cirebon Creative Festival 2026';
const EVENT_YEAR = 2026;

$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$navLinks = [
    ['label' => 'Tentang Acara', 'href' => '#tentang'],
    ['label' => 'Kategori',      'href' => '#kategori'],
    ['label' => 'Sorotan',       'href' => '#sorotan'],
    ['label' => 'Jadwal',        'href' => '#jadwal'],
];

$pillars = [
    [
        'number'      => '01',
        'title'       => 'Kuliner Nusantara',
        'description' => 'Angkat cita rasa khas Cirebon dan Nusantara — dari emping melinjo hingga kopi lokal — ke pasar yang lebih luas.',
        'points'      => ['Makanan & minuman siap saji', 'Produk kemasan dan oleh-oleh', 'Kopi, teh, dan minuman lokal'],
        'icon'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 11h18M5 11a7 7 0 0 0 14 0M8.5 7.5c0-1 1-1.5 1-2.5M12.5 7.5c0-1 1-1.5 1-2.5M16.5 7.5c0-1 1-1.5 1-2.5"/>',
    ],
    [
        'number'      => '02',
        'title'       => 'Fashion & Kriya',
        'description' => 'Beri ruang bagi batik Trusmi, tenun, aksesori, dan kriya tangan untuk bercerita melalui karya yang bisa dipakai dan dikoleksi.',
        'points'      => ['Batik, tenun, dan tekstil', 'Kriya tangan & kerajinan', 'Fashion lokal dan aksesori'],
        'icon'        => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3l1.9 4.7L18.5 9.6l-4.6 1.9L12 16l-1.9-4.5L5.5 9.6l4.6-1.9L12 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M18 15.5l.9 2.1 2.1.9-2.1.9-.9 2.1-.9-2.1-2.1-.9 2.1-.9.9-2.1z"/>',
    ],
    [
        'number'      => '03',
        'title'       => 'Jaringan & Pasar Luas',
        'description' => 'Pertemukan pelaku UMKM dengan buyer, kurator, dan mitra usaha lewat sesi business matching, workshop, dan promosi digital.',
        'points'      => ['Temu buyer & business matching', 'Workshop dan kelas praktis', 'Eksposur promosi digital'],
        'icon'        => '<circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h18M12 3c2.4 2.5 3.7 5.6 3.7 9s-1.3 6.5-3.7 9c-2.4-2.5-3.7-5.6-3.7-9s1.3-6.5 3.7-9z"/>',
    ],
];

$schedule = [
    [
        'step'        => '01',
        'title'       => 'Pendaftaran Tenant',
        'date'        => '1 Sep – 15 Nov 2026',
        'description' => 'Isi data UMKM, unggah foto produk, dan kirim pendaftaran melalui halaman Daftar Tenant.',
        'badge'       => 'Dibuka',
    ],
    [
        'step'        => '02',
        'title'       => 'Kurasi & Seleksi',
        'date'        => '16 – 25 Nov 2026',
        'description' => 'Tim kurator menilai kualitas produk, kesiapan tenant, dan kesesuaian kategori festival.',
        'badge'       => null,
    ],
    [
        'step'        => '03',
        'title'       => 'Pengumuman',
        'date'        => '28 Nov 2026',
        'description' => 'Hasil seleksi diumumkan melalui email dan dashboard akun masing-masing UMKM.',
        'badge'       => null,
    ],
    [
        'step'        => '04',
        'title'       => 'Hari Festival',
        'date'        => '12 – 13 Des 2026',
        'description' => 'Dua hari pameran, transaksi, workshop, dan temu mitra di Kota Cirebon.',
        'badge'       => null,
    ],
];

// Sorotan tenant untuk carousel. Foto memakai aset unggahan asli milik UMKM.
$highlights = [
    [
        'tenant'      => 'Mochi Yummy',
        'category'    => 'Kuliner Nusantara',
        'tagline'     => 'Mochi viral dengan isian lumer.',
        'description' => 'Mochi lembut yang dibuat harian dengan isian stroberi segar, matcha pekat, dan kacang merah klasik. Teksturnya tetap lembut saat sampai di tangan pembeli — favorit baru untuk oleh-oleh khas Cirebon.',
        'image'       => 'uploads/produk/3cacb423a7b6780dfd61362eb5a8921a.jpg',
        'alt'         => 'Aneka mochi warna pastel dengan isian stroberi, matcha, dan kacang merah',
        'tags'        => ['Mochi', 'Oleh-oleh', 'Homemade'],
        'accent'      => 'rose',
        'location'    => 'Kota Cirebon',
        'highlight'   => 'Best seller',
    ],
    [
        'tenant'      => 'Cimol Bojot Carmen',
        'category'    => 'Kuliner Nusantara',
        'tagline'     => 'Cimol kenyal, keju lumer di tengah.',
        'description' => 'Cimol bojot dengan isian keju lumer dan bumbu pedas manis yang bikin nagih. Digoreng saat dipesan agar bagian luar tetap renyah dan bagian dalamnya meleleh sempurna.',
        'image'       => 'uploads/produk/a95d08203926f7e83570c098e5e7e1e1.jpg',
        'alt'         => 'Semangkuk cimol bojot berbalut bumbu pedas manis',
        'tags'        => ['Cimol', 'Pedas manis', 'Street food'],
        'accent'      => 'orange',
        'location'    => 'Kota Cirebon',
        'highlight'   => 'Favorit pengunjung',
    ],
    [
        'tenant'      => 'Batik Trusmi by Jiwoo',
        'category'    => 'Fashion & Kriya',
        'tagline'     => 'Motif awan, cerita Trusmi.',
        'description' => 'Aneka kain batik khas Cirebon dengan motif awan dan pesisir yang digambar tangan. Setiap lembar membawa cerita Trusmi — siap dijahit menjadi kemeja, dress, atau koleksi interior.',
        'image'       => 'uploads/produk/a9e58e139bb8fa25ed0a41c9c8909b19.jpg',
        'alt'         => 'Kain batik Cirebon bermotif awan biru',
        'tags'        => ['Batik tulis', 'Trusmi', 'Kain panjang'],
        'accent'      => 'sky',
        'location'    => 'Trusmi, Cirebon',
        'highlight'   => 'Kriya pilihan',
    ],
    [
        'tenant'      => 'Sempol Ayam',
        'category'    => 'Kuliner Nusantara',
        'tagline'     => 'Sempol ayam hangat, saus tomat segar.',
        'description' => 'Sempol ayam dengan daging ayam pilihan dan bumbu rumahan, disajikan hangat bersama saus tomat segar. Camilan sederhana yang selalu jadi alasan untuk mampir lagi.',
        'image'       => 'uploads/produk/b44b40d56a8ee2bc89191144e5f39e42.jpg',
        'alt'         => 'Sempol ayam di tusuk sate dengan saus tomat',
        'tags'        => ['Sempol', 'Camilan', 'Halal'],
        'accent'      => 'amber',
        'location'    => 'Kota Cirebon',
        'highlight'   => 'Camilan hangat',
    ],
];

$accentStyles = [
    'rose' => [
        'badge' => 'text-rose-200 ring-rose-300/30',
        'dot'   => 'bg-rose-300',
    ],
    'orange' => [
        'badge' => 'text-orange-200 ring-orange-300/30',
        'dot'   => 'bg-orange-300',
    ],
    'sky' => [
        'badge' => 'text-sky-200 ring-sky-300/30',
        'dot'   => 'bg-sky-300',
    ],
    'amber' => [
        'badge' => 'text-amber-200 ring-amber-300/30',
        'dot'   => 'bg-amber-300',
    ],
];
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= EVENT_NAME ?> — Festival UMKM &amp; Ekonomi Kreatif Cirebon</title>
    <meta name="description" content="Cirebon Creative Festival 2026 mempertemukan UMKM, perajin, dan kreator lokal Cirebon dengan pembeli, kurator, dan mitra usaha. Daftarkan tenant-mu sekarang.">
    <meta name="theme-color" content="#020617">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= EVENT_NAME ?>">
    <meta property="og:description" content="Dua hari pameran, workshop, dan temu mitra untuk ekonomi kreatif Cirebon. 12–13 Desember 2026.">
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
        html { scroll-padding-top: 5.5rem; }

        .hero-grid {
            background-image:
                linear-gradient(to right, rgba(255, 255, 255, 0.055) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.055) 1px, transparent 1px);
            background-size: 64px 64px;
            -webkit-mask-image: radial-gradient(ellipse at center, #000 30%, transparent 78%);
            mask-image: radial-gradient(ellipse at center, #000 30%, transparent 78%);
        }

        /* Animasi reveal hanya aktif jika JavaScript berjalan */
        .reveal-init {
            opacity: 0;
            transform: translateY(20px);
            transition:
                opacity 0.7s cubic-bezier(0.22, 1, 0.36, 1),
                transform 0.7s cubic-bezier(0.22, 1, 0.36, 1);
            transition-delay: var(--reveal-delay, 0ms);
        }
        .reveal-in {
            opacity: 1;
            transform: none;
        }

        /* ---- Carousel sorotan tenant ---- */
        #tenant-track {
            transition: transform 750ms cubic-bezier(0.22, 1, 0.36, 1);
            will-change: transform;
        }

        .slider-ready .slider-slide .slide-media img {
            transform: scale(1.04);
            transition: transform 1400ms cubic-bezier(0.22, 1, 0.36, 1);
        }
        .slider-ready .slider-slide.is-active .slide-media img {
            transform: scale(1.1);
        }

        .slider-ready .slider-slide .slide-copy {
            opacity: 0.35;
            transform: translateY(14px);
            transition:
                opacity 600ms ease,
                transform 600ms cubic-bezier(0.22, 1, 0.36, 1);
        }
        .slider-ready .slider-slide.is-active .slide-copy {
            opacity: 1;
            transform: none;
        }

        .slider-dot.is-active {
            width: 2rem;
            background-color: #fcd34d;
        }

        .slider-thumb.is-active {
            border-color: rgba(252, 211, 77, 0.45);
            background-color: rgba(252, 211, 77, 0.08);
            box-shadow: 0 0 32px -14px rgba(251, 191, 36, 0.75);
        }
        .slider-thumb.is-active img {
            transform: scale(1.06);
        }

        #slider-progress {
            transform: scaleX(0);
            transform-origin: left center;
            background-image: linear-gradient(90deg, #fcd34d, #fb923c);
        }
        #slider-progress.is-running {
            animation: ccf-slider-progress var(--slide-duration, 6500ms) linear forwards;
        }
        #slider-progress.is-paused {
            animation-play-state: paused;
        }

        @keyframes ccf-slider-progress {
            from { transform: scaleX(0); }
            to   { transform: scaleX(1); }
        }

        .nav-active {
            background-color: rgba(255, 255, 255, 0.07);
            color: #fff;
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .reveal-init {
                opacity: 1;
                transform: none;
                transition: none;
            }

            #tenant-track { transition: none; }
            .slider-ready .slider-slide .slide-media img,
            .slider-ready .slider-slide .slide-copy {
                opacity: 1;
                transform: none;
                transition: none;
            }
            #slider-progress {
                transform: scaleX(1);
            }
            #slider-progress.is-running {
                animation: none;
                transform: scaleX(1);
            }
        }

        @media print {
            .reveal-init { opacity: 1; transform: none; }
        }
    </style>
</head>
<body class="bg-stone-50 font-sans text-slate-900 antialiased selection:bg-amber-300 selection:text-slate-950">

<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-amber-400 focus:px-4 focus:py-2 focus:text-sm focus:font-bold focus:text-slate-950">
    Lewati ke konten utama
</a>

<!-- Indikator progres baca -->
<div class="pointer-events-none fixed inset-x-0 top-0 z-[60] h-[3px]" aria-hidden="true">
    <div id="scroll-progress" class="h-full w-full origin-left scale-x-0 bg-gradient-to-r from-amber-300 via-orange-400 to-amber-300"></div>
</div>

<!-- ============================= NAVBAR ============================= -->
<header id="site-header" class="sticky top-0 z-50 border-b border-white/10 bg-slate-950/90 backdrop-blur-xl transition-shadow duration-300">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:h-20 lg:px-8">

        <a href="#home" class="group flex items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-amber-300 via-amber-400 to-orange-500 text-sm font-extrabold tracking-tight text-slate-950 shadow-lg shadow-amber-500/20 transition group-hover:scale-105">
                CCF
            </span>
            <span class="flex flex-col leading-none">
                <span class="text-sm font-bold tracking-tight text-white">Cirebon Creative</span>
                <span class="mt-1 text-[10px] font-semibold uppercase tracking-[0.28em] text-amber-300/80">Festival 2026</span>
            </span>
        </a>

        <nav class="hidden items-center gap-1 lg:flex" aria-label="Navigasi utama">
            <?php foreach ($navLinks as $link): ?>
                <a href="<?= htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8') ?>"
                   class="rounded-lg px-4 py-2 text-sm font-medium text-slate-300 transition hover:bg-white/5 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                    <?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="hidden items-center gap-3 lg:flex">
            <a href="login.php" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-200 transition hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                Masuk
            </a>
            <a href="register.php" class="rounded-xl bg-amber-400 px-4 py-2.5 text-sm font-bold text-slate-950 shadow-lg shadow-amber-500/25 transition hover:-translate-y-0.5 hover:bg-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">
                Daftar Tenant
            </a>
        </div>

        <button id="menu-toggle" type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-white transition hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 lg:hidden"
                aria-controls="mobile-menu" aria-expanded="false" aria-label="Buka menu navigasi">
            <svg id="icon-open" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/>
            </svg>
            <svg id="icon-close" class="hidden h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div id="mobile-menu" class="hidden border-t border-white/10 bg-slate-950/95 lg:hidden">
        <nav class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-4 sm:px-6" aria-label="Navigasi seluler">
            <?php foreach ($navLinks as $link): ?>
                <a href="<?= htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8') ?>"
                   class="rounded-lg px-3 py-3 text-sm font-medium text-slate-200 transition hover:bg-white/5 hover:text-white">
                    <?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
            <div class="my-2 h-px bg-white/10"></div>
            <a href="login.php" class="rounded-lg px-3 py-3 text-sm font-semibold text-slate-200 transition hover:bg-white/5 hover:text-white">
                Masuk
            </a>
            <a href="register.php" class="mt-1 rounded-xl bg-amber-400 px-3 py-3 text-center text-sm font-bold text-slate-950 transition hover:bg-amber-300">
                Daftar Tenant
            </a>
        </nav>
    </div>
</header>

<main id="main">

    <!-- ============================= HERO ============================= -->
    <section id="home" class="relative isolate overflow-hidden bg-slate-950">
        <div class="hero-grid absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="absolute -right-32 -top-40 -z-10 h-[28rem] w-[28rem] rounded-full bg-amber-500/20 blur-[120px]" aria-hidden="true"></div>
        <div class="absolute -bottom-52 -left-32 -z-10 h-[26rem] w-[26rem] rounded-full bg-orange-600/10 blur-[120px]" aria-hidden="true"></div>

        <div class="mx-auto grid max-w-7xl items-center gap-14 px-4 pb-20 pt-16 sm:px-6 lg:grid-cols-[1.08fr_0.92fr] lg:gap-16 lg:px-8 lg:pb-28 lg:pt-24">

            <div data-reveal>
                <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3.5 py-1.5 text-xs font-semibold text-slate-200 backdrop-blur">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-60"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-amber-400"></span>
                    </span>
                    Pendaftaran tenant 2026 dibuka
                </span>

                <h1 class="mt-6 text-balance text-4xl font-extrabold leading-[1.05] tracking-tight text-white sm:text-5xl lg:text-6xl">
                    Tempat ide kreatif Cirebon
                    <span class="block font-serif text-[1.08em] font-normal italic text-amber-300">bertemu pasar.</span>
                </h1>

                <p class="mt-6 max-w-xl text-base leading-relaxed text-slate-300 sm:text-lg">
                    Dua hari penuh untuk memamerkan produk UMKM, belajar dari praktisi, dan terhubung dengan buyer serta mitra usaha. Ini panggungmu, para kreator Cirebon.
                </p>

                <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                    <a href="register.php" class="group inline-flex items-center justify-center gap-2 rounded-xl bg-amber-400 px-6 py-3.5 text-sm font-bold text-slate-950 shadow-xl shadow-amber-500/25 transition hover:-translate-y-0.5 hover:bg-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">
                        Daftar Tenant Sekarang
                        <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12l-7.5 7.5M21 12H3"/>
                        </svg>
                    </a>
                    <a href="#tentang" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/5 px-6 py-3.5 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">
                        Jelajahi Acara
                    </a>
                </div>

                <dl class="mt-10 grid max-w-md grid-cols-3 gap-6 border-t border-white/10 pt-6">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">Tenant</dt>
                        <dd class="mt-1 text-2xl font-bold text-white">120+</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">Kategori</dt>
                        <dd class="mt-1 text-2xl font-bold text-white">3</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">Hari</dt>
                        <dd class="mt-1 text-2xl font-bold text-white">2</dd>
                    </div>
                </dl>
            </div>

            <!-- Kartu informasi acara -->
            <div class="relative mx-auto w-full max-w-md lg:max-w-none" data-reveal style="--reveal-delay: 120ms">
                <div class="absolute -inset-6 rounded-[2.5rem] bg-gradient-to-tr from-amber-500/20 via-orange-500/10 to-transparent blur-2xl" aria-hidden="true"></div>

                <div aria-hidden="true" class="absolute -left-6 -top-5 z-10 hidden rotate-[-6deg] items-center gap-2 rounded-xl border border-white/10 bg-slate-900/90 px-3.5 py-2 text-xs font-semibold text-white shadow-xl backdrop-blur lg:flex">
                    <span class="h-2 w-2 rounded-full bg-orange-400"></span>
                    Kuliner Nusantara
                </div>
                <div aria-hidden="true" class="absolute -bottom-5 -right-5 z-10 hidden rotate-[5deg] items-center gap-2 rounded-xl border border-white/10 bg-slate-900/90 px-3.5 py-2 text-xs font-semibold text-white shadow-xl backdrop-blur lg:flex">
                    <span class="h-2 w-2 rounded-full bg-amber-300"></span>
                    Fashion &amp; Kriya
                </div>

                <div class="relative rounded-[28px] border border-white/10 bg-white/[0.04] p-6 shadow-2xl shadow-black/40 backdrop-blur-xl sm:p-7">
                    <div class="flex items-center justify-between gap-4">
                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-400/10 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-emerald-300 ring-1 ring-inset ring-emerald-400/25">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                            Pendaftaran dibuka
                        </span>
                        <span class="font-mono text-[11px] uppercase tracking-[0.25em] text-slate-400">CCF / 26</span>
                    </div>

                    <p class="mt-7 font-serif text-3xl italic leading-snug text-white sm:text-[2rem]">
                        Festival UMKM &amp;<br>Ekonomi Kreatif
                    </p>

                    <div class="mt-6 grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-white/10 bg-slate-950/50 p-4">
                            <svg class="h-5 w-5 text-amber-300" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                            </svg>
                            <p class="mt-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Tanggal</p>
                            <p class="mt-1 text-sm font-semibold text-white">12–13 Des 2026</p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-slate-950/50 p-4">
                            <svg class="h-5 w-5 text-amber-300" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                            </svg>
                            <p class="mt-3 text-[10px] font-bold uppercase tracking-widest text-slate-400">Lokasi</p>
                            <p class="mt-1 text-sm font-semibold text-white">Kota Cirebon</p>
                        </div>
                    </div>

                    <ul class="mt-6 space-y-3 border-t border-white/10 pt-6 text-sm text-slate-300">
                        <li class="flex items-center gap-3">
                            <svg class="h-4 w-4 shrink-0 text-amber-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                            </svg>
                            120+ tenant UMKM terkurasi
                        </li>
                        <li class="flex items-center gap-3">
                            <svg class="h-4 w-4 shrink-0 text-amber-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                            </svg>
                            Business matching &amp; workshop
                        </li>
                        <li class="flex items-center gap-3">
                            <svg class="h-4 w-4 shrink-0 text-amber-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                            </svg>
                            Promosi lintas kanal digital
                        </li>
                    </ul>

                    <a href="register.php" class="mt-7 flex items-center justify-between rounded-2xl bg-white px-5 py-4 text-sm font-bold text-slate-950 transition hover:bg-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">
                        Amankan slot tenant-mu
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12l-7.5 7.5M21 12H3"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================= TENTANG ACARA ============================= -->
    <section id="tentang" class="bg-stone-50 py-20 sm:py-24 lg:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            <div class="grid gap-10 lg:grid-cols-12 lg:items-end">
                <div class="lg:col-span-7" data-reveal>
                    <p class="inline-flex items-center gap-3 text-xs font-bold uppercase tracking-[0.25em] text-amber-800">
                        <span class="h-px w-8 bg-amber-500"></span>
                        Tentang Acara
                    </p>
                    <h2 class="mt-4 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl lg:text-[2.75rem] lg:leading-[1.1]">
                        Bukan sekadar bazar — ini panggung
                        <span class="font-serif font-normal italic text-amber-700">ekonomi kreatif</span>
                        Cirebon.
                    </h2>
                    <p class="mt-5 max-w-2xl text-base leading-relaxed text-slate-600 sm:text-lg">
                        Cirebon Creative Festival 2026 mempertemukan pelaku UMKM, perajin, dan kreator lokal dengan pembeli, kurator, serta mitra usaha. Dua hari penuh untuk memamerkan produk, belajar, dan membangun jaringan yang bertahan lama.
                    </p>
                </div>

                <div class="lg:col-span-5" data-reveal style="--reveal-delay: 120ms">
                    <figure class="rounded-2xl border border-amber-200 bg-amber-50/70 p-6">
                        <blockquote class="font-serif text-xl italic leading-snug text-amber-950 sm:text-2xl">
                            “Produk yang baik butuh panggung yang tepat.”
                        </blockquote>
                        <figcaption class="mt-3 text-sm leading-relaxed text-amber-900">
                            Tiga pilar festival dirancang agar setiap tenant mendapat ruang tampil, ilmu, dan akses pasar yang nyata.
                        </figcaption>
                    </figure>
                </div>
            </div>

            <!-- Tiga pilar / kategori -->
            <div id="kategori" class="mt-14 grid gap-6 md:grid-cols-3">
                <?php foreach ($pillars as $index => $pillar): ?>
                    <article class="group relative flex flex-col rounded-3xl border border-slate-200 bg-white p-7 shadow-sm transition duration-300 hover:-translate-y-1.5 hover:border-amber-300 hover:shadow-xl hover:shadow-amber-500/10"
                             data-reveal style="--reveal-delay: <?= $index * 100 ?>ms">
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br from-amber-100 to-orange-100 text-amber-700 ring-1 ring-amber-200">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                <?= $pillar['icon'] ?>
                            </svg>
                        </span>

                        <span class="mt-6 text-xs font-bold uppercase tracking-[0.2em] text-amber-700">
                            Pilar <?= htmlspecialchars($pillar['number'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <h3 class="mt-2 text-xl font-bold tracking-tight text-slate-900">
                            <?= htmlspecialchars($pillar['title'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">
                            <?= htmlspecialchars($pillar['description'], ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <ul class="mt-5 space-y-2.5 border-t border-slate-100 pt-5 text-sm text-slate-600">
                            <?php foreach ($pillar['points'] as $point): ?>
                                <li class="flex items-start gap-2.5">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                    </svg>
                                    <?= htmlspecialchars($point, ENT_QUOTES, 'UTF-8') ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================= SOROTAN TENANT ============================= -->
    <section id="sorotan" class="relative isolate overflow-hidden border-y border-white/10 bg-slate-950 py-20 sm:py-24 lg:py-28">
        <div class="hero-grid pointer-events-none absolute inset-0 -z-10 opacity-40" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -left-40 top-10 -z-10 h-96 w-96 rounded-full bg-amber-500/15 blur-[120px]" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -right-40 bottom-0 -z-10 h-96 w-96 rounded-full bg-orange-600/10 blur-[120px]" aria-hidden="true"></div>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            <div class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between" data-reveal>
                <div class="max-w-2xl">
                    <p class="inline-flex items-center gap-3 text-xs font-bold uppercase tracking-[0.25em] text-amber-300">
                        <span class="h-px w-8 bg-amber-400"></span>
                        Galeri Sorotan
                    </p>
                    <h2 id="sorotan-title" class="mt-4 text-balance text-3xl font-extrabold tracking-tight text-white sm:text-4xl lg:text-[2.75rem] lg:leading-[1.1]">
                        Produk lokal yang sedang
                        <span class="font-serif font-normal italic text-amber-300">naik panggung.</span>
                    </h2>
                    <p class="mt-4 text-base leading-relaxed text-slate-300">
                        Empat tenant pilihan dari kurasi CCF 2026. Geser untuk melihat cerita, kategori, dan keunikan produk mereka.
                    </p>
                </div>

                <div class="flex items-center gap-2 self-start lg:self-auto" role="group" aria-label="Kontrol galeri sorotan">
                    <button id="slider-prev" type="button" aria-controls="tenant-slider-viewport" aria-label="Sorotan sebelumnya"
                            class="grid h-11 w-11 place-items-center rounded-full border border-white/15 bg-white/5 text-white transition duration-200 hover:-translate-y-0.5 hover:border-amber-300/50 hover:bg-amber-400 hover:text-slate-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12l7.5-7.5M3 12h18"/>
                        </svg>
                    </button>
                    <button id="slider-play" type="button" aria-controls="tenant-slider-viewport" aria-pressed="false" aria-label="Jeda slideshow otomatis"
                            class="grid h-11 w-11 place-items-center rounded-full border border-amber-300/30 bg-amber-400/10 text-amber-200 transition duration-200 hover:-translate-y-0.5 hover:bg-amber-400 hover:text-slate-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">
                        <svg id="icon-pause" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5v14M15 5v14"/>
                        </svg>
                        <svg id="icon-play" class="hidden h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 5.25v13.5L19.5 12 7.5 5.25z"/>
                        </svg>
                    </button>
                    <button id="slider-next" type="button" aria-controls="tenant-slider-viewport" aria-label="Sorotan berikutnya"
                            class="grid h-11 w-11 place-items-center rounded-full border border-white/15 bg-white/5 text-white transition duration-200 hover:-translate-y-0.5 hover:border-amber-300/50 hover:bg-amber-400 hover:text-slate-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12l-7.5 7.5M21 12H3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div id="tenant-slider" class="mt-10" data-reveal style="--reveal-delay: 120ms">

                <div id="tenant-slider-viewport"
                     class="slider-viewport overflow-hidden rounded-[32px] focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-4 focus-visible:ring-offset-slate-950"
                     role="region" aria-roledescription="carousel" aria-labelledby="sorotan-title" tabindex="0">
                    <div id="tenant-track" class="flex items-stretch">
                        <?php foreach ($highlights as $index => $slide): ?>
                            <?php
                            $style = $accentStyles[$slide['accent']] ?? $accentStyles['amber'];
                            $slideNumber = sprintf('%02d', $index + 1);
                            $isFirst = $index === 0;
                            ?>
                            <article class="slider-slide w-full shrink-0<?= $isFirst ? ' is-active' : '' ?>"
                                     role="group" aria-roledescription="slide"
                                     aria-label="Sorotan <?= $index + 1 ?> dari <?= count($highlights) ?>"
                                     aria-hidden="<?= $isFirst ? 'false' : 'true' ?>">
                                <div class="grid h-full overflow-hidden rounded-[32px] border border-white/10 bg-slate-900/70 transition duration-500 hover:border-amber-300/30 hover:shadow-2xl hover:shadow-amber-500/10 lg:grid-cols-[1.12fr_0.88fr]">

                                    <div class="slide-media relative min-h-[300px] overflow-hidden sm:min-h-[380px] lg:min-h-[540px]">
                                        <img src="<?= $e($slide['image']) ?>"
                                             alt="<?= $e($slide['alt']) ?>"
                                             width="736" height="736"
                                             loading="<?= $isFirst ? 'eager' : 'lazy' ?>"
                                             decoding="async" draggable="false"
                                             class="absolute inset-0 h-full w-full object-cover">
                                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/25 to-transparent lg:bg-gradient-to-r lg:from-slate-950/25 lg:via-slate-950/5 lg:to-slate-900/95" aria-hidden="true"></div>

                                        <div class="absolute inset-x-5 top-5 flex flex-wrap items-center gap-2 sm:inset-x-6 sm:top-6">
                                            <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-slate-950/70 px-3.5 py-1.5 text-[11px] font-bold uppercase tracking-wider ring-1 ring-inset backdrop-blur <?= $e($style['badge']) ?>">
                                                <span class="h-1.5 w-1.5 rounded-full <?= $e($style['dot']) ?>"></span>
                                                <?= $e($slide['category']) ?>
                                            </span>
                                            <span class="rounded-full border border-white/15 bg-slate-950/60 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-white/90 backdrop-blur">
                                                <?= $e($slide['highlight']) ?>
                                            </span>
                                        </div>

                                        <div class="absolute inset-x-5 bottom-5 flex items-end justify-between gap-4 sm:inset-x-6 sm:bottom-6">
                                            <p class="inline-flex items-center gap-2 text-[11px] font-semibold text-white/85">
                                                <svg class="h-4 w-4 text-amber-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                                                </svg>
                                                <?= $e($slide['location']) ?>
                                            </p>
                                            <span class="rounded-full bg-slate-950/55 px-2.5 py-1 font-mono text-[11px] uppercase tracking-[0.25em] text-white/80 backdrop-blur">
                                                <?= $slideNumber ?> / <?= sprintf('%02d', count($highlights)) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="slide-copy relative flex h-full flex-col justify-between gap-8 p-6 sm:p-8 lg:p-10">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-emerald-300">
                                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                                    Tenant terkurasi
                                                </span>
                                                <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[10px] font-semibold text-slate-300">
                                                    <svg class="h-3 w-3 text-amber-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L1.5 7.7l5.9-.9L10 1.5z"/>
                                                    </svg>
                                                    <?= $e($slide['highlight']) ?>
                                                </span>
                                            </div>

                                            <p class="mt-6 text-[11px] font-bold uppercase tracking-[0.22em] text-amber-300/90">
                                                <?= $e($slide['category']) ?>
                                            </p>
                                            <h3 class="mt-2 text-2xl font-extrabold tracking-tight text-white sm:text-3xl">
                                                <?= $e($slide['tenant']) ?>
                                            </h3>
                                            <p class="mt-2 font-serif text-xl italic leading-snug text-amber-200 sm:text-2xl">
                                                <?= $e($slide['tagline']) ?>
                                            </p>
                                            <p class="mt-4 text-sm leading-relaxed text-slate-300">
                                                <?= $e($slide['description']) ?>
                                            </p>

                                            <ul class="mt-5 flex flex-wrap gap-2">
                                                <?php foreach ($slide['tags'] as $tag): ?>
                                                    <li class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[11px] font-semibold text-slate-300">
                                                        <?= $e($tag) ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>

                                        <div class="border-t border-white/10 pt-6">
                                            <a href="register.php" class="group inline-flex w-full items-center justify-between gap-3 rounded-xl bg-amber-400 px-5 py-3.5 text-sm font-bold text-slate-950 shadow-lg shadow-amber-500/20 transition hover:-translate-y-0.5 hover:bg-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">
                                                Daftar jadi tenant
                                                <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12l-7.5 7.5M21 12H3"/>
                                                </svg>
                                            </a>
                                            <a href="#kategori" class="mt-3 inline-flex items-center gap-2 text-xs font-semibold text-slate-400 transition hover:text-amber-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                                                Lihat kategori festival
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12l-7.5 7.5M21 12H3"/>
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mt-5 flex flex-col gap-4">
                    <div class="flex items-center justify-between gap-4">
                        <div id="slider-dots" class="flex items-center gap-2 md:hidden" role="group" aria-label="Pilih sorotan">
                            <?php foreach ($highlights as $index => $slide): ?>
                                <button type="button" data-slide-to="<?= $index ?>"
                                        class="slider-dot relative h-2.5 w-2.5 rounded-full bg-white/20 transition-all duration-300 after:absolute after:-inset-2 after:content-[''] hover:bg-white/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300<?= $index === 0 ? ' is-active' : '' ?>"
                                        aria-label="Tampilkan sorotan <?= $index + 1 ?>: <?= $e($slide['tenant']) ?>"
                                        <?= $index === 0 ? 'aria-current="true"' : '' ?>></button>
                            <?php endforeach; ?>
                        </div>

                        <p class="hidden items-center gap-3 text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 md:flex" aria-hidden="true">
                            Geser
                            <span class="h-px w-8 bg-white/15"></span>
                            atau gunakan panah
                        </p>

                        <p class="ml-auto text-xs font-semibold text-slate-400">
                            <span id="slider-counter" class="font-mono text-white">01</span>
                            / <?= sprintf('%02d', count($highlights)) ?>
                        </p>
                    </div>

                    <div class="h-1 w-full overflow-hidden rounded-full bg-white/10" aria-hidden="true">
                        <div id="slider-progress" class="h-full w-full rounded-full"></div>
                    </div>
                </div>

                <div id="slider-thumbs" class="mt-6 hidden gap-3 md:grid md:grid-cols-2 lg:grid-cols-4" role="group" aria-label="Pilih sorotan tenant">
                    <?php foreach ($highlights as $index => $slide): ?>
                        <button type="button" data-slide-to="<?= $index ?>"
                                class="slider-thumb group flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.03] p-2.5 text-left transition hover:bg-white/[0.06] focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300<?= $index === 0 ? ' is-active' : '' ?>"
                                aria-label="Tampilkan sorotan <?= $index + 1 ?>: <?= $e($slide['tenant']) ?>, <?= $e($slide['category']) ?>"
                                <?= $index === 0 ? 'aria-current="true"' : '' ?>>
                            <span class="relative h-12 w-12 shrink-0 overflow-hidden rounded-xl">
                                <img src="<?= $e($slide['image']) ?>" alt="" width="96" height="96" loading="lazy" decoding="async" draggable="false"
                                     class="h-full w-full object-cover transition duration-500 group-hover:scale-110">
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-xs font-bold text-white"><?= $e($slide['tenant']) ?></span>
                                <span class="mt-0.5 block truncate text-[10px] font-semibold uppercase tracking-wider text-slate-500"><?= $e($slide['category']) ?></span>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <p id="slider-status" class="sr-only" aria-live="polite" aria-atomic="true"></p>
            </div>
        </div>
    </section>

    <!-- ============================= JADWAL ============================= -->
    <section id="jadwal" class="border-y border-slate-200 bg-white py-20 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between" data-reveal>
                <div class="max-w-2xl">
                    <p class="inline-flex items-center gap-3 text-xs font-bold uppercase tracking-[0.25em] text-amber-800">
                        <span class="h-px w-8 bg-amber-500"></span>
                        Jadwal
                    </p>
                    <h2 class="mt-4 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                        Rangkaian menuju hari festival
                    </h2>
                    <p class="mt-4 text-base leading-relaxed text-slate-600">
                        Catat tanggalnya. Pastikan pendaftaranmu masuk sebelum kurasi dimulai.
                    </p>
                </div>
                <a href="register.php" class="inline-flex shrink-0 items-center gap-2 self-start rounded-xl border border-slate-300 px-5 py-3 text-sm font-bold text-slate-900 transition hover:border-amber-400 hover:bg-amber-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 md:self-auto">
                    Daftar sebelum 15 Nov
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12l-7.5 7.5M21 12H3"/>
                    </svg>
                </a>
            </div>

            <ol class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <?php foreach ($schedule as $index => $item): ?>
                    <li class="relative flex flex-col rounded-2xl border border-slate-200 bg-stone-50 p-6 transition duration-300 hover:border-amber-300 hover:bg-white hover:shadow-lg hover:shadow-amber-500/10"
                        data-reveal style="--reveal-delay: <?= $index * 80 ?>ms">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs font-extrabold uppercase tracking-[0.2em] text-amber-700">
                                <?= htmlspecialchars($item['step'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                            <?php if ($item['badge'] !== null): ?>
                                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                                    <?= htmlspecialchars($item['badge'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <h3 class="mt-4 text-base font-bold text-slate-900">
                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>
                        <p class="mt-1 text-sm font-semibold text-amber-700">
                            <?= htmlspecialchars($item['date'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">
                            <?= htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

    <!-- ============================= CTA ============================= -->
    <section class="relative isolate overflow-hidden bg-slate-950 py-16 sm:py-20">
        <div class="absolute left-1/2 top-0 -z-10 h-72 w-[36rem] -translate-x-1/2 rounded-full bg-amber-500/20 blur-[110px]" aria-hidden="true"></div>
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6" data-reveal>
            <h2 class="text-balance text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                Siap membawa produkmu ke
                <span class="font-serif font-normal italic text-amber-300">panggung Cirebon?</span>
            </h2>
            <p class="mx-auto mt-4 max-w-2xl text-base leading-relaxed text-slate-300">
                Kuota tenant terbatas dan diseleksi secara kurasi. Daftarkan UMKM-mu hari ini, lengkap dengan foto dan deskripsi produk terbaik.
            </p>
            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <a href="register.php" class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-400 px-6 py-3.5 text-sm font-bold text-slate-950 shadow-xl shadow-amber-500/25 transition hover:-translate-y-0.5 hover:bg-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">
                    Daftar Tenant
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12l-7.5 7.5M21 12H3"/>
                    </svg>
                </a>
                <a href="login.php" class="inline-flex items-center justify-center rounded-xl border border-white/20 bg-white/5 px-6 py-3.5 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">
                    Sudah punya akun? Masuk
                </a>
            </div>
        </div>
    </section>
</main>

<!-- ============================= FOOTER ============================= -->
<footer class="border-t border-white/10 bg-slate-950 text-slate-400">
    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_1.2fr]">

            <div>
                <a href="#home" class="inline-flex items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-amber-300 via-amber-400 to-orange-500 text-sm font-extrabold tracking-tight text-slate-950">
                        CCF
                    </span>
                    <span class="flex flex-col leading-none">
                        <span class="text-sm font-bold tracking-tight text-white">Cirebon Creative</span>
                        <span class="mt-1 text-[10px] font-semibold uppercase tracking-[0.28em] text-amber-300/80">Festival 2026</span>
                    </span>
                </a>
                <p class="mt-5 max-w-sm text-sm leading-relaxed text-slate-400">
                    Festival UMKM dan ekonomi kreatif yang merayakan karya, rasa, dan cerita dari Cirebon.
                </p>
                <div class="mt-6 flex items-center gap-3">
                    <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" aria-label="Instagram Cirebon Creative Festival"
                       class="grid h-10 w-10 place-items-center rounded-xl border border-white/10 bg-white/5 text-slate-300 transition hover:border-amber-400/40 hover:bg-white/10 hover:text-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                            <rect x="3" y="3" width="18" height="18" rx="5"/>
                            <circle cx="12" cy="12" r="4"/>
                            <circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/>
                        </svg>
                    </a>
                    <a href="https://tiktok.com" target="_blank" rel="noopener noreferrer" aria-label="TikTok Cirebon Creative Festival"
                       class="grid h-10 w-10 place-items-center rounded-xl border border-white/10 bg-white/5 text-slate-300 transition hover:border-amber-400/40 hover:bg-white/10 hover:text-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M16.5 3c.4 2.2 1.8 3.7 4 4v3c-1.5 0-2.9-.5-4-1.3v6.6a6.3 6.3 0 11-6.3-6.3c.3 0 .6 0 .9.1v3.1a3.2 3.2 0 102.3 3.1V3h3.1z"/>
                        </svg>
                    </a>
                    <a href="mailto:hello@ccf2026.id" aria-label="Email Cirebon Creative Festival"
                       class="grid h-10 w-10 place-items-center rounded-xl border border-white/10 bg-white/5 text-slate-300 transition hover:border-amber-400/40 hover:bg-white/10 hover:text-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                        </svg>
                    </a>
                </div>
            </div>

            <div>
                <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-white">Navigasi</h2>
                <ul class="mt-4 space-y-3 text-sm">
                    <?php foreach ($navLinks as $link): ?>
                        <li>
                            <a href="<?= htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8') ?>" class="transition hover:text-amber-300">
                                <?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li><a href="register.php" class="transition hover:text-amber-300">Daftar Tenant</a></li>
                </ul>
            </div>

            <div>
                <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-white">Kategori</h2>
                <ul class="mt-4 space-y-3 text-sm">
                    <?php foreach ($pillars as $pillar): ?>
                        <li>
                            <a href="#kategori" class="transition hover:text-amber-300">
                                <?= htmlspecialchars($pillar['title'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div>
                <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-white">Kontak</h2>
                <ul class="mt-4 space-y-3 text-sm">
                    <li>
                        <a href="mailto:hello@ccf2026.id" class="transition hover:text-amber-300">hello@ccf2026.id</a>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-300/80" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                        </svg>
                        <span>Kota Cirebon,<br>Jawa Barat, Indonesia</span>
                    </li>
                    <li class="text-slate-400">Senin–Jumat, 09.00–17.00 WIB</li>
                </ul>
            </div>
        </div>

        <div class="mt-12 flex flex-col gap-3 border-t border-white/10 pt-6 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-slate-400">
                &copy; <?= EVENT_YEAR ?> <?= EVENT_NAME ?>. Seluruh hak cipta dilindungi.
            </p>
            <p class="text-xs text-slate-400">
                Dipersembahkan untuk UMKM &amp; kreator Cirebon.
            </p>
        </div>
    </div>
</footer>

<script>
    (function () {
        'use strict';

        /* ---- Bayangan navbar saat halaman di-scroll ---- */
        var header = document.getElementById('site-header');
        function updateHeaderShadow() {
            if (!header) return;
            header.classList.toggle('shadow-2xl', window.scrollY > 8);
            header.classList.toggle('shadow-black/30', window.scrollY > 8);
        }
        updateHeaderShadow();
        window.addEventListener('scroll', updateHeaderShadow, { passive: true });

        /* ---- Menu seluler ---- */
        var toggle = document.getElementById('menu-toggle');
        var menu = document.getElementById('mobile-menu');
        var iconOpen = document.getElementById('icon-open');
        var iconClose = document.getElementById('icon-close');

        function setMenu(open) {
            if (!toggle || !menu) return;
            menu.classList.toggle('hidden', !open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Tutup menu navigasi' : 'Buka menu navigasi');
            if (iconOpen) iconOpen.classList.toggle('hidden', open);
            if (iconClose) iconClose.classList.toggle('hidden', !open);
        }

        if (toggle && menu) {
            toggle.addEventListener('click', function () {
                setMenu(menu.classList.contains('hidden'));
            });

            menu.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', function () { setMenu(false); });
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') setMenu(false);
            });

            window.addEventListener('resize', function () {
                if (window.innerWidth >= 1024) setMenu(false);
            });
        }

        /* ---- Animasi reveal saat elemen masuk viewport ---- */
        var revealItems = document.querySelectorAll('[data-reveal]');
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (revealItems.length && !reduceMotion && 'IntersectionObserver' in window) {
            revealItems.forEach(function (el) { el.classList.add('reveal-init'); });

            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('reveal-in');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

            revealItems.forEach(function (el) { observer.observe(el); });
        }

        /* ---- Progres baca + penanda navigasi aktif ---- */
        var scrollProgress = document.getElementById('scroll-progress');
        var navAnchors = Array.prototype.slice.call(document.querySelectorAll('header nav a[href^="#"]'));
        var spyTargets = {
            tentang: document.getElementById('tentang'),
            kategori: document.getElementById('kategori'),
            sorotan: document.getElementById('sorotan'),
            jadwal: document.getElementById('jadwal')
        };
        var scrollTicking = false;

        function setActiveNav(id) {
            navAnchors.forEach(function (anchor) {
                anchor.classList.toggle('nav-active', id !== null && anchor.getAttribute('href') === '#' + id);
            });
        }

        function updateScrollUi() {
            var doc = document.documentElement;
            var max = doc.scrollHeight - window.innerHeight;
            var ratio = max > 0 ? Math.min(1, Math.max(0, window.scrollY / max)) : 0;
            if (scrollProgress) scrollProgress.style.transform = 'scaleX(' + ratio + ')';

            var line = window.innerHeight * 0.35;
            var active = null;
            var order = ['kategori', 'tentang', 'sorotan', 'jadwal'];

            for (var i = 0; i < order.length; i++) {
                var el = spyTargets[order[i]];
                if (!el) continue;
                var rect = el.getBoundingClientRect();
                if (rect.top <= line && rect.bottom > line) {
                    active = order[i];
                    break;
                }
            }

            setActiveNav(active);
            scrollTicking = false;
        }

        window.addEventListener('scroll', function () {
            if (!scrollTicking) {
                scrollTicking = true;
                window.requestAnimationFrame(updateScrollUi);
            }
        }, { passive: true });
        window.addEventListener('resize', updateScrollUi, { passive: true });
        updateScrollUi();

        /* ---- Carousel sorotan tenant ---- */
        (function initTenantSlider() {
            var root = document.getElementById('tenant-slider');
            if (!root) return;

            var viewport = document.getElementById('tenant-slider-viewport');
            var track = document.getElementById('tenant-track');
            var slides = Array.prototype.slice.call(track.querySelectorAll('.slider-slide'));
            if (!slides.length) return;

            var prevBtn = document.getElementById('slider-prev');
            var nextBtn = document.getElementById('slider-next');
            var playBtn = document.getElementById('slider-play');
            var iconPause = document.getElementById('icon-pause');
            var iconPlay = document.getElementById('icon-play');
            var counter = document.getElementById('slider-counter');
            var status = document.getElementById('slider-status');
            var bar = document.getElementById('slider-progress');
            var dots = Array.prototype.slice.call(document.querySelectorAll('#slider-dots [data-slide-to]'));
            var thumbs = Array.prototype.slice.call(document.querySelectorAll('#slider-thumbs [data-slide-to]'));
            var total = slides.length;
            var current = 0;
            var duration = 6500;
            var timer = null;
            var userPaused = reduceMotion;
            var hoverPaused = false;
            var inView = false;
            var canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

            root.classList.add('slider-ready');

            function pad(index) {
                return String(index + 1).padStart(2, '0');
            }

            function clearTimer() {
                if (timer) {
                    window.clearTimeout(timer);
                    timer = null;
                }
            }

            function restartProgress() {
                if (!bar) return;
                bar.classList.remove('is-running');
                bar.classList.remove('is-paused');
                void bar.offsetWidth;

                if (reduceMotion) return;

                bar.style.setProperty('--slide-duration', duration + 'ms');
                bar.classList.add('is-running');
                if (userPaused || hoverPaused) bar.classList.add('is-paused');
            }

            function schedule() {
                clearTimer();
                if (reduceMotion || userPaused || hoverPaused || document.hidden || !inView) return;

                timer = window.setTimeout(function () {
                    setSlide(current + 1, true);
                    schedule();
                }, duration);
            }

            function setSlide(index, announce) {
                current = (index + total) % total;
                track.style.transform = 'translate3d(-' + (current * 100) + '%, 0, 0)';

                slides.forEach(function (slide, i) {
                    var active = i === current;
                    slide.classList.toggle('is-active', active);
                    slide.setAttribute('aria-hidden', active ? 'false' : 'true');
                    if ('inert' in slide) slide.inert = !active;

                    slide.querySelectorAll('a, button').forEach(function (el) {
                        if (active) el.removeAttribute('tabindex');
                        else el.setAttribute('tabindex', '-1');
                    });
                });

                dots.concat(thumbs).forEach(function (btn) {
                    var active = Number(btn.getAttribute('data-slide-to')) === current;
                    btn.classList.toggle('is-active', active);
                    if (active) btn.setAttribute('aria-current', 'true');
                    else btn.removeAttribute('aria-current');
                });

                if (counter) counter.textContent = pad(current);

                if (announce && status) {
                    var title = slides[current].querySelector('h3');
                    status.textContent = 'Sorotan ' + (current + 1) + ' dari ' + total + ': ' +
                        (title ? title.textContent.trim() : '');
                }

                restartProgress();
            }

            function updatePlayButton() {
                if (!playBtn) return;

                if (reduceMotion) {
                    playBtn.setAttribute('aria-pressed', 'true');
                    playBtn.setAttribute('aria-label', 'Slideshow otomatis dinonaktifkan');
                    playBtn.setAttribute('title', 'Slideshow otomatis dinonaktifkan');
                    playBtn.disabled = true;
                    playBtn.classList.add('opacity-40', 'cursor-not-allowed');
                    if (iconPause) iconPause.classList.add('hidden');
                    if (iconPlay) iconPlay.classList.remove('hidden');
                    return;
                }

                playBtn.setAttribute('aria-pressed', userPaused ? 'true' : 'false');
                playBtn.setAttribute('aria-label', userPaused ? 'Lanjutkan slideshow otomatis' : 'Jeda slideshow otomatis');
                playBtn.setAttribute('title', userPaused ? 'Lanjutkan slideshow' : 'Jeda slideshow');
                if (iconPause) iconPause.classList.toggle('hidden', userPaused);
                if (iconPlay) iconPlay.classList.toggle('hidden', !userPaused);
            }

            function setUserPaused(paused) {
                if (reduceMotion) return;
                userPaused = paused;
                updatePlayButton();

                if (paused) {
                    clearTimer();
                    if (bar) bar.classList.add('is-paused');
                } else {
                    hoverPaused = false;
                    restartProgress();
                    schedule();
                }
            }

            function goTo(index, announce) {
                setSlide(index, announce !== false);
                schedule();
            }

            if (prevBtn) prevBtn.addEventListener('click', function () { goTo(current - 1); });
            if (nextBtn) nextBtn.addEventListener('click', function () { goTo(current + 1); });
            if (playBtn) playBtn.addEventListener('click', function () { setUserPaused(!userPaused); });

            dots.concat(thumbs).forEach(function (btn) {
                btn.addEventListener('click', function () {
                    goTo(Number(btn.getAttribute('data-slide-to')));
                });
            });

            if (canHover) {
                root.addEventListener('mouseenter', function () {
                    hoverPaused = true;
                    clearTimer();
                    if (bar) bar.classList.add('is-paused');
                });

                root.addEventListener('mouseleave', function () {
                    hoverPaused = false;
                    if (!userPaused) {
                        restartProgress();
                        schedule();
                    }
                });
            }

            viewport.addEventListener('keydown', function (event) {
                if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    goTo(current - 1);
                } else if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    goTo(current + 1);
                } else if (event.key === 'Home') {
                    event.preventDefault();
                    goTo(0);
                } else if (event.key === 'End') {
                    event.preventDefault();
                    goTo(total - 1);
                }
            });

            var touchStartX = null;
            var touchStartY = null;

            viewport.addEventListener('touchstart', function (event) {
                if (event.touches.length !== 1) return;
                touchStartX = event.touches[0].clientX;
                touchStartY = event.touches[0].clientY;
            }, { passive: true });

            viewport.addEventListener('touchend', function (event) {
                if (touchStartX === null) return;

                var dx = event.changedTouches[0].clientX - touchStartX;
                var dy = event.changedTouches[0].clientY - touchStartY;
                touchStartX = null;
                touchStartY = null;

                if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy)) {
                    goTo(dx < 0 ? current + 1 : current - 1);
                }
            }, { passive: true });

            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    clearTimer();
                    if (bar) bar.classList.add('is-paused');
                } else if (!userPaused && !hoverPaused) {
                    restartProgress();
                    schedule();
                }
            });

            if ('IntersectionObserver' in window) {
                var sectionObserver = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        inView = entry.isIntersecting;

                        if (inView) {
                            if (!userPaused && !hoverPaused && !document.hidden) {
                                restartProgress();
                                schedule();
                            }
                        } else {
                            clearTimer();
                            if (bar) bar.classList.add('is-paused');
                        }
                    });
                }, { threshold: 0.2 });

                sectionObserver.observe(root);
            } else {
                inView = true;
            }

            updatePlayButton();
            setSlide(0, false);
            if (inView) schedule();
        })();
    })();
</script>

<?php require __DIR__ . '/partials/chatbot.php'; ?>

</body>
</html>
