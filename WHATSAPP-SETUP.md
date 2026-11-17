# Integrasi WhatsApp — Cirebon Creative Festival 2026

Integrasi ini mengirim notifikasi WhatsApp melalui gateway **Fonnte** atau **Wablas** menggunakan cURL.

## File yang terlibat

| File | Fungsi |
| --- | --- |
| `whatsapp.php` | Fungsi reusable `sendWhatsAppNotification()` + konfigurasi gateway |
| `umkm/form-pendaftaran.php` | Mengirim notifikasi ke admin saat ada pendaftaran tenant baru |
| `admin/dashboard.php` | Mengirim notifikasi ke UMKM saat status berubah menjadi `Diterima` atau `Ditolak` |

## 1. Pastikan ekstensi cURL aktif

Buka `php.ini`, pastikan baris berikut tidak diawali `;`:

```ini
extension=curl
```

Lalu restart Apache/Laragon.

## 2. Siapkan akun gateway

### Opsi A — Fonnte

1. Daftar di <https://fonnte.com>.
2. Hubungkan perangkat WhatsApp yang akan menjadi pengirim.
3. Buka menu **Device**, salin **Token**.
4. Gunakan konfigurasi:

```text
WA_PROVIDER = fonnte
WA_API_URL  = https://api.fonnte.com/send
```

### Opsi B — Wablas

1. Daftar di <https://wablas.com>.
2. Hubungkan perangkat WhatsApp.
3. Salin token dari menu **Device/API**.
4. Gunakan konfigurasi:

```text
WA_PROVIDER = wablas
WA_API_URL  = https://DOMAIN-AKUN-ANDA.wablas.com/api/send-message
```

> Ganti `DOMAIN-AKUN-ANDA` dengan domain yang diberikan Wablas.

## 3. Isi token dan nomor admin

### Opsi A — Environment variable (disarankan untuk produksi)

Tambahkan konfigurasi berikut ke virtual host Apache atau `.htaccess`:

```apache
SetEnv WA_ENABLED true
SetEnv WA_PROVIDER fonnte
SetEnv WA_API_TOKEN token-rahasia-anda
SetEnv WA_ADMIN_NUMBER 6281234567890
```

Untuk Wablas, sesuaikan juga:

```apache
SetEnv WA_PROVIDER wablas
SetEnv WA_API_URL https://domain-anda.wablas.com/api/send-message
```

Restart Apache setelah mengubah konfigurasi.

### Opsi B — Fallback lokal

Edit bagian konfigurasi di `whatsapp.php`:

```php
$waToken = 'token-rahasia-anda';
$waAdminNumber = '6281234567890';
```

Jangan menyimpan token asli di repository publik. Untuk produksi, pindahkan ke environment variable.

## 4. Format nomor telepon

Nomor akan dinormalisasi otomatis:

| Input | Hasil |
| --- | --- |
| `081234567890` | `6281234567890` |
| `81234567890` | `6281234567890` |
| `+62 812 3456 7890` | `6281234567890` |
| `0062 812 3456 7890` | `6281234567890` |

Nomor admin sebaiknya menggunakan format `62...` tanpa tanda `+`.

## 5. Cara kerja notifikasi

### Pendaftaran baru

Setelah data berhasil disimpan di tabel `pendaftaran`, sistem mengirim pesan ke `WA_ADMIN_NUMBER` berisi:

- Nama UMKM
- Nama pemilik
- Kategori produk
- Nomor WhatsApp UMKM
- ID pendaftaran
- Ringkasan deskripsi
- Tautan ke dashboard admin

### Perubahan status

Saat admin mengubah status menjadi `Diterima` atau `Ditolak`, sistem mengirim pesan ke nomor WhatsApp UMKM berisi:

- Status terbaru
- Catatan admin (jika ada)
- Tautan ke dashboard UMKM

Notifikasi hanya dikirim jika status benar-benar berubah. Kegagalan pengiriman WhatsApp **tidak** membatalkan pendaftaran atau keputusan admin.

## 6. Pengujian

1. Pastikan perangkat gateway WhatsApp masih terhubung.
2. Ajukan pendaftaran baru dari akun UMKM.
3. Periksa WhatsApp admin.
4. Dari dashboard admin, terima atau tolak pendaftaran.
5. Periksa WhatsApp UMKM.

Jika gagal, periksa log PHP/Apache. Fungsi akan menulis log dengan prefix `[WA]`.

## 7. Troubleshooting

| Gejala | Penyebab umum | Solusi |
| --- | --- | --- |
| `WA_API_TOKEN belum diisi` | Token kosong | Isi environment variable atau fallback di `whatsapp.php` |
| HTTP 401/403 | Token salah/kedaluwarsa | Salin ulang token dari dashboard gateway |
| HTTP 404 | URL endpoint salah | Periksa `WA_API_URL`, terutama untuk Wablas |
| cURL SSL error | CA bundle tidak ditemukan | Aktifkan `curl.cainfo` di `php.ini` |
| Pesan tidak masuk | Perangkat gateway offline | Hubungkan ulang perangkat di dashboard gateway |
| Pesan tidak masuk | Nomor tidak valid | Gunakan format `08...` atau `62...` |

## 8. Catatan keamanan

- Jangan menampilkan token di halaman web atau log.
- Gunakan environment variable untuk produksi.
- `whatsapp.php` memblokir akses langsung melalui browser.
- Notifikasi bersifat best-effort; alur utama aplikasi tetap berjalan meskipun gateway gagal.
