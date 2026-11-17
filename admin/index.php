<?php
/**
 * admin/index.php — Pintu masuk folder admin.
 * Mencegah directory listing dan mengarahkan ke dashboard.
 */

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

require_role('admin');
redirect(url('admin/dashboard.php'), 302);
