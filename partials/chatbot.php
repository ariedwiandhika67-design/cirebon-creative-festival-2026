<?php
/**
 * partials/chatbot.php — Widget "Tanya CCF 2026"
 *
 * Widget ini bisa dipakai di halaman publik maupun dashboard.
 * Role dan sapaan awal dibaca dari session PHP, lalu diteruskan ke
 * microservice Node.js dalam bentuk signed token (HMAC-SHA256).
 */

declare(strict_types=1);

if (defined('CCF_CHATBOT_RENDERED')) {
    return;
}
define('CCF_CHATBOT_RENDERED', true);

// Konfigurasi URL Node.js + pembuat signed token.
require_once __DIR__ . '/../chatbot-config.php';

// Pastikan token CSRF tersedia. Semua halaman yang memakai partial ini
// sudah menjalankan session_start() sebelum output HTML.
if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$chatE = static fn (?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$chatUser = $_SESSION['user'] ?? null;
$chatRole = 'guest';
$chatName = '';

if (is_array($chatUser) && in_array(($chatUser['role'] ?? ''), ['admin', 'umkm'], true)) {
    $chatRole = (string) $chatUser['role'];
    $chatName = trim((string) ($chatUser['nama_lengkap'] ?? ''));
} elseif (in_array(($_SESSION['role'] ?? ''), ['admin', 'umkm'], true)) {
    $chatRole = (string) $_SESSION['role'];
}

$chatToken = chatbot_auth_token(is_array($chatUser) ? $chatUser : null);

$chatRoleLabel = match ($chatRole) {
    'admin' => 'Admin Copilot',
    'umkm'  => 'Asisten UMKM',
    default => 'FAQ Publik',
};

$chatWelcome = match ($chatRole) {
    'admin' => 'Halo' . ($chatName !== '' ? ', ' . $chatName : '') . '. Saya Admin Copilot CCF 2026. Saya bisa merangkum pendaftaran, breakdown kategori, atau menyusun draft catatan kurasi.',
    'umkm'  => 'Halo' . ($chatName !== '' ? ', ' . $chatName : '') . '. Saya Tanya CCF 2026. Tanyakan jadwal, kategori, syarat foto, cara daftar, atau status pendaftaranmu.',
    default => 'Halo! Saya Tanya CCF 2026. Saya bisa membantu info jadwal, kategori tenant, syarat foto, dan cara mendaftar.',
};

$chatSuggestions = match ($chatRole) {
    'admin' => [
        'Rekap total pendaftar',
        'Breakdown kategori',
        'Buat catatan penolakan',
        'Draft catatan penerimaan',
    ],
    'umkm' => [
        'Kapan festival 2026?',
        'Syarat foto produk',
        'Cara daftar tenant',
        'Cek status pendaftaran',
    ],
    default => [
        'Kapan festival 2026?',
        'Kategori tenant apa saja?',
        'Syarat foto produk',
        'Cara daftar tenant',
    ],
};

// Chatbot sekarang dilayani oleh microservice Node.js.
$chatApiUrl = CHATBOT_SERVICE_URL;
?>

<div id="ccf-chatbot"
     data-api-url="<?= $chatE($chatApiUrl) ?>"
     data-auth-token="<?= $chatE($chatToken) ?>"
     data-welcome="<?= $chatE($chatWelcome) ?>"
     data-suggestions="<?= $chatE((string) json_encode($chatSuggestions, JSON_UNESCAPED_UNICODE)) ?>">

    <!-- ============================= PANEL CHAT ============================= -->
    <section id="ccf-chat-panel"
             class="fixed inset-x-4 bottom-20 z-50 hidden sm:inset-x-auto sm:bottom-24 sm:right-6 sm:w-[24rem]"
             role="dialog"
             aria-modal="false"
             aria-labelledby="ccf-chat-title"
             aria-hidden="true">
        <div class="flex h-[min(70vh,34rem)] flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/25">

            <!-- Header -->
            <header class="flex items-center justify-between gap-3 bg-slate-950 px-4 py-3 text-white">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-amber-300 via-amber-400 to-orange-500 text-[10px] font-extrabold tracking-tight text-slate-950 shadow-lg shadow-amber-500/20"
                          aria-hidden="true">AI</span>
                    <div class="min-w-0">
                        <h2 id="ccf-chat-title" class="truncate text-sm font-bold tracking-tight">Tanya CCF 2026</h2>
                        <p class="truncate text-[10px] font-semibold uppercase tracking-[0.18em] text-amber-300">
                            <?= $chatE($chatRoleLabel) ?>
                        </p>
                    </div>
                </div>

                <button id="ccf-chat-close"
                        type="button"
                        class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400"
                        aria-label="Tutup chat">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </header>

            <!-- Riwayat pesan -->
            <div id="ccf-chat-log"
                 class="flex-1 space-y-3 overflow-y-auto bg-stone-50 p-4"
                 role="log"
                 aria-live="polite"
                 aria-relevant="additions"></div>

            <!-- Input -->
            <div class="border-t border-slate-200 bg-white p-3">
                <div id="ccf-chat-suggestions" class="mb-2 flex max-h-20 flex-wrap gap-1.5 overflow-y-auto"></div>

                <form id="ccf-chat-form" class="flex items-end gap-2" autocomplete="off">
                    <label for="ccf-chat-input" class="sr-only">Tulis pertanyaan untuk Tanya CCF 2026</label>
                    <textarea id="ccf-chat-input"
                              name="message"
                              rows="1"
                              maxlength="500"
                              placeholder="Tulis pertanyaan..."
                              class="max-h-24 min-h-[2.75rem] flex-1 resize-none rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-500 focus:border-amber-400 focus:ring-4 focus:ring-amber-100"></textarea>

                    <button id="ccf-chat-send"
                            type="submit"
                            class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-slate-900 text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            aria-label="Kirim pesan">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                        </svg>
                    </button>
                </form>

                <p class="mt-2 text-center text-[10px] leading-relaxed text-slate-500">
                    Jawaban bersifat informatif. Data pribadi hanya ditampilkan sesuai peran akun.
                </p>
            </div>
        </div>
    </section>

    <!-- ============================= TOMBOL FLOATING ============================= -->
    <button id="ccf-chat-toggle"
            type="button"
            class="fixed bottom-4 right-4 z-50 inline-flex items-center gap-2 rounded-full bg-slate-950 px-4 py-3 text-sm font-bold text-white shadow-2xl shadow-slate-950/30 transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 sm:bottom-6 sm:right-6"
            aria-controls="ccf-chat-panel"
            aria-expanded="false"
            aria-label="Buka Tanya CCF 2026">
        <span class="relative grid h-6 w-6 place-items-center" aria-hidden="true">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm3.75 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm3.75 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM21 12c0 4.556-4.03 8.25-9 8.25a9.76 9.76 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
            </svg>
            <span class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-emerald-400 ring-2 ring-slate-950"></span>
        </span>
        <span class="hidden sm:inline">Tanya CCF</span>
    </button>
</div>

<script>
    (function () {
        'use strict';

        const root = document.getElementById('ccf-chatbot');
        if (!root) return;

        const panel = document.getElementById('ccf-chat-panel');
        const toggle = document.getElementById('ccf-chat-toggle');
        const close = document.getElementById('ccf-chat-close');
        const log = document.getElementById('ccf-chat-log');
        const form = document.getElementById('ccf-chat-form');
        const input = document.getElementById('ccf-chat-input');
        const send = document.getElementById('ccf-chat-send');
        const suggestionsEl = document.getElementById('ccf-chat-suggestions');

        if (!panel || !toggle || !close || !log || !form || !input || !send || !suggestionsEl) return;

        const apiUrl = (root.dataset.apiUrl || '').trim();
        const authToken = (root.dataset.authToken || '').trim();
        const welcome = root.dataset.welcome || 'Halo! Ada yang bisa saya bantu?';

        let suggestions = [];
        try {
            suggestions = JSON.parse(root.dataset.suggestions || '[]');
        } catch (error) {
            suggestions = [];
        }

        let initialized = false;
        let isSending = false;

        function scrollToBottom() {
            log.scrollTop = log.scrollHeight;
        }

        function resizeInput() {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 96) + 'px';
        }

        function addMessage(text, sender, options) {
            const opts = options || {};
            const row = document.createElement('div');
            row.className = sender === 'user'
                ? 'flex justify-end'
                : 'flex items-end gap-2';

            if (sender === 'bot') {
                const avatar = document.createElement('span');
                avatar.className = 'mb-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-slate-900 text-[9px] font-extrabold text-amber-300';
                avatar.textContent = 'AI';
                avatar.setAttribute('aria-hidden', 'true');
                row.appendChild(avatar);
            }

            const wrap = document.createElement('div');
            wrap.className = sender === 'user' ? 'max-w-[85%]' : 'max-w-[85%] min-w-0';

            const bubble = document.createElement('div');
            bubble.className = sender === 'user'
                ? 'whitespace-pre-line rounded-2xl rounded-br-md bg-slate-900 px-3.5 py-2.5 text-sm leading-relaxed text-white shadow-sm'
                : 'whitespace-pre-line rounded-2xl rounded-bl-md border border-slate-200 bg-white px-3.5 py-2.5 text-sm leading-relaxed text-slate-700 shadow-sm';

            if (opts.error) {
                bubble.className = 'whitespace-pre-line rounded-2xl rounded-bl-md border border-red-200 bg-red-50 px-3.5 py-2.5 text-sm leading-relaxed text-red-700 shadow-sm';
            }

            bubble.textContent = text;
            wrap.appendChild(bubble);

            if (Array.isArray(opts.links) && opts.links.length > 0) {
                const linksWrap = document.createElement('div');
                linksWrap.className = 'mt-2 flex flex-wrap gap-1.5';

                opts.links.forEach(function (link) {
                    if (!link || typeof link.url !== 'string' || typeof link.label !== 'string') return;

                    const anchor = document.createElement('a');
                    anchor.href = link.url;
                    anchor.textContent = link.label;
                    anchor.className = 'inline-flex items-center rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-[11px] font-bold text-amber-800 transition hover:bg-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400';
                    linksWrap.appendChild(anchor);
                });

                if (linksWrap.childElementCount > 0) {
                    wrap.appendChild(linksWrap);
                }
            }

            if (opts.copyable) {
                const copyButton = document.createElement('button');
                copyButton.type = 'button';
                copyButton.textContent = 'Salin catatan';
                copyButton.className = 'mt-2 inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-bold text-slate-600 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400';

                copyButton.addEventListener('click', async function () {
                    try {
                        if (navigator.clipboard && window.isSecureContext) {
                            await navigator.clipboard.writeText(text);
                        } else {
                            const helper = document.createElement('textarea');
                            helper.value = text;
                            helper.style.position = 'fixed';
                            helper.style.opacity = '0';
                            document.body.appendChild(helper);
                            helper.select();
                            document.execCommand('copy');
                            helper.remove();
                        }
                        copyButton.textContent = 'Tersalin!';
                        window.setTimeout(function () { copyButton.textContent = 'Salin catatan'; }, 1800);
                    } catch (error) {
                        copyButton.textContent = 'Gagal menyalin';
                    }
                });

                wrap.appendChild(copyButton);
            }

            row.appendChild(wrap);
            log.appendChild(row);
            scrollToBottom();
        }

        function addLoading() {
            const row = document.createElement('div');
            row.id = 'ccf-chat-loading';
            row.className = 'flex items-end gap-2';

            const avatar = document.createElement('span');
            avatar.className = 'mb-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-slate-900 text-[9px] font-extrabold text-amber-300';
            avatar.textContent = 'AI';
            avatar.setAttribute('aria-hidden', 'true');

            const bubble = document.createElement('div');
            bubble.className = 'flex items-center gap-1 rounded-2xl rounded-bl-md border border-slate-200 bg-white px-4 py-3 shadow-sm';

            for (let index = 0; index < 3; index += 1) {
                const dot = document.createElement('span');
                dot.className = 'h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400';
                dot.style.animationDelay = (index * 120) + 'ms';
                bubble.appendChild(dot);
            }

            row.appendChild(avatar);
            row.appendChild(bubble);
            log.appendChild(row);
            scrollToBottom();
        }

        function removeLoading() {
            const loading = document.getElementById('ccf-chat-loading');
            if (loading) loading.remove();
        }

        function renderSuggestions(items) {
            suggestionsEl.innerHTML = '';

            if (!Array.isArray(items)) return;

            items.slice(0, 4).forEach(function (text) {
                if (typeof text !== 'string' || text.trim() === '') return;

                const chip = document.createElement('button');
                chip.type = 'button';
                chip.textContent = text;
                chip.className = 'rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-semibold text-slate-600 transition hover:border-amber-300 hover:bg-amber-50 hover:text-amber-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400';
                chip.addEventListener('click', function () {
                    if (isSending) return;
                    input.value = text;
                    resizeInput();
                    form.requestSubmit();
                });

                suggestionsEl.appendChild(chip);
            });
        }

        function setBusy(busy) {
            isSending = busy;
            send.disabled = busy;
            send.classList.toggle('opacity-50', busy);
        }

        async function sendMessage() {
            const message = input.value.trim();

            if (message === '' || isSending) return;

            if (!apiUrl) {
                addMessage('Konfigurasi endpoint chat tidak ditemukan. Muat ulang halaman.', 'bot', { error: true });
                return;
            }

            addMessage(message, 'user');
            input.value = '';
            resizeInput();
            setBusy(true);
            addLoading();

            try {
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                };

                // Admin/UMKM: kirim signed token dari PHP agar Node.js dapat
                // membaca uid, nama, dan role pengguna dari session login.
                // Guest tidak mengirim Authorization karena token memang kosong.
                if (authToken !== '') {
                    headers.Authorization = 'Bearer ' + authToken;
                }

                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify({ message: message })
                });

                let data = null;
                try {
                    data = await response.json();
                } catch (error) {
                    throw new Error('Respons server tidak valid. Silakan coba lagi.');
                }

                removeLoading();

                if (!response.ok || !data || data.ok !== true) {
                    const reply = data && typeof data.reply === 'string'
                        ? data.reply
                        : 'Maaf, terjadi kesalahan saat memproses pertanyaan.';
                    addMessage(reply, 'bot', { error: true });
                    return;
                }

                addMessage(String(data.reply || 'Maaf, jawaban tidak tersedia.'), 'bot', {
                    links: data.links,
                    copyable: data.copyable === true
                });

                if (Array.isArray(data.suggestions)) {
                    renderSuggestions(data.suggestions);
                }
            } catch (error) {
                removeLoading();
                addMessage(
                    error && error.message ? error.message : 'Koneksi ke server gagal. Periksa jaringan lalu coba lagi.',
                    'bot',
                    { error: true }
                );
            } finally {
                setBusy(false);
                input.focus();
            }
        }

        function setOpen(open) {
            panel.classList.toggle('hidden', !open);
            panel.setAttribute('aria-hidden', open ? 'false' : 'true');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Tutup Tanya CCF 2026' : 'Buka Tanya CCF 2026');

            if (open) {
                if (!initialized) {
                    initialized = true;
                    addMessage(welcome, 'bot');
                    renderSuggestions(suggestions);
                }

                window.setTimeout(function () {
                    input.focus();
                    scrollToBottom();
                }, 60);
            } else {
                toggle.focus();
            }
        }

        toggle.addEventListener('click', function () {
            setOpen(panel.classList.contains('hidden'));
        });

        close.addEventListener('click', function () {
            setOpen(false);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !panel.classList.contains('hidden')) {
                setOpen(false);
            }
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            sendMessage();
        });

        input.addEventListener('input', resizeInput);
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                form.requestSubmit();
            }
        });

        resizeInput();
    })();
</script>
