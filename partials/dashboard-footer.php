<?php
/**
 * partials/dashboard-footer.php
 * Penutup layout dashboard.
 */

declare(strict_types=1);

if (!defined('CCF_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

$footerUser = $user ?? current_user();
?>
<footer class="border-t border-slate-200 bg-white">
    <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <p>&copy; 2026 Cirebon Creative Festival. Seluruh hak cipta dilindungi.</p>
        <?php if ($footerUser !== null): ?>
            <p>
                Masuk sebagai
                <span class="font-semibold text-slate-700"><?= e((string) $footerUser['email']) ?></span>
                &middot;
                <a href="<?= e(url('index.php')) ?>" class="font-semibold text-slate-600 underline-offset-4 transition hover:text-amber-700 hover:underline">Lihat situs</a>
            </p>
        <?php endif; ?>
    </div>
</footer>

<?php require __DIR__ . '/chatbot.php'; ?>

</body>
</html>
