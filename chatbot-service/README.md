# CCF 2026 Chatbot Service (Node.js)

Microservice chatbot untuk **Cirebon Creative Festival 2026**. Semua logika chatbot berada di Node.js/Express, bukan di PHP. Chatbot memakai aturan lokal (rule-based) dan dapat memakai provider AI sebagai fallback untuk pertanyaan yang tidak dikenali.

PHP hanya bertugas:

1. Menampilkan widget chatbot di halaman.
2. Membuat signed token berisi user ID dan role.
3. Mengirim request ke endpoint Node.js.

---

## 1. Struktur

```text
chatbot-service/
├── package.json
├── server.js
├── .env.example
├── .gitignore
└── README.md
```

File pendukung di root project:

- `chatbot-config.php` — URL service, URL uji koneksi, dan pembuat signed token.
- `partials/chatbot.php` — widget frontend yang memanggil Node.js.
- `admin/chatbot-config.php` — halaman admin untuk mengatur provider AI, API key, endpoint, model, dan status aktif.
- `database.sql` — berisi tabel `chatbot_configurations`.

---

## 2. Menjalankan service

Prasyarat: **Node.js 18+** dan MySQL yang sama dengan project PHP.

```bash
cd chatbot-service
npm install
npm start
```

Service berjalan di:

```text
http://localhost:3000
```

Cek health:

```bash
curl http://localhost:3000/health
```

Hasil:

```json
{ "ok": true, "service": "ccf-2026-chatbot", "status": "running" }
```

Untuk development dengan auto-reload:

```bash
npm run dev
```

---

## 3. Konfigurasi `.env`

Salin `.env.example` menjadi `.env`:

```bash
copy .env.example .env
```

Isi penting:

```env
PORT=3000

DB_HOST=localhost
DB_PORT=3306
DB_NAME=db_umkm_festival
DB_USER=root
DB_PASS=

CHATBOT_SHARED_SECRET=ccf-chatbot-dev-secret-change-me
APP_BASE_URL=http://localhost/cireboncreativefestival2026
CORS_ORIGIN=http://localhost,http://127.0.0.1

CHAT_MAX_MESSAGE_LENGTH=500
CHAT_RATE_LIMIT=30
CHAT_RATE_WINDOW=60

AI_TIMEOUT_MS=15000
AI_MAX_OUTPUT_TOKENS=512
```

> `CHATBOT_SHARED_SECRET` **harus sama** dengan nilai di `chatbot-config.php`.
> Untuk produksi, ganti dengan string acak yang kuat, misalnya hasil `openssl rand -hex 32`.

Jika `.env` tidak dibuat, service tetap berjalan memakai nilai default yang sama.

---

## 4. Endpoint

### `POST /api/chat`

Request:

```json
{
  "message": "Kapan festival 2026?"
}
```

Header opsional untuk admin/UMKM:

```http
Authorization: Bearer <signed-token>
```

Response sukses:

```json
{
  "ok": true,
  "role": "guest",
  "reply": "Jadwal Cirebon Creative Festival 2026: ...",
  "suggestions": ["Kapan festival 2026?", "..."],
  "links": [],
  "copyable": false,
  "intent": "schedule"
}
```

Response error tetap memakai bentuk JSON:

```json
{
  "ok": false,
  "role": "guest",
  "reply": "Pesan terlalu panjang. Maksimal 500 karakter.",
  "error": "message_too_long"
}
```

Status yang mungkin:

- `200` — berhasil.
- `400` — JSON tidak valid.
- `401` — token tidak valid/kedaluwarsa.
- `403` — origin CORS tidak diizinkan.
- `404` — endpoint tidak ditemukan.
- `422` — pesan kosong/terlalu panjang.
- `429` — rate limit.
- `500` — kesalahan server/database.

### `POST /api/chatbot-config/test`

Endpoint khusus admin untuk tombol **Uji Koneksi** di `admin/chatbot-config.php`.
Endpoint ini tidak menyimpan konfigurasi; hanya mengirim prompt uji ke provider.

Header wajib:

```http
Authorization: Bearer <signed-token-admin>
```

Request:

```json
{
  "provider_name": "gemini",
  "api_key": "AIza...",
  "endpoint_url": "https://generativelanguage.googleapis.com/v1beta",
  "model_name": "models/gemini-2.5-flash"
}
```

Response sukses:

```json
{
  "ok": true,
  "message": "Koneksi ke gemini berhasil.",
  "provider": "gemini",
  "model": "models/gemini-2.5-flash",
  "latency_ms": 812,
  "sample": "OK"
}
```

Response gagal memakai `ok: false` dan `message` yang sudah disanitasi (API key tidak pernah dikembalikan).

---

## 5. Contoh frontend PHP/HTML

### Opsi A — sudah terpasang di widget

`partials/chatbot.php` sudah memanggil Node.js. Bagian pentingnya:

```php
require_once __DIR__ . '/../chatbot-config.php';

$chatToken = chatbot_auth_token($_SESSION['user'] ?? null);
```

```html
<div id="ccf-chatbot"
     data-api-url="<?= htmlspecialchars(CHATBOT_SERVICE_URL) ?>"
     data-auth-token="<?= htmlspecialchars($chatToken) ?>">
</div>
```

```js
const headers = {
  'Content-Type': 'application/json',
  'Accept': 'application/json'
};

if (authToken) {
  headers.Authorization = 'Bearer ' + authToken;
}

const response = await fetch(apiUrl, {
  method: 'POST',
  headers,
  body: JSON.stringify({ message: 'Kapan festival 2026?' })
});

const data = await response.json();
console.log(data.reply);
```

### Opsi B — fetch manual dari halaman PHP

```html
<script>
async function tanyaCCF(message) {
  const response = await fetch('http://localhost:3000/api/chat', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ message })
  });

  const data = await response.json();
  return data.reply;
}

tanyaCCF('Kapan festival 2026?').then(console.log);
</script>
```

Untuk user login, sertakan token:

```js
headers.Authorization = 'Bearer ' + <?= json_encode($chatToken) ?>;
```

### Opsi C — axios

```js
import axios from 'axios';

const { data } = await axios.post(
  'http://localhost:3000/api/chat',
  { message: 'Cara daftar tenant?' },
  {
    headers: {
      Authorization: `Bearer ${token}`
    }
  }
);

console.log(data.reply);
```

---

## 6. Signed token (PHP → Node.js)

Token dibuat di `chatbot-config.php`:

```php
$payload = [
    'uid'  => (int) $user['id'],
    'role' => (string) $user['role'],
    'name' => (string) ($user['nama_lengkap'] ?? ''),
    'exp'  => time() + CHATBOT_TOKEN_TTL,
];

$encoded = chatbot_base64url(json_encode($payload));
$signature = hash_hmac('sha256', $encoded, CHATBOT_SHARED_SECRET);
$token = $encoded . '.' . $signature;
```

Node.js memverifikasi signature dengan HMAC-SHA256 dan mengecek masa berlaku token. Guest tidak perlu token; admin/UMKM wajib mengirim token agar bisa mengakses rekap atau status pendaftaran.

> Token hanya berisi identitas dan role. Jangan pernah mengirim password atau data sensitif lain ke service chatbot.

---

## 7. CORS

Frontend PHP berjalan di origin berbeda (misalnya `http://localhost`) dari service Node.js (`http://localhost:3000`). Karena itu CORS diaktifkan.

Atur origin yang diizinkan di `.env`:

```env
CORS_ORIGIN=http://localhost,http://127.0.0.1
```

Untuk development cepat bisa memakai:

```env
CORS_ORIGIN=*
```

Jangan gunakan `*` di produksi.

---

## 8. Produksi

Rekomendasi:

1. Jalankan Node.js di belakang reverse proxy (Nginx/Apache), misalnya:
   - `https://domain.com/chatbot/` → `http://127.0.0.1:3000/`
2. Set `CHATBOT_SERVICE_URL=https://domain.com/chatbot/api/chat` di environment PHP.
3. Set `APP_BASE_URL=https://domain.com` di `.env` Node.js.
4. Gunakan HTTPS agar token tidak dikirim dalam koneksi plaintext.
5. Ganti `CHATBOT_SHARED_SECRET` dengan nilai acak yang kuat dan simpan hanya di server.
6. Batasi `CORS_ORIGIN` ke domain frontend.
7. Jangan membuka port database ke publik.
8. File `.htaccess` di folder ini memblokir akses web langsung ke `chatbot-service/` agar `.env` dan source Node tidak bisa diunduh lewat Apache/Laragon.
9. Idealnya folder `chatbot-service` diletakkan di luar document root. `.htaccess` hanya lapisan pengaman tambahan dan bisa diabaikan jika `AllowOverride` dimatikan.

---

## 9. Troubleshooting

### `Failed to fetch` di browser

- Pastikan service berjalan: `curl http://localhost:3000/health`.
- Pastikan `CORS_ORIGIN` memuat origin halaman PHP.
- Jika halaman memakai HTTPS, service juga harus HTTPS (hindari mixed content).

### `401 invalid_token`

- Pastikan `CHATBOT_SHARED_SECRET` di PHP dan Node.js sama.
- Muat ulang halaman agar token baru dibuat.
- Cek jam server; token memakai waktu Unix.

### `500 server_error`

- Cek log terminal Node.js.
- Pastikan database MySQL aktif dan kredensial `.env` benar.
- Pastikan tabel `users`, `umkm`, dan `pendaftaran` tersedia.

### Uji koneksi AI gagal

- Pastikan service Node.js berjalan dan `GET /health` mengembalikan `200`.
- Periksa API key, endpoint, dan nama model di halaman admin.
- Untuk Gemini, gunakan endpoint base `/v1beta` dan model `models/gemini-2.5-flash`.
- Untuk OpenAI/Custom, gunakan URL lengkap `/v1/chat/completions`.
- Cek log terminal Node.js untuk detail error provider.

### Tabel `chatbot_configurations` tidak ditemukan

- Impor ulang `database.sql`, atau jalankan migrasi `CREATE TABLE chatbot_configurations`.
- Pastikan database yang dipakai PHP dan Node.js sama (`db_umkm_festival`).

### Data admin/UMKM kosong

- Pastikan user login memiliki role yang benar.
- Untuk UMKM, pastikan profil di tabel `umkm` sudah ada.
- Untuk status, pastikan ada baris di tabel `pendaftaran`.

---

## 10. Konfigurasi AI dari admin

Buka:

```text
http://localhost/cireboncreativefestival2026/admin/chatbot-config.php
```

Halaman ini menyimpan konfigurasi ke tabel `chatbot_configurations`:

| Kolom | Keterangan |
|---|---|
| `provider_name` | `gemini`, `openai`, atau `custom` |
| `api_key` | API key provider (disembunyikan di UI) |
| `endpoint_url` | URL endpoint provider |
| `model_name` | Nama model, misalnya `models/gemini-2.5-flash` |
| `is_active` | `1` = dipakai Node.js, `0` = nonaktif |
| `updated_by` | Admin yang terakhir menyimpan |

Alur kerja:

1. Admin mengisi form dan menekan **Uji Koneksi** untuk memastikan provider merespons.
2. Admin menekan **Simpan Konfigurasi**.
3. Pada setiap pertanyaan yang tidak dikenali aturan lokal, Node.js membaca baris dengan `is_active = 1` dari database.
4. Jika provider aktif dan API key tersedia, Node.js mengirim pertanyaan ke provider.
5. Jika provider gagal, chatbot kembali ke jawaban rule-based. Perubahan konfigurasi langsung berlaku tanpa restart service.

Provider yang didukung:

- **Gemini** — endpoint default `https://generativelanguage.googleapis.com/v1beta`, model default `models/gemini-2.5-flash`.
- **OpenAI** — endpoint default `https://api.openai.com/v1/chat/completions`, model default `gpt-4o-mini`.
- **Custom/Local** — endpoint OpenAI-compatible, misalnya Ollama `http://localhost:11434/v1/chat/completions` dengan model `llama3.1`.

> API key disimpan sebagai plaintext di database pada implementasi ini. Untuk produksi, gunakan HTTPS, batasi akses admin, dan pertimbangkan enkripsi atau secret manager.

---

## 11. Catatan

- Chatbot ini **rule-based dengan fallback AI opsional**.
- Semua query database memakai prepared statement (`mysql2`).
- Rate limit disimpan di memory; untuk multi-instance produksi gunakan Redis.
- File `api-chat.php` lama sudah dihapus. Logika chatbot sepenuhnya berada di `chatbot-service/server.js`.
