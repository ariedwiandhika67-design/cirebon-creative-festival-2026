<?php
// Sertakan file whatsapp.php yang sudah kamu buat
require_once __DIR__ . '/whatsapp.php';

// Masukkan nomor WhatsApp tujuan pengujian (bisa nomor HP-mu sendiri)
// Contoh format: 08123456789 atau 628123456789
$nomorTujuan = '083185538874'; // <-- Ganti dengan nomor WhatsApp kamu

// Pesan uji coba
$pesanUji = "Halo! Ini adalah pesan uji coba (test notification) dari sistem Cirebon Creative Festival 2026.";

// Eksekusi fungsi pengiriman
echo "Mengirim pesan WhatsApp ke $nomorTujuan...<br>";
$hasil = sendWhatsAppNotification($nomorTujuan, $pesanUji);

// Tampilkan hasil respons dari Fonnte/Wablas
echo "<pre>";
print_r($hasil);
echo "</pre>";
?>