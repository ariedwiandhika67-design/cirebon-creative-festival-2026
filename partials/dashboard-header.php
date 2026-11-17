<?php
/**
 * partials/dashboard-header.php
 * Layout atas untuk dashboard admin & UMKM.
 *
 * Variabel yang disiapkan halaman:
 *   $pageTitle (string)
 *   $user      (array dari require_role())
 *   $navItems  (array: label, href, active)
 */

declare(strict_types=1);

if (!defined('CCF_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

$pageTitle = $pageTitle ?? 'Dashboard';
$user      = $user ?? current_user();
$navItems  = $navItems ?? [];

if ($user === null) {
    redirect(url('login.php'), 302);
}

$roleLabel = $user['role'] === 'admin' ? 'Administrator' : 'Tenant UMKM';
$initial   = mb_strtoupper(mb_substr((string) $user['nama_lengkap'], 0, 1));
$homeUrl   = dashboard_url((string) $user['role']);
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — Cirebon Creative Festival 2026</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Dashboard tenant dan admin Cirebon Creative Festival 2026.">
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
        html { scroll-padding-top: 6rem; }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
        }
    </style>
</head>
<body class="flex min-h-screen flex-col bg-stone-50 font-sans text-slate-900 antialiased selection:bg-amber-300 selection:text-slate-950">

<header class="sticky top-0 z-40 border-b border-white/10 bg-slate-950/95 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:h-20 lg:px-8">

        <div class="flex min-w-0 items-center gap-6">
            <a href="<?= e($homeUrl) ?>" class="inline-flex shrink-0 items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-amber-300 via-amber-400 to-orange-500 text-sm font-extrabold tracking-tight text-slate-950 shadow-lg shadow-amber-500/20">
                    CCF
                </span>
                <span class="hidden flex-col leading-none sm:flex">
                    <span class="text-sm font-bold tracking-tight text-white">Cirebon Creative</span>
                    <span class="mt-1 text-[10px] font-semibold uppercase tracking-[0.28em] text-amber-300/80">Festival 2026</span>
                </span>
            </a>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="Navigasi dashboard">
                <?php foreach ($navItems as $item): ?>
                    <a href="<?= e((string) $item['href']) ?>"
                       <?= !empty($item['active']) ? 'aria-current="page"' : '' ?>
                       class="rounded-lg px-3.5 py-2 text-sm font-semibold transition <?= !empty($item['active']) ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' ?> focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                        <?= e((string) $item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
            <div class="hidden text-right sm:block">
                <p class="max-w-[12rem] truncate text-sm font-semibold text-white"><?= e((string) $user['nama_lengkap']) ?></p>
                <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-amber-300/80"><?= e($roleLabel) ?></p>
            </div>

            <span class="grid h-9 w-9 place-items-center rounded-full bg-white/10 text-xs font-bold text-white ring-1 ring-inset ring-white/10" aria-hidden="true">
                <?= e($initial !== '' ? $initial : '?') ?>
            </span>

            <form method="post" action="<?= e(url('logout.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 px-2.5 text-xs font-semibold text-slate-200 transition hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 sm:px-3"
                        title="Keluar dari akun">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
                    </svg>
                    <span class="hidden sm:inline">Keluar</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Navigasi mobile -->
    <nav class="flex items-center gap-1 overflow-x-auto border-t border-white/10 px-4 py-2 lg:hidden" aria-label="Navigasi dashboard seluler">
        <?php foreach ($navItems as $item): ?>
            <a href="<?= e((string) $item['href']) ?>"
               <?= !empty($item['active']) ? 'aria-current="page"' : '' ?>
               class="whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-semibold transition <?= !empty($item['active']) ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' ?>">
                <?= e((string) $item['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</header>
