<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Panel') ?> | <?= esc(env('BUSINESS_NAME', 'Marwis Sweet')) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <script src="<?= base_url('assets/js/app.js') ?>" defer></script>
</head>
<?php $panelPath = trim(uri_string(), '/'); ?>
<body class="min-h-screen bg-white text-brand-cocoa antialiased">
    <a class="skip-link" href="#panel-content">Lewati ke konten</a>
    <div class="min-h-screen lg:grid lg:grid-cols-[17.5rem_1fr]">
        <div class="fixed inset-0 z-40 hidden bg-brand-cocoa/45 lg:hidden" data-drawer-overlay></div>
        <aside id="panel-navigation" class="fixed inset-y-0 left-0 z-50 w-[17.5rem] -translate-x-full border-r border-brand-cocoa bg-white transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0" data-drawer aria-label="Navigasi panel">
            <div class="flex h-full flex-col">
                <div class="flex items-center justify-between border-b border-border-warm p-5">
                    <a class="flex items-center gap-3 focus-ring" href="<?= site_url('panel') ?>">
                        <span class="label-cut inline-grid h-10 w-10 place-items-center bg-brand-sun font-display text-lg" aria-hidden="true">M</span>
                        <span><strong class="block font-display leading-none"><?= esc(env('BUSINESS_NAME', 'Marwis Sweet')) ?></strong><span class="mt-1 block text-xs text-ink-muted">Panel usaha</span></span>
                    </a>
                    <button class="button-icon lg:hidden" type="button" data-drawer-close><span class="sr-only">Tutup navigasi</span><svg aria-hidden="true" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
                </div>
                <nav class="flex-1 overflow-y-auto p-4" aria-label="Navigasi utama">
                    <p class="nav-heading">Utama</p>
                    <a class="panel-nav-link <?= $panelPath === 'panel' ? 'is-active' : '' ?>" href="<?= site_url('panel') ?>">Ringkasan</a>
                    <?php if (($user['role'] ?? null) === 'owner'): ?>
                        <p class="nav-heading mt-7">Master usaha</p>
                        <a class="panel-nav-link <?= str_starts_with($panelPath, 'panel/catalog') ? 'is-active' : '' ?>" href="<?= site_url('panel/catalog') ?>">Produk dan kategori</a>
                        <a class="panel-nav-link <?= str_starts_with($panelPath, 'panel/master') ? 'is-active' : '' ?>" href="<?= site_url('panel/master') ?>">Bahan, resep, supplier</a>
                        <a class="panel-nav-link <?= str_starts_with($panelPath, 'panel/settings') ? 'is-active' : '' ?>" href="<?= site_url('panel/settings') ?>">Identitas dan banner</a>
                        <a class="panel-nav-link <?= str_starts_with($panelPath, 'panel/users') ? 'is-active' : '' ?>" href="<?= site_url('panel/users') ?>">Akun kasir</a>
                        <a class="panel-nav-link <?= str_starts_with($panelPath, 'panel/reviews') ? 'is-active' : '' ?>" href="<?= site_url('panel/reviews') ?>">Moderasi ulasan</a>
                    <?php endif ?>
                    <p class="nav-heading mt-7">Operasional</p>
                    <?php if (($user['role'] ?? null) === 'owner'): ?><a class="panel-nav-link <?= str_starts_with($panelPath, 'panel/inventory') ? 'is-active' : '' ?>" href="<?= site_url('panel/inventory') ?>">Penerimaan dan stok</a><?php endif ?><a class="panel-nav-link <?= str_starts_with($panelPath, 'panel/opnames') ? 'is-active' : '' ?>" href="<?= site_url('panel/opnames') ?>">Stok opname</a><a class="panel-nav-link <?= str_starts_with($panelPath, 'panel/expenses') ? 'is-active' : '' ?>" href="<?= site_url('panel/expenses') ?>">Pengeluaran</a><a class="panel-nav-link <?= str_starts_with($panelPath, 'panel/shifts') ? 'is-active' : '' ?>" href="<?= site_url('panel/shifts') ?>">Shift kasir</a><a class="panel-nav-link <?= str_starts_with($panelPath, 'panel/pos') ? 'is-active' : '' ?>" href="<?= site_url('panel/pos') ?>">POS</a><a class="panel-nav-link <?= str_starts_with($panelPath, 'panel/transactions') ? 'is-active' : '' ?>" href="<?= site_url('panel/transactions') ?>">Transaksi dan struk</a><a class="panel-nav-link <?= str_starts_with($panelPath, 'panel/reports') ? 'is-active' : '' ?>" href="<?= site_url('panel/reports') ?>">Laporan penjualan</a><?php if (($user['role'] ?? null) === 'owner'): ?><a class="panel-nav-link <?= str_starts_with($panelPath, 'panel/accounting') ? 'is-active' : '' ?>" href="<?= site_url('panel/accounting') ?>">Pembukuan</a><?php endif ?>
                </nav>
                <div class="border-t border-border-warm p-4"><a class="block border border-border-warm bg-surface-soft-yellow p-4 focus-ring" href="<?= site_url('/') ?>" target="_blank" rel="noopener"><span class="text-xs font-bold uppercase tracking-wider text-brand-chili">Katalog publik</span><span class="mt-1 block text-sm font-semibold">Lihat tampilan pelanggan</span></a></div>
            </div>
        </aside>
        <div class="min-w-0">
            <header class="sticky top-0 z-30 flex min-h-[72px] items-center justify-between gap-4 border-b border-border-warm bg-surface-white px-4 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <button class="button-icon lg:hidden" type="button" data-drawer-open aria-expanded="false" aria-controls="panel-navigation"><span class="sr-only">Buka navigasi</span><svg aria-hidden="true" viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
                    <div class="min-w-0"><p class="truncate text-sm font-bold"><?= esc($user['name'] ?? '') ?></p><p class="text-xs text-ink-muted"><?= ($user['role'] ?? '') === 'owner' ? 'Pemilik' : 'Kasir' ?></p></div>
                </div>
                <form action="<?= site_url('logout') ?>" method="post"><?= csrf_field() ?><button class="button-secondary" type="submit">Keluar</button></form>
            </header>
            <main id="panel-content" class="p-4 sm:p-6 lg:p-8">
                <?= $this->renderSection('content') ?>
            </main>
        </div>
    </div>
    <?php $flashSuccess = session()->getFlashdata('success'); $flashError = session()->getFlashdata('error'); ?>
    <?php if ($flashSuccess || $flashError): ?>
        <dialog class="app-dialog" open data-flash-dialog aria-labelledby="flash-title" aria-describedby="flash-message" <?= $flashError ? 'role="alertdialog"' : '' ?>>
            <div class="app-dialog-icon <?= $flashError ? 'is-danger' : 'is-success' ?>" aria-hidden="true"><?= $flashError ? '!' : '&#10003;' ?></div>
            <h2 class="font-display text-xl" id="flash-title"><?= $flashError ? 'Belum berhasil' : 'Berhasil' ?></h2>
            <p class="mt-2 text-ink-muted" id="flash-message"><?= esc($flashError ?: $flashSuccess) ?></p>
            <button class="<?= $flashError ? 'button-primary-inverse' : 'button-primary' ?> mt-6 w-full" type="button" data-dialog-close autofocus>Mengerti</button>
        </dialog>
    <?php endif ?>
    <dialog class="app-dialog" data-confirm-dialog aria-labelledby="confirm-title" aria-describedby="confirm-message">
        <div class="app-dialog-icon is-warning" aria-hidden="true">?</div>
        <h2 class="font-display text-xl" id="confirm-title">Konfirmasi tindakan</h2>
        <p class="mt-2 text-ink-muted" id="confirm-message" data-confirm-message></p>
        <div class="mt-6 grid grid-cols-2 gap-3"><button class="button-secondary" type="button" data-dialog-close autofocus>Batal</button><button class="button-primary" type="button" data-confirm-accept>Lanjutkan</button></div>
    </dialog>
</body>
</html>
