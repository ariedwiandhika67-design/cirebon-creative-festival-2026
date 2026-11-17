<?php
/**
 * umkm/index.php — Pintu masuk folder UMKM.
 * Mencegah directory listing dan mengarahkan ke dashboard.
 */

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

require_role('umkm');
redirect(url('umkm/dashboard.php'), 302);
