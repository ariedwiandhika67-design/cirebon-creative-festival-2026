-- =============================================================
--  Cirebon Creative Festival 2026
--  Database  : db_umkm_festival
--  Engine    : InnoDB (wajib untuk Foreign Key)
--  Charset   : utf8mb4 / utf8mb4_unicode_ci
-- =============================================================

CREATE DATABASE IF NOT EXISTS `db_umkm_festival`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `db_umkm_festival`;

-- -------------------------------------------------------------
-- Tabel: users
-- Menyimpan akun UMKM maupun admin.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama_lengkap`  VARCHAR(100) NOT NULL,
    `nim`           VARCHAR(30) NULL DEFAULT NULL,  -- identitas admin/mahasiswa, opsional untuk UMKM
    `email`         VARCHAR(150) NOT NULL,
    `password`      VARCHAR(255) NOT NULL,          -- simpan hasil password_hash(), bukan plain text
    `role`          ENUM('umkm', 'admin') NOT NULL DEFAULT 'umkm',
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    UNIQUE KEY `uq_users_nim` (`nim`),
    KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabel: umkm
-- Profil UMKM, relasi 1:1 dengan users.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `umkm` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED NOT NULL,
    `nama_umkm`       VARCHAR(150) NOT NULL,
    `kategori_produk` VARCHAR(100) NOT NULL,
    `no_telepon`      VARCHAR(20) NOT NULL,
    `alamat`          TEXT NOT NULL,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_umkm_user_id` (`user_id`),
    KEY `idx_umkm_kategori` (`kategori_produk`),
    CONSTRAINT `fk_umkm_users`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabel: pendaftaran
-- Pendaftaran festival oleh UMKM beserta status verifikasi admin.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pendaftaran` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `umkm_id`         INT UNSIGNED NOT NULL,
    `deskripsi_produk` TEXT NOT NULL,
    `foto_produk`     VARCHAR(255) NOT NULL,        -- path relatif file, contoh: uploads/produk/xxx.jpg
    `status`          ENUM('Menunggu', 'Diterima', 'Ditolak') NOT NULL DEFAULT 'Menunggu',
    `catatan_admin`   TEXT NULL DEFAULT NULL,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pendaftaran_umkm_id` (`umkm_id`),
    KEY `idx_pendaftaran_status` (`status`),
    CONSTRAINT `fk_pendaftaran_umkm`
        FOREIGN KEY (`umkm_id`) REFERENCES `umkm` (`id`)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabel: chatbot_configurations
-- Menyimpan konfigurasi provider AI untuk layanan chatbot Node.js.
-- Hanya satu baris yang boleh aktif pada satu waktu.
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chatbot_configurations` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider_name` ENUM('gemini', 'openai', 'custom') NOT NULL DEFAULT 'gemini',
    `api_key`       VARCHAR(500) NULL DEFAULT NULL,
    `endpoint_url`  VARCHAR(500) NULL DEFAULT NULL,
    `model_name`    VARCHAR(150) NULL DEFAULT NULL,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 0,
    `updated_by`    INT UNSIGNED NULL DEFAULT NULL,
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_chatbot_config_active` (`is_active`),
    KEY `idx_chatbot_config_updated_by` (`updated_by`),
    CONSTRAINT `fk_chatbot_config_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed konfigurasi awal (nonaktif). Admin dapat mengaktifkan lewat
-- halaman admin/chatbot-config.php.
INSERT INTO `chatbot_configurations`
    (`provider_name`, `endpoint_url`, `model_name`, `is_active`)
SELECT 'gemini', 'https://generativelanguage.googleapis.com/v1beta', 'models/gemini-2.5-flash', 0
WHERE NOT EXISTS (
    SELECT 1 FROM `chatbot_configurations`
);

-- -------------------------------------------------------------
-- Data awal (opsional): akun admin default
-- Email    : admin@ccf2026.id
-- Password : Admin#2026
-- Hash di bawah dibuat dengan password_hash('Admin#2026', PASSWORD_DEFAULT).
-- GANTI password ini setelah login pertama.
--
-- Untuk membuat admin Arie Dwi Andhika (NIM 41257091), jalankan
-- create_admin.php setelah database.sql diimpor.
-- -------------------------------------------------------------
INSERT INTO `users` (`nama_lengkap`, `email`, `password`, `role`)
SELECT 'Administrator', 'admin@ccf2026.id',
       '$2y$10$O3l0pgB5Oqi8jn1SUn4tluz/tMwhwWnO9eotet8Hwi5zZrT4dA39W',
       'admin'
WHERE NOT EXISTS (
    SELECT 1 FROM `users` WHERE `email` = 'admin@ccf2026.id'
);
