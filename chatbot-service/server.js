'use strict';

/**
 * server.js — Node.js chatbot microservice
 * Cirebon Creative Festival 2026
 *
 * Endpoint:
 *   GET  /health                     cek status service
 *   POST /api/chat                   kirim pesan chatbot
 *   POST /api/chatbot-config/test    uji koneksi provider AI (admin)
 *
 * Autentikasi:
 *   - Guest boleh tanpa token.
 *   - Admin/UMKM mengirim signed token dari PHP:
 *       Authorization: Bearer <token>
 *   - Token dibuat oleh chatbot-config.php dan diverifikasi di sini
 *     menggunakan HMAC-SHA256 dengan shared secret.
 */

require('dotenv').config();

const crypto = require('node:crypto');
const express = require('express');
const cors = require('cors');
const mysql = require('mysql2/promise');

const app = express();

// ---------------------------------------------------------------
// Konfigurasi
// ---------------------------------------------------------------
const PORT = Number(process.env.PORT || 3000);
const SHARED_SECRET = process.env.CHATBOT_SHARED_SECRET || 'ccf-chatbot-dev-secret-change-me';
const APP_BASE_URL = (process.env.APP_BASE_URL || 'http://localhost/cireboncreativefestival2026').replace(/\/+$/, '');
const MAX_MESSAGE_LENGTH = Number(process.env.CHAT_MAX_MESSAGE_LENGTH || 500);
const RATE_LIMIT = Number(process.env.CHAT_RATE_LIMIT || 30);
const RATE_WINDOW_MS = Number(process.env.CHAT_RATE_WINDOW || 60) * 1000;
const AI_TIMEOUT_MS = Number(process.env.AI_TIMEOUT_MS || 15000);
const AI_MAX_OUTPUT_TOKENS = Number(process.env.AI_MAX_OUTPUT_TOKENS || 512);
const AI_MAX_REPLY_LENGTH = 4000;

const DB_CONFIG = {
    host: process.env.DB_HOST || 'localhost',
    port: Number(process.env.DB_PORT || 3306),
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASS || '',
    database: process.env.DB_NAME || 'db_umkm_festival',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0,
    charset: 'utf8mb4',
    dateStrings: true,
};

const allowedOrigins = (process.env.CORS_ORIGIN || 'http://localhost,http://127.0.0.1')
    .split(',')
    .map((value) => value.trim())
    .filter(Boolean);

const AI_PROVIDER_DEFAULTS = {
    gemini: {
        endpoint: 'https://generativelanguage.googleapis.com/v1beta',
        model: 'models/gemini-2.5-flash',
    },
    openai: {
        endpoint: 'https://api.openai.com/v1/chat/completions',
        model: 'gpt-4o-mini',
    },
    custom: {
        endpoint: 'http://localhost:11434/v1/chat/completions',
        model: 'llama3.1',
    },
};

const pool = mysql.createPool(DB_CONFIG);

// Samakan zona waktu session MySQL dengan aplikasi PHP (WIB).
pool.on('connection', (connection) => {
    connection.query("SET time_zone = '+07:00'", (error) => {
        if (error) {
            console.error('[CCF Chatbot] Gagal mengatur time zone:', error.message);
        }
    });
});

// ---------------------------------------------------------------
// Middleware
// ---------------------------------------------------------------
app.disable('x-powered-by');

app.use(cors({
    origin(origin, callback) {
        // Request tanpa Origin (curl, server-to-server) tetap diizinkan.
        if (!origin || allowedOrigins.includes('*') || allowedOrigins.includes(origin)) {
            return callback(null, true);
        }

        return callback(new Error('Origin tidak diizinkan oleh CORS.'));
    },
    methods: ['GET', 'POST', 'OPTIONS'],
    allowedHeaders: ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With'],
    maxAge: 86400,
}));

app.use(express.json({ limit: '16kb' }));

// ---------------------------------------------------------------
// Helper umum
// ---------------------------------------------------------------
function url(path = '') {
    return `${APP_BASE_URL}/${String(path).replace(/^\/+/, '')}`;
}

function formatTanggal(value, withTime = false) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    const text = String(value).trim();
    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}):(\d{2}))?/);

    if (!match) {
        return text;
    }

    const bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    const [, year, month, day, hour, minute] = match;
    let result = `${day} ${bulan[Number(month) - 1] || month} ${year}`;

    if (withTime) {
        result += ` · ${hour || '00'}:${minute || '00'} WIB`;
    }

    return result;
}

function num(value) {
    return new Intl.NumberFormat('id-ID').format(Number(value) || 0);
}

function norm(text) {
    return String(text)
        .toLocaleLowerCase('id-ID')
        .replace(/[&/_-]/g, ' ')
        .replace(/[^\p{L}\p{N}\s]+/gu, ' ')
        .replace(/\s+/gu, ' ')
        .trim();
}

function has(text, needles) {
    return needles.some((needle) => needle !== '' && text.includes(needle));
}

function sanitizeMessage(value) {
    if (typeof value !== 'string') {
        return '';
    }

    return value
        .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/g, ' ')
        .trim();
}

function suggestionsFor(role) {
    if (role === 'admin') {
        return [
            'Rekap total pendaftar',
            'Breakdown kategori',
            'Buat catatan penolakan',
            'Draft catatan penerimaan',
        ];
    }

    if (role === 'umkm') {
        return [
            'Kapan festival 2026?',
            'Syarat foto produk',
            'Cara daftar tenant',
            'Cek status pendaftaran',
        ];
    }

    return [
        'Kapan festival 2026?',
        'Kategori tenant apa saja?',
        'Syarat foto produk',
        'Cara daftar tenant',
    ];
}

// ---------------------------------------------------------------
// Konfigurasi AI provider (dibaca dari database setiap fallback)
// ---------------------------------------------------------------
function normalizeAiConfig(raw) {
    if (!raw || typeof raw !== 'object') {
        return null;
    }

    const provider = String(raw.provider_name || '').trim().toLowerCase();
    const defaults = AI_PROVIDER_DEFAULTS[provider];

    if (!defaults) {
        return null;
    }

    return {
        provider,
        apiKey: String(raw.api_key || '').trim(),
        endpointUrl: String(raw.endpoint_url || '').trim() || defaults.endpoint,
        modelName: String(raw.model_name || '').trim() || defaults.model,
        isActive: Number(raw.is_active) === 1,
    };
}

function isAiConfigured(config) {
    if (!config || config.isActive !== true) {
        return false;
    }

    return config.provider === 'custom' || config.apiKey !== '';
}

async function getActiveChatbotConfig() {
    try {
        const [rows] = await pool.query(
            `SELECT id, provider_name, api_key, endpoint_url, model_name, is_active
             FROM chatbot_configurations
             WHERE is_active = 1
             ORDER BY updated_at DESC, id DESC
             LIMIT 1`
        );

        return rows.length > 0 ? rows[0] : null;
    } catch (error) {
        console.error('[CCF Chatbot] Gagal membaca konfigurasi AI:', error.message);
        return null;
    }
}

function buildAiSystemPrompt(user) {
    const role = user && user.role ? user.role : 'guest';
    const name = user && user.name ? user.name : 'Pengunjung';

    return [
        'Anda adalah "Tanya CCF 2026", asisten virtual resmi Cirebon Creative Festival 2026.',
        'Jawab dalam Bahasa Indonesia yang ramah, jelas, dan ringkas. Jangan mengarang data pribadi atau status pendaftaran.',
        'Jika pengguna menanyakan data akun/pendaftaran spesifik, arahkan ke dashboard masing-masing atau minta login.',
        '',
        'Fakta festival:',
        '- Nama: Cirebon Creative Festival 2026.',
        '- Pendaftaran tenant: 1 September - 15 November 2026.',
        '- Kurasi: 16-25 November 2026; pengumuman: 28 November 2026.',
        '- Festival: 12-13 Desember 2026 di Kota Cirebon.',
        '- Kategori: Kuliner; Fashion & Kriya; Kerajinan Tangan; Lainnya.',
        '- Kontak: admin@ccf2026.id dan hello@ccf2026.id.',
        '',
        `Konteks pengguna: ${name} (${role}).`,
        'Jangan membahas API key, konfigurasi internal, atau data pengguna lain.',
    ].join('\n');
}

function providerErrorMessage(provider, status, data, text, apiKey = '') {
    let detail = '';

    if (data && data.error) {
        if (typeof data.error === 'string') {
            detail = data.error;
        } else if (typeof data.error.message === 'string') {
            detail = data.error.message;
        }
    }

    if (detail === '' && text) {
        detail = String(text).slice(0, 300);
    }

    detail = detail.replace(/\s+/g, ' ').trim();

    if (apiKey !== '') {
        detail = detail.split(apiKey).join('[redacted]');
    }

    const hints = {
        400: 'Periksa format permintaan.',
        401: 'Periksa API key.',
        403: 'Periksa izin API key atau akses model.',
        404: 'Periksa endpoint dan nama model.',
        429: 'Kuota atau rate limit provider tercapai.',
    };
    const hint = hints[status] || '';

    return `Koneksi ke ${provider} gagal (HTTP ${status}).${hint ? ' ' + hint : ''}${detail ? ' Detail: ' + detail : ''}`;
}

async function fetchJsonWithTimeout(url, options = {}, timeoutMs = AI_TIMEOUT_MS) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);

    try {
        let response;

        try {
            response = await fetch(url, { ...options, signal: controller.signal });
        } catch (error) {
            if (error && error.name === 'AbortError') {
                throw new Error(`Provider AI tidak merespons dalam ${Math.round(timeoutMs / 1000)} detik.`);
            }

            throw new Error(`Tidak dapat menghubungi provider AI: ${error.message}`);
        }

        const text = await response.text();
        let data = null;

        try {
            data = text ? JSON.parse(text) : null;
        } catch (error) {
            data = null;
        }

        return { response, data, text };
    } finally {
        clearTimeout(timer);
    }
}

function buildGeminiEndpoint(config) {
    const endpoint = String(config.endpointUrl || AI_PROVIDER_DEFAULTS.gemini.endpoint)
        .trim()
        .replace(/\/+$/, '');

    if (endpoint.includes(':generateContent')) {
        return endpoint;
    }

    const model = String(config.modelName || AI_PROVIDER_DEFAULTS.gemini.model).trim();
    const modelPath = model.startsWith('models/') ? model : `models/${model}`;

    if (endpoint.includes('/models/')) {
        return `${endpoint}:generateContent`;
    }

    return `${endpoint}/${modelPath}:generateContent`;
}

async function callGemini(config, message, user) {
    const endpoint = buildGeminiEndpoint(config);
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    };

    if (config.apiKey !== '') {
        headers['x-goog-api-key'] = config.apiKey;
    }

    const body = {
        systemInstruction: {
            parts: [{ text: buildAiSystemPrompt(user) }],
        },
        contents: [
            {
                role: 'user',
                parts: [{ text: message }],
            },
        ],
        generationConfig: {
            temperature: 0.4,
            maxOutputTokens: AI_MAX_OUTPUT_TOKENS,
        },
    };

    const { response, data, text } = await fetchJsonWithTimeout(endpoint, {
        method: 'POST',
        headers,
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(providerErrorMessage('Gemini', response.status, data, text, config.apiKey));
    }

    const candidate = data && Array.isArray(data.candidates) ? data.candidates[0] : null;
    const parts = candidate && candidate.content && Array.isArray(candidate.content.parts)
        ? candidate.content.parts
        : [];
    const reply = parts
        .map((part) => (part && typeof part.text === 'string' ? part.text : ''))
        .join('')
        .trim();

    if (reply === '') {
        throw new Error('Gemini tidak mengembalikan teks jawaban.');
    }

    return reply;
}

async function callOpenAiCompatible(config, message, user) {
    const provider = config.provider === 'custom' ? 'Custom/Local' : 'OpenAI';
    const defaults = AI_PROVIDER_DEFAULTS[config.provider];
    const endpoint = String(config.endpointUrl || defaults.endpoint).trim();
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    };

    if (config.apiKey !== '') {
        headers.Authorization = `Bearer ${config.apiKey}`;
    }

    const body = {
        model: config.modelName || defaults.model,
        messages: [
            { role: 'system', content: buildAiSystemPrompt(user) },
            { role: 'user', content: message },
        ],
        temperature: 0.4,
        max_tokens: AI_MAX_OUTPUT_TOKENS,
    };

    const { response, data, text } = await fetchJsonWithTimeout(endpoint, {
        method: 'POST',
        headers,
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(providerErrorMessage(provider, response.status, data, text, config.apiKey));
    }

    const content = data
        && data.choices
        && data.choices[0]
        && data.choices[0].message
        && data.choices[0].message.content;
    const reply = typeof content === 'string' ? content.trim() : '';

    if (reply === '') {
        throw new Error(`${provider} tidak mengembalikan teks jawaban.`);
    }

    return reply;
}

async function generateAiReply(message, user, config) {
    const reply = config.provider === 'gemini'
        ? await callGemini(config, message, user)
        : await callOpenAiCompatible(config, message, user);

    return reply.slice(0, AI_MAX_REPLY_LENGTH);
}

async function testAiConnection(config) {
    const started = Date.now();
    const sample = await generateAiReply(
        'Balas dengan satu kata: OK',
        { role: 'admin', name: 'Admin' },
        config
    );

    return {
        latencyMs: Date.now() - started,
        sample,
    };
}

// ---------------------------------------------------------------
// Token auth dari PHP
// ---------------------------------------------------------------
/**
 * Verifikasi signed token dari PHP.
 *
 * Format token:
 *   base64url(JSON payload) + "." + HMAC-SHA256 hex
 *
 * Payload yang diharapkan:
 *   { uid: number, role: "admin"|"umkm", name: string, exp: number }
 *
 * @param {string} token
 * @returns {{uid: number, role: string, name: string, exp: number}|null}
 */
function verifyToken(token) {
    const parts = String(token || '').split('.');

    if (parts.length !== 2) {
        return null;
    }

    const [payloadB64, signature] = parts;
    const expected = crypto.createHmac('sha256', SHARED_SECRET).update(payloadB64).digest('hex');
    const providedBuffer = Buffer.from(signature, 'hex');
    const expectedBuffer = Buffer.from(expected, 'hex');

    if (
        providedBuffer.length !== expectedBuffer.length
        || !crypto.timingSafeEqual(providedBuffer, expectedBuffer)
    ) {
        return null;
    }

    let payload;
    try {
        payload = JSON.parse(Buffer.from(payloadB64, 'base64url').toString('utf8'));
    } catch (error) {
        return null;
    }

    if (!payload || typeof payload !== 'object') {
        return null;
    }

    if (!['admin', 'umkm'].includes(payload.role)) {
        return null;
    }

    const uid = Number(payload.uid);
    const exp = Number(payload.exp);

    if (!Number.isFinite(uid) || uid <= 0) {
        return null;
    }

    if (!Number.isFinite(exp) || exp < Math.floor(Date.now() / 1000)) {
        return null;
    }

    return {
        uid,
        role: String(payload.role),
        name: typeof payload.name === 'string' ? payload.name.trim() : '',
        exp,
    };
}

function chatAuth(req, res, next) {
    const header = req.get('authorization') || '';
    const token = header.startsWith('Bearer ') ? header.slice(7).trim() : '';

    if (token === '') {
        req.chatUser = { uid: 0, role: 'guest', name: '' };
        return next();
    }

    const payload = verifyToken(token);

    if (payload === null) {
        return res.status(401).json({
            ok: false,
            role: 'guest',
            reply: 'Sesi chat tidak valid atau sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.',
            error: 'invalid_token',
        });
    }

    req.chatUser = {
        uid: Number(payload.uid) || 0,
        role: String(payload.role),
        name: String(payload.name || '').trim(),
    };

    return next();
}

/**
 * Sapaan personal berdasarkan nama dari token PHP.
 * Guest atau user tanpa nama tetap mendapat sapaan umum.
 */
function greetingFor(user) {
    const name = String((user && user.name) || '').trim();
    return name !== '' ? `Halo, ${name}!` : 'Halo!';
}

function roleLabelFor(user) {
    if (!user || user.role === 'guest') {
        return 'pengunjung';
    }

    return user.role === 'admin' ? 'Admin' : 'UMKM';
}

/**
 * Deteksi pertanyaan identitas dari pesan yang sudah di-lowercase.
 * Contoh: "siapa saya", "siapa nama saya", "who am i".
 */
function isIdentityQuestion(lowerMsg) {
    const text = String(lowerMsg || '');

    return (
        text.includes('siapa saya')
        || text.includes('siapa aku')
        || text.includes('saya siapa')
        || text.includes('aku siapa')
        || text.includes('siapa nama saya')
        || text.includes('nama saya siapa')
        || text.includes('identitas saya')
        || text.includes('who am i')
    );
}

/**
 * Jawab pertanyaan identitas berdasarkan payload token dari PHP.
 * Guest tidak memiliki uid/name sehingga diarahkan untuk login.
 */
function identityReply(user) {
    const role = String((user && user.role) || 'guest');
    const name = String((user && user.name) || '').trim();

    if (!user || role === 'guest' || Number(user.uid) <= 0) {
        return {
            reply: 'Anda belum masuk ke dalam sistem, silakan login terlebih dahulu.',
            suggestions: suggestionsFor('guest'),
            links: [
                { label: 'Masuk ke akun', url: url('login.php') },
                { label: 'Daftar UMKM', url: url('register.php') },
            ],
            intent: 'identity_guest',
        };
    }

    return {
        reply: `Kamu adalah ${name || 'Pengguna'}, yang sedang masuk sebagai ${role} di sistem Cirebon Creative Festival 2026.`,
        suggestions: suggestionsFor(role),
        links: [
            {
                label: 'Buka dashboard',
                url: role === 'admin' ? url('admin/dashboard.php') : url('umkm/dashboard.php'),
            },
        ],
        intent: 'identity',
    };
}

// ---------------------------------------------------------------
// Rate limit sederhana (in-memory)
// ---------------------------------------------------------------
const rateBuckets = new Map();

function rateLimit(req, res, next) {
    const now = Date.now();
    const key = `${req.ip}:${req.chatUser.role}:${req.chatUser.uid}`;
    let bucket = rateBuckets.get(key);

    if (!bucket || now - bucket.start >= RATE_WINDOW_MS) {
        bucket = { start: now, count: 0 };
        rateBuckets.set(key, bucket);
    }

    bucket.count += 1;

    if (bucket.count > RATE_LIMIT) {
        return res.status(429).json({
            ok: false,
            role: req.chatUser.role,
            reply: 'Terlalu banyak pesan dalam waktu singkat. Tunggu sekitar satu menit lalu coba lagi.',
            error: 'rate_limited',
        });
    }

    return next();
}

setInterval(() => {
    const now = Date.now();
    for (const [key, bucket] of rateBuckets) {
        if (now - bucket.start >= RATE_WINDOW_MS) {
            rateBuckets.delete(key);
        }
    }
}, 60_000).unref();

// ---------------------------------------------------------------
// Admin Copilot
// ---------------------------------------------------------------
async function adminStats() {
    const [[umkmRow]] = await pool.query('SELECT COUNT(*) AS total FROM umkm');
    const [statusRows] = await pool.query('SELECT status, COUNT(*) AS total FROM pendaftaran GROUP BY status');

    const counts = { Menunggu: 0, Diterima: 0, Ditolak: 0 };
    for (const row of statusRows) {
        if (Object.prototype.hasOwnProperty.call(counts, row.status)) {
            counts[row.status] = Number(row.total) || 0;
        }
    }

    const totalPendaftaran = Object.values(counts).reduce((total, value) => total + value, 0);

    const [categoryRows] = await pool.query(
        `SELECT m.kategori_produk,
                COUNT(DISTINCT m.id) AS total_umkm,
                COUNT(p.id) AS total_pendaftaran
         FROM umkm m
         LEFT JOIN pendaftaran p ON p.umkm_id = m.id
         GROUP BY m.kategori_produk
         ORDER BY total_pendaftaran DESC, total_umkm DESC, m.kategori_produk ASC`
    );

    const lines = [
        'Rekap Cirebon Creative Festival 2026',
        '',
        `Total UMKM: ${num(umkmRow.total)}`,
        `Total pendaftaran: ${num(totalPendaftaran)}`,
        `- Menunggu: ${num(counts.Menunggu)}`,
        `- Diterima: ${num(counts.Diterima)}`,
        `- Ditolak: ${num(counts.Ditolak)}`,
    ];

    if (categoryRows.length > 0) {
        lines.push('');
        lines.push('Breakdown kategori:');
        for (const row of categoryRows) {
            lines.push(
                `- ${row.kategori_produk}: ${num(row.total_pendaftaran)} pendaftaran, ${num(row.total_umkm)} UMKM`
            );
        }
    } else {
        lines.push('');
        lines.push('Belum ada data kategori UMKM.');
    }

    lines.push('');
    lines.push('Data diambil langsung dari database saat ini.');

    return {
        reply: lines.join('\n'),
        suggestions: suggestionsFor('admin'),
        intent: 'admin_stats',
    };
}

function extractUmkmName(message) {
    const patterns = [
        /untuk\s+(?:umkm\s+)?["“”']?([^"“”',.;\n]+?)["“”']?(?=\s+(?:karena|dengan|yang|agar|supaya|sebagai|$))/iu,
        /(?:tolak|menolak|terima|menerima|approve|reject)\s+(?:pendaftaran\s+)?(?:umkm\s+)?["“”']?([^"“”',.;\n]+?)["“”']?(?=\s+(?:karena|dengan|yang|agar|supaya|$))/iu,
        /umkm\s+["“”']?([^"“”',.;\n]+?)["“”']?(?=\s+(?:karena|dengan|yang|agar|supaya|$))/iu,
    ];

    for (const pattern of patterns) {
        const match = message.match(pattern);
        if (match && match[1] && match[1].trim() !== '') {
            return match[1].trim();
        }
    }

    return '';
}

function extractReason(message) {
    const because = message.match(/karena\s+(.+)$/iu);
    if (because) {
        return because[1].replace(/^[\s.,;]+|[\s.,;]+$/g, '');
    }

    const reason = message.match(/alasan(?:nya)?\s*[:\-]?\s*(.+)$/iu);
    if (reason) {
        return reason[1].replace(/^[\s.,;]+|[\s.,;]+$/g, '');
    }

    return '';
}

async function adminNote(message, normalized) {
    const isRejection = has(normalized, ['tolak', 'ditolak', 'penolakan', 'reject', 'rejection']);
    const isApproval = has(normalized, ['terima pendaftaran', 'diterima', 'penerimaan', 'approval', 'approve', 'lolos']);

    if (isRejection && isApproval) {
        return {
            reply: 'Pesanmu menyebut penolakan sekaligus penerimaan. Pilih salah satu agar draft catatan lebih tepat, misalnya:\n\n'
                + '- "Buat catatan penolakan untuk UMKM Batik Mega Mendung karena foto kurang jelas"\n'
                + '- "Draft catatan penerimaan untuk UMKM Tahu Kocek Julian"',
            suggestions: suggestionsFor('admin'),
            intent: 'admin_note_clarify',
        };
    }

    if (!isRejection && !isApproval) {
        return {
            reply: 'Saya bisa menyusun draft catatan kurasi. Sebutkan jenisnya, misalnya:\n\n'
                + '- "Buat catatan penolakan untuk UMKM ... karena ..."\n'
                + '- "Draft catatan penerimaan untuk UMKM ..."',
            suggestions: suggestionsFor('admin'),
            intent: 'admin_note_clarify',
        };
    }

    const extractedName = extractUmkmName(message);
    const reason = extractReason(message);
    let officialName = '';
    let category = '';

    if (extractedName !== '') {
        const [rows] = await pool.execute(
            `SELECT nama_umkm, kategori_produk
             FROM umkm
             WHERE nama_umkm LIKE ?
             ORDER BY nama_umkm ASC
             LIMIT 1`,
            [`%${extractedName}%`]
        );

        if (rows.length > 0) {
            officialName = String(rows[0].nama_umkm);
            category = String(rows[0].kategori_produk);
        }
    }

    const displayName = officialName !== ''
        ? officialName
        : (extractedName !== '' ? extractedName : 'UMKM yang bersangkutan');
    const categoryText = category !== '' ? ` pada kategori ${category}` : '';

    if (isRejection) {
        const reasonText = reason !== ''
            ? reason
            : 'kelengkapan deskripsi produk, kualitas foto, dan kesesuaian dengan kategori festival';

        const draft = `Terima kasih atas partisipasi ${displayName} dalam Cirebon Creative Festival 2026. `
            + `Setelah meninjau pendaftaran dan materi produk${categoryText}, kami belum dapat menerima `
            + `${displayName} sebagai tenant pada periode ini. Aspek yang perlu diperbaiki: ${reasonText}. `
            + `Kami mendorong ${displayName} untuk memperbaiki materi tersebut dan mengirim ulang pendaftaran `
            + `sebelum 15 November 2026 melalui dashboard UMKM. Keputusan ini bukan penilaian akhir terhadap `
            + `kualitas produk, dan kami mengapresiasi minat serta usaha yang telah ditunjukkan.`;

        return {
            reply: `Berikut draft catatan penolakan yang profesional:\n\n"${draft}"\n\n`
                + 'Catatan ini masih draft. Pastikan alasan spesifik, hindari bahasa yang menyalahkan, '
                + 'dan tawarkan langkah perbaikan sebelum dikirim.',
            suggestions: [
                'Buat catatan penolakan lain',
                'Rekap total pendaftar',
                'Breakdown kategori',
                'Draft catatan penerimaan',
            ],
            copyable: true,
            intent: 'admin_note_rejection',
        };
    }

    const draft = `Selamat, ${displayName}! Kami dengan senang hati menginformasikan bahwa pendaftaran `
        + `${displayName}${categoryText} diterima sebagai tenant Cirebon Creative Festival 2026. `
        + `Produkmu akan dipamerkan pada 12–13 Desember 2026 di Kota Cirebon. Informasi teknis mengenai `
        + `booth, jadwal setup, dan koordinasi tenant akan dikirim melalui email atau WhatsApp terdaftar. `
        + `Terima kasih atas partisipasi dan kerja keras yang telah ditunjukkan.`;

    return {
        reply: `Berikut draft catatan penerimaan yang profesional:\n\n"${draft}"\n\n`
            + 'Catatan ini masih draft. Sesuaikan detail booth, kontak, dan jadwal sebelum dikirim.',
        suggestions: [
            'Draft catatan penerimaan lain',
            'Rekap total pendaftar',
            'Breakdown kategori',
            'Buat catatan penolakan',
        ],
        copyable: true,
        intent: 'admin_note_approval',
    };
}

async function adminReply(message, user) {
    const normalized = norm(message);

    // Intent catatan kurasi harus dicek lebih dulu agar "terima/tolak"
    // tidak dianggap sebagai sapaan atau pertanyaan statistik.
    if (has(normalized, [
        'buat catatan', 'draft catatan', 'catatan penolakan', 'catatan penerimaan',
        'feedback', 'penolakan', 'penerimaan', 'approval', 'approve', 'reject',
        'rejection', 'tolak pendaftaran', 'terima pendaftaran',
    ])) {
        return adminNote(message, normalized);
    }

    if (has(normalized, [
        'rekap', 'total', 'jumlah', 'statistik', 'berapa', 'breakdown', 'sebaran',
        'kategori', 'data pendaftar', 'pendaftar', 'ringkasan',
    ])) {
        return adminStats();
    }

    if (has(normalized, ['terima kasih', 'makasih', 'thanks', 'thank you'])) {
        return {
            reply: 'Sama-sama. Saya siap membantu rekap data, breakdown kategori, atau draft catatan kurasi.',
            suggestions: suggestionsFor('admin'),
            intent: 'thanks',
        };
    }

    if (has(normalized, ['halo', 'hai', 'hi', 'pagi', 'siang', 'sore', 'malam'])) {
        return {
            reply: `${greetingFor(user)} Saya Admin Copilot CCF 2026. Saya siap membantu Anda sebagai Admin:\n\n`
                + '- Rekap total UMKM dan pendaftaran\n'
                + '- Breakdown pendaftaran per kategori\n'
                + '- Draft catatan penolakan atau penerimaan yang profesional\n\n'
                + 'Contoh: "Rekap total pendaftar" atau "Buat catatan penolakan untuk UMKM Batik Mega Mendung karena foto kurang jelas".',
            suggestions: suggestionsFor('admin'),
            intent: 'greeting',
        };
    }

    if (has(normalized, ['bantuan', 'bisa apa', 'fitur', 'help', 'menu'])) {
        return {
            reply: 'Fitur Admin Copilot:\n\n'
                + '1. Rekap total UMKM, pendaftaran, dan status kurasi.\n'
                + '2. Breakdown pendaftaran per kategori.\n'
                + '3. Draft catatan penolakan/penerimaan profesional.\n\n'
                + 'Sebutkan nama UMKM dan alasan bila ingin draft catatan yang lebih spesifik.',
            suggestions: suggestionsFor('admin'),
            intent: 'help',
        };
    }

    return {
        reply: 'Saya belum menemukan intent yang cocok. Coba tanyakan salah satu topik berikut:\n\n'
            + '- "Rekap total pendaftar"\n'
            + '- "Breakdown kategori"\n'
            + '- "Buat catatan penolakan untuk UMKM ... karena ..."\n'
            + '- "Draft catatan penerimaan untuk UMKM ..."',
        suggestions: suggestionsFor('admin'),
        intent: 'fallback',
    };
}

// ---------------------------------------------------------------
// UMKM / Guest Assistant
// ---------------------------------------------------------------
async function umkmStatus(user) {
    if (user.role !== 'umkm' || user.uid <= 0) {
        return {
            reply: 'Untuk mengecek status pendaftaran, kamu perlu masuk ke akun UMKM terlebih dahulu. '
                + 'Setelah login, buka dashboard UMKM atau tanyakan lagi di sini.',
            suggestions: suggestionsFor('guest'),
            links: [
                { label: 'Masuk ke akun', url: url('login.php') },
                { label: 'Daftar UMKM', url: url('register.php') },
            ],
            intent: 'status_guest',
        };
    }

    const [umkmRows] = await pool.execute(
        `SELECT m.id AS umkm_id, m.nama_umkm, m.kategori_produk
         FROM umkm m
         JOIN users u ON u.id = m.user_id
         WHERE m.user_id = ? AND u.role = ?
         LIMIT 1`,
        [user.uid, 'umkm']
    );
    const umkm = umkmRows[0];

    if (!umkm) {
        return {
            reply: `${greetingFor(user)} Akunmu belum memiliki profil UMKM. Hubungi admin festival di admin@ccf2026.id `
                + 'agar profil usaha dilengkapi sebelum mendaftar.',
            suggestions: suggestionsFor('umkm'),
            links: [{ label: 'Buka dashboard', url: url('umkm/dashboard.php') }],
            intent: 'status_no_profile',
        };
    }

    const [latestRows] = await pool.execute(
        `SELECT status, catatan_admin, created_at
         FROM pendaftaran
         WHERE umkm_id = ?
         ORDER BY created_at DESC, id DESC
         LIMIT 1`,
        [Number(umkm.umkm_id)]
    );
    const latest = latestRows[0];

    if (!latest) {
        return {
            reply: `${greetingFor(user)} Kamu belum memiliki pendaftaran produk untuk ${umkm.nama_umkm}. `
                + 'Buka dashboard UMKM, lalu klik "Daftar sebagai Tenant" untuk mengisi formulir.',
            suggestions: suggestionsFor('umkm'),
            links: [
                { label: 'Isi form pendaftaran', url: url('umkm/form-pendaftaran.php') },
                { label: 'Buka dashboard', url: url('umkm/dashboard.php') },
            ],
            intent: 'status_not_registered',
        };
    }

    const status = String(latest.status);
    const lines = [
        `${greetingFor(user)} Status pendaftaran ${umkm.nama_umkm}: ${status}`,
        `Diajukan: ${formatTanggal(latest.created_at, true)}`,
        '',
    ];

    if (status === 'Menunggu') {
        lines.push('Tim kurator sedang meninjau produkmu. Hasil kurasi akan muncul di dashboard dan dikirim ke email terdaftar.');
    } else if (status === 'Diterima') {
        lines.push('Selamat! Pendaftaranmu diterima. Siapkan produk untuk festival 12–13 Desember 2026. Informasi booth dan teknis akan dikirim melalui email.');
    } else if (status === 'Ditolak') {
        lines.push('Pendaftaran belum diterima. Perbaiki materi produk sesuai catatan admin, lalu kirim ulang sebelum 15 November 2026.');
    } else {
        lines.push('Pantau dashboard UMKM untuk informasi terbaru.');
    }

    if (latest.catatan_admin) {
        lines.push('');
        lines.push('Catatan admin:');
        lines.push(String(latest.catatan_admin));
    }

    const links = [{ label: 'Buka dashboard', url: url('umkm/dashboard.php') }];
    if (status === 'Ditolak') {
        links.push({ label: 'Perbaiki & kirim ulang', url: url('umkm/form-pendaftaran.php') });
    }

    return {
        reply: lines.join('\n'),
        suggestions: suggestionsFor('umkm'),
        links,
        intent: 'status',
    };
}

async function umkmReply(message, user) {
    const normalized = norm(message);
    const suggestionRole = user.role === 'guest' ? 'guest' : 'umkm';

    if (has(normalized, [
        'status', 'pendaftaran saya', 'sudah daftar', 'lolos', 'diterima', 'ditolak',
        'menunggu', 'catatan admin', 'hasil kurasi', 'pengumuman', 'cek pendaftaran',
    ])) {
        return umkmStatus(user);
    }

    if (has(normalized, [
        'tanggal', 'jadwal', 'kapan', 'desember', 'pelaksanaan', 'hari apa', 'tanggal berapa',
    ])) {
        return {
            reply: 'Jadwal Cirebon Creative Festival 2026:\n\n'
                + '- Pendaftaran tenant: 1 September – 15 November 2026\n'
                + '- Kurasi & seleksi: 16 – 25 November 2026\n'
                + '- Pengumuman: 28 November 2026\n'
                + '- Festival: 12 – 13 Desember 2026 di Kota Cirebon\n\n'
                + 'Pastikan pendaftaran dikirim sebelum 15 November 2026.',
            suggestions: suggestionsFor(suggestionRole),
            links: [{ label: 'Daftar sebagai tenant', url: url('register.php') }],
            intent: 'schedule',
        };
    }

    if (has(normalized, [
        'kategori', 'kuliner', 'fashion', 'kriya', 'kerajinan', 'jenis produk', 'bidang',
    ])) {
        return {
            reply: 'Kategori tenant CCF 2026:\n\n'
                + '- Kuliner — makanan/minuman siap saji, produk kemasan, kopi, dan teh lokal.\n'
                + '- Fashion & Kriya — batik, tenun, aksesori, fashion lokal, dan kriya tangan.\n'
                + '- Kerajinan Tangan — produk handmade, suvenir, dan kerajinan kreatif.\n'
                + '- Lainnya — kategori kreatif lain yang relevan dengan festival.\n\n'
                + 'Pilih kategori yang paling sesuai saat membuat profil UMKM.',
            suggestions: suggestionsFor(suggestionRole),
            intent: 'categories',
        };
    }

    if (has(normalized, [
        'foto', 'gambar', 'upload', 'unggah', 'ukuran', 'format', 'jpg', 'jpeg',
        'png', 'webp', '2 mb', 'resolusi', 'kualitas foto',
    ])) {
        return {
            reply: 'Syarat foto produk:\n\n'
                + '- Format JPG, PNG, atau WEBP.\n'
                + '- Ukuran maksimal 2 MB.\n'
                + '- Gunakan foto asli, fokus, terang, dan latar bersih.\n'
                + '- Produk terlihat jelas; hindari blur, watermark, atau edit berlebihan.\n'
                + '- Satu foto per pengajuan, diunggah melalui form pendaftaran.\n\n'
                + 'Foto yang baik membantu kurator menilai kualitas dan kesiapan produkmu.',
            suggestions: suggestionsFor(suggestionRole),
            intent: 'photo_requirements',
        };
    }

    if (has(normalized, [
        'cara daftar', 'cara mendaftar', 'langkah', 'prosedur', 'syarat', 'daftar tenant',
        'mendaftar', 'registrasi tenant', 'registrasi umkm', 'alur pendaftaran', 'proses pendaftaran',
    ])) {
        return {
            reply: 'Langkah mendaftar tenant CCF 2026:\n\n'
                + '1. Buat akun UMKM melalui halaman Daftar.\n'
                + '2. Lengkapi profil usaha: nama UMKM, kategori, telepon, dan alamat.\n'
                + '3. Masuk ke dashboard UMKM.\n'
                + '4. Klik "Daftar sebagai Tenant".\n'
                + '5. Isi deskripsi produk (10–2000 karakter) dan unggah foto.\n'
                + '6. Kirim pendaftaran, lalu pantau status di dashboard.\n\n'
                + 'Kurasi berlangsung 16–25 November 2026 dan pengumuman 28 November 2026.',
            suggestions: suggestionsFor(suggestionRole),
            links: [
                { label: 'Daftar akun UMKM', url: url('register.php') },
                { label: 'Masuk ke akun', url: url('login.php') },
            ],
            intent: 'registration_steps',
        };
    }

    if (has(normalized, ['biaya', 'harga', 'bayar', 'gratis', 'booth', 'stand', 'sewa'])) {
        return {
            reply: 'Informasi biaya booth belum tercantum di sistem. Silakan hubungi panitia di '
                + 'admin@ccf2026.id atau hello@ccf2026.id untuk rincian biaya dan fasilitas tenant.',
            suggestions: suggestionsFor(suggestionRole),
            intent: 'fee',
        };
    }

    if (has(normalized, ['lokasi', 'tempat', 'venue', 'dimana', 'di mana', 'alamat'])) {
        return {
            reply: 'Festival berlangsung 12–13 Desember 2026 di Kota Cirebon. '
                + 'Detail venue akan diumumkan melalui dashboard UMKM dan email tenant.',
            suggestions: suggestionsFor(suggestionRole),
            intent: 'location',
        };
    }

    if (has(normalized, ['kontak', 'email', 'hubungi', 'admin', 'panitia', 'whatsapp', 'telepon'])) {
        return {
            reply: 'Kontak panitia CCF 2026:\n\n'
                + '- Administrasi & pendaftaran: admin@ccf2026.id\n'
                + '- Informasi umum: hello@ccf2026.id\n\n'
                + 'Sertakan nama UMKM dan nomor pendaftaran agar tim lebih cepat membantu.',
            suggestions: suggestionsFor(suggestionRole),
            intent: 'contact',
        };
    }

    if (has(normalized, ['terima kasih', 'makasih', 'thanks', 'thank you'])) {
        return {
            reply: 'Sama-sama! Ada lagi yang bisa saya bantu seputar CCF 2026?',
            suggestions: suggestionsFor(suggestionRole),
            intent: 'thanks',
        };
    }

    if (has(normalized, ['halo', 'hai', 'hi', 'pagi', 'siang', 'sore', 'malam', 'assalamualaikum'])) {
        return {
            reply: `${greetingFor(user)} Saya Tanya CCF 2026, asisten untuk ${roleLabelFor(user)}. `
                + 'Saya bisa membantu info jadwal, kategori tenant, syarat foto, cara mendaftar, '
                + 'dan status pendaftaran.',
            suggestions: suggestionsFor(suggestionRole),
            intent: 'greeting',
        };
    }

    if (has(normalized, ['bantuan', 'bisa apa', 'fitur', 'help', 'menu'])) {
        return {
            reply: 'Saya bisa membantu:\n\n'
                + '- Jadwal festival dan periode pendaftaran\n'
                + '- Kategori tenant\n'
                + '- Syarat dan tips foto produk\n'
                + '- Langkah pendaftaran tenant\n'
                + '- Cek status pendaftaran (setelah login sebagai UMKM)\n\n'
                + 'Coba tanyakan salah satu topik di atas.',
            suggestions: suggestionsFor(suggestionRole),
            intent: 'help',
        };
    }

    return {
        reply: 'Saya belum menemukan jawaban yang tepat. Coba tanyakan:\n\n'
            + '- "Kapan festival 2026?"\n'
            + '- "Kategori tenant apa saja?"\n'
            + '- "Syarat foto produk?"\n'
            + '- "Cara daftar tenant?"\n'
            + '- "Cek status pendaftaran"',
        suggestions: suggestionsFor(suggestionRole),
        intent: 'fallback',
    };
}

// ---------------------------------------------------------------
// Routes
// ---------------------------------------------------------------
app.get('/health', (req, res) => {
    res.json({ ok: true, service: 'ccf-2026-chatbot', status: 'running' });
});

app.get('/', (req, res) => {
    res.json({
        ok: true,
        service: 'CCF 2026 Chatbot Service',
        endpoints: [
            'POST /api/chat',
            'POST /api/chatbot-config/test',
        ],
        health: 'GET /health',
    });
});

app.post('/api/chat', chatAuth, rateLimit, async (req, res) => {
    const role = req.chatUser.role;
    const message = sanitizeMessage(req.body ? req.body.message : '');

    if (message === '') {
        return res.status(422).json({
            ok: false,
            role,
            reply: 'Tulis pertanyaan terlebih dahulu.',
            error: 'empty_message',
        });
    }

    if ([...message].length > MAX_MESSAGE_LENGTH) {
        return res.status(422).json({
            ok: false,
            role,
            reply: `Pesan terlalu panjang. Maksimal ${MAX_MESSAGE_LENGTH} karakter.`,
            error: 'message_too_long',
        });
    }

    try {
        const lowerMsg = message.toLocaleLowerCase('id-ID');
        let result;

        if (isIdentityQuestion(lowerMsg)) {
            result = identityReply(req.chatUser);
        } else if (role === 'admin') {
            result = await adminReply(message, req.chatUser);
        } else {
            result = await umkmReply(message, req.chatUser);
        }

        // Jika aturan lokal tidak menemukan intent, gunakan provider AI
        // yang sedang aktif. Konfigurasi dibaca ulang dari database agar
        // perubahan dari halaman admin langsung berlaku.
        if (result.intent === 'fallback') {
            try {
                const aiConfig = normalizeAiConfig(await getActiveChatbotConfig());

                if (isAiConfigured(aiConfig)) {
                    const aiReply = await generateAiReply(message, req.chatUser, aiConfig);

                    result = {
                        reply: aiReply,
                        suggestions: suggestionsFor(role),
                        intent: 'ai_fallback',
                    };
                }
            } catch (error) {
                // Kegagalan provider AI tidak boleh memutus chatbot.
                console.error('[CCF Chatbot] AI fallback gagal:', error.message);
            }
        }

        return res.json({
            ok: true,
            role,
            reply: result.reply,
            suggestions: result.suggestions || suggestionsFor(role),
            links: result.links || [],
            copyable: Boolean(result.copyable),
            intent: result.intent || 'fallback',
        });
    } catch (error) {
        console.error('[CCF Chatbot] Gagal memproses pesan:', error);
        return res.status(500).json({
            ok: false,
            role,
            reply: 'Maaf, terjadi kesalahan saat memproses pertanyaan. Silakan coba lagi beberapa saat lagi.',
            error: 'server_error',
        });
    }
});

app.post('/api/chatbot-config/test', chatAuth, async (req, res) => {
    if (req.chatUser.role !== 'admin') {
        return res.status(403).json({
            ok: false,
            message: 'Hanya admin yang dapat menguji konfigurasi AI.',
            error: 'forbidden',
        });
    }

    const body = req.body && typeof req.body === 'object' ? req.body : {};
    const config = normalizeAiConfig({
        provider_name: body.provider_name,
        api_key: body.api_key,
        endpoint_url: body.endpoint_url,
        model_name: body.model_name,
        is_active: 1,
    });

    if (!config) {
        return res.status(422).json({
            ok: false,
            message: 'Provider AI tidak valid.',
            error: 'invalid_provider',
        });
    }

    if (config.provider !== 'custom' && config.apiKey === '') {
        return res.status(422).json({
            ok: false,
            message: 'API key wajib diisi untuk provider ini.',
            error: 'missing_api_key',
        });
    }

    try {
        const result = await testAiConnection(config);

        return res.json({
            ok: true,
            message: `Koneksi ke ${config.provider} berhasil.`,
            provider: config.provider,
            model: config.modelName,
            latency_ms: result.latencyMs,
            sample: result.sample,
        });
    } catch (error) {
        console.error('[CCF Chatbot] Uji koneksi AI gagal:', error.message);

        return res.status(422).json({
            ok: false,
            message: error.message || 'Koneksi ke provider AI gagal.',
            error: 'ai_test_failed',
        });
    }
});

app.use((req, res) => {
    res.status(404).json({
        ok: false,
        reply: 'Endpoint tidak ditemukan.',
        error: 'not_found',
    });
});

app.use((error, req, res, next) => {
    if (res.headersSent) {
        return next(error);
    }

    if (error && error.type === 'entity.parse.failed') {
        return res.status(400).json({
            ok: false,
            reply: 'Format JSON tidak valid.',
            error: 'invalid_json',
        });
    }

    if (error && error.message === 'Origin tidak diizinkan oleh CORS.') {
        return res.status(403).json({
            ok: false,
            reply: 'Origin frontend tidak diizinkan mengakses layanan chatbot.',
            error: 'cors_denied',
        });
    }

    console.error('[CCF Chatbot] Unhandled error:', error);
    return res.status(500).json({
        ok: false,
        reply: 'Terjadi kesalahan internal pada layanan chatbot.',
        error: 'server_error',
    });
});

// ---------------------------------------------------------------
// Start
// ---------------------------------------------------------------
app.listen(PORT, () => {
    console.log(`[CCF Chatbot] Service berjalan di http://localhost:${PORT}`);
    console.log(`[CCF Chatbot] Database target: ${DB_CONFIG.user}@${DB_CONFIG.host}:${DB_CONFIG.port}/${DB_CONFIG.database}`);
    console.log(`[CCF Chatbot] CORS origin: ${allowedOrigins.join(', ')}`);
});

// Tes koneksi database tanpa menghentikan service bila gagal.
(async () => {
    try {
        const connection = await pool.getConnection();
        connection.release();
        console.log('[CCF Chatbot] Koneksi database berhasil.');
    } catch (error) {
        console.error('[CCF Chatbot] Koneksi database gagal:', error.message);
    }
})();

// Graceful shutdown
async function shutdown(signal) {
    console.log(`[CCF Chatbot] Menerima ${signal}, menutup service...`);
    try {
        await pool.end();
    } finally {
        process.exit(0);
    }
}

process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));
