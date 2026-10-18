<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Penjualan') ?> | <?= esc(env('BUSINESS_NAME', 'Marwis Sweet')) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <script src="<?= base_url('assets/js/app.js') ?>" defer></script>
</head>
<body class="min-h-screen bg-white text-brand-cocoa antialiased xl:h-dvh xl:overflow-hidden">
    <a class="skip-link" href="#pos-content">Lewati ke area penjualan</a>
    <div class="min-h-screen xl:h-dvh xl:overflow-hidden">
        <header class="border-b border-brand-cocoa bg-surface-white xl:h-[4.5rem]">
            <div class="flex min-h-[4.5rem] flex-wrap items-center justify-between gap-x-6 gap-y-3 px-4 py-3 sm:px-6 xl:flex-nowrap xl:px-8 xl:py-0">
                <div class="flex min-w-0 items-center gap-3">
                    <a class="focus-ring grid h-11 w-11 shrink-0 place-items-center bg-brand-sun font-display text-lg" href="<?= site_url('panel') ?>" aria-label="Kembali ke dashboard">M</a>
                    <div class="min-w-0">
                        <p class="truncate font-display text-lg leading-none"><?= esc(env('BUSINESS_NAME', 'Marwis Sweet')) ?></p>
                        <p class="mt-1 text-xs font-bold uppercase tracking-wider text-brand-chili">Workspace penjualan</p>
                    </div>
                </div>
                <nav class="order-3 flex w-full gap-1 overflow-x-auto border-t border-border-warm pt-3 xl:order-none xl:w-auto xl:border-0 xl:pt-0" aria-label="Navigasi penjualan">
                    <a class="min-h-11 shrink-0 border-b-2 border-brand-cocoa px-4 py-3 text-sm font-black" href="<?= site_url('panel/pos') ?>" aria-current="page">Kasir</a>
                    <a class="min-h-11 shrink-0 border-b-2 border-transparent px-4 py-3 text-sm font-bold text-ink-muted hover:border-brand-sun hover:text-brand-cocoa" href="<?= site_url('panel/transactions') ?>">Riwayat</a>
                    <a class="min-h-11 shrink-0 border-b-2 border-transparent px-4 py-3 text-sm font-bold text-ink-muted hover:border-brand-sun hover:text-brand-cocoa" href="<?= site_url('panel/shifts') ?>">Shift</a>
                </nav>
                <div class="flex shrink-0 items-center gap-3">
                    <div class="hidden text-right sm:block"><p class="text-sm font-bold"><?= esc($user['name'] ?? '') ?></p><p class="text-xs text-ink-muted"><?= ($user['role'] ?? '') === 'owner' ? 'Pemilik' : 'Kasir' ?></p></div>
                    <a class="button-secondary" href="<?= site_url('panel') ?>">Panel</a>
                </div>
            </div>
        </header>
        <main id="pos-content" class="xl:flex xl:h-[calc(100dvh-4.5rem)] xl:min-h-0 xl:flex-col xl:overflow-hidden">
            <?php if (session()->getFlashdata('success')): ?><div class="alert-success mx-4 mt-4 shrink-0 sm:mx-6" role="status"><strong>Berhasil.</strong> <?= esc(session()->getFlashdata('success')) ?></div><?php endif ?>
            <?php if (session()->getFlashdata('error')): ?><div class="alert-danger mx-4 mt-4 shrink-0 sm:mx-6" role="alert"><strong>Belum berhasil.</strong> <?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
            <div class="xl:min-h-0 xl:flex-1 xl:overflow-hidden"><?= $this->renderSection('content') ?></div>
        </main>
    </div>
    <dialog class="app-dialog" data-confirm-dialog aria-labelledby="confirm-title" aria-describedby="confirm-message">
        <div class="app-dialog-icon is-warning" aria-hidden="true">?</div>
        <h2 class="font-display text-xl" id="confirm-title">Konfirmasi tindakan</h2>
        <p class="mt-2 text-ink-muted" id="confirm-message" data-confirm-message></p>
        <div class="mt-6 grid grid-cols-2 gap-3"><button class="button-secondary" type="button" data-dialog-close autofocus>Batal</button><button class="button-primary" type="button" data-confirm-accept>Lanjutkan</button></div>
    </dialog>
</body>
</html>
