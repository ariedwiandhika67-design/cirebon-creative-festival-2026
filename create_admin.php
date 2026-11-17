<?php
/**
 * create_admin.php — Setup akun admin awal Cirebon Creative Festival 2026
 *
 * Jalankan sekali dari browser:
 *   http://localhost/cireboncreativefestival2026/create_admin.php
 *
 * Atau dari terminal:
 *   php create_admin.php
 *
 * Script ini:
 *   1. Menambahkan kolom `nim` pada tabel users bila belum ada.
 *   2. Membuat akun admin dengan password_hash().
 *   3. Menampilkan pesan sukses/gagal.
 *
 * PENTING:
 *   - Script hanya boleh diakses dari localhost.
 *   - Hapus file ini setelah akun admin berhasil dibuat.
 *   - Segera ganti password default setelah login pertama.
 */

declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';

// ---------------------------------------------------------------
// Batasi akses setup hanya dari localhost / CLI
// ---------------------------------------------------------------
if (!$isCli) {
    $remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    if (!in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Forbidden: script setup hanya boleh dijalankan dari localhost.');
    }

    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
}

require_once __DIR__ . '/koneksi.php';

// ---------------------------------------------------------------
// Data admin yang akan dibuat
// ---------------------------------------------------------------
const ADMIN_NAME     = 'Arie Dwi Andhika';
const ADMIN_NIM      = '41257091';
const ADMIN_EMAIL    = 'admin@ccf2026.local';
const ADMIN_PASSWORD = 'admin123';
const ADMIN_ROLE     = 'admin';

/**
 * Escape output HTML.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$status  = 'error';
$message = '';
$details = [];

try {
    // -----------------------------------------------------------
    // Pastikan kolom nim tersedia (kompatibel dengan database lama)
    // -----------------------------------------------------------
    $nimColumnExists = (bool) $pdo
        ->query("SHOW COLUMNS FROM `users` LIKE 'nim'")
        ->fetch();

    if (!$nimColumnExists) {
        $pdo->exec(
            "ALTER TABLE `users`
             ADD COLUMN `nim` VARCHAR(30) NULL DEFAULT NULL
             AFTER `nama_lengkap`"
        );
    }

    $nimIndexExists = (bool) $pdo
        ->query("SHOW INDEX FROM `users` WHERE Key_name = 'uq_users_nim'")
        ->fetch();

    if (!$nimIndexExists) {
        try {
            $pdo->exec("ALTER TABLE `users` ADD UNIQUE KEY `uq_users_nim` (`nim`)");
        } catch (PDOException $e) {
            // Unique index bersifat opsional; jangan gagalkan pembuatan admin
            // hanya karena ada data lama yang duplikat.
            error_log('[CREATE ADMIN] Unique index nim gagal dibuat: ' . $e->getMessage());
        }
    }

    // -----------------------------------------------------------
    // Cek apakah email admin sudah terdaftar
    // -----------------------------------------------------------
    $stmt = $pdo->prepare(
        'SELECT id, nama_lengkap, nim, email, role
         FROM `users`
         WHERE email = ?
         LIMIT 1'
    );
    $stmt->execute([ADMIN_EMAIL]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['role'] !== 'admin') {
            $status  = 'error';
            $message = 'Email ' . ADMIN_EMAIL . ' sudah dipakai oleh akun non-admin.';
        } else {
            $status  = 'exists';
            $message = 'Akun admin sudah ada. Tidak ada data yang diubah.';

            $details = [
                'ID'    => (string) $existing['id'],
                'Nama'  => (string) $existing['nama_lengkap'],
                'NIM'   => (string) ($existing['nim'] ?? '-'),
                'Email' => (string) $existing['email'],
                'Role'  => (string) $existing['role'],
            ];
        }
    } else {
        // -------------------------------------------------------
        // Pastikan NIM belum dipakai akun lain
        // -------------------------------------------------------
        $stmt = $pdo->prepare(
            'SELECT id, email
             FROM `users`
             WHERE nim = ?
             LIMIT 1'
        );
        $stmt->execute([ADMIN_NIM]);
        $nimOwner = $stmt->fetch();

        if ($nimOwner) {
            $status  = 'error';
            $message = 'NIM ' . ADMIN_NIM . ' sudah dipakai oleh akun ' . $nimOwner['email'] . '.';
        } else {
            // ---------------------------------------------------
            // Buat hash password yang aman
            // ---------------------------------------------------
            $passwordHash = password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT);

            if ($passwordHash === false) {
                throw new RuntimeException('password_hash() gagal membuat hash password.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO `users` (`nama_lengkap`, `nim`, `email`, `password`, `role`)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                ADMIN_NAME,
                ADMIN_NIM,
                ADMIN_EMAIL,
                $passwordHash,
                ADMIN_ROLE,
            ]);

            $status  = 'success';
            $message = 'Akun admin berhasil dibuat.';

            $details = [
                'ID'       => (string) $pdo->lastInsertId(),
                'Nama'     => ADMIN_NAME,
                'NIM'      => ADMIN_NIM,
                'Email'    => ADMIN_EMAIL,
                'Role'     => ADMIN_ROLE,
                'Password' => ADMIN_PASSWORD,
            ];
        }
    }
} catch (Throwable $e) {
    error_log('[CREATE ADMIN] ' . $e->getMessage());
    $status  = 'error';
    $message = 'Gagal membuat akun admin. Periksa log server untuk detail teknis.';
}

// ---------------------------------------------------------------
// Output CLI
// ---------------------------------------------------------------
if ($isCli) {
    echo '[CREATE ADMIN] ' . $message . PHP_EOL;

    if ($details !== []) {
        foreach ($details as $label => $value) {
            echo $label . ': ' . $value . PHP_EOL;
        }
    }

    if ($status === 'success') {
        echo PHP_EOL . 'PENTING: Segera ganti password default setelah login.' . PHP_EOL;
        echo 'Hapus file create_admin.php setelah setup selesai.' . PHP_EOL;
    }

    exit($status === 'error' ? 1 : 0);
}

// ---------------------------------------------------------------
// Output browser
// ---------------------------------------------------------------
$title = match ($status) {
    'success' => 'Akun Admin Berhasil Dibuat',
    'exists'  => 'Akun Admin Sudah Ada',
    default   => 'Gagal Membuat Akun Admin',
};

$badgeClass = match ($status) {
    'success' => 'badge-success',
    'exists'  => 'badge-info',
    default   => 'badge-error',
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — CCF 2026</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --bg: #f8fafc;
            --green: #047857;
            --green-bg: #ecfdf5;
            --green-line: #a7f3d0;
            --blue: #1d4ed8;
            --blue-bg: #eff6ff;
            --blue-line: #bfdbfe;
            --red: #b91c1c;
            --red-bg: #fef2f2;
            --red-line: #fecaca;
            --amber: #92400e;
            --amber-bg: #fffbeb;
            --amber-line: #fde68a;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: radial-gradient(circle at top, #ffffff 0%, var(--bg) 55%, #eef2f7 100%);
            color: var(--ink);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .card {
            width: 100%;
            max-width: 620px;
            padding: 32px;
            border: 1px solid var(--line);
            border-radius: 24px;
            background: #ffffff;
            box-shadow: 0 24px 60px -28px rgba(15, 23, 42, 0.35);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            border: 1px solid transparent;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .badge-success { color: var(--green); background: var(--green-bg); border-color: var(--green-line); }
        .badge-info    { color: var(--blue); background: var(--blue-bg); border-color: var(--blue-line); }
        .badge-error   { color: var(--red); background: var(--red-bg); border-color: var(--red-line); }

        h1 {
            margin: 18px 0 8px;
            font-size: clamp(24px, 4vw, 32px);
            line-height: 1.15;
            letter-spacing: -0.03em;
        }

        p.lead {
            margin: 0;
            color: var(--muted);
            line-height: 1.65;
        }

        dl {
            display: grid;
            grid-template-columns: minmax(110px, 150px) 1fr;
            gap: 0;
            margin: 24px 0 0;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 16px;
        }

        dt, dd {
            margin: 0;
            padding: 12px 16px;
            border-bottom: 1px solid var(--line);
            font-size: 14px;
        }

        dt {
            background: #f8fafc;
            color: var(--muted);
            font-weight: 700;
        }

        dd {
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        dl > :nth-last-child(-n + 2) {
            border-bottom: 0;
        }

        .notice {
            margin-top: 20px;
            padding: 14px 16px;
            border: 1px solid var(--amber-line);
            border-radius: 14px;
            background: var(--amber-bg);
            color: var(--amber);
            font-size: 13px;
            line-height: 1.6;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 22px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 10px 16px;
            border: 1px solid transparent;
            border-radius: 12px;
            background: var(--ink);
            color: #ffffff;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
        }

        .button.secondary {
            border-color: var(--line);
            background: #ffffff;
            color: var(--ink);
        }

        code {
            padding: 2px 6px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #f8fafc;
            font-size: 12px;
        }

        @media (max-width: 520px) {
            .card { padding: 24px; }
            dl { grid-template-columns: 1fr; }
            dt { border-bottom: 0; }
        }
    </style>
</head>
<body>
    <main class="card">
        <span class="badge <?= e($badgeClass) ?>">Setup Admin CCF 2026</span>
        <h1><?= e($title) ?></h1>
        <p class="lead"><?= e($message) ?></p>

        <?php if ($details !== []): ?>
            <dl>
                <?php foreach ($details as $label => $value): ?>
                    <dt><?= e($label) ?></dt>
                    <dd><?= e($value) ?></dd>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>

        <?php if ($status === 'success'): ?>
            <div class="notice">
                <strong>Penting:</strong> password default <code>admin123</code> hanya untuk setup awal.
                Segera ganti password setelah login, lalu hapus file <code>create_admin.php</code>.
            </div>

            <div class="actions">
                <a class="button" href="login.php">Buka Halaman Login</a>
                <a class="button secondary" href="admin/dashboard.php">Dashboard Admin</a>
            </div>
        <?php elseif ($status === 'exists'): ?>
            <div class="notice">
                Akun ini sudah tersedia. Jika lupa password, gunakan mekanisme reset password
                atau perbarui hash password secara manual melalui database.
            </div>

            <div class="actions">
                <a class="button" href="login.php">Buka Halaman Login</a>
            </div>
        <?php else: ?>
            <div class="actions">
                <a class="button secondary" href="create_admin.php">Coba Lagi</a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
